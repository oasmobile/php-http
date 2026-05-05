<?php
declare(strict_types=1);

/**
 * MicroKernel aggregation layer scenario tests.
 *
 * Verifies cross-module interactions and aggregation layer behavior from a
 * user-scenario perspective: construct MicroKernel → configure multiple modules
 * → boot → send request → assert response.
 *
 * These tests establish a behavioral baseline for the MicroKernel aggregation
 * layer, verifying that all modules cooperate correctly when combined.
 *
 * @see SilexKernelCrossCommunityIntegrationTest for existing cross-community tests
 * @see BootstrapConfigurationIntegrationTest for existing configuration tests
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\HeaderAppendingAfterMiddleware;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\MarkerBeforeMiddleware;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MicroKernelAggregationScenarioTest extends ScenarioTestCase
{
    // -----------------------------------------------------------------
    // R16-AC1: Full pipeline traversal
    // -----------------------------------------------------------------

    /**
     * Configure routing + security + CORS + middleware + view handler + error handler
     * → boot MicroKernel → send a normal request → verify the request traverses
     * the complete pipeline and produces the expected response.
     */
    public function testFullPipelineTraversal(): void
    {
        $beforeMiddleware = new MarkerBeforeMiddleware('pipeline_before');
        $afterMiddleware  = new HeaderAppendingAfterMiddleware(
            'X-Pipeline-After',
            'after-applied',
        );

        $config = [
            'cache_dir'      => static::createTempCacheDir(),
            'routing'        => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'security'       => [
                'policies'      => [
                    'mauth' => new TestAuthenticationPolicy(),
                ],
                'firewalls'     => [
                    'aggregation.main' => new SimpleFirewall([
                        'pattern'  => '^/aggregation',
                        'policies' => ['mauth' => true],
                        'users'    => new TestApiUserProvider(),
                    ]),
                ],
                'access_rules'  => [
                    ['pattern' => '^/aggregation/pipeline', 'roles' => 'ROLE_USER'],
                ],
                'role_hierarchy' => [
                    'ROLE_ADMIN' => ['ROLE_USER'],
                ],
            ],
            'cors'           => [
                [
                    'pattern' => '/aggregation/.*',
                    'origins' => ['example.com'],
                    'headers' => ['Authorization'],
                    'max_age' => 600,
                ],
            ],
            'middlewares'    => [$beforeMiddleware, $afterMiddleware],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
        ];

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/aggregation/pipeline/full',
            ['sig' => 'abcd'],
            [
                'HTTP_ORIGIN' => 'http://example.com',
            ],
        );

        // Verify response status
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        // Verify controller was called (routing worked)
        $this->assertTrue($data['controller_called']);
        $this->assertSame('fullPipeline', $data['action']);

        // Verify security: user authenticated via pre_auth
        $this->assertSame('admin', $data['user']);
        $this->assertTrue($data['is_authenticated']);

        // Verify before middleware executed
        $this->assertSame('pipeline_before', $data['middleware_marker']);

        // Verify after middleware executed (added header to response)
        $this->assertSame('after-applied', $response->headers->get('X-Pipeline-After'));

        // Verify CORS headers present (cross-origin request)
        $this->assertSame('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));

        // Verify view handler processed the array return (JSON response)
        $this->assertJson($response->getContent());
    }

    // -----------------------------------------------------------------
    // R16-AC2: Minimal configuration
    // -----------------------------------------------------------------

    /**
     * Construct MicroKernel with only `routing` config → boot → send request
     * → verify basic request-response cycle works.
     */
    public function testMinimalConfiguration(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/aggregation/minimal');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['controller_called']);
        $this->assertSame('minimal', $data['action']);
    }

    // -----------------------------------------------------------------
    // R16-AC3: No optional modules
    // -----------------------------------------------------------------

    /**
     * Construct MicroKernel without `security`, `cors`, `twig` → boot → send
     * request → verify the kernel operates correctly with only routing.
     */
    public function testNoOptionalModules(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Verify no security is configured
        $this->assertNull($kernel->getToken());
        $this->assertNull($kernel->getUser());
        $this->assertNull($kernel->getTwig());
        $this->assertFalse($kernel->isGranted('ROLE_USER'));

        // Verify request-response still works
        $response = $this->handleRequest($kernel, 'GET', '/aggregation/minimal');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['controller_called']);
        $this->assertSame('minimal', $data['action']);
    }

    // -----------------------------------------------------------------
    // R16-AC4: addControllerInjectedArg
    // -----------------------------------------------------------------

    /**
     * Register a custom object via addControllerInjectedArg() → boot → verify
     * the object is available as a controller argument.
     */
    public function testAddControllerInjectedArg(): void
    {
        $service = new AggregationTestService('custom-injected-value');

        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);
        $kernel->addControllerInjectedArg($service);

        $response = $this->handleRequest($kernel, 'GET', '/aggregation/injected');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['controller_called']);
        $this->assertSame('injectedArg', $data['action']);
        $this->assertSame('custom-injected-value', $data['service_value']);
    }

    // -----------------------------------------------------------------
    // R16-AC5: addExtraParameters
    // -----------------------------------------------------------------

    /**
     * Add extra parameters via addExtraParameters() → verify getParameter()
     * returns the added values.
     */
    public function testAddExtraParameters(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Add extra parameters before boot
        $kernel->addExtraParameters([
            'app.name'    => 'test-app',
            'app.version' => '3.3.0',
            'app.debug'   => true,
        ]);

        // Boot the kernel
        $kernel->boot();

        // Verify getParameter() returns added values
        $this->assertSame('test-app', $kernel->getParameter('app.name'));
        $this->assertSame('3.3.0', $kernel->getParameter('app.version'));
        $this->assertTrue($kernel->getParameter('app.debug'));

        // Verify default value for non-existent key
        $this->assertNull($kernel->getParameter('non.existent'));
        $this->assertSame('fallback', $kernel->getParameter('non.existent', 'fallback'));

        // Verify addExtraParameters merges (does not overwrite)
        $kernel->addExtraParameters(['app.extra' => 'new-value']);
        $this->assertSame('test-app', $kernel->getParameter('app.name'));
        $this->assertSame('new-value', $kernel->getParameter('app.extra'));
    }

    // -----------------------------------------------------------------
    // R16-AC6: Slow request detection
    // -----------------------------------------------------------------

    /**
     * Configure a controller that exceeds the slow request threshold → verify
     * slow request logging behavior.
     *
     * We use a custom slow request handler to capture the callback invocation
     * rather than relying on mwarning() output.
     */
    public function testSlowRequestDetection(): void
    {
        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $this->createRoutingConfig(
                __DIR__ . '/aggregation-scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
            ),
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Set a very low threshold (10ms) so the 60ms sleep in the controller triggers it
        $slowRequestCaptured = false;
        $capturedRequest     = null;

        $reflection = new \ReflectionClass($kernel);

        $thresholdProp = $reflection->getProperty('slowRequestThreshold');
        $thresholdProp->setValue($kernel, 10); // 10ms threshold

        $handlerProp = $reflection->getProperty('slowRequestHandler');
        $handlerProp->setValue($kernel, function (
            Request $request,
            float $startTime,
            float $responseSentTime,
            float $endTime,
        ) use (&$slowRequestCaptured, &$capturedRequest) {
            $slowRequestCaptured = true;
            $capturedRequest     = $request;
        });

        // Use run() instead of handleRequest() because slow request detection
        // happens in run() after response->send() and terminate()
        $kernel->boot();
        $request = Request::create('/aggregation/slow', 'GET');

        // Capture output from response->send()
        ob_start();
        $kernel->run($request);
        ob_end_clean();

        // Verify slow request handler was invoked
        $this->assertTrue($slowRequestCaptured, 'Slow request handler should have been invoked');
        $this->assertNotNull($capturedRequest);
        $this->assertSame('/aggregation/slow', $capturedRequest->getPathInfo());
    }
}
