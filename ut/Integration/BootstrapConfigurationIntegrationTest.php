<?php
/**
 * Integration test for Bootstrap_Configuration chain (Requirement 9).
 *
 * Each test method directly constructs a SilexKernel with specific configuration,
 * verifying that the corresponding ServiceProvider is registered and behaves correctly.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Test\Security\SessionServiceProvider;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapConfigurationIntegrationTest extends TestCase
{
    use RouteCacheCleaner;

    /**
     * Clean route cache before each test to avoid stale cache issues.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->cleanRouteCache(__DIR__ . '/../cache');
    }

    // ---------------------------------------------------------------
    // AC 1: routing configured → CacheableRouterProvider registered,
    //        routes matchable
    // ---------------------------------------------------------------

    public function testRoutingConfigRegistersRouterProviderAndRoutesAreMatchable()
    {
        $app = new SilexKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'routing'   => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
            ],
            true
        );

        $app->boot();

        // After boot with routing config, request_matcher should be a GroupUrlMatcher
        $this->assertInstanceOf(
            GroupUrlMatcher::class,
            $app['request_matcher'],
            'request_matcher should be a GroupUrlMatcher when routing is configured'
        );

        // Routes defined in integration.routes.yml should be matchable
        $matched = $app['request_matcher']->match('/integration/public');
        $this->assertArrayHasKey('_controller', $matched);
        $this->assertContains('publicAction', $matched['_controller']);
    }

    // ---------------------------------------------------------------
    // AC 2: security configured → SimpleSecurityProvider registered,
    //        firewall active
    // ---------------------------------------------------------------

    public function testSecurityConfigRegistersSecurityProviderAndFirewallIsActive()
    {
        $app = new SilexKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'routing'   => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
            ],
            true
        );

        // Register security provider via service_providers magic property
        $securityProvider = new SimpleSecurityProvider();
        $secPolicy        = new TestAuthenticationPolicy();
        $securityProvider->addAuthenticationPolicy('mauth', $secPolicy);
        $securityProvider->addFirewall(
            'integration.secured',
            new SimpleFirewall(
                [
                    'pattern'  => '^/integration/secured',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]
            )
        );
        $securityProvider->addAccessRule(
            new TestAccessRule('^/integration/secured', 'ROLE_USER')
        );

        $app->service_providers = [
            $securityProvider,
            new SessionServiceProvider(),
        ];

        $app->boot();

        // security.firewalls should exist in the container after boot
        $this->assertTrue(
            isset($app['security.firewalls']),
            'security.firewalls should be registered in the container'
        );
        $this->assertInternalType('array', $app['security.firewalls']);
        $this->assertArrayHasKey(
            'integration.secured',
            $app['security.firewalls'],
            'The integration.secured firewall should be registered'
        );
    }

    // ---------------------------------------------------------------
    // AC 3: cors configured → CrossOriginResourceSharingProvider
    //        registered, CORS headers present
    // ---------------------------------------------------------------

    public function testCorsConfigRegistersCorsProviderAndHeadersArePresent()
    {
        $app = new SilexKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'routing'   => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
                'cors'      => [
                    [
                        'pattern' => '*',
                        'origins' => ['*'],
                    ],
                ],
            ],
            true
        );

        $app->view_handlers = [new JsonViewHandler()];

        $app->boot();

        // Send a request with Origin header to trigger CORS processing
        $request = Request::create(
            '/integration/public',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_Origin' => 'http://example.com',
            ]
        );

        $response = $app->handle($request);

        // CORS headers should be present on the response
        $this->assertTrue(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Access-Control-Allow-Origin header should be present when CORS is configured'
        );
    }

    // ---------------------------------------------------------------
    // AC 4: twig configured → SimpleTwigServiceProvider registered,
    //        templates renderable
    // ---------------------------------------------------------------

    public function testTwigConfigRegistersTwigProviderAndTemplatesAreRenderable()
    {
        $app = new SilexKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'twig'      => [
                    'template_dir' => __DIR__ . '/templates',
                ],
            ],
            true
        );

        $app->boot();

        // twig service should be available
        $this->assertTrue(
            isset($app['twig']),
            'twig service should be registered when twig is configured'
        );
        $this->assertInstanceOf(
            \Twig_Environment::class,
            $app['twig'],
            'twig service should be a Twig_Environment instance'
        );

        // Template should be renderable
        $rendered = $app['twig']->render('test.html.twig', ['name' => 'World']);
        $this->assertEquals('Hello World!', $rendered);
    }

    // ---------------------------------------------------------------
    // AC 5: middlewares configured → before/after middlewares execute
    // ---------------------------------------------------------------

    public function testMiddlewaresConfigExecutesBeforeAndAfterMiddlewares()
    {
        $app = new SilexKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'routing'   => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
            ],
            true
        );

        $app->view_handlers = [new JsonViewHandler()];

        // Configure middleware via the middlewares magic property
        $testMiddleware    = new TestMiddleware();
        $app->middlewares  = [$testMiddleware];

        $app->boot();

        // Before handling, middleware should not have been called
        $this->assertCount(0, $testMiddleware->getBeforeCalls(), 'before() should not be called before handling');
        $this->assertCount(0, $testMiddleware->getAfterCalls(), 'after() should not be called before handling');

        // Handle a request
        $request  = Request::create('/integration/public', 'GET');
        $response = $app->handle($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // After handling, both before and after should have been called
        $this->assertCount(
            1,
            $testMiddleware->getBeforeCalls(),
            'before() should be called once after handling a request'
        );
        $this->assertCount(
            1,
            $testMiddleware->getAfterCalls(),
            'after() should be called once after handling a request'
        );
    }
}
