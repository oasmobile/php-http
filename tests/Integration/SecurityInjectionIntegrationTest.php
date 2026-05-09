<?php
declare(strict_types=1);

/**
 * Integration test for SecurityTrait pre-boot injection API.
 *
 * Verifies the complete ServiceProvider flow: register() → addSecurityConfig() → boot()
 * → secured routes return 200 (not 403).
 *
 * Ref: Expected Behavior 1, 2, 3, 4, 8
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Response;

class SecurityInjectionIntegrationTest extends ScenarioTestCase
{
    // -----------------------------------------------------------------
    // Test 1: ServiceProvider registers security config via addSecurityConfig()
    //         → boot → secured route returns 200 (not 403)
    // Ref: Expected Behavior 1, 3
    // -----------------------------------------------------------------

    /**
     * ServiceProvider calls addSecurityConfig() during register phase with full
     * security config (policies, firewalls, access_rules, role_hierarchy).
     * After boot, a request with valid credentials to a secured route returns 200.
     */
    public function testServiceProviderBatchInjectionSecuredRouteReturns200(): void
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

        // Simulate ServiceProvider register() phase: inject security config
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
                ['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN'],
                ['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        // Boot and handle request with valid credentials
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/integration/secured/admin',
            ['sig' => 'abcd'],
        );

        // Should return 200 (not 403) — security config was merged at boot
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertStringContainsString('securedAdmin', $data['called']);
        $this->assertSame('admin', $data['user']);
        $this->assertTrue($data['admin']);
    }

    // -----------------------------------------------------------------
    // Test 2: Multiple ServiceProviders inject different firewalls
    //         → boot merges all correctly
    // Ref: Expected Behavior 1, 2, 3
    // -----------------------------------------------------------------

    /**
     * Multiple ServiceProviders inject different firewalls and access_rules
     * via separate addSecurityConfig() / addFirewall() calls.
     * After boot, all firewalls are active and routes are correctly secured.
     */
    public function testMultipleServiceProvidersInjectDifferentFirewallsMergedCorrectly(): void
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

        // ServiceProvider A: inject policy + first firewall
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
        ]);

        // ServiceProvider B: inject access_rules and role_hierarchy
        $kernel->addAccessRule(['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN']);
        $kernel->addAccessRule(['pattern' => '^/integration/secured/user', 'roles' => 'ROLE_USER']);
        $kernel->addAccessRule(['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER']);

        // ServiceProvider C: inject role_hierarchy
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_PARENT', ['ROLE_CHILD', 'ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_CHILD', ['ROLE_USER']);

        // Boot and verify admin route works
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/integration/secured/admin',
            ['sig' => 'abcd'],
        );
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);
        $this->assertTrue($data['admin']);
    }

    /**
     * Verify that role hierarchy from multiple injections works correctly:
     * parent user (ROLE_PARENT inherits ROLE_CHILD, ROLE_USER) can access user route.
     */
    public function testMultipleServiceProvidersRoleHierarchyWorksEndToEnd(): void
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

        // Inject all security config via fine-grained APIs (simulating multiple providers)
        $kernel->addPolicy('mauth', new TestAuthenticationPolicy());
        $kernel->addSecurityConfig([
            'firewalls' => [
                'integration.secured' => new SimpleFirewall([
                    'pattern'  => '^/integration/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
        ]);
        $kernel->addAccessRule(['pattern' => '^/integration/secured/user', 'roles' => 'ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_PARENT', ['ROLE_CHILD', 'ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_CHILD', ['ROLE_USER']);

        // Parent user should access user route (inherits ROLE_USER)
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/integration/secured/user',
            ['sig' => 'parent'],
        );
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('parent', $data['user']);
    }

    // -----------------------------------------------------------------
    // Test 3: ServiceProvider uses getSecurityConfig() for conditional injection
    // Ref: Expected Behavior 8
    // -----------------------------------------------------------------

    /**
     * ServiceProvider calls getSecurityConfig() to inspect current state,
     * then conditionally injects additional config based on what's already registered.
     */
    public function testGetSecurityConfigForConditionalInjection(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/integration.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
            // Constructor_Config provides policy
            'security'      => [
                'policies' => [
                    'mauth' => new TestAuthenticationPolicy(),
                ],
            ],
        ];

        $kernel = $this->buildKernel($config);

        // ServiceProvider A: query current config to check if policy exists
        $currentConfig = $kernel->getSecurityConfig();
        $this->assertArrayHasKey('policies', $currentConfig);
        $this->assertArrayHasKey('mauth', $currentConfig['policies']);

        // Conditionally inject firewall only if policy 'mauth' is already registered
        if (isset($currentConfig['policies']['mauth'])) {
            $kernel->addSecurityConfig([
                'firewalls' => [
                    'integration.secured' => new SimpleFirewall([
                        'pattern'  => '^/integration/secured',
                        'policies' => ['mauth' => true],
                        'users'    => new TestApiUserProvider(),
                    ]),
                ],
            ]);
            $kernel->addAccessRule(['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN']);
            $kernel->addAccessRule(['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER']);
            $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
        }

        // Verify getSecurityConfig() now shows the full merged view
        $mergedConfig = $kernel->getSecurityConfig();
        $this->assertArrayHasKey('firewalls', $mergedConfig);
        $this->assertArrayHasKey('integration.secured', $mergedConfig['firewalls']);
        $this->assertArrayHasKey('access_rules', $mergedConfig);
        $this->assertCount(2, $mergedConfig['access_rules']);

        // Boot and verify the secured route works
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/integration/secured/admin',
            ['sig' => 'abcd'],
        );
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);
    }

    // -----------------------------------------------------------------
    // Test 4: ServiceProvider calls injection API after boot → LogicException
    // Ref: Expected Behavior 4
    // -----------------------------------------------------------------

    /**
     * Calling addSecurityConfig() after boot throws LogicException.
     */
    public function testAddSecurityConfigAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');

        $kernel->addSecurityConfig([
            'firewalls' => [
                'late.firewall' => new SimpleFirewall([
                    'pattern'  => '^/late',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
        ]);
    }

    /**
     * Calling addFirewall() after boot throws LogicException.
     */
    public function testAddFirewallAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');

        $kernel->addFirewall('late.firewall', [
            'pattern'  => '^/late',
            'policies' => ['mauth' => true],
        ]);
    }

    /**
     * Calling getSecurityConfig() after boot throws LogicException.
     */
    public function testGetSecurityConfigAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot query security config after the kernel has been booted.');

        $kernel->getSecurityConfig();
    }

    /**
     * Calling addAccessRule() after boot throws LogicException.
     */
    public function testAddAccessRuleAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');

        $kernel->addAccessRule(['pattern' => '^/late', 'roles' => 'ROLE_USER']);
    }

    /**
     * Calling addPolicy() after boot throws LogicException.
     */
    public function testAddPolicyAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');

        $kernel->addPolicy('late.policy', new TestAuthenticationPolicy());
    }

    /**
     * Calling addRoleHierarchy() after boot throws LogicException.
     */
    public function testAddRoleHierarchyAfterBootThrowsLogicException(): void
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
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');

        $kernel->addRoleHierarchy('ROLE_LATE', ['ROLE_USER']);
    }
}
