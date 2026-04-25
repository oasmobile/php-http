<?php
/**
 * Manual test script for Task 13 — PHP 8.5 Phase 1 Framework Replacement.
 *
 * Exercises 8 scenarios that verify MicroKernel subsystems work end-to-end
 * via simulated HTTP requests (MicroKernel::handle()).
 *
 * Exit code: 0 = all passed, 1 = at least one failure.
 *
 * Usage: php test-task-13.php
 */

require_once __DIR__ . '/../../../../ut/bootstrap.php';

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingStrategy;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// ── Helpers ──────────────────────────────────────────────────────────

$passed  = 0;
$failed  = 0;
$errors  = [];

function report(string $scenario, string $subCase, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $errors;
    $status = $ok ? 'PASS' : 'FAIL';
    $msg    = "[$status] $scenario — $subCase";
    if (!$ok && $detail) {
        $msg .= " ($detail)";
    }
    echo $msg . PHP_EOL;
    if ($ok) {
        $passed++;
    } else {
        $failed++;
        $errors[] = "$scenario — $subCase: $detail";
    }
}

/**
 * Create a fresh temp cache dir to avoid cross-test contamination.
 */
function freshCacheDir(string $label): string
{
    $dir = sys_get_temp_dir() . '/oasis-manual-test-' . md5($label) . '-' . getmypid();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

/**
 * Recursively remove a directory.
 */
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

/** @var string[] cache dirs to clean up at the end */
$cacheDirs = [];

// Reset trusted proxies to a known state before tests
$savedProxies   = Request::getTrustedProxies();
$savedHeaderSet = Request::getTrustedHeaderSet();

echo str_repeat('=', 70) . PHP_EOL;
echo "Manual Test — Task 13: MicroKernel Subsystem Verification" . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;
echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 1: MicroKernel 启动与基本请求
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 1: MicroKernel 启动与基本请求 ---" . PHP_EOL;

$cacheDir1 = freshCacheDir('scenario1');
$cacheDirs[] = $cacheDir1;

try {
    $app1 = new MicroKernel(
        [
            'cache_dir'      => $cacheDir1,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
        ],
        true
    );

    // 1a: MicroKernel 实例创建成功
    report('场景1', 'MicroKernel 实例创建', $app1 instanceof MicroKernel);

    // 1b: 发送 GET / 请求，确认返回 200 + 正确 controller
    $request1  = Request::create('/', 'GET');
    $response1 = $app1->handle($request1);

    report('场景1', 'GET / 返回 200', $response1->getStatusCode() === 200,
        'actual: ' . $response1->getStatusCode());

    $json1 = json_decode($response1->getContent(), true);
    $expectedController = 'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\TestController::home()';
    report('场景1', 'controller 匹配',
        is_array($json1) && isset($json1['called']) && $json1['called'] === $expectedController,
        'actual: ' . ($json1['called'] ?? 'null'));

    // 1c: 发送不存在的路由，确认返回 404
    $request404  = Request::create('/nonexistent-route-xyz', 'GET');
    $response404 = $app1->handle($request404);

    report('场景1', '不存在路由返回 404', $response404->getStatusCode() === 404,
        'actual: ' . $response404->getStatusCode());

    $app1->shutdown();
} catch (\Throwable $e) {
    report('场景1', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

// Reset trusted proxies
Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 2: Middleware 链执行
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 2: Middleware 链执行 ---" . PHP_EOL;

$cacheDir2 = freshCacheDir('scenario2');
$cacheDirs[] = $cacheDir2;

/**
 * Recording middleware that tracks execution order.
 */
class RecordingMiddleware extends AbstractMiddleware
{
    private string $name;
    private int $beforePriority;
    private int $afterPriority;
    private ?Response $shortCircuitResponse;
    private static array $executionLog = [];

    public function __construct(string $name, int $beforePriority, int $afterPriority, ?Response $shortCircuitResponse = null)
    {
        $this->name                 = $name;
        $this->beforePriority       = $beforePriority;
        $this->afterPriority        = $afterPriority;
        $this->shortCircuitResponse = $shortCircuitResponse;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        self::$executionLog[] = $this->name . ':before';
        if ($this->shortCircuitResponse !== null) {
            return $this->shortCircuitResponse;
        }
        return null;
    }

    public function after(Request $request, Response $response): void
    {
        self::$executionLog[] = $this->name . ':after';
    }

    public function getBeforePriority(): int|false { return $this->beforePriority; }
    public function getAfterPriority(): int|false  { return $this->afterPriority; }
    public function onlyForMasterRequest(): bool   { return true; }

    public static function getLog(): array  { return self::$executionLog; }
    public static function resetLog(): void { self::$executionLog = []; }
}

try {
    // 2a: Middleware 按 priority 顺序执行（高 priority 先执行）
    RecordingMiddleware::resetLog();

    $mwHigh = new RecordingMiddleware('high', 100, -100);
    $mwMid  = new RecordingMiddleware('mid', 50, -50);
    $mwLow  = new RecordingMiddleware('low', 10, -10);

    $app2a = new MicroKernel(
        [
            'cache_dir'      => $cacheDir2,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
            'middlewares'    => [$mwLow, $mwHigh, $mwMid], // 故意乱序传入
        ],
        true
    );

    $request2a  = Request::create('/', 'GET');
    $response2a = $app2a->handle($request2a);

    $log2a = RecordingMiddleware::getLog();

    // before 应按 priority 降序: high(100) → mid(50) → low(10)
    $beforeLog = array_values(array_filter($log2a, fn($e) => str_contains($e, ':before')));
    $expectedBefore = ['high:before', 'mid:before', 'low:before'];
    report('场景2', 'before middleware 按 priority 降序执行',
        $beforeLog === $expectedBefore,
        'actual: ' . implode(', ', $beforeLog));

    // after 应按 priority 降序: low(-10) → mid(-50) → high(-100)
    // （after priority 也是数值越大越先执行）
    $afterLog = array_values(array_filter($log2a, fn($e) => str_contains($e, ':after')));
    $expectedAfter = ['low:after', 'mid:after', 'high:after'];
    report('场景2', 'after middleware 按 priority 降序执行',
        $afterLog === $expectedAfter,
        'actual: ' . implode(', ', $afterLog));

    report('场景2', '正常请求返回 200', $response2a->getStatusCode() === 200,
        'actual: ' . $response2a->getStatusCode());

    $app2a->shutdown();

    // 2b: Before middleware 短路 — 返回 Response 时后续 middleware 和 controller 不执行
    RecordingMiddleware::resetLog();

    $cacheDir2b  = freshCacheDir('scenario2b');
    $cacheDirs[] = $cacheDir2b;

    $shortCircuitResponse = new Response('short-circuited', 403);
    $mwShortCircuit = new RecordingMiddleware('blocker', 100, -100, $shortCircuitResponse);
    $mwAfterBlock   = new RecordingMiddleware('after-blocker', 50, -50);

    $app2b = new MicroKernel(
        [
            'cache_dir'      => $cacheDir2b,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
            'middlewares'    => [$mwShortCircuit, $mwAfterBlock],
        ],
        true
    );

    $request2b  = Request::create('/', 'GET');
    $response2b = $app2b->handle($request2b);

    $log2b = RecordingMiddleware::getLog();

    report('场景2', '短路 middleware 返回 403',
        $response2b->getStatusCode() === 403,
        'actual: ' . $response2b->getStatusCode());

    report('场景2', '短路 response body 正确',
        $response2b->getContent() === 'short-circuited',
        'actual: ' . $response2b->getContent());

    // after-blocker 的 before 不应被调用（被短路）
    $afterBlockerBefore = array_filter($log2b, fn($e) => $e === 'after-blocker:before');
    report('场景2', '短路后后续 middleware before() 未执行',
        count($afterBlockerBefore) === 0,
        'log: ' . implode(', ', $log2b));

    $app2b->shutdown();
} catch (\Throwable $e) {
    report('场景2', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 3: CORS preflight
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 3: CORS preflight ---" . PHP_EOL;

$cacheDir3 = freshCacheDir('scenario3');
$cacheDirs[] = $cacheDir3;

try {
    $app3 = new MicroKernel(
        [
            'cache_dir'      => $cacheDir3,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
            'cors'           => [
                [
                    'pattern' => '/cors/.*',
                    'origins' => ['localhost', 'cors.example.com'],
                    'headers' => ['X-Custom-Header'],
                ],
                new CrossOriginResourceSharingStrategy([
                    'pattern' => '*',
                    'origins' => '*',
                ]),
            ],
        ],
        true
    );

    // 3a: OPTIONS preflight 请求返回 PrefilightResponse
    $preflightRequest = Request::create('/cors/home', 'OPTIONS', [], [], [], [
        'HTTP_Origin'                        => 'http://cors.example.com',
        'HTTP_Access-Control-Request-Method'  => 'GET',
        'HTTP_Access-Control-Request-Headers' => 'X-Custom-Header',
    ]);

    $preflightResponse = $app3->handle($preflightRequest);

    // CORS preflight 返回 204 No Content（标准行为）
    report('场景3', 'preflight 返回 204',
        $preflightResponse->getStatusCode() === 204,
        'actual: ' . $preflightResponse->getStatusCode());

    report('场景3', 'Access-Control-Allow-Origin header 存在',
        $preflightResponse->headers->has('Access-Control-Allow-Origin'),
        'headers: ' . implode(', ', array_keys($preflightResponse->headers->all())));

    // 3b: 普通 GET 请求带 Origin header，确认 CORS response headers
    $corsGetRequest = Request::create('/cors/home', 'GET', [], [], [], [
        'HTTP_Origin' => 'http://cors.example.com',
    ]);

    $corsGetResponse = $app3->handle($corsGetRequest);

    report('场景3', '普通 CORS GET 返回 200',
        $corsGetResponse->getStatusCode() === 200,
        'actual: ' . $corsGetResponse->getStatusCode());

    report('场景3', '普通 CORS GET 有 Allow-Origin header',
        $corsGetResponse->headers->has('Access-Control-Allow-Origin'),
        'headers: ' . implode(', ', array_keys($corsGetResponse->headers->all())));

    $app3->shutdown();
} catch (\Throwable $e) {
    report('场景3', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 4: View Handler 链
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 4: View Handler 链 ---" . PHP_EOL;

$cacheDir4 = freshCacheDir('scenario4');
$cacheDirs[] = $cacheDir4;

try {
    // FallbackViewHandler 需要 kernel 引用，使用 lazy wrapper 模式
    $lazyViewHandler4 = null;
    $appRef4          = null;

    $viewHandlerCallable4 = function ($result, $request) use (&$lazyViewHandler4, &$appRef4) {
        if ($lazyViewHandler4 === null) {
            $lazyViewHandler4 = new FallbackViewHandler($appRef4, new RouteBasedResponseRendererResolver());
        }
        return $lazyViewHandler4($result, $request);
    };

    $app4 = new MicroKernel(
        [
            'cache_dir'      => $cacheDir4,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/fallback-test.routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [$viewHandlerCallable4],
            'error_handlers' => [new ExceptionWrapper()],
        ],
        true
    );
    $appRef4 = $app4;

    // 4a: 控制器返回字符串（非 Response），View Handler 链将其转为 Response
    $request4a  = Request::create('/panel/ok', 'GET');
    $response4a = $app4->handle($request4a);

    report('场景4', '控制器返回字符串 → View Handler 生成 Response',
        $response4a->getStatusCode() === 200,
        'actual status: ' . $response4a->getStatusCode());

    report('场景4', 'Response body 正确',
        trim($response4a->getContent()) === 'Hello world!',
        'actual: ' . $response4a->getContent());

    // 4b: API 路由返回数组，View Handler 链将其转为 JSON Response
    $request4b  = Request::create('/api/ok', 'GET');
    $response4b = $app4->handle($request4b);

    $json4b = json_decode($response4b->getContent(), true);
    report('场景4', 'API 路由返回 JSON',
        is_array($json4b) && isset($json4b['result']) && $json4b['result'] === 'Hello world!',
        'actual: ' . $response4b->getContent());

    $app4->shutdown();
} catch (\Throwable $e) {
    report('场景4', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 5: Error Handler 链
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 5: Error Handler 链 ---" . PHP_EOL;

$cacheDir5 = freshCacheDir('scenario5');
$cacheDirs[] = $cacheDir5;

try {
    // 5a: 控制器抛出异常，Error Handler 链捕获并生成 Response
    $lazyViewHandler5 = null;
    $appRef5          = null;

    $viewHandlerCallable5 = function ($result, $request) use (&$lazyViewHandler5, &$appRef5) {
        if ($lazyViewHandler5 === null) {
            $lazyViewHandler5 = new FallbackViewHandler($appRef5, new RouteBasedResponseRendererResolver());
        }
        return $lazyViewHandler5($result, $request);
    };

    $app5 = new MicroKernel(
        [
            'cache_dir'      => $cacheDir5,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/fallback-test.routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [$viewHandlerCallable5],
            'error_handlers' => [new ExceptionWrapper()],
        ],
        true
    );
    $appRef5 = $app5;

    // /panel/error 抛出 RuntimeException("Oops!")
    $request5a  = Request::create('/panel/error', 'GET');
    $response5a = $app5->handle($request5a);

    report('场景5', '异常被 Error Handler 捕获，返回 500',
        $response5a->getStatusCode() === 500,
        'actual: ' . $response5a->getStatusCode());

    report('场景5', 'Response 包含异常信息',
        str_contains($response5a->getContent(), 'RuntimeException'),
        'actual: ' . substr($response5a->getContent(), 0, 200));

    // 5b: API 路由抛出异常，Error Handler 返回 JSON 格式错误
    $request5b  = Request::create('/api/error', 'GET');
    $response5b = $app5->handle($request5b);

    $json5b = json_decode($response5b->getContent(), true);
    report('场景5', 'API 异常返回 JSON 格式',
        is_array($json5b) && isset($json5b['code']) && $json5b['code'] === 500,
        'actual: ' . $response5b->getContent());

    report('场景5', 'JSON 包含异常类型',
        is_array($json5b) && isset($json5b['exception']['type']) && $json5b['exception']['type'] === 'RuntimeException',
        'actual type: ' . ($json5b['exception']['type'] ?? 'null'));

    $app5->shutdown();

    // 5c: Error handler 返回 null 时异常继续传播（使用 JsonErrorHandler 处理）
    $cacheDir5c  = freshCacheDir('scenario5c');
    $cacheDirs[] = $cacheDir5c;

    $nullHandlerCalled = false;
    $jsonHandlerCalled = false;

    $nullHandler = function ($exception, $request, $code) use (&$nullHandlerCalled) {
        $nullHandlerCalled = true;
        return null; // 返回 null，让异常继续传播到下一个 handler
    };

    $jsonHandler = function ($exception, $request, $code) use (&$jsonHandlerCalled) {
        $jsonHandlerCalled = true;
        return new Response(
            json_encode(['caught_by' => 'json_handler', 'code' => $code]),
            $code,
            ['Content-Type' => 'application/json']
        );
    };

    $app5c = new MicroKernel(
        [
            'cache_dir'      => $cacheDir5c,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/fallback-test.routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [$nullHandler, $jsonHandler],
        ],
        true
    );

    $request5c  = Request::create('/panel/error', 'GET');
    $response5c = $app5c->handle($request5c);

    report('场景5', 'null handler 被调用', $nullHandlerCalled);
    report('场景5', 'json handler 被调用（异常传播）', $jsonHandlerCalled);

    $json5c = json_decode($response5c->getContent(), true);
    report('场景5', '最终由 json handler 生成 Response',
        is_array($json5c) && ($json5c['caught_by'] ?? '') === 'json_handler',
        'actual: ' . $response5c->getContent());

    $app5c->shutdown();
} catch (\Throwable $e) {
    report('场景5', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 6: Cookie 写入
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 6: Cookie 写入 ---" . PHP_EOL;

$cacheDir6 = freshCacheDir('scenario6');
$cacheDirs[] = $cacheDir6;

try {
    $app6 = new MicroKernel(
        [
            'cache_dir'      => $cacheDir6,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
        ],
        true
    );

    // /cookie/set 路由通过 ResponseCookieContainer 添加 cookie
    $request6  = Request::create('/cookie/set', 'GET');
    $response6 = $app6->handle($request6);

    report('场景6', 'cookie/set 返回 200',
        $response6->getStatusCode() === 200,
        'actual: ' . $response6->getStatusCode());

    // 检查 Response headers 中是否包含 Set-Cookie
    $cookies6 = $response6->headers->getCookies();
    $hasCookie = false;
    foreach ($cookies6 as $cookie) {
        if ($cookie->getName() === 'name' && $cookie->getValue() === 'John') {
            $hasCookie = true;
            break;
        }
    }

    report('场景6', 'Response 包含 Set-Cookie: name=John', $hasCookie,
        'cookies: ' . implode(', ', array_map(fn($c) => $c->getName() . '=' . $c->getValue(), $cookies6)));

    $app6->shutdown();
} catch (\Throwable $e) {
    report('场景6', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 7: Twig 渲染
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 7: Twig 渲染 ---" . PHP_EOL;

$cacheDir7 = freshCacheDir('scenario7');
$cacheDirs[] = $cacheDir7;

try {
    $app7 = new MicroKernel(
        [
            'cache_dir' => $cacheDir7,
            'twig'      => [
                'template_dir' => __DIR__ . '/../../../../ut/Integration/templates',
            ],
        ],
        true
    );

    $app7->boot();

    // 7a: getTwig() 返回 Twig\Environment 实例
    $twig7 = $app7->getTwig();
    report('场景7', 'getTwig() 返回 Twig\\Environment',
        $twig7 instanceof \Twig\Environment,
        'actual: ' . ($twig7 === null ? 'null' : get_class($twig7)));

    // 7b: 模板渲染输出正确
    $rendered7 = $twig7->render('test.html.twig', ['name' => 'MicroKernel']);
    report('场景7', '模板渲染输出正确',
        trim($rendered7) === 'Hello MicroKernel!',
        'actual: ' . trim($rendered7));

    $app7->shutdown();

    // 7c: 通过路由请求触发 Twig 渲染（完整请求链路）
    $cacheDir7c  = freshCacheDir('scenario7c');
    $cacheDirs[] = $cacheDir7c;

    $app7c = new MicroKernel(
        [
            'cache_dir'      => $cacheDir7c,
            'routing'        => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'twig'           => [
                'template_dir' => __DIR__ . '/../../../../ut/Twig/templates',
                'asset_base'   => 'http://example.com/assets',
            ],
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
        ],
        true
    );

    // /twig/ 路由调用 TwigController::a()，渲染 a.twig
    $request7c  = Request::create('/twig/', 'GET');
    $response7c = $app7c->handle($request7c);

    report('场景7', 'Twig 路由返回 200',
        $response7c->getStatusCode() === 200,
        'actual: ' . $response7c->getStatusCode());

    report('场景7', 'Twig 渲染包含 hello 变量',
        str_contains($response7c->getContent(), 'hello'),
        'actual (first 200 chars): ' . substr($response7c->getContent(), 0, 200));

    report('场景7', 'Twig 渲染包含 asset 路径',
        str_contains($response7c->getContent(), 'http://example.com/assets'),
        'content does not contain asset base');

    $app7c->shutdown();
} catch (\Throwable $e) {
    report('场景7', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;


// ═══════════════════════════════════════════════════════════════════════
// 场景 8: Bootstrap_Config 完整性
// ═══════════════════════════════════════════════════════════════════════

echo "--- 场景 8: Bootstrap_Config 完整性 ---" . PHP_EOL;

$cacheDir8 = freshCacheDir('scenario8');
$cacheDirs[] = $cacheDir8;

try {
    $testMiddleware8 = new RecordingMiddleware('test8', MicroKernel::BEFORE_PRIORITY_EARLIEST, MicroKernel::AFTER_PRIORITY_LATEST);

    $app8 = new MicroKernel(
        [
            'cache_dir'          => $cacheDir8,
            'routing'            => [
                'path'       => __DIR__ . '/../../../../ut/routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'twig'               => [
                'template_dir' => __DIR__ . '/../../../../ut/Twig/templates',
                'asset_base'   => 'http://example.com/assets',
            ],
            'cors'               => [
                new CrossOriginResourceSharingStrategy([
                    'pattern' => '*',
                    'origins' => '*',
                ]),
            ],
            'middlewares'        => [$testMiddleware8],
            'view_handlers'      => [new JsonViewHandler()],
            'error_handlers'     => [new JsonErrorHandler()],
            'injected_args'      => [new JsonViewHandler()],
            'trusted_proxies'    => ['10.0.0.1'],
            'trusted_header_set' => 'HEADER_X_FORWARDED_FOR',
        ],
        true
    );

    // 8a: MicroKernel 实例创建成功（所有 key 都被接受）
    report('场景8', 'Bootstrap_Config 完整配置创建成功', $app8 instanceof MicroKernel);

    // 8b: boot 成功
    $app8->boot();
    report('场景8', 'boot() 成功', true);

    // 8c: 各子系统正常初始化
    report('场景8', 'Twig 已注册',
        $app8->getTwig() instanceof \Twig\Environment);

    report('场景8', 'Routing 已注册',
        $app8->getRequestMatcher() !== null);

    // 8d: 发送请求验证完整链路
    RecordingMiddleware::resetLog();

    $request8 = Request::create('/', 'GET', [], [], [], [
        'HTTP_Origin' => 'http://example.com',
    ]);
    $response8 = $app8->handle($request8);

    report('场景8', '完整配置下请求返回 200',
        $response8->getStatusCode() === 200,
        'actual: ' . $response8->getStatusCode());

    // Middleware 被执行
    $log8 = RecordingMiddleware::getLog();
    report('场景8', 'Middleware 在完整配置下被执行',
        in_array('test8:before', $log8) && in_array('test8:after', $log8),
        'log: ' . implode(', ', $log8));

    // CORS header 存在
    report('场景8', 'CORS header 在完整配置下存在',
        $response8->headers->has('Access-Control-Allow-Origin'),
        'headers: ' . implode(', ', array_keys($response8->headers->all())));

    // Cookie 子系统已注册（ResponseCookieContainer 作为 injected arg）
    // 通过 /cookie/set 路由验证
    $request8cookie  = Request::create('/cookie/set', 'GET');
    $response8cookie = $app8->handle($request8cookie);
    $cookies8 = $response8cookie->headers->getCookies();
    $hasCookie8 = false;
    foreach ($cookies8 as $cookie) {
        if ($cookie->getName() === 'name') {
            $hasCookie8 = true;
            break;
        }
    }
    report('场景8', 'Cookie 子系统在完整配置下工作', $hasCookie8,
        'cookies: ' . implode(', ', array_map(fn($c) => $c->getName(), $cookies8)));

    // 8e: getCacheDirectories() 包含所有配置的 cache dir
    $cacheDirs8 = $app8->getCacheDirectories();
    report('场景8', 'getCacheDirectories() 包含 cache_dir',
        in_array($cacheDir8, $cacheDirs8),
        'dirs: ' . implode(', ', $cacheDirs8));

    $app8->shutdown();
} catch (\Throwable $e) {
    report('场景8', '异常', false, get_class($e) . ': ' . $e->getMessage());
}

Request::setTrustedProxies($savedProxies, $savedHeaderSet);

echo PHP_EOL;

// ═══════════════════════════════════════════════════════════════════════
// 汇总
// ═══════════════════════════════════════════════════════════════════════

echo str_repeat('=', 70) . PHP_EOL;
echo "Results: $passed passed, $failed failed" . PHP_EOL;

if ($failed > 0) {
    echo PHP_EOL . "Failures:" . PHP_EOL;
    foreach ($errors as $err) {
        echo "  - $err" . PHP_EOL;
    }
}

echo str_repeat('=', 70) . PHP_EOL;

// ── Cleanup ──────────────────────────────────────────────────────────

foreach ($cacheDirs as $dir) {
    removeDirRecursive($dir);
}

exit($failed > 0 ? 1 : 0);
