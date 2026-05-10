<?php
declare(strict_types=1);

/**
 * Regression tests for ISS-3.7.0-L01: ErrorHandlerTrait HTTP status code mismatch.
 *
 * Bug: When an error handler returns a non-Response object (e.g. WrappedExceptionInfo)
 * whose getCode() differs from the original $code passed to the handler,
 * ErrorHandlerTrait uses the original $code for setStatusCode() instead of
 * the handler-returned object's getCode().
 *
 * These tests are expected to FAIL until the bug is fixed.
 *
 * @see ISS-3.7.0-L01-error-handler-status-code-mismatch.md
 */

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ErrorHandlerStatusCodeRegressionTest extends ScenarioTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function buildConfig(array $errorHandlers, array $viewHandlers = [], array $extra = []): array
    {
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
    // registerErrorHandlers: status code from handler response
    // -----------------------------------------------------------------

    /**
     * When error handler returns an object with getCode() != original $code,
     * the HTTP response status code should use getCode() value.
     *
     * Scenario: RuntimeException (original $code = 500) → error handler returns
     * object with getCode() = 400 → view handler wraps it → HTTP status should be 400.
     */
    public function testRegisterErrorHandlers_usesHandlerResponseCode(): void
    {
        // Error handler: returns an object with getCode() = 400 (simulating ExceptionWrapper
        // handling a DataValidationException)
        $errorHandler = function (\Exception $e, Request $request, int $code): WrappedExceptionInfo {
            $info = new WrappedExceptionInfo($e, $code);
            $info->setCode(Response::HTTP_BAD_REQUEST); // 400
            return $info;
        };

        // View handler: converts WrappedExceptionInfo to a JsonResponse
        $viewHandler = function (mixed $result, Request $request): ?Response {
            if ($result instanceof WrappedExceptionInfo) {
                return new JsonResponse([
                    'code'    => $result->getCode(),
                    'message' => $result->getException()->getMessage(),
                ]);
            }
            return null;
        };

        $config = $this->buildConfig([$errorHandler], [$viewHandler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        // Body correctly reports 400
        $data = json_decode($response->getContent(), true);
        $this->assertSame(400, $data['code']);

        // BUG: HTTP status code should be 400, but currently returns 500
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'HTTP status code should match handler response getCode() (400), not original $code (500)',
        );
    }

    /**
     * Same bug via ExceptionWrapper + DataValidationException path (the real-world scenario).
     *
     * ExceptionWrapper recognizes DataValidationException and sets code to 400.
     * The HTTP response status should be 400, not 500.
     */
    public function testRegisterErrorHandlers_ExceptionWrapper_DataValidationException(): void
    {
        // Error handler: ExceptionWrapper (the standard oasis/http error handler)
        // We need a controller that throws DataValidationException, but we can
        // achieve the same effect with a custom handler that simulates the behavior.
        $errorHandler = function (\Exception $e, Request $request, int $code): WrappedExceptionInfo {
            // Simulate ExceptionWrapper behavior for DataValidationException
            $info = new WrappedExceptionInfo($e, $code);
            // ExceptionWrapper sets 400 for DataValidationException;
            // here we just set it directly since the controller throws RuntimeException
            $info->setCode(Response::HTTP_BAD_REQUEST);
            return $info;
        };

        $viewHandler = function (mixed $result, Request $request): ?Response {
            if ($result instanceof WrappedExceptionInfo) {
                return new JsonResponse($result->toArray());
            }
            return null;
        };

        $config = $this->buildConfig([$errorHandler], [$viewHandler]);
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-runtime');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(400, $data['code'], 'Response body code should be 400');

        // BUG: HTTP status should be 400
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'HTTP status code should be 400 (from WrappedExceptionInfo::getCode()), not 500',
        );
    }

    // -----------------------------------------------------------------
    // registerSingleErrorHandler: same bug (post-boot registration path)
    // -----------------------------------------------------------------

    /**
     * Same bug exists in registerSingleErrorHandler (used when error() is called
     * after kernel boot).
     *
     * This verifies the post-boot error handler registration path has the same issue.
     */
    public function testRegisterSingleErrorHandler_usesHandlerResponseCode(): void
    {
        // Build kernel with no error handlers, boot it, then register via error()
        $viewHandler = function (mixed $result, Request $request): ?Response {
            if ($result instanceof WrappedExceptionInfo) {
                return new JsonResponse([
                    'code'    => $result->getCode(),
                    'message' => $result->getException()->getMessage(),
                ]);
            }
            return null;
        };

        $config = $this->buildConfig([], [$viewHandler]);
        $kernel = $this->buildKernel($config);
        $kernel->boot();

        // Register error handler AFTER boot → uses registerSingleErrorHandler path
        $kernel->error(function (\Exception $e, Request $request, int $code): WrappedExceptionInfo {
            $info = new WrappedExceptionInfo($e, $code);
            $info->setCode(Response::HTTP_NOT_FOUND); // 404
            return $info;
        });

        $request  = Request::create('/scenario/error/throw-runtime', 'GET');
        $response = $kernel->handle($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(404, $data['code'], 'Response body code should be 404');

        // BUG: HTTP status should be 404
        $this->assertSame(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            'HTTP status code should be 404 (from WrappedExceptionInfo::getCode()), not 500',
        );
    }

    // -----------------------------------------------------------------
    // Baseline: HttpExceptionInterface is NOT affected
    // -----------------------------------------------------------------

    /**
     * Verify that HttpExceptionInterface exceptions are NOT affected by this bug
     * (their status code comes from getStatusCode() and is used as $code directly).
     */
    public function testHttpExceptionInterface_statusCodeCorrect(): void
    {
        $errorHandler = function (\Exception $e, Request $request, int $code): WrappedExceptionInfo {
            // For HttpException, $code is already correct (403)
            $info = new WrappedExceptionInfo($e, $code);
            return $info;
        };

        $viewHandler = function (mixed $result, Request $request): ?Response {
            if ($result instanceof WrappedExceptionInfo) {
                return new JsonResponse([
                    'code'    => $result->getCode(),
                    'message' => $result->getException()->getMessage(),
                ]);
            }
            return null;
        };

        $config = $this->buildConfig([$errorHandler], [$viewHandler]);
        $kernel = $this->buildKernel($config);

        // AccessDeniedHttpException → $code = 403 from getStatusCode()
        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-forbidden');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(403, $data['code']);

        // This should pass — HttpExceptionInterface is not affected
        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $response->getStatusCode(),
            'HttpExceptionInterface status code should work correctly (baseline)',
        );
    }

    // -----------------------------------------------------------------
    // End-to-end: ExceptionWrapper + real business exceptions
    // -----------------------------------------------------------------

    /**
     * Build config using ExceptionWrapper as error handler (the standard oasis/http pattern).
     *
     * @return array<string, mixed>
     */
    private function buildExceptionWrapperConfig(): array
    {
        $viewHandler = function (mixed $result, Request $request): ?Response {
            if ($result instanceof WrappedExceptionInfo) {
                return new JsonResponse($result->toArray());
            }
            return null;
        };

        return $this->buildConfig([new ExceptionWrapper()], [$viewHandler]);
    }

    /**
     * MandatoryValueMissingException (mandatory parameter not provided) → HTTP 400.
     */
    public function testExceptionWrapper_MandatoryValueMissing_shouldReturn400(): void
    {
        $config = $this->buildExceptionWrapperConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-mandatory-missing');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(400, $data['code'], 'Body code should be 400 for MandatoryValueMissingException');
        $this->assertSame('name', $data['extra']['key']);

        // BUG: HTTP status should be 400, currently returns 500
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'MandatoryValueMissingException: HTTP status should be 400, not 500',
        );
    }

    /**
     * InvalidDataTypeException (parameter type mismatch) → HTTP 400.
     */
    public function testExceptionWrapper_InvalidDataType_shouldReturn400(): void
    {
        $config = $this->buildExceptionWrapperConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-invalid-type');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(400, $data['code'], 'Body code should be 400 for InvalidDataTypeException');
        $this->assertSame('age', $data['extra']['key']);

        // BUG: HTTP status should be 400, currently returns 500
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'InvalidDataTypeException: HTTP status should be 400, not 500',
        );
    }

    /**
     * InvalidValueException (parameter value invalid) → HTTP 400.
     */
    public function testExceptionWrapper_InvalidValue_shouldReturn400(): void
    {
        $config = $this->buildExceptionWrapperConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-invalid-value');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(400, $data['code'], 'Body code should be 400 for InvalidValueException');
        $this->assertSame('status', $data['extra']['key']);

        // BUG: HTTP status should be 400, currently returns 500
        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'InvalidValueException: HTTP status should be 400, not 500',
        );
    }

    /**
     * ExistenceViolationException (resource not found) → HTTP 404.
     */
    public function testExceptionWrapper_ExistenceViolation_shouldReturn404(): void
    {
        $config = $this->buildExceptionWrapperConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/error/throw-existence-violation');

        $data = json_decode($response->getContent(), true);
        $this->assertSame(404, $data['code'], 'Body code should be 404 for ExistenceViolationException');
        $this->assertSame('id', $data['extra']['key']);

        // BUG: HTTP status should be 404, currently returns 500
        $this->assertSame(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            'ExistenceViolationException: HTTP status should be 404, not 500',
        );
    }
}
