<?php
/**
 * Integration test for Bootstrap_Configuration chain.
 *
 * Each test method directly constructs a MicroKernel with specific configuration,
 * verifying that the corresponding subsystem is registered and behaves correctly.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
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
    protected function setUp(): void
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
        $app = new MicroKernel(
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
            $app->getRequestMatcher(),
            'request_matcher should be a GroupUrlMatcher when routing is configured'
        );

        // Routes defined in integration.routes.yml should be matchable
        $matched = $app->getRequestMatcher()->match('/integration/public');
        $this->assertArrayHasKey('_controller', $matched);
        $this->assertStringContainsString('publicAction', $matched['_controller']);
    }

    // ---------------------------------------------------------------
    // AC 3: cors configured → CORS subscriber registered,
    //        CORS headers present
    // ---------------------------------------------------------------

    public function testCorsConfigRegistersCorsProviderAndHeadersArePresent()
    {
        $app = new MicroKernel(
            [
                'cache_dir'      => __DIR__ . '/../cache',
                'routing'        => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
                'cors'           => [
                    [
                        'pattern' => '*',
                        'origins' => ['*'],
                    ],
                ],
                'view_handlers'  => [new JsonViewHandler()],
            ],
            true
        );

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
    // AC 4: twig configured → Twig registered,
    //        templates renderable
    // ---------------------------------------------------------------

    public function testTwigConfigRegistersTwigProviderAndTemplatesAreRenderable()
    {
        $app = new MicroKernel(
            [
                'cache_dir' => __DIR__ . '/../cache',
                'twig'      => [
                    'template_dir' => __DIR__ . '/templates',
                ],
            ],
            true
        );

        $app->boot();

        // twig should be available via getTwig()
        $this->assertInstanceOf(
            \Twig\Environment::class,
            $app->getTwig(),
            'getTwig() should return a Twig\\Environment instance when twig is configured'
        );

        // Template should be renderable
        $rendered = $app->getTwig()->render('test.html.twig', ['name' => 'World']);
        $this->assertEquals('Hello World!', $rendered);
    }

    // ---------------------------------------------------------------
    // AC 5: middlewares configured → before/after middlewares execute
    // ---------------------------------------------------------------

    public function testMiddlewaresConfigExecutesBeforeAndAfterMiddlewares()
    {
        $testMiddleware = new TestMiddleware();

        $app = new MicroKernel(
            [
                'cache_dir'      => __DIR__ . '/../cache',
                'routing'        => [
                    'path'       => __DIR__ . '/integration.routes.yml',
                    'namespaces' => [
                        'Oasis\\Mlib\\Http\\Test\\Integration\\',
                    ],
                ],
                'view_handlers'  => [new JsonViewHandler()],
                'middlewares'    => [$testMiddleware],
            ],
            true
        );

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
