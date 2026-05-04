<?php
/**
 * Manual Test Script — Task 10: Programmatic Route Injection E2E Verification
 *
 * Covers:
 *   10.1 编程式路由注入端到端验证
 *   10.2 Boot 后冻结行为验证
 *   10.3 YAML + 编程式路由混合场景验证
 *   10.4 无 routing 配置 + 编程式路由场景验证
 *
 * Usage: php .kiro/specs/hotfix-3.1.1/tests/test-task-10.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

// ─── Helpers ─────────────────────────────────────────────────────────

$passed  = 0;
$failed  = 0;
$results = [];

function createTempCacheDir(): string
{
    $dir = sys_get_temp_dir() . '/oasis-http-manual-test-' . getmypid() . '-' . mt_rand();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
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

/**
 * @param string $label  Test label
 * @param callable $fn   Test body — should throw on failure
 */
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
        $act = get_class($actual);
        throw new \RuntimeException("assertInstanceOf failed: expected {$class}, got {$act}" . ($msg ? " — {$msg}" : ''));
    }
}

function assertNotNull(mixed $actual, string $msg = ''): void
{
    if ($actual === null) {
        throw new \RuntimeException("assertNotNull failed: value is null" . ($msg ? " — {$msg}" : ''));
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

$cacheDirs = [];

function makeKernelWithRouting(): MicroKernel
{
    global $cacheDirs;
    $cacheDir    = createTempCacheDir();
    $cacheDirs[] = $cacheDir;
    return new MicroKernel(
        [
            'cache_dir' => $cacheDir,
            'routing'   => [
                'path'      => __DIR__ . '/../../../../ut/Routing/fixtures/simple.routes.yml',
                'cache_dir' => 'false',
            ],
        ],
        true
    );
}

function makeKernelWithoutRouting(): MicroKernel
{
    global $cacheDirs;
    $cacheDir    = createTempCacheDir();
    $cacheDirs[] = $cacheDir;
    return new MicroKernel(['cache_dir' => $cacheDir], true);
}

// ═══════════════════════════════════════════════════════════════════════
// 10.1 编程式路由注入端到端验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 10.1 编程式路由注入端到端验证 ──\n";

runTest('addRoute() 注入路由 → boot → matchRequest() 返回对应 _controller', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('health', new Route('/health-check', [
        '_controller' => 'HealthController::check',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher, 'requestMatcher should not be null');

    $request = Request::create('/health-check', 'GET');
    $result  = $matcher->matchRequest($request);
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

    $r1 = $matcher->matchRequest(Request::create('/api/users', 'GET'));
    assertSame('UserController::list', $r1['_controller']);

    $r2 = $matcher->matchRequest(Request::create('/api/orders', 'GET'));
    assertSame('OrderController::list', $r2['_controller']);

    $kernel->shutdown();
});

runTest('addRoute() 注入路由 + YAML 路由均可匹配', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('health', new Route('/health-check', [
        '_controller' => 'HealthController::check',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    // YAML route: simple.home → /
    $yamlResult = $matcher->matchRequest(Request::create('/', 'GET'));
    assertSame('SimpleController::home', $yamlResult['_controller'], 'YAML route should still work');

    // Programmatic route
    $progResult = $matcher->matchRequest(Request::create('/health-check', 'GET'));
    assertSame('HealthController::check', $progResult['_controller'], 'Programmatic route should work');

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 10.2 Boot 后冻结行为验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 10.2 Boot 后冻结行为验证 ──\n";

runTest('boot 后 addRoute() 抛出 LogicException', function () {
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

runTest('boot 后 addRoutes() 抛出 LogicException', function () {
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

runTest('boot 后 getRouteCollection()->add() 抛出 LogicException', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $router = $kernel->getRouter();
    assertNotNull($router, 'router should not be null');
    $collection = $router->getRouteCollection();

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->add('sneaky', new Route('/sneaky', ['_controller' => 'SneakyController::action'])),
        'RouteCollection::add() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 getRouteCollection()->addCollection() 抛出 LogicException', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $router     = $kernel->getRouter();
    assertNotNull($router);
    $collection = $router->getRouteCollection();

    $extra = new RouteCollection();
    $extra->add('extra', new Route('/extra', ['_controller' => 'ExtraController::action']));

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

    $router     = $kernel->getRouter();
    assertNotNull($router);
    $collection = $router->getRouteCollection();

    assertThrows(
        \LogicException::class,
        'frozen after boot',
        fn() => $collection->remove('simple.home'),
        'RouteCollection::remove() after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后 getRouteCollection() 返回 FrozenRouteCollection 实例', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $router     = $kernel->getRouter();
    assertNotNull($router);
    $collection = $router->getRouteCollection();

    assertInstanceOf(
        \Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection::class,
        $collection,
        'Should be FrozenRouteCollection after boot'
    );

    $kernel->shutdown();
});

runTest('boot 后只读操作正常（get / all / count / getIterator）', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $router     = $kernel->getRouter();
    assertNotNull($router);
    $collection = $router->getRouteCollection();

    // get()
    $home = $collection->get('simple.home');
    assertNotNull($home, 'get(simple.home) should return a Route');

    // all()
    $all = $collection->all();
    if (!is_array($all) || count($all) < 2) {
        throw new \RuntimeException('all() should return at least 2 routes, got ' . count($all));
    }

    // count()
    $count = $collection->count();
    if ($count < 2) {
        throw new \RuntimeException("count() should be >= 2, got {$count}");
    }

    // getIterator()
    $iterCount = 0;
    foreach ($collection as $name => $route) {
        $iterCount++;
    }
    assertSame($count, $iterCount, 'getIterator count should match count()');

    $kernel->shutdown();
});

runTest('异常消息清晰可读 — addRoute', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    try {
        $kernel->addRoute('x', new Route('/x', ['_controller' => 'X::x']));
        throw new \RuntimeException('Expected LogicException');
    } catch (\LogicException $e) {
        $msg = $e->getMessage();
        // 消息应包含足够信息让调用方理解原因
        if (!str_contains($msg, 'boot') && !str_contains($msg, 'booted')) {
            throw new \RuntimeException("Message should mention 'boot': {$msg}");
        }
    }

    $kernel->shutdown();
});

runTest('异常消息清晰可读 — FrozenRouteCollection::add', function () {
    $kernel = makeKernelWithRouting();
    $kernel->boot();

    $collection = $kernel->getRouter()->getRouteCollection();

    try {
        $collection->add('x', new Route('/x', ['_controller' => 'X::x']));
        throw new \RuntimeException('Expected LogicException');
    } catch (\LogicException $e) {
        $msg = $e->getMessage();
        if (!str_contains($msg, 'frozen') && !str_contains($msg, 'boot')) {
            throw new \RuntimeException("Message should mention 'frozen' or 'boot': {$msg}");
        }
    }

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 10.3 YAML + 编程式路由混合场景验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 10.3 YAML + 编程式路由混合场景验证 ──\n";

runTest('YAML 路由和编程式路由均可匹配', function () {
    $kernel = makeKernelWithRouting();
    $kernel->addRoute('api_status', new Route('/api/status', [
        '_controller' => 'StatusController::index',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    // YAML: simple.home → /
    $r1 = $matcher->matchRequest(Request::create('/', 'GET'));
    assertSame('SimpleController::home', $r1['_controller']);

    // YAML: simple.about → /about
    $r2 = $matcher->matchRequest(Request::create('/about', 'GET'));
    assertSame('SimpleController::about', $r2['_controller']);

    // Programmatic: api_status → /api/status
    $r3 = $matcher->matchRequest(Request::create('/api/status', 'GET'));
    assertSame('StatusController::index', $r3['_controller']);

    $kernel->shutdown();
});

runTest('同名路由：编程式路由覆盖 YAML 路由（后入覆盖先入）', function () {
    $kernel = makeKernelWithRouting();

    // simple.home is defined in YAML as SimpleController::home
    // Override with programmatic route
    $kernel->addRoute('simple.home', new Route('/', [
        '_controller' => 'OverrideController::home',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    $result = $matcher->matchRequest(Request::create('/', 'GET'));
    assertSame('OverrideController::home', $result['_controller'], 'Programmatic should override YAML same-name route');

    $kernel->shutdown();
});

runTest('多个编程式路由 + YAML 路由混合 — 全部可达', function () {
    $kernel = makeKernelWithRouting();

    $batch = new RouteCollection();
    $batch->add('api_v1', new Route('/api/v1', ['_controller' => 'ApiV1Controller::index']));
    $batch->add('api_v2', new Route('/api/v2', ['_controller' => 'ApiV2Controller::index']));
    $kernel->addRoutes($batch);

    $kernel->addRoute('health', new Route('/health', ['_controller' => 'HealthController::check']));

    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher);

    // YAML routes
    assertSame('SimpleController::home', $matcher->matchRequest(Request::create('/', 'GET'))['_controller']);
    assertSame('SimpleController::about', $matcher->matchRequest(Request::create('/about', 'GET'))['_controller']);

    // Batch programmatic routes
    assertSame('ApiV1Controller::index', $matcher->matchRequest(Request::create('/api/v1', 'GET'))['_controller']);
    assertSame('ApiV2Controller::index', $matcher->matchRequest(Request::create('/api/v2', 'GET'))['_controller']);

    // Single programmatic route
    assertSame('HealthController::check', $matcher->matchRequest(Request::create('/health', 'GET'))['_controller']);

    $kernel->shutdown();
});

// ═══════════════════════════════════════════════════════════════════════
// 10.4 无 routing 配置 + 编程式路由场景验证
// ═══════════════════════════════════════════════════════════════════════

echo "\n── 10.4 无 routing 配置 + 编程式路由场景验证 ──\n";

runTest('无 routing 配置 + addRoute() → boot → 路由可达', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('standalone', new Route('/standalone', [
        '_controller' => 'StandaloneController::index',
    ]));
    $kernel->boot();

    $matcher = $kernel->getRequestMatcher();
    assertNotNull($matcher, 'requestMatcher should not be null even without YAML routing');

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

runTest('无 routing 配置 + 编程式路由 → boot 后 getRouter() 返回非 null', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('check', new Route('/check', ['_controller' => 'CheckController::action']));
    $kernel->boot();

    $router = $kernel->getRouter();
    assertNotNull($router, 'getRouter() should return non-null when programmatic routes exist');

    $kernel->shutdown();
});

runTest('无 routing 配置 + 编程式路由 → boot 后冻结生效', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('check', new Route('/check', ['_controller' => 'CheckController::action']));
    $kernel->boot();

    // addRoute after boot
    assertThrows(
        \LogicException::class,
        'Cannot add routes after the kernel has been booted',
        fn() => $kernel->addRoute('late', new Route('/late', ['_controller' => 'Late::action'])),
        'addRoute() after boot (no YAML config)'
    );

    // RouteCollection write after boot
    $collection = $kernel->getRouter()->getRouteCollection();
    assertInstanceOf(
        \Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection::class,
        $collection,
        'Should be FrozenRouteCollection even without YAML config'
    );

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

runTest('无 routing 配置 + 编程式路由 → 未定义路径返回 ResourceNotFoundException', function () {
    $kernel = makeKernelWithoutRouting();
    $kernel->addRoute('only', new Route('/only', ['_controller' => 'OnlyController::action']));
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
