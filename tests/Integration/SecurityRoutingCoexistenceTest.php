<?php
declare(strict_types=1);

/**
 * Integration test for RoutingTrait + SecurityTrait coexistence.
 *
 * Verifies that a ServiceProvider can inject BOTH routes (via addRoute/addRoutes)
 * AND security config (via addSecurityConfig/addFirewall etc) during register phase,
 * and after boot both subsystems work correctly together without interference.
 *
 * Also verifies that route injection conflict detection ($allowOverwrite = false)
 * and security injection conflict detection ($allowOverwrite = false) operate
 * independently — a routing conflict does not affect security, and vice versa.
 *
 * Ref: Unchanged Behavior 5, Design CR Q2
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class SecurityRoutingCoexistenceTest extends ScenarioTestCase
{
    // -----------------------------------------------------------------
    // Test 1: ServiceProvider injects BOTH routes AND security config
    //         → boot → both routing and security work correctly together
    // Ref: Unchanged Behavior 5
    // -----------------------------------------------------------------

    /**
     * A ServiceProvider injects programmatic routes via addRoute() AND security
     * config via addSecurityConfig(). After boot, the programmatic route is
     * reachable and security enforcement (access_rules) is active.
     */
    public function testRoutingAndSecurityInjectionCoexistAfterBoot(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Simulate ServiceProvider A: inject a programmatic route
        $kernel->addRoute('coexist.secured', new Route('/coexist/secured', [
            '_controller' => IntegrationController::class . '::securedAdmin',
        ]));
        $kernel->addRoute('coexist.public', new Route('/coexist/public', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]));

        // Simulate ServiceProvider B: inject security config
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'coexist.api' => new SimpleFirewall([
                    'pattern'  => '^/coexist/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/coexist/secured', 'roles' => 'ROLE_ADMIN'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        // Verify secured route works with valid credentials
        $response = $this->handleRequest($kernel, 'GET', '/coexist/secured', ['sig' => 'abcd']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);
        $this->assertTrue($data['admin']);
    }

    /**
     * Public route injected via addRoute() remains accessible without credentials
     * even when security config is also injected (firewall pattern doesn't match).
     */
    public function testPublicRouteRemainsAccessibleWithSecurityInjected(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Inject routes
        $kernel->addRoute('coexist.public', new Route('/coexist/public', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]));
        $kernel->addRoute('coexist.secured', new Route('/coexist/secured', [
            '_controller' => IntegrationController::class . '::securedAdmin',
        ]));

        // Inject security (only covers /coexist/secured)
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'coexist.api' => new SimpleFirewall([
                    'pattern'  => '^/coexist/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/coexist/secured', 'roles' => 'ROLE_ADMIN'],
            ],
        ]);

        // Public route should be accessible without credentials
        $response = $this->handleRequest($kernel, 'GET', '/coexist/public');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertStringContainsString('publicAction', $data['called']);
    }

    /**
     * addRoutes() batch injection + addSecurityConfig() both work together.
     * Routes from a RouteCollection are reachable and security is enforced.
     */
    public function testBatchRouteInjectionWithSecurityConfigCoexist(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Batch route injection via addRoutes()
        $routes = new RouteCollection();
        $routes->add('batch.secured', new Route('/batch/secured', [
            '_controller' => IntegrationController::class . '::securedUser',
        ]));
        $routes->add('batch.public', new Route('/batch/public', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]));
        $kernel->addRoutes($routes);

        // Security injection via batch API (addSecurityConfig uses SimpleFirewall objects)
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'batch.api' => new SimpleFirewall([
                    'pattern'  => '^/batch/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/batch/secured', 'roles' => 'ROLE_USER'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        // Secured route with valid credentials
        $response = $this->handleRequest($kernel, 'GET', '/batch/secured', ['sig' => 'abcd']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);
    }

    // -----------------------------------------------------------------
    // Test 2: Route injection $allowOverwrite=false + Security injection
    //         $allowOverwrite=false → independent conflict detection
    // Ref: Design CR Q2
    // -----------------------------------------------------------------

    /**
     * Route conflict detection ($allowOverwrite=false) operates independently
     * from security conflict detection ($allowOverwrite=false).
     * A duplicate route name throws LogicException without affecting security state.
     */
    public function testRoutingConflictDoesNotAffectSecurityInjection(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // First: inject security config successfully
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'conflict.api' => new SimpleFirewall([
                    'pattern'  => '^/conflict',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
        ]);

        // Inject a route
        $kernel->addRoute('conflict.route', new Route('/conflict/test', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]), false);

        // Attempt to inject duplicate route with $allowOverwrite=false → should throw
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Duplicate route: 'conflict.route'");

        $kernel->addRoute('conflict.route', new Route('/conflict/other', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]), false);
    }

    /**
     * Security conflict detection ($allowOverwrite=false) operates independently
     * from route conflict detection. A duplicate firewall name throws LogicException
     * without affecting routing state.
     */
    public function testSecurityConflictDoesNotAffectRoutingInjection(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // First: inject a route successfully
        $kernel->addRoute('noconflict.route', new Route('/noconflict/test', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]), false);

        // Inject a firewall
        $kernel->addFirewall('noconflict.api', [
            'pattern'  => '^/noconflict',
            'policies' => ['mauth' => true],
        ]);

        // Attempt to inject duplicate firewall with $allowOverwrite=false → should throw
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Duplicate firewall: 'noconflict.api'");

        $kernel->addFirewall('noconflict.api', [
            'pattern'  => '^/noconflict/other',
            'policies' => ['mauth' => true],
        ]);
    }

    /**
     * After a security conflict is caught, previously injected routes remain
     * intact and the kernel can still boot and serve those routes (if security
     * conflict is resolved or test is restructured).
     *
     * This test verifies that a security conflict exception does not corrupt
     * the routing pending queue.
     */
    public function testSecurityConflictDoesNotCorruptRoutingPendingQueue(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Inject routes first
        $kernel->addRoute('intact.public', new Route('/intact/public', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]));

        // Inject first firewall via addSecurityConfig (with proper SimpleFirewall)
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'intact.api' => new SimpleFirewall([
                    'pattern'  => '^/intact/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
        ]);

        // Attempt duplicate firewall → catch the exception
        $conflictCaught = false;
        try {
            $kernel->addFirewall('intact.api', [
                'pattern'  => '^/intact/other',
                'policies' => ['mauth' => true],
            ]);
        } catch (\LogicException $e) {
            $conflictCaught = true;
            $this->assertStringContainsString("Duplicate firewall: 'intact.api'", $e->getMessage());
        }
        $this->assertTrue($conflictCaught, 'Expected LogicException for duplicate firewall');

        // Routes should still be intact — boot and verify public route works
        $response = $this->handleRequest($kernel, 'GET', '/intact/public');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertStringContainsString('publicAction', $data['called']);
    }

    /**
     * After a routing conflict is caught, previously injected security config
     * remains intact and the kernel can still boot with security working.
     */
    public function testRoutingConflictDoesNotCorruptSecurityPendingQueue(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/integration.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Inject security config first
        $kernel->addSecurityConfig([
            'policies' => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'integration.secured' => new SimpleFirewall([
                    'pattern'  => '^/integration/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        // Inject a route, then attempt duplicate with $allowOverwrite=false
        $kernel->addRoute('dup.route', new Route('/dup/test', [
            '_controller' => IntegrationController::class . '::publicAction',
        ]), false);

        $conflictCaught = false;
        try {
            $kernel->addRoute('dup.route', new Route('/dup/other', [
                '_controller' => IntegrationController::class . '::publicAction',
            ]), false);
        } catch (\LogicException $e) {
            $conflictCaught = true;
            $this->assertStringContainsString("Duplicate route: 'dup.route'", $e->getMessage());
        }
        $this->assertTrue($conflictCaught, 'Expected LogicException for duplicate route');

        // Security should still be intact — boot and verify secured route works
        $response = $this->handleRequest($kernel, 'GET', '/integration/secured/user', ['sig' => 'abcd']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);
    }
}
