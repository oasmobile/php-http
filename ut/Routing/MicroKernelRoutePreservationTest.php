<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Preservation Property Tests — Property 2
 *
 * 非 Bug 条件下路由行为不变。
 * 这些测试在未修复代码上必须全部通过，确认需要保持的基线行为。
 * 修复后重新运行，确认无回归。
 *
 * Ref: Requirement 4, AC 1–4; Requirement 3, AC 4
 */
class MicroKernelRoutePreservationTest extends TestCase
{
    use TestTrait;
    use RouteCacheCleaner;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../cache');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore exception handler to prevent PHPUnit warnings
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

    // ─── Helper ─────────────────────────────────────────────────────

    /**
     * Create a booted MicroKernel with simple.routes.yml (cache disabled).
     */
    private function createBootedKernelWithRouting(): MicroKernel
    {
        $cacheDir = static::createTempCacheDir();
        $kernel = new MicroKernel(
            [
                'cache_dir' => $cacheDir,
                'routing'   => [
                    'path'      => __DIR__ . '/fixtures/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );
        $kernel->boot();

        return $kernel;
    }

    // ═══════════════════════════════════════════════════════════════════
    // PBT: YAML 路由匹配 Preservation
    // ═══════════════════════════════════════════════════════════════════

    /**
     * PBT: 对于随机选取的已定义 YAML 路由，matchRequest() 返回对应 _controller。
     *
     * Ref: Requirement 4, AC 1
     */
    public function testYamlRouteMatchReturnsCorrectController(): void
    {
        $kernel = $this->createBootedKernelWithRouting();

        // Collect defined routes from simple.routes.yml
        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $routeMap = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $routeMap[$name] = [
                'path'       => $route->getPath(),
                'controller' => $route->getDefault('_controller'),
            ];
        }
        $this->assertNotEmpty($routeMap, 'Should have at least one defined route');

        $routeNames = array_keys($routeMap);

        $this->forAll(
            Generators::elements($routeNames)
        )->then(function (string $routeName) use ($kernel, $routeMap) {
            $info    = $routeMap[$routeName];
            $matcher = $kernel->getRequestMatcher();
            $this->assertNotNull($matcher);

            $request = Request::create($info['path'], 'GET');
            $result  = $matcher->matchRequest($request);

            $this->assertSame(
                $info['controller'],
                $result['_controller'],
                sprintf(
                    'Route "%s" (path: %s) should match controller "%s"',
                    $routeName,
                    $info['path'],
                    $info['controller']
                )
            );
        });

        $kernel->shutdown();
    }

    /**
     * PBT: 对于随机生成的未定义路径，match() 抛出 ResourceNotFoundException。
     *
     * Ref: Requirement 4, AC 1
     */
    public function testUndefinedPathThrowsResourceNotFoundException(): void
    {
        $kernel = $this->createBootedKernelWithRouting();

        $this->forAll(
            Generators::string()
        )->then(function (string $randomSuffix) use ($kernel) {
            // Prefix with /__preservation_undefined__/ to guarantee no collision
            $path = '/__preservation_undefined__/' . bin2hex($randomSuffix);

            $matcher = $kernel->getRequestMatcher();
            $this->assertNotNull($matcher);

            try {
                $matcher->matchRequest(Request::create($path, 'GET'));
                $this->fail(sprintf('Expected ResourceNotFoundException for path "%s"', $path));
            } catch (ResourceNotFoundException $e) {
                $this->assertTrue(true);
            }
        });

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Unit: 无 routing 配置 → getRouter() 返回 null
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Unit: 无 routing 配置 + 无编程式路由 → getRouter() 返回 null。
     *
     * Ref: Requirement 4, AC 2
     */
    public function testNoRoutingConfigReturnsNullRouter(): void
    {
        $cacheDir = static::createTempCacheDir();
        $kernel = new MicroKernel(['cache_dir' => $cacheDir], true);
        $kernel->boot();

        $this->assertNull(
            $kernel->getRouter(),
            'getRouter() should return null when no routing config is provided'
        );
        $this->assertNull(
            $kernel->getRequestMatcher(),
            'getRequestMatcher() should return null when no routing config is provided'
        );
        $this->assertNull(
            $kernel->getUrlGenerator(),
            'getUrlGenerator() should return null when no routing config is provided'
        );

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Unit: addMiddleware() 注入的中间件在 boot 后正常工作
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Unit: addMiddleware() 在 boot 前调用后，boot 后中间件正常注册
     * （KernelEvents::REQUEST listener 存在）。
     *
     * Ref: Requirement 4, AC 3
     */
    public function testAddMiddlewareRegistersListenerAfterBoot(): void
    {
        $cacheDir = static::createTempCacheDir();
        $kernel = new MicroKernel(
            [
                'cache_dir' => $cacheDir,
                'routing'   => [
                    'path'      => __DIR__ . '/fixtures/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        // Create a simple test middleware that tracks invocation
        $invoked = false;
        $middleware = new class($invoked) extends AbstractMiddleware {
            private bool $invoked;

            public function __construct(bool &$invoked)
            {
                $this->invoked = &$invoked;
            }

            public function before(Request $request, MicroKernel $kernel): Response|null
            {
                $this->invoked = true;
                return null;
            }

            public function after(Request $request, Response $response): void
            {
            }
        };

        $kernel->addMiddleware($middleware);
        $kernel->boot();

        // Verify the middleware's before() listener is registered on KernelEvents::REQUEST
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $kernel->getContainer()->get('event_dispatcher');
        $listeners  = $dispatcher->getListeners(KernelEvents::REQUEST);

        $this->assertNotEmpty(
            $listeners,
            'KernelEvents::REQUEST should have listeners after boot with middleware'
        );

        // The middleware listener should be among the registered listeners.
        // We verify by counting: with routing + middleware, there should be at least 2
        // REQUEST listeners (routing listener + middleware before listener).
        $this->assertGreaterThanOrEqual(
            2,
            count($listeners),
            'Should have at least 2 REQUEST listeners (routing + middleware)'
        );

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Unit: boot 后 getRouteCollection() 只读操作返回正确结果
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Unit: boot 后 get() 返回已知路由。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testGetRouteCollectionGetReturnsKnownRoute(): void
    {
        $kernel     = $this->createBootedKernelWithRouting();
        $collection = $kernel->getRouter()->getRouteCollection();

        $homeRoute = $collection->get('simple.home');
        $this->assertNotNull($homeRoute, 'get("simple.home") should return a Route');
        $this->assertSame('/', $homeRoute->getPath());
        $this->assertSame('SimpleController::home', $homeRoute->getDefault('_controller'));

        $aboutRoute = $collection->get('simple.about');
        $this->assertNotNull($aboutRoute, 'get("simple.about") should return a Route');
        $this->assertSame('/about', $aboutRoute->getPath());
        $this->assertSame('SimpleController::about', $aboutRoute->getDefault('_controller'));

        $nonexistent = $collection->get('nonexistent');
        $this->assertNull($nonexistent, 'get("nonexistent") should return null');

        $kernel->shutdown();
    }

    /**
     * Unit: boot 后 all() 返回全部路由。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testGetRouteCollectionAllReturnsAllRoutes(): void
    {
        $kernel     = $this->createBootedKernelWithRouting();
        $collection = $kernel->getRouter()->getRouteCollection();

        $all = $collection->all();
        $this->assertIsArray($all);
        $this->assertCount(2, $all, 'simple.routes.yml defines 2 routes');
        $this->assertArrayHasKey('simple.home', $all);
        $this->assertArrayHasKey('simple.about', $all);

        $kernel->shutdown();
    }

    /**
     * Unit: boot 后 count() 返回正确数量。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testGetRouteCollectionCountReturnsCorrectNumber(): void
    {
        $kernel     = $this->createBootedKernelWithRouting();
        $collection = $kernel->getRouter()->getRouteCollection();

        $this->assertSame(2, $collection->count(), 'simple.routes.yml defines 2 routes');

        $kernel->shutdown();
    }

    /**
     * Unit: boot 后 getIterator() 可遍历且内容正确。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testGetRouteCollectionIteratorIsTraversable(): void
    {
        $kernel     = $this->createBootedKernelWithRouting();
        $collection = $kernel->getRouter()->getRouteCollection();

        $names = [];
        foreach ($collection as $name => $route) {
            $names[] = $name;
            $this->assertInstanceOf(
                \Symfony\Component\Routing\Route::class,
                $route,
                sprintf('Route "%s" should be a Route instance', $name)
            );
        }

        $this->assertCount(2, $names, 'Iterator should yield 2 routes');
        $this->assertContains('simple.home', $names);
        $this->assertContains('simple.about', $names);

        $kernel->shutdown();
    }
}
