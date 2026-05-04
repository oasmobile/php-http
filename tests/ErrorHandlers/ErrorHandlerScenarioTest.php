<?php
declare(strict_types=1);

/**
 * Error Handling module scenario tests.
 *
 * Verifies Error Handler Chain behavior from a user-scenario perspective:
 * construct MicroKernel → configure error handlers → boot → send request
 * that triggers exception → assert response.
 *
 * These tests establish a behavioral baseline for the Silex → Symfony migration
 * audit, complementing existing unit/integration tests with scenario-level coverage.
 *
 * @see ExceptionWrapperTest for existing unit tests on ExceptionWrapper
 * @see HttpExceptionTest for existing integration tests on HTTP exception handling
 */

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorHandlerScenarioTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to error handler scenario routes.
     *
     * @param array<callable> $errorHandlers
     * @param array<callable> $viewHandlers
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function buildErrorHandlerConfig(
        array $errorHandlers = [],
        array $viewHandlers = [],
        array $extra = [],
    ): array {
        return array_merge(
            [
                'cache_dir'      => static::createTempCacheDir(),
                'routing'        => $this->createRoutingConfig(
                    __DIR__ . '/scenario.routes.yml',
                    ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ),
                'error_handlers' => $errorHandlers,
                'view_handlers'  => $viewHandlers,
            ],
            $extra,
        );
    }

    // -----------------------------------------------------------------
    // R10-AC1: Custom error handler
    // -----------------------------------------------------------------

    /**
     * Register an error handler via Bootstrap_Config → boot MicroKernel →
     * send request that triggers an exception → verify the error handler
     * receives the exception and its Response is returned.
     */
    public function testCustomErrorHandler(): void
    {
        $handlerCalled = false;

        $customHandler = function (\Exception $e, Request $request, int $code) use (&$handlerCalled): Response {
            $handlerCalled = true;

            return new JsonResponse([
                'handled'   => true,
                'message'   => $e->getMessage(),
                'code'      => $code,
                'handler'   => 'custom',
            ], $code);
        };

        $config = $this->buildErrorHandlerConfig([$customHandler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        $this->assertTrue($handlerCalled, 'Custom error handler should have been called');
        $data = $this->assertJsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertTrue($data['handled']);
        $this->assertSame('Scenario runtime error', $data['message']);
        $this->assertSame(500, $data['code']);
        $this->assertSame('custom', $data['handler']);
    }

    // -----------------------------------------------------------------
    // R10-AC2: Error handler chain ordering
    // -----------------------------------------------------------------

    /**
     * Register multiple error handlers → trigger an exception → verify
     * handlers are invoked in registration order and the first handler
     * returning a Response short-circuits the chain.
     */
    public function testErrorHandlerChainOrdering(): void
    {
        $callOrder = [];

        // First handler: returns a Response → should short-circuit
        $firstHandler = function (\Exception $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'first';

            return new JsonResponse([
                'handler' => 'first',
                'message' => $e->getMessage(),
            ], $code);
        };

        // Second handler: should NOT be called due to short-circuit
        $secondHandler = function (\Exception $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'second';

            return new JsonResponse([
                'handler' => 'second',
            ], $code);
        };

        $config = $this->buildErrorHandlerConfig([$firstHandler, $secondHandler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        // Only the first handler should have been called
        $this->assertSame(['first'], $callOrder);
        $data = $this->assertJsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertSame('first', $data['handler']);
    }

    // -----------------------------------------------------------------
    // R10-AC3: Error handler passthrough
    // -----------------------------------------------------------------

    /**
     * Register an error handler that returns null → verify the exception
     * propagates to the next handler or to the default Symfony exception handling.
     *
     * Chain: passthrough handler (returns null) → catching handler (returns Response)
     */
    public function testErrorHandlerPassthrough(): void
    {
        $callOrder = [];

        // First handler: returns null → passthrough
        $passthroughHandler = function (\Exception $e, Request $request, int $code) use (&$callOrder) {
            $callOrder[] = 'passthrough';

            return null;
        };

        // Second handler: catches and returns a Response
        $catchingHandler = function (\Exception $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'catching';

            return new JsonResponse([
                'handler' => 'catching',
                'message' => $e->getMessage(),
            ], $code);
        };

        $config = $this->buildErrorHandlerConfig([$passthroughHandler, $catchingHandler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        // Both handlers should have been called in order
        $this->assertSame(['passthrough', 'catching'], $callOrder);
        $data = $this->assertJsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertSame('catching', $data['handler']);
    }

    // -----------------------------------------------------------------
    // R10-AC4: HTTP exception status code preservation
    // -----------------------------------------------------------------

    /**
     * Throw an HttpException with status 403 → verify the response status
     * code is 403.
     *
     * The error handler receives the HTTP status code from the HttpException
     * and the response preserves it.
     */
    public function testHttpExceptionStatusCodePreservation(): void
    {
        $receivedCode = null;

        $handler = function (\Exception $e, Request $request, int $code) use (&$receivedCode): Response {
            $receivedCode = $code;

            return new JsonResponse([
                'handler' => 'status_code_test',
                'message' => $e->getMessage(),
                'code'    => $code,
            ], $code);
        };

        $config = $this->buildErrorHandlerConfig([$handler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');

        // The handler should have received 403 from the AccessDeniedHttpException
        $this->assertSame(403, $receivedCode);
        $data = $this->assertJsonResponse($response, Response::HTTP_FORBIDDEN);
        $this->assertSame(403, $data['code']);
        $this->assertSame('Access denied for scenario test', $data['message']);
    }

    // -----------------------------------------------------------------
    // R10-AC5: FallbackViewHandler error rendering
    // -----------------------------------------------------------------

    /**
     * Configure no custom error handler → trigger an exception → verify
     * FallbackViewHandler produces a response.
     *
     * When no error handler is registered, Symfony's default ErrorListener
     * handles the exception. However, when ExceptionWrapper + FallbackViewHandler
     * are configured as the error/view handler chain (the standard oasis/http
     * pattern), the FallbackViewHandler renders the WrappedExceptionInfo.
     *
     * This test verifies the standard oasis/http error rendering pipeline:
     * exception → ExceptionWrapper → WrappedExceptionInfo → FallbackViewHandler → Response
     */
    public function testFallbackViewHandlerErrorRendering(): void
    {
        // Use ExceptionWrapper as error handler (returns WrappedExceptionInfo, not Response)
        // and FallbackViewHandler as view handler — this is the standard oasis/http pattern.
        $kernelRef = null;
        $lazyFallback = null;

        $viewHandlerCallable = function ($result, $request) use (&$lazyFallback, &$kernelRef) {
            if ($lazyFallback === null) {
                $lazyFallback = new FallbackViewHandler($kernelRef, new RouteBasedResponseRendererResolver());
            }

            return $lazyFallback($result, $request);
        };

        $config = $this->buildErrorHandlerConfig(
            [new ExceptionWrapper()],
            [$viewHandlerCallable],
        );
        $kernel = $this->buildKernel($config);
        $kernelRef = $kernel;

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        // FallbackViewHandler should have produced a response via DefaultHtmlRenderer.
        // The default format is 'html', so DefaultHtmlRenderer::renderOnException is used,
        // which (without Twig) falls back to JSON-serialized WrappedExceptionInfo.
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        // The response body should contain the exception information.
        // DefaultHtmlRenderer encodes spaces as &nbsp; in HTML output,
        // so we check for the exception type and message accounting for HTML encoding.
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('RuntimeException', $content);
        // The message may contain &nbsp; instead of spaces in HTML rendering
        $this->assertStringContainsString('Scenario', $content);
        $this->assertStringContainsString('runtime', $content);
        $this->assertStringContainsString('error', $content);
    }
}
