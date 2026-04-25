<?php
/**
 * Property-Based Tests for Routing Resolution.
 *
 * CP1: 路由解析幂等性 — 对于任何已定义路由 path，router->match() 结果在多次调用间一致。
 * 测试未定义路由抛出 ResourceNotFoundException。
 * 测试 %param% 参数替换幂等性。
 *
 * 集成级：启动 MicroKernel 实例，使用真实路由配置。
 *
 * Ref: Requirement 15, AC 1/2/3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

class RoutingPropertyTest extends TestCase
{
    use TestTrait;
    use RouteCacheCleaner;

    private MicroKernel $kernel;

    /** @var array<string, array{path: string, host: string|null}> Route name → path/host info */
    private array $definedRoutes = [];

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        // Save current exception handler state before creating the kernel
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../cache');

        $cacheDir = static::createTempCacheDir();
        $config = [
            'cache_dir' => $cacheDir,
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
        ];
        $this->kernel = new MicroKernel($config, true);
        $this->kernel->boot();

        // Collect all defined routes from the router
        $router = $this->kernel->getRouter();
        $this->assertNotNull($router, 'Router must be available after boot');

        foreach ($router->getRouteCollection() as $name => $route) {
            $this->definedRoutes[$name] = [
                'path' => $route->getPath(),
                'host' => $route->getHost() ?: null,
            ];
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }

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

    // ─── CP1: 路由解析幂等性 ─────────────────────────────────────────

    /**
     * For any defined route, router->match() returns the same result across
     * multiple invocations (idempotency).
     */
    public function testRouteResolutionIsIdempotent(): void
    {
        // Use simple routes without host requirements for straightforward matching
        $simpleRoutes = array_filter(
            $this->definedRoutes,
            fn(array $info) => $info['host'] === null
        );
        $this->assertNotEmpty($simpleRoutes, 'There should be at least one route without host requirement');

        $routeNames = array_keys($simpleRoutes);

        $this->forAll(
            Generators::elements($routeNames),
            Generators::choose(2, 10)
        )->then(function (string $routeName, int $repeatCount) use ($simpleRoutes) {
            $path = $simpleRoutes[$routeName]['path'];

            // Replace route parameters with concrete values for matching
            $concretePath = $this->concretizePath($path);

            $router = $this->kernel->getRouter();
            $context = new RequestContext();

            $results = [];
            for ($i = 0; $i < $repeatCount; $i++) {
                try {
                    $matcher = $router->getMatcher();
                    $result = $matcher->match($concretePath);
                    // Normalize: remove _route_params if present (it's transient)
                    unset($result['_route_params']);
                    $results[] = $result;
                } catch (ResourceNotFoundException $e) {
                    // Some routes may not match without proper host context;
                    // that's fine — the point is consistency
                    $results[] = ['__exception__' => 'ResourceNotFound:' . $e->getMessage()];
                } catch (MethodNotAllowedException $e) {
                    // Some routes are method-restricted (e.g., PUT only);
                    // matching with default GET context throws this — still consistent
                    $results[] = ['__exception__' => 'MethodNotAllowed:' . implode(',', $e->getAllowedMethods())];
                }
            }

            // All results must be identical
            $first = $results[0];
            for ($i = 1; $i < count($results); $i++) {
                $this->assertEquals(
                    $first,
                    $results[$i],
                    sprintf(
                        'Route "%s" (path: %s) match result differs between call 1 and call %d',
                        $routeName,
                        $concretePath,
                        $i + 1
                    )
                );
            }
        });
    }

    // ─── Undefined routes throw ResourceNotFoundException ────────────

    /**
     * For any random string that is NOT a defined route path,
     * the router throws ResourceNotFoundException.
     */
    public function testUndefinedRouteThrowsResourceNotFoundException(): void
    {
        $this->forAll(
            Generators::string()
        )
            ->when(function (string $path) {
                // Ensure the path starts with / and is not a known route
                return strlen($path) > 0;
            })
            ->then(function (string $randomString) {
                // Prefix with /__undefined__/ to ensure it doesn't accidentally match
                $path = '/__undefined__/' . bin2hex($randomString);

                $router = $this->kernel->getRouter();
                $matcher = $router->getMatcher();

                try {
                    $matcher->match($path);
                    $this->fail(sprintf('Expected ResourceNotFoundException for path "%s"', $path));
                } catch (ResourceNotFoundException $e) {
                    // Expected behavior
                    $this->assertTrue(true);
                }
            });
    }

    // ─── %param% 参数替换幂等性 ──────────────────────────────────────

    /**
     * Parameter replacement (%param%) in route defaults is idempotent:
     * calling getRouteCollection() multiple times yields the same replaced values.
     */
    public function testParameterReplacementIsIdempotent(): void
    {
        // Create a kernel with extra parameters for %param% replacement
        $cacheDir = static::createTempCacheDir() . '/param-test';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir' => $cacheDir,
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
                'cache_dir'  => 'false', // disable route cache to test replacement each time
            ],
        ];
        $kernel = new MicroKernel($config, true);
        $kernel->addExtraParameters([
            'app.config1' => 'one',
            'app.config2' => 'two',
        ]);
        $kernel->boot();

        $this->forAll(
            Generators::choose(2, 5)
        )->then(function (int $repeatCount) use ($kernel) {
            $router = $kernel->getRouter();
            $this->assertNotNull($router);

            $collections = [];
            for ($i = 0; $i < $repeatCount; $i++) {
                $defaults = [];
                foreach ($router->getRouteCollection() as $name => $route) {
                    $defaults[$name] = $route->getDefaults();
                }
                $collections[] = $defaults;
            }

            // All collections must be identical
            $first = $collections[0];
            for ($i = 1; $i < count($collections); $i++) {
                $this->assertEquals(
                    $first,
                    $collections[$i],
                    sprintf('Route defaults differ between getRouteCollection() call 1 and call %d', $i + 1)
                );
            }

            // Verify that %param% values were actually replaced
            $paramConfigRoute = $first['param.config_value'] ?? null;
            if ($paramConfigRoute !== null) {
                $this->assertEquals('one', $paramConfigRoute['one'] ?? null, '%app.config1% should be replaced with "one"');
                $this->assertEquals('two', $paramConfigRoute['two'] ?? null, '%app.config2% should be replaced with "two"');
                $this->assertEquals('onetwo', $paramConfigRoute['three'] ?? null, '%app.config1%%app.config2% should be replaced with "onetwo"');
            }
        });

        $kernel->shutdown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Replace route parameter placeholders with concrete values for matching.
     * e.g., /param/id/{id} → /param/id/1, /param/id/{slug} → /param/id/test
     */
    private function concretizePath(string $path): string
    {
        return preg_replace_callback(
            '#\{(\w+)\}#',
            function (array $matches) {
                $paramName = $matches[1];
                // Use deterministic values based on parameter name
                return match ($paramName) {
                    'id'   => '1',
                    'slug' => 'test-slug',
                    'game' => 'naruto',
                    default => 'value',
                };
            },
            $path
        );
    }
}
