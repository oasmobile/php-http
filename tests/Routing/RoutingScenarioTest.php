<?php
declare(strict_types=1);

/**
 * Routing module scenario tests.
 *
 * Verifies Routing behavior from a user-scenario perspective:
 * construct MicroKernel → configure routing → boot → send request → assert response.
 *
 * These tests establish a behavioral baseline for the Silex → Symfony migration
 * audit, complementing existing unit/integration tests with scenario-level coverage.
 *
 * @see MicroKernelRouteInjectionIntegrationTest for existing integration tests
 * @see CacheableRouterProviderTest for existing unit tests
 */

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RoutingScenarioTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to scenario routes.
     *
     * @param array<string, mixed> $routingOverrides Extra routing config to merge
     * @return array<string, mixed>
     */
    private function buildRoutingConfig(array $routingOverrides = []): array
    {
        $routing = array_merge(
            $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            $routingOverrides,
        );

        return [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $routing,
            'view_handlers' => [new JsonViewHandler()],
        ];
    }

    // -----------------------------------------------------------------
    // R4-AC1: YAML route loading and matching
    // -----------------------------------------------------------------

    /**
     * Configure routing.path → boot → send request → correct controller is invoked.
     */
    public function testYamlRouteLoadingAndMatching(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/home');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('home', $data['action']);
        $this->assertSame('ScenarioRoutingController', $data['controller']);
    }

    /**
     * Verify a second YAML route also matches correctly.
     */
    public function testYamlRouteLoadingSecondRoute(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/about');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('about', $data['action']);
    }

    // -----------------------------------------------------------------
    // R4-AC2: Programmatic route injection
    // -----------------------------------------------------------------

    /**
     * addRoute() before boot → injected route is matchable.
     */
    public function testProgrammaticRouteInjection(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);

        $kernel->addRoute('scenario.injected', new Route('/scenario/routing/injected', [
            '_controller' => 'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\ScenarioRoutingController::injected',
        ]));

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/injected');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('injected', $data['action']);
    }

    // -----------------------------------------------------------------
    // R4-AC3: Mixed routing priority (programmatic > YAML)
    // -----------------------------------------------------------------

    /**
     * YAML + addRoute() with overlapping path → programmatic route takes priority.
     */
    public function testMixedRoutingPriority(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);

        // Override the YAML route path /scenario/routing/home with a programmatic route
        $kernel->addRoute('scenario.routing.override', new Route('/scenario/routing/home', [
            '_controller' => 'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\ScenarioRoutingController::mixed',
        ]));

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/home');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        // Programmatic route should win: returns 'mixed' action with 'programmatic' source
        $this->assertSame('mixed', $data['action']);
        $this->assertSame('programmatic', $data['source']);
    }

    // -----------------------------------------------------------------
    // R4-AC4: Route parameter replacement
    // -----------------------------------------------------------------

    /**
     * %param% placeholder → resolved using kernel parameter value.
     *
     * A separate routes file defines _controller = "%controller_class%::paramAction".
     * We set the 'controller_class' parameter via addExtraParameters() before boot.
     */
    public function testRouteParameterReplacement(): void
    {
        // Use a dedicated routes file with %param% placeholder (needs YAML quoting)
        $routing = array_merge(
            $this->createRoutingConfig(
                __DIR__ . '/scenario-param.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            ['cache_dir' => 'false'],
        );

        $config = [
            'cache_dir'     => static::createTempCacheDir(),
            'routing'       => $routing,
            'view_handlers' => [new JsonViewHandler()],
        ];

        $kernel = $this->buildKernel($config);

        // Set the parameter that the route's %controller_class% placeholder references
        $kernel->addExtraParameters([
            'controller_class' => 'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\ScenarioRoutingController',
        ]);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/param');

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('param', $data['action']);
    }

    // -----------------------------------------------------------------
    // R4-AC5: Boot-after route freeze (addRoute)
    // -----------------------------------------------------------------

    /**
     * boot → addRoute() → LogicException.
     */
    public function testBootAfterRouteFreeze(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cannot add routes after.*boot/i');

        $kernel->addRoute('late_route', new Route('/late', [
            '_controller' => 'LateController::action',
        ]));
    }

    // -----------------------------------------------------------------
    // R4-AC6: Boot-after RouteCollection freeze
    // -----------------------------------------------------------------

    /**
     * boot → getRouter()->getRouteCollection()->add() → LogicException.
     */
    public function testBootAfterRouteCollectionFreeze(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);
        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $collection = $router->getRouteCollection();
        $this->assertInstanceOf(FrozenRouteCollection::class, $collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/frozen/i');

        $collection->add('frozen_route', new Route('/frozen', [
            '_controller' => 'FrozenController::action',
        ]));
    }

    // -----------------------------------------------------------------
    // R4-AC7: Route cache behavior
    // -----------------------------------------------------------------

    /**
     * cache_dir configured → cached matcher is created → reused on second boot.
     */
    public function testRouteCacheBehavior(): void
    {
        $cacheDir = static::createTempCacheDir() . '/routing-cache-test';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = $this->buildRoutingConfig(['cache_dir' => $cacheDir]);

        // First boot: cache should be created
        $kernel1 = $this->buildKernel($config);
        $response1 = $this->handleRequest($kernel1, 'GET', '/scenario/routing/home');
        $this->assertStatusCode($response1, Response::HTTP_OK);

        // Verify cache files were created in the routing cache directory
        $cacheFiles = glob($cacheDir . '/*');
        $this->assertNotEmpty($cacheFiles, 'Route cache files should be created after first boot');
        $cacheFileCount = count($cacheFiles);

        // Record modification times of cache files
        $mtimes = [];
        foreach ($cacheFiles as $file) {
            $mtimes[basename($file)] = filemtime($file);
        }

        // Small delay to ensure mtime would differ if files were regenerated
        usleep(100_000); // 100ms

        // Second boot: cache should be reused (no new files created)
        $kernel2 = $this->buildKernel($config);
        $response2 = $this->handleRequest($kernel2, 'GET', '/scenario/routing/home');
        $this->assertStatusCode($response2, Response::HTTP_OK);

        // Verify cache files were reused (same count, same mtimes)
        $cacheFiles2 = glob($cacheDir . '/*');
        $this->assertCount($cacheFileCount, $cacheFiles2, 'No new cache files should be created on second boot');

        foreach ($cacheFiles2 as $file) {
            $basename = basename($file);
            if (isset($mtimes[$basename])) {
                $this->assertSame(
                    $mtimes[$basename],
                    filemtime($file),
                    "Cache file '$basename' should not be regenerated on second boot",
                );
            }
        }

        // Clean up the test-specific cache directory
        $this->removeDirRecursive($cacheDir);
    }

    // -----------------------------------------------------------------
    // R4-AC8: Undefined route → 404
    // -----------------------------------------------------------------

    /**
     * Request to an undefined path → 404 response.
     */
    public function testUndefinedRoute(): void
    {
        $config = $this->buildRoutingConfig();
        $kernel = $this->buildKernel($config);

        $response = $this->handleRequest($kernel, 'GET', '/scenario/routing/nonexistent');

        $this->assertStatusCode($response, Response::HTTP_NOT_FOUND);
    }

    // -----------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
