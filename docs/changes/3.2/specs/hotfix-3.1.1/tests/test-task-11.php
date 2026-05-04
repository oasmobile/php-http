<?php
/**
 * Manual Test Script — Task 11: Manual Testing (All Sub-tasks)
 *
 * Covers:
 *   11.1 编程式路由注入端到端验证
 *   11.2 Boot 后冻结行为验证
 *   11.3 YAML + 编程式路由混合场景验证
 *   11.4 无 routing 配置 + 编程式路由场景验证
 *   11.5 Closure controller 端到端验证
 *   11.6 缓存目录清理后重新 boot 验证
 *
 * Usage: php .kiro/specs/hotfix-3.1.1/tests/test-task-11.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

// ─── Helpers ─────────────────────────────────────────────────────────

$passed  = 0;
$failed  = 0;
$results = [];
$cacheDirs = [];

function createTempCacheDir(): string
{
    global $cacheDirs;
    $dir = sys_get_temp_dir() . '/oasis-http-manual-t11-' . getmypid() . '-' . mt_rand();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $cacheDirs[] = $dir;
    return $dir;
}

function removeDirRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? removeDirRecursive($path) : @unlink($path);
    }
    @rmdir($dir);
}

function runTest(string $label, callable $fn): void
{
    global $passed, $failed, $results;
    try {
        $fn();
        $passed++;
        $results[] = ['label' => $label, 'status' => 'PASS'];
        echo "  ✅ PASS: {$label}\n";
    } catch (\Throwable $e) {
        $failed++;
        $msg = get_class($e) . ': ' . $e->getMessage();
        $results[] = ['label' => $label, 'status' => 'FAIL', 'error' => $msg];
        echo "  ❌ FAIL: {$label}\n";
        echo "          {$msg}\n";
    }
}

function assertSame(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new \RuntimeException("assertSame failed: expected {$exp}, got {$act}" . ($msg ? " — {$msg}" : ''));
    }
}

function assertInstanceOf(string $class, mixed $actual, string $msg = ''): void
{
    if (!($actual instanceof $class)) {
        $act = is_object($actual) ? get_class($actual) : gettype($actual);
        throw new \RuntimeException("assertInstanceOf failed: expected {$class}, got {$act}" . ($msg ? " — {$msg}" : ''));
    }
}

function assertNotNull(mixed $actual, string $msg = ''): void
{
    if ($actual === null) {
        throw new \RuntimeException("assertNotNull failed: value is null" . ($msg ? " — {$msg}" : ''));
    }
}

function assertTrue(bool $actual, string $msg = ''): void
{
    if (!$actual) {
        throw new \RuntimeException("assertTrue failed" . ($msg ? " — {$msg}" : ''));
    }
}

function assertThrows(string $exceptionClass, string $messageContains, callable $fn, string $msg = ''): void
{
    try {
        $fn();
        throw new \RuntimeException("Expected {$exceptionClass} was not thrown" . ($msg ? " — {$msg}" : ''));
    } catch (\Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new \RuntimeException(
                "Expected {$exceptionClass}, got " . get_class($e) . ": " . $e->getMessage()
                . ($msg ? " — {$msg}" : '')
            );
        }
        if ($messageContains && !str_contains($e->getMessage(), $messageContains)) {
            throw new \RuntimeException(
                "Exception message does not contain '{$messageContains}': " . $e->getMessage()
                . ($msg ? " — {$msg}" : '')
            );
        }
    }
}

$fixturesDir = __DIR__ . '/../../../../ut/Routing/fixtures';

function makeKernelWithRouting(): MicroKernel
{
    global $fixturesDir;
    $cacheDir = createTempCacheDir();
    return new MicroKernel(
        [
            'cache_dir' => $cacheDir,
            'routing'   => [
                'path'      => $fixturesDir . '/simple.routes.yml',
                'cache_dir' => 'false',
            ],
        ],
        true
    );
}

function makeKernelWithoutRouting(): MicroKernel
{
    $cacheDir = createTempCacheDir();
    return new MicroKernel(['cache_dir' => $cacheDir], true);
}

/**
 * Create a MicroKernel with YAML routing and a REAL cache_dir (cache enabled).
 */
function makeKernelWithCache(bool $debug = true, ?string $yamlPath = null): MicroKernel
{
    global $fixturesDir;
    $cacheDir = createTempCacheDir();
    $yamlPath = $yamlPath ?? $fixturesDir . '/simple.routes.yml';
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


// ═══════════════════════════════════════════════════════════════════════
// 11.1 编程式路由注入端到端验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.1 编程式路由注入端到端验证 ──\n";

runTest('addRoute() 注入路由 → boot → matchRequest() 返回对应 _controller', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('health', new Route('/health-check', [
        '_controller' => 'HealthController::check',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher, 'requestMatcher should not be null');

    $result = $matcher->matchRequest(Request::create('/health-check', 'GET'));
    assertSame('HealthController::check', $result['_controller'], '_controller should match');

    $kernel->shutdown();
});

runTest('addRoutes() 批量注入 → boot → 所有路由均可匹配', function () {
    $kernel = makeKernelWithRouting();

    $collection = new RouteCollection();
    $collection->add('api_users', new Route('/api/users', [
        '_controller' => 'UserController::list',
    ]));
    $collection->add('api_orders', new Route('/api/orders', [
        '_controller' => 'OrderController::list',
    ]));
    $kernel->addRoutes($collection);
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    assertSame('UserController::list', $matcher->matchRequest(Request::create('/api/users', 'GET'))['_controller']);
    assertSame('OrderController::list', $matcher->matchRequest(Request::create('/api/orders', 'GET'))['_controller']);

    $kernel->shutdown();
});

runTest('编程式路由 + YAML 路由均可匹配', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('health', new Route('/health-check', [
        '_controller' => 'HealthController::check',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    // YAML route
    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller'], 'YAML route should work');
    // Programmatic route
    assertSame('HealthController::check', $matcher->matchRequest(Request::create('/health-check', 'GET'))['_controller'], 'Programmatic route should work');

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 11.2 Boot 后冻结行为验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.2 Boot 后冻结行为验证 ──\n";

runTest('boot 后 addRoute() 抛出 LogicException，消息包含 boot', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    assertThrows(
        \LogicException::class,
        'Cannot add routes after the kernel has been booted',
        fn() => $kernel->addRoute('late', new Route('/late', ['_controller' => 'LateController::action'])),
        'addRoute() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 addRoutes() 抛出 LogicException，消息包含 boot', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $routes = new RouteCollection();
    $routes->add('late_batch', new Route('/late-batch', ['_controller' => 'LateController::batch']));

    assertThrows(
        \LogicException::class,
        'Cannot add routes after the kernel has been booted',
        fn() => $kernel->addRoutes($routes),
        'addRoutes() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 getRouteCollection()->add() 抛出 LogicException，消息包含 frozen', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $collection = $kernel->getRouter()->getRouteCollection();
    assertInstanceOf(FrozenRouteCollection::class, $collection, 'Should be FrozenRouteCollection');

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->add('sneaky', new Route('/sneaky', ['_controller' => 'S::a'])),
        'RouteCollection::add() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 getRouteCollection()->addCollection() 抛出 LogicException', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $collection = $kernel->getRouter()->getRouteCollection();
    $extra = new RouteCollection();
    $extra->add('extra', new Route('/extra', ['_controller' => 'E::a']));

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->addCollection($extra),
        'RouteCollection::addCollection() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 getRouteCollection()->remove() 抛出 LogicException', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $collection = $kernel->getRouter()->getRouteCollection();

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->remove('simple.home'),
        'RouteCollection::remove() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后只读操作正常（get / all / count / getIterator）', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('read_test', new Route('/read-test', ['_controller' => 'R::a']));
    $kernel->boot();

    $collection = $kernel->getRouter()->getRouteCollection();
    assertInstanceOf(FrozenRouteCollection::class, $collection);

    // get()
    assertNotNull($collection->get('simple.home'), 'get(simple.home) should return a Route');
    assertNotNull($collection->get('read_test'), 'get(read_test) should return a Route');

    // all()
    $all = $collection->all();
    assertTrue(count($all) === 3, 'all() should return 3 routes (2 YAML + 1 programmatic), got ' . count($all));

    // count()
    assertSame(3, $collection->count(), 'count() should be 3');

    // getIterator()
    $names = [];
    foreach ($collection as $name => $route) {
        $names[] = $name;
    }
    assertSame(3, count($names), 'getIterator should yield 3 routes');
    assertTrue(in_array('simple.home', $names), 'Iterator should contain simple.home');
    assertTrue(in_array('read_test', $names), 'Iterator should contain read_test');

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 11.3 YAML + 编程式路由混合场景验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.3 YAML + 编程式路由混合场景验证 ──\n";

runTest('YAML 路由和编程式路由均可匹配', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('api_status', new Route('/api/status', [
        '_controller' => 'StatusController::index',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller']);
    assertSame('SimpleController::about', $matcher->matchRequest(Request::create('/about', 'GET'))['_controller']);
    assertSame('StatusController::index', $matcher->matchRequest(Request::create('/api/status', 'GET'))['_controller']);

    $kernel->shutdown();
});

runTest('同名路由：编程式路由优先于 YAML 路由', function () {
    $kernel = makeKernelWithRouting();

    // simple.home is defined in YAML as SimpleController::home
    $kernel->addRoute('simple.home', new Route('/', [
        '_controller' => 'OverrideController::home',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    $result = $matcher->matchRequest(Request::create('/', 'GET'));
    assertSame('OverrideController::home', $result['_controller'], 'Programmatic should override YAML same-name route');

    // Non-overridden YAML route should still work
    assertSame('SimpleController::about', $matcher->matchRequest(Request::create('/about', 'GET'))['_controller']);

    $kernel->shutdown();
});

runTest('addRoutes() 批量 + addRoute() 单条 + YAML — 全部可达', function () {
    $kernel = makeKernelWithRouting();

    $batch = new RouteCollection();
    $batch->add('api_v1', new Route('/api/v1', ['_controller' => 'ApiV1::index']));
    $batch->add('api_v2', new Route('/api/v2', ['_controller' => 'ApiV2::index']));
    $kernel->addRoutes($batch);
    $kernel->addRoute('health', new Route('/health', ['_controller' => 'Health::check']));

    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller']);
    assertSame('SimpleController::about', $matcher->matchRequest(Request::create('/about', 'GET'))['_controller']);
    assertSame('ApiV1::index', $matcher->matchRequest(Request::create('/api/v1', 'GET'))['_controller']);
    assertSame('ApiV2::index', $matcher->matchRequest(Request::create('/api/v2', 'GET'))['_controller']);
    assertSame('Health::check', $matcher->matchRequest(Request::create('/health', 'GET'))['_controller']);

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 11.4 无 routing 配置 + 编程式路由场景验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.4 无 routing 配置 + 编程式路由场景验证 ──\n";

runTest('无 routing 配置 + addRoute() → boot → 路由可达', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('standalone', new Route('/standalone', [
        '_controller' => 'StandaloneController::index',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher, 'requestMatcher should not be null');

    $result = $matcher->matchRequest(Request::create('/standalone', 'GET'));
    assertSame('StandaloneController::index', $result['_controller']);

    $kernel->shutdown();
});

runTest('无 routing 配置 + addRoutes() → boot → 所有路由可达', function () {
    $kernel = makeKernelWithoutRouting();

    $collection = new RouteCollection();
    $collection->add('r1', new Route('/route-1', ['_controller' => 'C1::action']));
    $collection->add('r2', new Route('/route-2', ['_controller' => 'C2::action']));
    $kernel->addRoutes($collection);
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    assertSame('C1::action', $matcher->matchRequest(Request::create('/route-1', 'GET'))['_controller']);
    assertSame('C2::action', $matcher->matchRequest(Request::create('/route-2', 'GET'))['_controller']);

    $kernel->shutdown();
});

runTest('无 routing 配置 + 编程式路由 → getRouter() 返回非 null', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('check', new Route('/check', ['_controller' => 'Check::action']));
    $kernel->boot();

    assertNotNull($kernel->getRouter(), 'getRouter() should return non-null with programmatic routes');

    $kernel->shutdown();
});

runTest('无 routing 配置 + 编程式路由 → boot 后冻结生效', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('check', new Route('/check', ['_controller' => 'Check::action']));
    $kernel->boot();

    assertThrows(
        \LogicException::class,
        'Cannot add routes after the kernel has been booted',
        fn() => $kernel->addRoute('late', new Route('/late', ['_controller' => 'Late::a'])),
        'addRoute() after boot (no YAML config)'
    );

    $collection = $kernel->getRouter()->getRouteCollection();
    assertInstanceOf(FrozenRouteCollection::class, $collection, 'Should be FrozenRouteCollection');

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->add('sneaky', new Route('/sneaky', ['_controller' => 'S::a'])),
        'RouteCollection::add() after boot (no YAML config)'
    );

    $kernel->shutdown();
});

runTest('无 routing 配置 + 无编程式路由 → getRouter() 返回 null', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->boot();

    $router = $kernel->getRouter();
    if ($router !== null) {
        throw new \RuntimeException('getRouter() should return null when no routing config and no programmatic routes');
    }

    $kernel->shutdown();
});

runTest('无 routing 配置 + 编程式路由 → 未定义路径抛 ResourceNotFoundException', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('only', new Route('/only', ['_controller' => 'Only::action']));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    assertThrows(
        \Symfony\Component\Routing\Exception\ResourceNotFoundException::class,
        '',
        fn() => $matcher->matchRequest(Request::create('/nonexistent', 'GET')),
        'Undefined path should throw ResourceNotFoundException'
    );

    $kernel->shutdown();
});


// ═══════════════════════════════════════════════════════════════════════
// 11.5 Closure controller 端到端验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.5 Closure controller 端到端验证 ──\n";

runTest('YAML 路由（启用缓存）+ Closure 编程式路由 → boot 成功无序列化错误', function () {
    $kernel = makeKernelWithCache(true);

    // Inject a route with a Closure controller — this would cause serialization
    // errors if programmatic routes were merged into the YAML cache compilation.
    $kernel->addRoute('closure_route', new Route('/closure-test', [
        '_controller' => function () { return 'closure-response'; },
    ]));

    // Boot should succeed without serialization errors
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher, 'requestMatcher should not be null');

    $kernel->shutdown();
});

runTest('Closure 编程式路由可匹配', function () {
    $kernel = makeKernelWithCache(true);

    $closureController = function () { return 'closure-response'; };
    $kernel->addRoute('closure_route', new Route('/closure-test', [
        '_controller' => $closureController,
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    $result = $matcher->matchRequest(Request::create('/closure-test', 'GET'));
    assertSame($closureController, $result['_controller'], 'Closure controller should be returned as-is');

    $kernel->shutdown();
});

runTest('YAML 路由（启用缓存）+ Closure 编程式路由 → YAML 路由也可达', function () {
    $kernel = makeKernelWithCache(true);

    $kernel->addRoute('closure_route', new Route('/closure-test', [
        '_controller' => function () { return 'closure-response'; },
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    // YAML routes should still work
    $yamlResult = $matcher->matchRequest(Request::create('/', 'GET'));
    assertSame('SimpleController::home', $yamlResult['_controller'], 'YAML route should still be reachable');

    $aboutResult = $matcher->matchRequest(Request::create('/about', 'GET'));
    assertSame('SimpleController::about', $aboutResult['_controller'], 'YAML /about route should still be reachable');

    $kernel->shutdown();
});

runTest('Closure 编程式路由 + 缓存启用 → 缓存文件不包含 Closure 路由名', function () {
    $cacheDir = createTempCacheDir();
    $routingCacheDir = $cacheDir . '/routing';

    global $fixturesDir;
    $kernel = new MicroKernel(
        [
            'cache_dir' => $cacheDir,
            'routing'   => [
                'path'      => $fixturesDir . '/simple.routes.yml',
                'cache_dir' => $routingCacheDir,
            ],
        ],
        true
    );

    $kernel->addRoute('closure_secret', new Route('/closure-secret', [
        '_controller' => function () { return 'secret'; },
    ]));
    $kernel->boot();

    // Check that cache files do not contain the programmatic route name
    $cacheContainsProgrammaticRoute = false;
    if (is_dir($routingCacheDir)) {
        foreach (scandir($routingCacheDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $content = @file_get_contents($routingCacheDir . '/' . $entry);
            if ($content !== false && str_contains($content, 'closure_secret')) {
                $cacheContainsProgrammaticRoute = true;
                break;
            }
        }
    }

    assertTrue(!$cacheContainsProgrammaticRoute, 'Cache files should NOT contain programmatic route name "closure_secret"');

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 11.6 缓存目录清理后重新 boot 验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 11.6 缓存目录清理后重新 boot 验证 ──\n";

runTest('首次 boot 生成缓存 → 删除缓存 → 重新 boot → 缓存重新生成且路由正常', function () {
    $cacheDir = createTempCacheDir();
    $routingCacheDir = $cacheDir . '/routing';

    global $fixturesDir;
    $config = [
        'cache_dir' => $cacheDir,
        'routing'   => [
            'path'      => $fixturesDir . '/simple.routes.yml',
            'cache_dir' => $routingCacheDir,
        ],
    ];

    // First boot — generates cache
    $kernel1 = new MicroKernel($config, true);
    $kernel1->addRoute('cached_route', new Route('/cached', ['_controller' => 'Cached::action']));
    $kernel1->boot();

    $matcher1 = $kernel1->getRequestMatcher();
    assertNotNull($matcher1);
    assertSame('Cached::action', $matcher1->matchRequest(Request::create('/cached', 'GET'))['_controller']);
    assertSame('SimpleController::home', $matcher1->matchRequest(Request::create('/', 'GET'))['_controller']);

    // Verify cache directory was created
    assertTrue(is_dir($routingCacheDir), 'Routing cache directory should exist after first boot');

    $kernel1->shutdown();

    // Delete the routing cache directory
    removeDirRecursive($routingCacheDir);
    assertTrue(!is_dir($routingCacheDir), 'Routing cache directory should be deleted');

    // Second boot — should regenerate cache and routes should work
    $kernel2 = new MicroKernel($config, true);
    $kernel2->addRoute('cached_route', new Route('/cached', ['_controller' => 'Cached::action']));
    $kernel2->boot();

    $matcher2 = $kernel2->getRequestMatcher();
    assertNotNull($matcher2);
    assertSame('Cached::action', $matcher2->matchRequest(Request::create('/cached', 'GET'))['_controller']);
    assertSame('SimpleController::home', $matcher2->matchRequest(Request::create('/', 'GET'))['_controller']);

    // Verify cache was regenerated
    assertTrue(is_dir($routingCacheDir), 'Routing cache directory should be regenerated after second boot');

    $kernel2->shutdown();
});

runTest('删除全部缓存目录 → 重新 boot → YAML 路由正常匹配', function () {
    $cacheDir = createTempCacheDir();
    $routingCacheDir = $cacheDir . '/routing';

    global $fixturesDir;
    $config = [
        'cache_dir' => $cacheDir,
        'routing'   => [
            'path'      => $fixturesDir . '/simple.routes.yml',
            'cache_dir' => $routingCacheDir,
        ],
    ];

    // First boot
    $kernel1 = new MicroKernel($config, true);
    $kernel1->boot();
    $kernel1->shutdown();

    // Delete entire cache dir (not just routing)
    removeDirRecursive($cacheDir);
    assertTrue(!is_dir($cacheDir), 'Entire cache directory should be deleted');

    // Re-create cache dir (MicroKernel needs it to exist)
    mkdir($cacheDir, 0777, true);

    // Second boot — should regenerate everything
    $kernel2 = new MicroKernel($config, true);
    $kernel2->boot();

    $matcher = $kernel2->getRequestMatcher();
    assertNotNull($matcher);
    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller']);
    assertSame('SimpleController::about', $matcher->matchRequest(Request::create('/about', 'GET'))['_controller']);

    $kernel2->shutdown();
});

runTest('删除缓存 → 重新 boot（含 Closure 编程式路由）→ 路由正常', function () {
    $cacheDir = createTempCacheDir();
    $routingCacheDir = $cacheDir . '/routing';

    global $fixturesDir;
    $config = [
        'cache_dir' => $cacheDir,
        'routing'   => [
            'path'      => $fixturesDir . '/simple.routes.yml',
            'cache_dir' => $routingCacheDir,
        ],
    ];

    $closureController = function () { return 'closure-response'; };

    // First boot with Closure route
    $kernel1 = new MicroKernel($config, true);
    $kernel1->addRoute('closure_cached', new Route('/closure-cached', [
        '_controller' => $closureController,
    ]));
    $kernel1->boot();
    $kernel1->shutdown();

    // Delete routing cache
    removeDirRecursive($routingCacheDir);

    // Second boot — should regenerate cache and both routes should work
    $kernel2 = new MicroKernel($config, true);
    $kernel2->addRoute('closure_cached', new Route('/closure-cached', [
        '_controller' => $closureController,
    ]));
    $kernel2->boot();

    $matcher = $kernel2->getRequestMatcher();
    assertNotNull($matcher);

    // YAML route
    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller']);

    // Closure route
    $result = $matcher->matchRequest(Request::create('/closure-cached', 'GET'));
    assertSame($closureController, $result['_controller'], 'Closure controller should work after cache rebuild');

    $kernel2->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════\n";
echo "  RESULTS: {$passed} passed, {$failed} failed, " . ($passed + $failed) . " total\n";
echo "══════════════════════════════════════════════════════════════\n\n";

if ($failed > 0) {
    echo "Failed tests:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo "  - {$r['label']}\n    {$r['error']}\n";
        }
    }
    echo "\n";
}

// Cleanup temp cache dirs
foreach ($cacheDirs as $dir) {
    removeDirRecursive($dir);
}

exit($failed > 0 ? 1 : 0);
