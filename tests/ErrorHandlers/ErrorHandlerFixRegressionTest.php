<?php
declare(strict_types=1);

/**
 * Regression tests for Error Handling module fixes discovered during
 * the Silex migration behavior audit (release 3.3.0).
 *
 * These tests verify the correctness of specific fixes and ensure
 * they do not introduce new issues.
 *
 * Fix: Exception type filtering (shouldRunErrorHandler)
 * - Restores Silex ExceptionListenerWrapper::shouldRun() behavior
 * - When a handler's first parameter declares a specific exception type,
 *   the handler is skipped if the exception is not an instance of that type
 */

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorHandlerFixRegressionTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to error handler scenario routes.
     *
     * @param array<callable> $errorHandlers
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function buildConfig(array $errorHandlers, array $extra = []): array
    {
        return array_merge(
            [
                'cache_dir'      => static::createTempCacheDir(),
                'routing'        => $this->createRoutingConfig(
                    __DIR__ . '/scenario.routes.yml',
                    ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ),
                'error_handlers' => $errorHandlers,
            ],
            $extra,
        );
    }

    // -----------------------------------------------------------------
    // Fix: Exception type filtering (shouldRunErrorHandler)
    // Equivalent to Silex ExceptionListenerWrapper::shouldRun()
    // -----------------------------------------------------------------

    /**
     * Handler with specific exception type in first parameter should only
     * be called when the exception matches that type.
     *
     * Scenario: Register a handler typed for AccessDeniedHttpException,
     * then trigger a RuntimeException → handler should be skipped.
     */
    public function testTypedHandlerSkippedForNonMatchingException(): void
    {
        $typedHandlerCalled = false;
        $fallbackHandlerCalled = false;

        // First handler: only handles AccessDeniedHttpException
        $typedHandler = function (AccessDeniedHttpException $e, Request $request, int $code) use (&$typedHandlerCalled): Response {
            $typedHandlerCalled = true;

            return new JsonResponse(['handler' => 'typed', 'code' => $code], $code);
        };

        // Second handler: catches all exceptions
        $fallbackHandler = function (\Exception $e, Request $request, int $code) use (&$fallbackHandlerCalled): Response {
            $fallbackHandlerCalled = true;

            return new JsonResponse(['handler' => 'fallback', 'message' => $e->getMessage()], $code);
        };

        $config = $this->buildConfig([$typedHandler, $fallbackHandler]);
        $kernel = $this->buildKernel($config);

        // Trigger a RuntimeException (not AccessDeniedHttpException)
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        // Typed handler should NOT have been called (exception type mismatch)
        $this->assertFalse($typedHandlerCalled, 'Typed handler should be skipped for non-matching exception');
        // Fallback handler should have been called
        $this->assertTrue($fallbackHandlerCalled, 'Fallback handler should catch the exception');

        $data = $this->assertJsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertSame('fallback', $data['handler']);
    }

    /**
     * Handler with specific exception type in first parameter should be
     * called when the exception matches that type.
     *
     * Scenario: Register a handler typed for AccessDeniedHttpException,
     * then trigger an AccessDeniedHttpException → handler should be called.
     */
    public function testTypedHandlerCalledForMatchingException(): void
    {
        $typedHandlerCalled = false;

        // Handler: only handles AccessDeniedHttpException
        $typedHandler = function (AccessDeniedHttpException $e, Request $request, int $code) use (&$typedHandlerCalled): Response {
            $typedHandlerCalled = true;

            return new JsonResponse(['handler' => 'typed', 'message' => $e->getMessage()], $code);
        };

        $config = $this->buildConfig([$typedHandler]);
        $kernel = $this->buildKernel($config);

        // Trigger an AccessDeniedHttpException
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');

        // Typed handler should have been called (exception type matches)
        $this->assertTrue($typedHandlerCalled, 'Typed handler should be called for matching exception');

        $data = $this->assertJsonResponse($response, Response::HTTP_FORBIDDEN);
        $this->assertSame('typed', $data['handler']);
        $this->assertSame('Access denied for scenario test', $data['message']);
    }

    /**
     * Handler with specific exception type should also match subclasses
     * (instanceof semantics, not exact type match).
     *
     * Scenario: Register a handler typed for \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface,
     * then trigger an AccessDeniedHttpException (which implements HttpExceptionInterface) → handler should be called.
     */
    public function testTypedHandlerMatchesSubclasses(): void
    {
        $handlerCalled = false;

        // Handler: handles any HttpException (base class of AccessDeniedHttpException)
        $handler = function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request, int $code) use (&$handlerCalled): Response {
            $handlerCalled = true;

            return new JsonResponse(['handler' => 'http_exception', 'code' => $code], $code);
        };

        $config = $this->buildConfig([$handler]);
        $kernel = $this->buildKernel($config);

        // Trigger an AccessDeniedHttpException (subclass of HttpException)
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');

        // Handler should be called (AccessDeniedHttpException extends HttpException)
        $this->assertTrue($handlerCalled, 'Handler should match subclass exceptions');
        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    /**
     * Handler with generic \Exception type should match all exceptions
     * (no filtering applied).
     */
    public function testGenericExceptionHandlerMatchesAll(): void
    {
        $callCount = 0;

        $handler = function (\Exception $e, Request $request, int $code) use (&$callCount): Response {
            $callCount++;

            return new JsonResponse(['handler' => 'generic'], $code);
        };

        $config = $this->buildConfig([$handler]);
        $kernel = $this->buildKernel($config);

        // RuntimeException
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');
        $this->assertSame(1, $callCount);
        $this->assertStatusCode($response, Response::HTTP_INTERNAL_SERVER_ERROR);

        // AccessDeniedHttpException (need a new kernel for a fresh request)
        $kernel = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');
        $this->assertSame(2, $callCount);
        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    /**
     * Handler with no type declaration on first parameter should match
     * all exceptions (no filtering applied).
     */
    public function testUntypedHandlerMatchesAll(): void
    {
        $handlerCalled = false;

        // Handler with no type declaration on first parameter
        $handler = function ($e, Request $request, int $code) use (&$handlerCalled): Response {
            $handlerCalled = true;

            return new JsonResponse(['handler' => 'untyped'], $code);
        };

        $config = $this->buildConfig([$handler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        $this->assertTrue($handlerCalled, 'Untyped handler should match all exceptions');
        $this->assertStatusCode($response, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Multiple typed handlers in chain: only the matching one should be called.
     *
     * Scenario: [NotFoundHandler, AccessDeniedHandler, FallbackHandler]
     * Trigger AccessDeniedHttpException → only AccessDeniedHandler should fire.
     */
    public function testMultipleTypedHandlersChainFiltering(): void
    {
        $callOrder = [];

        $notFoundHandler = function (NotFoundHttpException $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'not_found';

            return new JsonResponse(['handler' => 'not_found'], $code);
        };

        $accessDeniedHandler = function (AccessDeniedHttpException $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'access_denied';

            return new JsonResponse(['handler' => 'access_denied'], $code);
        };

        $fallbackHandler = function (\Exception $e, Request $request, int $code) use (&$callOrder): Response {
            $callOrder[] = 'fallback';

            return new JsonResponse(['handler' => 'fallback'], $code);
        };

        $config = $this->buildConfig([$notFoundHandler, $accessDeniedHandler, $fallbackHandler]);
        $kernel = $this->buildKernel($config);

        // Trigger AccessDeniedHttpException
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');

        // NotFoundHandler should be skipped, AccessDeniedHandler should fire and short-circuit
        $this->assertSame(['access_denied'], $callOrder);
        $data = $this->assertJsonResponse($response, Response::HTTP_FORBIDDEN);
        $this->assertSame('access_denied', $data['handler']);
    }

    /**
     * Invokable class handler with typed first parameter should be
     * correctly filtered by exception type.
     */
    public function testInvokableClassHandlerTypeFiltering(): void
    {
        $handler = new class {
            public bool $called = false;

            public function __invoke(AccessDeniedHttpException $e, Request $request, int $code): Response
            {
                $this->called = true;

                return new JsonResponse(['handler' => 'invokable_typed'], $code);
            }
        };

        $fallback = function (\Exception $e, Request $request, int $code): Response {
            return new JsonResponse(['handler' => 'fallback'], $code);
        };

        $config = $this->buildConfig([$handler, $fallback]);
        $kernel = $this->buildKernel($config);

        // Trigger RuntimeException — invokable handler should be skipped
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        $this->assertFalse($handler->called, 'Invokable typed handler should be skipped for non-matching exception');
        $data = $this->assertJsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertSame('fallback', $data['handler']);
    }
}
