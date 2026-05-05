<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Integration Tests — 编程式路由注入端到端验证。
 *
 * 覆盖 addRoute / addRoutes 注入、YAML 覆盖、无 routing 配置、
 * boot 后冻结（MicroKernel 层 + RouteCollection 层）、boot 后只读操作。
 *
 * Ref: Requirement 1, AC 1–4; Requirement 2, AC 1–2; Requirement 3, AC 1–4
 */
class MicroKernelRouteInjectionIntegrationTest extends TestCase
{
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

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Create a MicroKernel with YAML routing config (cache disabled, not booted).
     */
    private function createKernelWithRouting(): MicroKernel
    {
        $cacheDir = static::createTempCacheDir();

        return new MicroKernel(
            [
                'cache_dir' => $cacheDir,
                'routing'   => [
                    'path'      => __DIR__ . '/fixtures/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );
    }

    /**
     * Create a MicroKernel WITHOUT routing config (cache disabled, not booted).
     */
    private function createKernelWithoutRouting(): MicroKernel
    {
        $cacheDir = static::createTempCacheDir();

        return new MicroKernel(['cache_dir' => $cacheDir], true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 编程式路由注入后可匹配
    // ═══════════════════════════════════════════════════════════════════

    /**
     * addRoute() 注入路由 → boot → matchRequest() 返回对应 _controller。
     *
     * Ref: Requirement 1, AC 1
     */
    public function testAddRouteInjectedRouteIsMatchableAfterBoot(): void
    {
        $kernel = $this->createKernelWithRouting();

        $kernel->addRoute('injected_health', new Route('/health-check', [
            '_controller' => 'HealthController::check',
        ]));

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $result = $matcher->matchRequest(Request::create('/health-check', 'GET'));
        $this->assertSame('HealthController::check', $result['_controller']);

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 批量注入后可匹配
    // ═══════════════════════════════════════════════════════════════════

    /**
     * addRoutes() 注入 RouteCollection → boot → 所有路由均可匹配。
     *
     * Ref: Requirement 1, AC 2
     */
    public function testAddRoutesAllInjectedRoutesAreMatchableAfterBoot(): void
    {
        $kernel = $this->createKernelWithRouting();

        $collection = new RouteCollection();
        $collection->add('api_users', new Route('/api/users', [
            '_controller' => 'ApiController::listUsers',
        ]));
        $collection->add('api_orders', new Route('/api/orders', [
            '_controller' => 'ApiController::listOrders',
        ]));
        $collection->add('api_products', new Route('/api/products', [
            '_controller' => 'ApiController::listProducts',
        ]));

        $kernel->addRoutes($collection);
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $this->assertSame(
            'ApiController::listUsers',
            $matcher->matchRequest(Request::create('/api/users', 'GET'))['_controller']
        );
        $this->assertSame(
            'ApiController::listOrders',
            $matcher->matchRequest(Request::create('/api/orders', 'GET'))['_controller']
        );
        $this->assertSame(
            'ApiController::listProducts',
            $matcher->matchRequest(Request::create('/api/products', 'GET'))['_controller']
        );

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 编程式路由覆盖 YAML 同名路由
    // ═══════════════════════════════════════════════════════════════════

    /**
     * YAML 定义 simple.home → addRoute('simple.home', ...) 覆盖 →
     * boot → matchRequest() 返回编程式路由的 _controller。
     *
     * Ref: Requirement 1, AC 3
     */
    public function testProgrammaticRouteOverridesYamlSameNameRoute(): void
    {
        $kernel = $this->createKernelWithRouting();

        // simple.routes.yml defines simple.home → SimpleController::home
        // Override with a programmatic route using the same name
        $kernel->addRoute('simple.home', new Route('/', [
            '_controller' => 'OverrideController::home',
        ]));

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $result = $matcher->matchRequest(Request::create('/', 'GET'));
        $this->assertSame(
            'OverrideController::home',
            $result['_controller'],
            'Programmatic route should override YAML route with the same name'
        );

        // The other YAML route (simple.about) should remain unaffected
        $resultAbout = $matcher->matchRequest(Request::create('/about', 'GET'));
        $this->assertSame(
            'SimpleController::about',
            $resultAbout['_controller'],
            'Non-overridden YAML route should still be matchable'
        );

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 无 routing 配置 + 编程式路由
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Bootstrap_Config 无 routing key → addRoute() 注入路由 →
     * boot → matchRequest() 返回对应 _controller。
     *
     * Ref: Requirement 1, AC 4
     */
    public function testProgrammaticRouteWithoutYamlRoutingConfig(): void
    {
        $kernel = $this->createKernelWithoutRouting();

        $kernel->addRoute('standalone_api', new Route('/standalone/api', [
            '_controller' => 'StandaloneController::api',
        ]));

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher, 'Request matcher should be available with programmatic routes only');

        $router = $kernel->getRouter();
        $this->assertNotNull($router, 'Router should be available with programmatic routes only');

        $result = $matcher->matchRequest(Request::create('/standalone/api', 'GET'));
        $this->assertSame('StandaloneController::api', $result['_controller']);

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Boot 后 MicroKernel 层冻结
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Boot 后 addRoute() 抛出 LogicException。
     *
     * Ref: Requirement 2, AC 1
     */
    public function testAddRouteAfterBootThrowsLogicException(): void
    {
        $kernel = $this->createKernelWithRouting();
        $kernel->boot();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cannot add routes after.*boot/i');

        $kernel->addRoute('late_route', new Route('/late', [
            '_controller' => 'LateController::action',
        ]));
    }

    /**
     * Boot 后 addRoutes() 抛出 LogicException。
     *
     * Ref: Requirement 2, AC 2
     */
    public function testAddRoutesAfterBootThrowsLogicException(): void
    {
        $kernel = $this->createKernelWithRouting();
        $kernel->boot();

        $collection = new RouteCollection();
        $collection->add('late_batch', new Route('/late-batch', [
            '_controller' => 'LateBatchController::action',
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cannot add routes after.*boot/i');

        $kernel->addRoutes($collection);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Boot 后 RouteCollection 层冻结
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Boot 后 getRouteCollection()->add() 抛出 LogicException。
     *
     * Ref: Requirement 3, AC 1
     */
    public function testPostBootRouteCollectionAddThrowsLogicException(): void
    {
        $kernel = $this->createKernelWithRouting();
        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $collection = $router->getRouteCollection();
        $this->assertInstanceOf(FrozenRouteCollection::class, $collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/frozen/i');

        $collection->add('frozen_add', new Route('/frozen-add', [
            '_controller' => 'FrozenController::add',
        ]));
    }

    /**
     * Boot 后 getRouteCollection()->addCollection() 抛出 LogicException。
     *
     * Ref: Requirement 3, AC 2
     */
    public function testPostBootRouteCollectionAddCollectionThrowsLogicException(): void
    {
        $kernel = $this->createKernelWithRouting();
        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $collection = $router->getRouteCollection();
        $this->assertInstanceOf(FrozenRouteCollection::class, $collection);

        $extra = new RouteCollection();
        $extra->add('frozen_batch', new Route('/frozen-batch', [
            '_controller' => 'FrozenController::batch',
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/frozen/i');

        $collection->addCollection($extra);
    }

    /**
     * Boot 后 getRouteCollection()->remove() 抛出 LogicException。
     *
     * Ref: Requirement 3, AC 3
     */
    public function testPostBootRouteCollectionRemoveThrowsLogicException(): void
    {
        $kernel = $this->createKernelWithRouting();
        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $collection = $router->getRouteCollection();
        $this->assertInstanceOf(FrozenRouteCollection::class, $collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/frozen/i');

        $collection->remove('simple.home');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Boot 后只读操作正常
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Boot 后 getRouteCollection()->get() 返回已知路由。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testPostBootRouteCollectionGetReturnsKnownRoute(): void
    {
        $kernel = $this->createKernelWithRouting();

        $kernel->addRoute('injected_read', new Route('/injected-read', [
            '_controller' => 'ReadController::action',
        ]));

        $kernel->boot();

        $collection = $kernel->getRouter()->getRouteCollection();
        $this->assertInstanceOf(FrozenRouteCollection::class, $collection);

        // YAML route
        $homeRoute = $collection->get('simple.home');
        $this->assertNotNull($homeRoute);
        $this->assertSame('/', $homeRoute->getPath());

        // Programmatic route
        $injectedRoute = $collection->get('injected_read');
        $this->assertNotNull($injectedRoute);
        $this->assertSame('/injected-read', $injectedRoute->getPath());
        $this->assertSame('ReadController::action', $injectedRoute->getDefault('_controller'));

        $kernel->shutdown();
    }

    /**
     * Boot 后 getRouteCollection()->all() 返回全部路由（YAML + 编程式）。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testPostBootRouteCollectionAllIncludesBothYamlAndProgrammatic(): void
    {
        $kernel = $this->createKernelWithRouting();

        $kernel->addRoute('injected_all', new Route('/injected-all', [
            '_controller' => 'AllController::action',
        ]));

        $kernel->boot();

        $collection = $kernel->getRouter()->getRouteCollection();
        $all = $collection->all();

        // simple.routes.yml has 2 routes + 1 programmatic = 3
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('simple.home', $all);
        $this->assertArrayHasKey('simple.about', $all);
        $this->assertArrayHasKey('injected_all', $all);

        $kernel->shutdown();
    }

    /**
     * Boot 后 getRouteCollection()->count() 返回正确数量。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testPostBootRouteCollectionCountIsCorrect(): void
    {
        $kernel = $this->createKernelWithRouting();

        $collection = new RouteCollection();
        $collection->add('count_a', new Route('/count-a', [
            '_controller' => 'CountController::a',
        ]));
        $collection->add('count_b', new Route('/count-b', [
            '_controller' => 'CountController::b',
        ]));

        $kernel->addRoutes($collection);
        $kernel->boot();

        $frozenCollection = $kernel->getRouter()->getRouteCollection();

        // 2 YAML + 2 programmatic = 4
        $this->assertSame(4, $frozenCollection->count());

        $kernel->shutdown();
    }

    /**
     * Boot 后 getRouteCollection()->getIterator() 可遍历且内容正确。
     *
     * Ref: Requirement 3, AC 4
     */
    public function testPostBootRouteCollectionIteratorIsTraversable(): void
    {
        $kernel = $this->createKernelWithRouting();

        $kernel->addRoute('iter_route', new Route('/iter', [
            '_controller' => 'IterController::action',
        ]));

        $kernel->boot();

        $collection = $kernel->getRouter()->getRouteCollection();

        $names = [];
        foreach ($collection as $name => $route) {
            $names[] = $name;
            $this->assertInstanceOf(Route::class, $route);
        }

        // 2 YAML + 1 programmatic = 3
        $this->assertCount(3, $names);
        $this->assertContains('simple.home', $names);
        $this->assertContains('simple.about', $names);
        $this->assertContains('iter_route', $names);

        $kernel->shutdown();
    }
}
