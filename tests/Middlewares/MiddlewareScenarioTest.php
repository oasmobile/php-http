<?php
declare(strict_types=1);

/**
 * Middleware module scenario tests.
 *
 * Verifies Middleware behavior from a user-scenario perspective:
 * construct MicroKernel → configure middlewares → boot → send request → assert response.
 *
 * These tests establish a behavioral baseline for the Silex → Symfony migration
 * audit, complementing existing unit/integration tests with scenario-level coverage.
 *
 * @see AbstractMiddlewareTest for existing unit tests on default priorities
 * @see SilexKernelCrossCommunityIntegrationTest for existing integration tests on middleware execution
 */

namespace Oasis\Mlib\Http\Test\Middlewares;

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\ExceptionThrowingMiddleware;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\HeaderAppendingAfterMiddleware;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\MarkerBeforeMiddleware;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\ShortCircuitMiddleware;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\SubRequestAwareMiddleware;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MiddlewareScenarioTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to middleware scenario routes.
     *
     * @param array<\Oasis\Mlib\Http\Middlewares\MiddlewareInterface> $middlewares
     * @param array<string, mixed>                                     $extra
     *
     * @return array<string, mixed>
     */
    private function buildMiddlewareConfig(array $middlewares, array $extra = []): array
    {
        return array_merge(
            [
                'cache_dir'      => static::createTempCacheDir(),
                'routing'        => $this->createRoutingConfig(
                    __DIR__ . '/scenario.routes.yml',
                    ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ),
                'view_handlers'  => [new JsonViewHandler()],
                'error_handlers' => [new JsonErrorHandler()],
                'middlewares'    => $middlewares,
            ],
            $extra,
        );
    }

    // -----------------------------------------------------------------
    // R6-AC1: Before middleware execution
    // -----------------------------------------------------------------

    /**
     * Register a before middleware → boot → send request → verify the
     * middleware executes before the controller.
     *
     * The MarkerBeforeMiddleware sets a request attribute that the
     * controller echoes back, proving the middleware ran first.
     */
    public function testBeforeMiddlewareExecution(): void
    {
        $marker = new MarkerBeforeMiddleware('before_executed');

        $config = $this->buildMiddlewareConfig([$marker]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/middleware/echo');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['controller_called']);
        $this->assertSame('before_executed', $data['middleware_marker']);
    }

    // -----------------------------------------------------------------
    // R6-AC2: After middleware execution
    // -----------------------------------------------------------------

    /**
     * Register an after middleware → boot → send request → verify the
     * middleware executes after the controller and can modify the response.
     *
     * The HeaderAppendingAfterMiddleware adds a custom header to the
     * response, proving it ran after the controller produced the response.
     */
    public function testAfterMiddlewareExecution(): void
    {
        $afterMiddleware = new HeaderAppendingAfterMiddleware(
            'X-Scenario-After',
            'middleware-applied',
        );

        $config = $this->buildMiddlewareConfig([$afterMiddleware]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/middleware/hello');

        // Controller should have executed normally
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['controller_called']);

        // After middleware should have added the header
        $this->assertSame(
            'middleware-applied',
            $response->headers->get('X-Scenario-After'),
        );
    }

    // -----------------------------------------------------------------
    // R6-AC3: Middleware priority ordering
    // -----------------------------------------------------------------

    /**
     * Register multiple before middlewares with different priorities →
     * verify execution order is strictly descending by priority
     * (higher priority number executes first).
     *
     * Priority 100 ("first") should execute before priority 50 ("second"),
     * which should execute before priority 10 ("third").
     */
    public function testMiddlewarePriorityOrdering(): void
    {
        $sharedLog = [];

        $first  = new MarkerBeforeMiddleware('first', 100);
        $second = new MarkerBeforeMiddleware('second', 50);
        $third  = new MarkerBeforeMiddleware('third', 10);

        $first->useSharedLog($sharedLog);
        $second->useSharedLog($sharedLog);
        $third->useSharedLog($sharedLog);

        // Register in non-priority order to verify sorting
        $config = $this->buildMiddlewareConfig([$third, $first, $second]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/middleware/hello');

        $this->assertStatusCode($response, Response::HTTP_OK);

        // Execution order should follow priority descending: first(100) → second(50) → third(10)
        $this->assertSame(['first', 'second', 'third'], $sharedLog);
    }

    // -----------------------------------------------------------------
    // R6-AC4: Before middleware short-circuit
    // -----------------------------------------------------------------

    /**
     * Register a before middleware that returns a Response → verify the
     * controller does not execute and the middleware's Response is returned.
     */
    public function testBeforeMiddlewareShortCircuit(): void
    {
        $shortCircuit = new ShortCircuitMiddleware(Response::HTTP_ACCEPTED);

        $config = $this->buildMiddlewareConfig([$shortCircuit]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/middleware/hello');

        // The short-circuit middleware should have returned its own response
        $data = $this->assertJsonResponse($response, Response::HTTP_ACCEPTED);
        $this->assertTrue($data['short_circuited']);
        $this->assertSame('ShortCircuitMiddleware', $data['middleware']);

        // The controller should NOT have been called — verify by checking
        // that the response body does not contain controller_called
        $this->assertArrayNotHasKey('controller_called', $data);
    }

    // -----------------------------------------------------------------
    // R6-AC5: Master-request-only filtering
    // -----------------------------------------------------------------

    /**
     * Register a middleware with onlyForMasterRequest() = true → verify
     * the middleware executes for main requests and does not execute for
     * sub-requests.
     *
     * We test this by:
     * 1. Sending a main request → middleware should execute (call count = 1)
     * 2. Manually dispatching a sub-request → middleware should NOT execute
     */
    public function testMasterRequestOnlyFiltering(): void
    {
        $masterOnlyMiddleware = new SubRequestAwareMiddleware(true);

        $config = $this->buildMiddlewareConfig([$masterOnlyMiddleware]);
        $kernel = $this->buildKernel($config);
        $kernel->boot();

        // 1. Main request — middleware should execute
        $mainRequest  = Request::create('/scenario/middleware/hello', 'GET');
        $mainResponse = $kernel->handle($mainRequest, HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame(Response::HTTP_OK, $mainResponse->getStatusCode());
        $this->assertSame(1, $masterOnlyMiddleware->getBeforeCallCount());

        // 2. Sub-request — middleware should NOT execute (onlyForMasterRequest = true)
        $subRequest  = Request::create('/scenario/middleware/hello', 'GET');
        $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        $this->assertSame(Response::HTTP_OK, $subResponse->getStatusCode());
        // Call count should still be 1 (not incremented by sub-request)
        $this->assertSame(1, $masterOnlyMiddleware->getBeforeCallCount());
    }

    /**
     * Register a middleware with onlyForMasterRequest() = false → verify
     * the middleware executes for BOTH main requests and sub-requests.
     */
    public function testSubRequestMiddlewareExecutesForBothRequestTypes(): void
    {
        $allRequestsMiddleware = new SubRequestAwareMiddleware(false);

        $config = $this->buildMiddlewareConfig([$allRequestsMiddleware]);
        $kernel = $this->buildKernel($config);
        $kernel->boot();

        // 1. Main request
        $mainRequest = Request::create('/scenario/middleware/hello', 'GET');
        $kernel->handle($mainRequest, HttpKernelInterface::MAIN_REQUEST);
        $this->assertSame(1, $allRequestsMiddleware->getBeforeCallCount());

        // 2. Sub-request — middleware should also execute
        $subRequest = Request::create('/scenario/middleware/hello', 'GET');
        $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->assertSame(2, $allRequestsMiddleware->getBeforeCallCount());
    }

    // -----------------------------------------------------------------
    // R6-AC6: Middleware exception behavior
    // -----------------------------------------------------------------

    /**
     * Register a before middleware that throws an exception → verify the
     * Error_Handler_Chain is invoked and produces a response.
     *
     * The JsonErrorHandler is configured as the error handler, so it should
     * catch the RuntimeException and return a JSON error response.
     */
    public function testMiddlewareExceptionBehavior(): void
    {
        $exceptionMiddleware = new ExceptionThrowingMiddleware('Middleware boom');

        $config = $this->buildMiddlewareConfig([$exceptionMiddleware]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/middleware/hello');

        // The error handler chain should have caught the exception.
        // JsonErrorHandler returns an array → JsonViewHandler converts to JSON response.
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Middleware boom', $data['message']);
        $this->assertSame(500, $data['code']);
    }
}
