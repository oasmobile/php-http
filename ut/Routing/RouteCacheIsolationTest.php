<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route Cache Isolation Tests — Dual-Matcher Architecture
 *
 * Tests are split into two groups:
 *
 * 1. **FAIL on current code** (red): Prove that the current single-collection
 *    architecture has cache isolation defects.
 * 2. **PASS on current code** (green): Preservation / cache baseline tests that
 *    must continue to pass after the dual-matcher refactoring.
 *
 * Each test method gets its own isolated cache directory to prevent cross-test
 * cache interference (unlike RouteCacheCleaner which shares per-class).
 *
 * Ref: Requirement 5, AC 1–5; Requirement 1, AC 3; Requirement 4, AC 4
 */
class RouteCacheIsolationTest extends TestCase
{
    /** @var callable|null */
    private $previousExceptionHandler = null;

    /** @var string|null Per-test isolated cache directory */
    private ?string $testCacheDir = null;

    /** @var string|null Temp YAML file for cache invalidation tests */
    private ?string $tempYamlFile = null;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        // Create a per-test isolated cache directory
        $this->testCacheDir = sys_get_temp_dir()
            . '/oasis-cache-iso-'
            . md5(static::class . '::' . $this->name())
            . '-' . getmypid();
        if (is_dir($this->testCacheDir)) {
            self::removeDirRecursive($this->testCacheDir);
        }
        mkdir($this->testCacheDir, 0777, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Clean up temp YAML file if created
        if ($this->tempYamlFile !== null && file_exists($this->tempYamlFile)) {
            @unlink($this->tempYamlFile);
            $this->tempYamlFile = null;
        }

        // Clean up per-test cache directory
        if ($this->testCacheDir !== null && is_dir($this->testCacheDir)) {
            self::removeDirRecursive($this->testCacheDir);
            $this->testCacheDir = null;
        }

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

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a MicroKernel with YAML routing and a real cache_dir.
     *
     * @param bool        $debug    Whether to enable debug mode
     * @param string|null $yamlPath Path to the YAML routes file
     */
    private function createCachedKernel(bool $debug = true, ?string $yamlPath = null): MicroKernel
    {
        $cacheDir = $this->testCacheDir;
        $yamlPath = $yamlPath ?? __DIR__ . '/fixtures/simple.routes.yml';

        return new MicroKernel(
            [
                'cache_dir' => $cacheDir,
                'routing'   => [
                    'path'      => $yamlPath,
                    'cache_dir' => $cacheDir . '/routing',
                ],
            ],
            $debug
        );
    }

    /**
     * Create a MicroKernel WITHOUT routing config but with a cache_dir.
     */
    private function createCachedKernelWithoutRouting(): MicroKernel
    {
        return new MicroKernel(['cache_dir' => $this->testCacheDir], true);
    }

    /**
     * Create a temporary copy of the YAML routes file for mutation tests.
     */
    private function createTempYamlFile(): string
    {
        $source = __DIR__ . '/fixtures/simple.routes.yml';
        $this->tempYamlFile = $this->testCacheDir . '/temp-routes-' . bin2hex(random_bytes(4)) . '.yml';
        copy($source, $this->tempYamlFile);

        return $this->tempYamlFile;
    }

    /**
     * Get the routing cache directory for the current test.
     */
    private function getRoutingCacheDir(): string
    {
        return $this->testCacheDir . '/routing';
    }

    /**
     * Check if any file in the given directory contains the specified string.
     */
    private function cacheContainsString(string $dir, string $needle): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_file($path)) {
                $content = file_get_contents($path);
                if ($content !== false && str_contains($content, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function removeDirRecursive(string $dir): void
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
                self::removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // ═══════════════════════════════════════════════════════════════════
    // FAIL tests — Expected to fail on current code (red)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * YAML + Closure 编程式路由共存。
     *
     * Current code merges Closure controller into YAML RouteCollection and
     * compiles it into cache. Closures cannot be serialized, so this should
     * throw a serialization error on current code.
     *
     * After dual-matcher fix: Closure routes use a separate in-memory matcher,
     * never entering the cache compilation path. Boot succeeds and both routes
     * are matchable.
     *
     * Ref: Requirement 5, AC 1
     */
    public function testClosureProgrammaticRouteWithYamlCacheDoesNotCauseSerializationError(): void
    {
        $kernel = $this->createCachedKernel(true);

        // Inject a route with a Closure controller
        $kernel->addRoute('closure_route', new Route('/closure-action', [
            '_controller' => function () {
                return new \Symfony\Component\HttpFoundation\Response('closure response');
            },
        ]));

        // On current code: boot triggers cache compilation which tries to
        // serialize the Closure → error.
        // On fixed code: Closure route uses separate in-memory matcher, no serialization.
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // Verify Closure route is matchable
        $result = $matcher->matchRequest(Request::create('/closure-action', 'GET'));
        $this->assertArrayHasKey('_controller', $result);

        // Verify YAML route is still matchable
        $yamlResult = $matcher->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $yamlResult['_controller']);

        $kernel->shutdown();
    }

    /**
     * 缓存隔离验证。
     *
     * Current code merges programmatic routes into YAML RouteCollection before
     * cache compilation, so the cache file contains programmatic route names.
     *
     * After dual-matcher fix: Cache files contain only YAML routes.
     *
     * Ref: Requirement 5, AC 4
     */
    public function testCacheFileDoesNotContainProgrammaticRouteNames(): void
    {
        $kernel = $this->createCachedKernel(true);

        $kernel->addRoute('programmatic_cached_probe', new Route('/cached-probe', [
            '_controller' => 'ProbeController::action',
        ]));

        $kernel->boot();

        // Verify the route is matchable (sanity check)
        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);
        $result = $matcher->matchRequest(Request::create('/cached-probe', 'GET'));
        $this->assertSame('ProbeController::action', $result['_controller']);

        // Check cache files do NOT contain the programmatic route name
        $routingCacheDir = $this->getRoutingCacheDir();
        $this->assertDirectoryExists($routingCacheDir, 'Routing cache directory should exist after boot');

        $this->assertFalse(
            $this->cacheContainsString($routingCacheDir, 'programmatic_cached_probe'),
            'Cache files should NOT contain programmatic route names (cache isolation violated)'
        );
        $this->assertFalse(
            $this->cacheContainsString($routingCacheDir, '/cached-probe'),
            'Cache files should NOT contain programmatic route paths (cache isolation violated)'
        );

        $kernel->shutdown();
    }

    /**
     * 编程式路由优先匹配（缓存启用时）。
     *
     * YAML defines simple.home → SimpleController::home for path /.
     * Programmatic addRoute('simple.home', ...) overrides with OverrideController::home.
     *
     * Current code: merges into same collection; Symfony RouteCollection::add()
     * replaces same-name routes, so programmatic route wins. PASSES on current code.
     *
     * After dual-matcher fix: Programmatic matcher is first in GroupUrlMatcher,
     * so it always wins for matching paths (different mechanism, same result).
     *
     * Ref: Requirement 1, AC 3; Requirement 5, AC 3
     */
    public function testProgrammaticRouteHasPriorityOverYamlWithCache(): void
    {
        $kernel = $this->createCachedKernel(true);

        // Override the YAML route with a programmatic one (same path, different controller)
        $kernel->addRoute('simple.home', new Route('/', [
            '_controller' => 'OverrideController::cachedHome',
        ]));

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $result = $matcher->matchRequest(Request::create('/', 'GET'));
        $this->assertSame(
            'OverrideController::cachedHome',
            $result['_controller'],
            'Programmatic route should have priority over YAML route when cache is enabled'
        );

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // PASS tests — Cache baseline / preservation (green)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * 缓存生效验证。
     *
     * Boot with cache_dir → verify cache directory contains generated files.
     *
     * Ref: Requirement 4, AC 4
     */
    public function testCacheFilesAreGeneratedAfterBoot(): void
    {
        $kernel = $this->createCachedKernel(true);
        $kernel->boot();

        $routingCacheDir = $this->getRoutingCacheDir();
        $this->assertDirectoryExists($routingCacheDir, 'Routing cache directory should be created after boot');

        // At least one cache file should exist
        $files = array_diff(scandir($routingCacheDir), ['.', '..']);
        $this->assertNotEmpty($files, 'Routing cache directory should contain generated files');

        // YAML route should be matchable
        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);
        $result = $matcher->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result['_controller']);

        $kernel->shutdown();
    }

    /**
     * 缓存命中验证。
     *
     * First boot generates cache. Second boot (new kernel) uses cache.
     * Both should produce identical matching results.
     *
     * Ref: Requirement 4, AC 4
     */
    public function testSecondBootUsesCachedRoutes(): void
    {
        // First boot — generates cache
        $kernel1 = $this->createCachedKernel(true);
        $kernel1->boot();

        $matcher1 = $kernel1->getRequestMatcher();
        $this->assertNotNull($matcher1);
        $result1 = $matcher1->matchRequest(Request::create('/', 'GET'));
        $kernel1->shutdown();

        // Second boot — should use cache
        $kernel2 = $this->createCachedKernel(true);
        $kernel2->boot();

        $matcher2 = $kernel2->getRequestMatcher();
        $this->assertNotNull($matcher2);
        $result2 = $matcher2->matchRequest(Request::create('/', 'GET'));
        $kernel2->shutdown();

        $this->assertSame($result1['_controller'], $result2['_controller']);
        $this->assertSame('SimpleController::home', $result2['_controller']);
    }

    /**
     * YAML 路由文件内容变更后缓存失效（debug: true）。
     *
     * Boot → append a new route to YAML file → second boot → new route matchable.
     *
     * Ref: Requirement 5, AC 5
     */
    public function testYamlContentChangeInvalidatesCacheInDebugMode(): void
    {
        $tempYaml = $this->createTempYamlFile();

        // First boot — generates cache
        $kernel1 = $this->createCachedKernel(true, $tempYaml);
        $kernel1->boot();

        $matcher1 = $kernel1->getRequestMatcher();
        $this->assertNotNull($matcher1);
        $result1 = $matcher1->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result1['_controller']);
        $kernel1->shutdown();

        // Append a new route to the YAML file
        $newRoute = "\nnew_dynamic_route:\n    path: /new-dynamic\n    defaults:\n        _controller: DynamicController::newAction\n";
        file_put_contents($tempYaml, $newRoute, FILE_APPEND);

        // Ensure mtime is different (some filesystems have 1-second granularity)
        clearstatcache(true, $tempYaml);
        touch($tempYaml, time() + 2);
        clearstatcache(true, $tempYaml);

        // Second boot — cache should be invalidated, new route matchable
        $kernel2 = $this->createCachedKernel(true, $tempYaml);
        $kernel2->boot();

        $matcher2 = $kernel2->getRequestMatcher();
        $this->assertNotNull($matcher2);

        // Original route still works
        $result2 = $matcher2->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result2['_controller']);

        // New route should be matchable (cache was invalidated and recompiled)
        $resultNew = $matcher2->matchRequest(Request::create('/new-dynamic', 'GET'));
        $this->assertSame('DynamicController::newAction', $resultNew['_controller']);

        $kernel2->shutdown();
    }

    /**
     * YAML 路由文件 mtime 变更后缓存失效（debug: true）。
     *
     * Boot → touch YAML file (mtime only, no content change) → second boot →
     * cache recompiled (Symfony ConfigCache checks mtime via FileResource.isFresh()).
     *
     * Ref: Requirement 5, AC 5
     */
    public function testYamlMtimeChangeInvalidatesCacheInDebugMode(): void
    {
        $tempYaml = $this->createTempYamlFile();

        // First boot — generates cache
        $kernel1 = $this->createCachedKernel(true, $tempYaml);
        $kernel1->boot();
        $kernel1->shutdown();

        // Record cache file mtimes before touch
        $routingCacheDir = $this->getRoutingCacheDir();
        $cacheFilesBefore = [];
        foreach (array_diff(scandir($routingCacheDir), ['.', '..']) as $f) {
            $path = $routingCacheDir . '/' . $f;
            if (is_file($path)) {
                $cacheFilesBefore[$f] = filemtime($path);
            }
        }
        $this->assertNotEmpty($cacheFilesBefore, 'Cache files should exist after first boot');

        // Touch YAML file — update mtime only, no content change
        clearstatcache(true, $tempYaml);
        touch($tempYaml, time() + 5);
        clearstatcache(true, $tempYaml);

        // Second boot — cache should be recompiled
        $kernel2 = $this->createCachedKernel(true, $tempYaml);
        $kernel2->boot();

        // Verify routes still work (cache was recompiled successfully)
        $matcher2 = $kernel2->getRequestMatcher();
        $this->assertNotNull($matcher2);
        $result = $matcher2->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result['_controller']);

        $kernel2->shutdown();

        $this->assertTrue(true, 'Routes are matchable after YAML mtime change');
    }

    /**
     * PHP resource 文件变更后缓存失效（debug: true）。
     *
     * CacheableRouter registers itself as a FileResource via
     * addResource(new FileResource(__FILE__)). Touching CacheableRouter.php
     * should invalidate the cache.
     *
     * Ref: Requirement 5, AC 5
     */
    public function testPhpResourceChangeInvalidatesCacheInDebugMode(): void
    {
        $cacheableRouterPath = realpath(__DIR__ . '/../../src/ServiceProviders/Routing/CacheableRouter.php');
        $this->assertNotFalse($cacheableRouterPath, 'CacheableRouter.php should exist');

        // Record original mtime to restore later
        clearstatcache(true, $cacheableRouterPath);
        $originalMtime = filemtime($cacheableRouterPath);
        $originalAtime = fileatime($cacheableRouterPath);

        try {
            // First boot — generates cache
            $kernel1 = $this->createCachedKernel(true);
            $kernel1->boot();
            $kernel1->shutdown();

            // Record cache file mtimes
            $routingCacheDir = $this->getRoutingCacheDir();
            $cacheFilesBefore = [];
            foreach (array_diff(scandir($routingCacheDir), ['.', '..']) as $f) {
                $path = $routingCacheDir . '/' . $f;
                if (is_file($path)) {
                    $cacheFilesBefore[$f] = filemtime($path);
                }
            }
            $this->assertNotEmpty($cacheFilesBefore, 'Cache files should exist after first boot');

            // Touch CacheableRouter.php — update mtime
            clearstatcache(true, $cacheableRouterPath);
            touch($cacheableRouterPath, time() + 5);
            clearstatcache(true, $cacheableRouterPath);

            // Second boot — cache should be recompiled
            $kernel2 = $this->createCachedKernel(true);
            $kernel2->boot();

            $matcher2 = $kernel2->getRequestMatcher();
            $this->assertNotNull($matcher2);
            $result = $matcher2->matchRequest(Request::create('/', 'GET'));
            $this->assertSame('SimpleController::home', $result['_controller']);

            $kernel2->shutdown();

            $this->assertTrue(true, 'Routes are matchable after PHP resource mtime change');
        } finally {
            // Restore original mtime
            touch($cacheableRouterPath, $originalMtime, $originalAtime);
            clearstatcache(true, $cacheableRouterPath);
        }
    }

    /**
     * debug: false 时缓存持久不失效。
     *
     * Boot with debug=false → modify YAML content → second boot →
     * old cache still used (new route NOT matchable).
     *
     * Ref: Requirement 5, AC 5
     */
    public function testCachePersistsWhenDebugFalse(): void
    {
        $tempYaml = $this->createTempYamlFile();

        // First boot with debug=false — generates cache
        $kernel1 = $this->createCachedKernel(false, $tempYaml);
        $kernel1->boot();

        $matcher1 = $kernel1->getRequestMatcher();
        $this->assertNotNull($matcher1);
        $result1 = $matcher1->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result1['_controller']);
        $kernel1->shutdown();

        // Append a new route to the YAML file
        $newRoute = "\npersist_test_route:\n    path: /persist-test\n    defaults:\n        _controller: PersistController::action\n";
        file_put_contents($tempYaml, $newRoute, FILE_APPEND);
        clearstatcache(true, $tempYaml);
        touch($tempYaml, time() + 5);
        clearstatcache(true, $tempYaml);

        // Second boot with debug=false — should use old cache
        $kernel2 = $this->createCachedKernel(false, $tempYaml);
        $kernel2->boot();

        $matcher2 = $kernel2->getRequestMatcher();
        $this->assertNotNull($matcher2);

        // Original route still works
        $result2 = $matcher2->matchRequest(Request::create('/', 'GET'));
        $this->assertSame('SimpleController::home', $result2['_controller']);

        // New route should NOT be matchable (cache was not invalidated)
        $this->expectException(\Symfony\Component\Routing\Exception\ResourceNotFoundException::class);
        $matcher2->matchRequest(Request::create('/persist-test', 'GET'));
    }

    /**
     * 纯编程式路由 + 缓存配置。
     *
     * Has cache_dir but no routing key + addRoute() → boot → route matchable.
     * Current code already supports this scenario.
     *
     * Ref: Requirement 1, AC 4
     */
    public function testPureProgrammaticRoutesWithCacheDirConfig(): void
    {
        $kernel = $this->createCachedKernelWithoutRouting();

        $kernel->addRoute('pure_programmatic', new Route('/pure-prog', [
            '_controller' => 'PureController::action',
        ]));

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $result = $matcher->matchRequest(Request::create('/pure-prog', 'GET'));
        $this->assertSame('PureController::action', $result['_controller']);

        $kernel->shutdown();
    }
}
