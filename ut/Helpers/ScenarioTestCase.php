<?php
declare(strict_types=1);

/**
 * Base test case for scenario-level integration tests.
 *
 * Provides helper methods for constructing MicroKernel instances,
 * sending HTTP requests, and asserting responses. Each test method
 * can build a differently-configured kernel via buildKernel().
 *
 * Unlike WebTestCase (which calls createApplication() in setUp()),
 * ScenarioTestCase defers kernel construction to individual test
 * methods for maximum flexibility.
 */

namespace Oasis\Mlib\Http\Test\Helpers;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ScenarioTestCase extends TestCase
{
    use RouteCacheCleaner;

    protected ?MicroKernel $kernel = null;

    /** @var callable|null Saved exception handler state from before setUp */
    private $previousExceptionHandler = null;

    /** @var bool Whether we captured the exception handler state */
    private bool $exceptionHandlerCaptured = false;

    protected function setUp(): void
    {
        // Capture exception handler state before any kernel creation.
        // MicroKernel boot registers its own exception handler; we need
        // to restore the original state in tearDown to avoid PHPUnit's
        // "did not remove its own exception handlers" warning.
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        $this->exceptionHandlerCaptured = true;
    }

    /**
     * Construct a MicroKernel with the given bootstrap config.
     *
     * If a previous kernel exists, it is shut down first.
     * Stores the kernel for automatic shutdown in tearDown().
     */
    protected function buildKernel(array $config, bool $isDebug = false): MicroKernel
    {
        // Shutdown previous kernel if exists (supports multiple kernels per test)
        if ($this->kernel !== null) {
            $this->kernel->shutdown();
            $this->kernel = null;
        }

        $this->kernel = new MicroKernel($config, $isDebug);

        return $this->kernel;
    }

    /**
     * Boot the kernel, create a Request, handle it, and return the Response.
     *
     * @param array<string, mixed> $parameters Query/request parameters
     * @param array<string, mixed> $server     Server parameters (e.g. HTTP_HOST)
     */
    protected function handleRequest(
        MicroKernel $kernel,
        string $method,
        string $uri,
        array $parameters = [],
        array $server = [],
    ): Response {
        $kernel->boot();
        $request = Request::create($uri, $method, $parameters, [], [], $server);

        return $kernel->handle($request);
    }

    /**
     * Assert the response status code matches and decode the JSON body.
     *
     * @return array<string, mixed> Decoded JSON data
     */
    protected function assertJsonResponse(Response $response, int $expectedStatus): array
    {
        $this->assertSame($expectedStatus, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * Assert only the response status code.
     */
    protected function assertStatusCode(Response $response, int $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $response->getStatusCode());
    }

    /**
     * Create a minimal routing config pointing to a YAML routes file.
     *
     * @param string        $routesFile Absolute path to the routes YAML file
     * @param array<string> $namespaces Controller namespace prefixes
     *
     * @return array{path: string, namespaces: array<string>}
     */
    protected function createRoutingConfig(string $routesFile, array $namespaces = []): array
    {
        return [
            'path'       => $routesFile,
            'namespaces' => $namespaces,
        ];
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            $this->kernel->shutdown();
        }
        $this->kernel = null;

        // Restore exception handler to the state before setUp.
        // This prevents PHPUnit's "did not remove its own exception handlers" warning.
        if ($this->exceptionHandlerCaptured) {
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
        }
        $this->previousExceptionHandler = null;
        $this->exceptionHandlerCaptured = false;
    }
}
