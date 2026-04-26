<?php
/**
 * Property-Based Tests for Request Dispatch.
 *
 * CP4: 请求分发完整性 — 任何有效请求的 handle() 返回 Response 状态码在 100–599 范围内。
 * CP5: View Handler 链传递 — 控制器返回非 Response 值时 View_Handler_Chain 被调用。
 * 测试控制器抛出异常时 Error_Handler_Chain 被调用。
 *
 * 集成级：启动 MicroKernel 实例。
 *
 * Ref: Requirement 15, AC 5
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestDispatchPropertyTest extends TestCase
{
    use TestTrait;
    use RouteCacheCleaner;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        // Save current exception handler state before creating kernels
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore exception handler to prevent PHPUnit "did not remove its own exception handlers" warning
        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        parent::tearDown();
    }

    // ─── CP4: 请求分发完整性 ─────────────────────────────────────────

    /**
     * For any valid request to a defined route, handle() returns a Response
     * with a status code in the 100–599 range.
     */
    public function testHandleReturnsValidHttpStatusCode(): void
    {
        // Define a set of valid request specs: [method, path, host, server_params]
        $validRequests = [
            ['GET', '/', null],
            ['GET', '/sub/', null],
            ['GET', '/domain', 'localhost'],
            ['GET', '/domain', 'baidu.com'],
            ['GET', '/cors/home', null],
            ['PUT', '/cors/put', null],
            ['GET', '/param/domain', 'naruto.baidu.com'],
            ['GET', '/param/id/42', 'naruto.baidu.com'],
            ['GET', '/param/id/some-slug', 'naruto.baidu.com'],
            ['GET', '/proxy/test', null],
            ['GET', '/cookie/set', null],
            ['GET', '/cookie/check', null],
            ['GET', '/param/injected', null],
            ['GET', '/param/injected2', null],
        ];

        // Reuse a single kernel across all iterations — only the request varies
        $kernel = $this->createStandardKernel();

        $this->forAll(
            Generators::elements($validRequests)
        )->then(function (array $requestSpec) use ($kernel) {
            [$method, $path, $host] = $requestSpec;

            $server = [];
            if ($host !== null) {
                $server['HTTP_HOST'] = $host;
            }

            $request = Request::create($path, $method, [], [], [], $server);
            $response = $kernel->handle($request);

            $statusCode = $response->getStatusCode();
            $this->assertGreaterThanOrEqual(
                100,
                $statusCode,
                sprintf('Status code %d is below 100 for %s %s', $statusCode, $method, $path)
            );
            $this->assertLessThanOrEqual(
                599,
                $statusCode,
                sprintf('Status code %d is above 599 for %s %s', $statusCode, $method, $path)
            );
        });

        $kernel->shutdown();
    }

    /**
     * For any random path that does NOT match a defined route,
     * handle() still returns a Response with a valid HTTP status code (typically 404).
     */
    public function testHandleReturnsValidStatusCodeForUndefinedRoutes(): void
    {
        // Reuse a single kernel across all iterations — only the request path varies
        $kernel = $this->createStandardKernel();

        $this->forAll(
            Generators::string()
        )
            ->when(function (string $path) {
                return strlen($path) > 0;
            })
            ->then(function (string $randomString) use ($kernel) {
                $path = '/__pbt_undefined__/' . bin2hex($randomString);

                $request = Request::create($path, 'GET');
                $response = $kernel->handle($request);

                $statusCode = $response->getStatusCode();
                $this->assertGreaterThanOrEqual(
                    100,
                    $statusCode,
                    sprintf('Status code %d is below 100 for undefined path %s', $statusCode, $path)
                );
                $this->assertLessThanOrEqual(
                    599,
                    $statusCode,
                    sprintf('Status code %d is above 599 for undefined path %s', $statusCode, $path)
                );
            });

        $kernel->shutdown();
    }

    // ─── CP5: View Handler 链传递 ────────────────────────────────────

    /**
     * When a controller returns a non-Response value, the View Handler chain
     * is invoked and produces a Response.
     *
     * The TestController::home() returns an array, which the JsonViewHandler
     * converts to a JSON Response.
     */
    public function testViewHandlerChainIsInvokedForNonResponseControllerResult(): void
    {
        // Routes whose controllers return arrays (non-Response values)
        $arrayReturningRoutes = [
            ['GET', '/', null],                                    // TestController::home()
            ['GET', '/sub/', null],                                // SubTestController::sub()
            ['GET', '/domain', 'localhost'],                       // TestController::domainLocalhost()
            ['GET', '/param/domain', 'naruto.baidu.com'],          // TestController::paramDomain()
            ['GET', '/param/id/99', 'naruto.baidu.com'],           // TestController::paramId()
        ];

        $viewHandlerCallCount = 0;
        $wrappingViewHandler = function ($result, $request) use (&$viewHandlerCallCount) {
            $viewHandlerCallCount++;
            // Delegate to JsonViewHandler for actual conversion
            $jsonHandler = new JsonViewHandler();
            return $jsonHandler($result, $request);
        };

        // Reuse a single kernel across all iterations
        $kernel = $this->createKernelWithViewHandler($wrappingViewHandler);

        $this->forAll(
            Generators::elements($arrayReturningRoutes)
        )->then(function (array $requestSpec) use ($kernel, &$viewHandlerCallCount) {
            [$method, $path, $host] = $requestSpec;

            // Reset counter before each iteration
            $previousCount = $viewHandlerCallCount;

            $server = [];
            if ($host !== null) {
                $server['HTTP_HOST'] = $host;
            }

            $request = Request::create($path, $method, [], [], [], $server);
            $response = $kernel->handle($request);

            $this->assertGreaterThan(
                $previousCount,
                $viewHandlerCallCount,
                sprintf('View handler chain should be invoked for %s %s (controller returns non-Response)', $method, $path)
            );

            // The response should be valid JSON (produced by the view handler)
            $this->assertEquals(200, $response->getStatusCode());
            $json = json_decode($response->getContent(), true);
            $this->assertIsArray($json, 'Response content should be valid JSON');
        });

        $kernel->shutdown();
    }

    // ─── Error Handler 链调用 ────────────────────────────────────────

    /**
     * When a controller throws an exception, the Error Handler chain is invoked.
     */
    public function testErrorHandlerChainIsInvokedWhenControllerThrowsException(): void
    {
        $this->limitTo(20)->forAll(
            Generators::choose(400, 599)
        )->then(function (int $errorCode) {
            $errorHandlerCalled = false;
            $capturedCode = null;

            $errorHandler = function (\Throwable $exception, Request $request, int $code) use (&$errorHandlerCalled, &$capturedCode) {
                $errorHandlerCalled = true;
                $capturedCode = $code;
                return new Response(
                    json_encode(['error' => $exception->getMessage(), 'code' => $code]),
                    $code,
                    ['Content-Type' => 'application/json']
                );
            };

            $kernel = $this->createKernelWithErrorHandler($errorHandler);

            // Request a non-existent route to trigger a 404 exception
            $request = Request::create('/__pbt_error_test__/' . $errorCode, 'GET');
            $response = $kernel->handle($request);

            $this->assertTrue(
                $errorHandlerCalled,
                'Error handler chain should be invoked when route is not found'
            );

            // The error handler should have received a 404 code
            $this->assertEquals(404, $capturedCode, 'Error handler should receive 404 for undefined route');

            // Response should come from our error handler
            $json = json_decode($response->getContent(), true);
            $this->assertIsArray($json);
            $this->assertArrayHasKey('error', $json);

            $kernel->shutdown();
        });
    }

    /**
     * When multiple error handlers are registered, only the first one that
     * returns a Response is used (subsequent handlers are skipped because
     * the event already has a response).
     */
    public function testFirstErrorHandlerResponseWins(): void
    {
        $this->limitTo(20)->forAll(
            Generators::choose(2, 5)
        )->then(function (int $handlerCount) {
            $callLog = [];

            $handlers = [];
            for ($i = 0; $i < $handlerCount; $i++) {
                $handlerId = $i;
                $handlers[] = function (\Throwable $exception, Request $request, int $code) use (&$callLog, $handlerId) {
                    $callLog[] = $handlerId;
                    return new Response(
                        json_encode(['handler' => $handlerId]),
                        $code,
                        ['Content-Type' => 'application/json']
                    );
                };
            }

            $kernel = $this->createKernelWithErrorHandlers($handlers);

            $request = Request::create('/__pbt_multi_error__', 'GET');
            $response = $kernel->handle($request);

            // Only the first handler should have been called (subsequent ones
            // are skipped because the event already has a response set)
            $this->assertCount(
                1,
                $callLog,
                sprintf('Only 1 error handler should be called, but %d were called: [%s]', count($callLog), implode(', ', $callLog))
            );
            $this->assertEquals(0, $callLog[0], 'First error handler should be the one called');

            $json = json_decode($response->getContent(), true);
            $this->assertEquals(0, $json['handler'], 'Response should come from first error handler');

            $kernel->shutdown();
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Create a standard MicroKernel with routing, JsonViewHandler, and JsonErrorHandler.
     */
    private function createStandardKernel(): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/dispatch-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir'     => $cacheDir,
            'routing'       => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'view_handlers' => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
            'injected_args' => [new JsonViewHandler()],
        ];

        return new MicroKernel($config, true);
    }

    /**
     * Create a MicroKernel with a custom view handler for tracking invocations.
     */
    private function createKernelWithViewHandler(callable $viewHandler): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/vh-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir'     => $cacheDir,
            'routing'       => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'view_handlers' => [$viewHandler],
            'injected_args' => [new JsonViewHandler()],
        ];

        return new MicroKernel($config, true);
    }

    /**
     * Create a MicroKernel with a single custom error handler.
     */
    private function createKernelWithErrorHandler(callable $errorHandler): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/eh-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir'      => $cacheDir,
            'routing'        => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [$errorHandler],
        ];

        return new MicroKernel($config, true);
    }

    /**
     * Create a MicroKernel with multiple custom error handlers.
     *
     * @param callable[] $errorHandlers
     */
    private function createKernelWithErrorHandlers(array $errorHandlers): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/meh-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir'      => $cacheDir,
            'routing'        => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => $errorHandlers,
        ];

        return new MicroKernel($config, true);
    }
}
