<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * RoutingTrait $allowOverwrite 参数行为测试。
 *
 * 验证 addRoute() 和 addRoutes() 的 fail-fast 冲突检测：
 * - $allowOverwrite = true（默认）→ 静默覆盖（向后兼容）
 * - $allowOverwrite = false → 重复路由名抛 LogicException
 *
 * CRITICAL: 这些测试在 $allowOverwrite 参数实现前必须失败。
 * 失败原因：addRoute()/addRoutes() 当前签名不接受第三个参数。
 *
 * Ref: Design CR Q2 — RoutingTrait 对齐
 */
class MicroKernelRouteOverwriteTest extends TestCase
{
    private function createKernel(): MicroKernel
    {
        return new MicroKernel(
            [
                'routing' => [
                    'path'      => __DIR__ . '/fixtures/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    // ─── addRoute() + $allowOverwrite = true（默认）─────────────────

    /**
     * addRoute() 使用重复路由名 + $allowOverwrite = true → 静默覆盖。
     *
     * 当 $allowOverwrite = true（默认值），注册同名路由应静默成功，
     * 后注册的路由覆盖先注册的。
     */
    public function testAddRouteWithDuplicateNameAndAllowOverwriteTrueShouldSucceed(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('duplicate_route', new Route('/first', [
            '_controller' => 'FirstController::action',
        ]));

        // 默认 $allowOverwrite = true，重复名应静默覆盖
        $kernel->addRoute('duplicate_route', new Route('/second', [
            '_controller' => 'SecondController::action',
        ]), true);

        // 不应抛异常，boot 应成功
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // 后注册的路由应覆盖先注册的
        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/second', 'GET')
        );
        $this->assertSame('SecondController::action', $result['_controller']);
    }

    // ─── addRoute() + $allowOverwrite = false ───────────────────────

    /**
     * addRoute() 使用重复路由名 + $allowOverwrite = false → 抛 LogicException。
     *
     * 当 $allowOverwrite = false，注册同名路由应立即抛出 LogicException，
     * 实现 fail-fast 冲突检测。
     */
    public function testAddRouteWithDuplicateNameAndAllowOverwriteFalseShouldThrow(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('conflict_route', new Route('/original', [
            '_controller' => 'OriginalController::action',
        ]));

        $this->expectException(\LogicException::class);

        // $allowOverwrite = false，重复名应抛异常
        $kernel->addRoute('conflict_route', new Route('/duplicate', [
            '_controller' => 'DuplicateController::action',
        ]), false);
    }

    // ─── addRoutes() + $allowOverwrite = true（默认）─────────────────

    /**
     * addRoutes() 使用重复路由名 + $allowOverwrite = true → 静默覆盖。
     *
     * 当 $allowOverwrite = true（默认值），RouteCollection 中包含与已注册路由
     * 同名的路由时，应静默覆盖。
     */
    public function testAddRoutesWithDuplicateNameAndAllowOverwriteTrueShouldSucceed(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('shared_name', new Route('/first-version', [
            '_controller' => 'FirstController::action',
        ]));

        $collection = new RouteCollection();
        $collection->add('shared_name', new Route('/second-version', [
            '_controller' => 'SecondController::action',
        ]));

        // 默认 $allowOverwrite = true，重复名应静默覆盖
        $kernel->addRoutes($collection, true);

        // 不应抛异常，boot 应成功
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // 后注册的路由应覆盖先注册的
        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/second-version', 'GET')
        );
        $this->assertSame('SecondController::action', $result['_controller']);
    }

    // ─── addRoutes() + $allowOverwrite = false ──────────────────────

    /**
     * addRoutes() 使用重复路由名 + $allowOverwrite = false → 抛 LogicException。
     *
     * 当 $allowOverwrite = false，RouteCollection 中包含与已注册路由同名的路由时，
     * 应立即抛出 LogicException。
     */
    public function testAddRoutesWithDuplicateNameAndAllowOverwriteFalseShouldThrow(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('conflict_name', new Route('/existing', [
            '_controller' => 'ExistingController::action',
        ]));

        $collection = new RouteCollection();
        $collection->add('conflict_name', new Route('/conflicting', [
            '_controller' => 'ConflictingController::action',
        ]));

        $this->expectException(\LogicException::class);

        // $allowOverwrite = false，重复名应抛异常
        $kernel->addRoutes($collection, false);
    }

    // ─── 向后兼容：不传 $allowOverwrite ─────────────────────────────

    /**
     * 现有调用方不传 $allowOverwrite → 向后兼容（默认 true，静默覆盖）。
     *
     * 验证不传第三个参数时，行为与修复前一致：
     * 重复路由名不抛异常，后者覆盖前者。
     */
    public function testAddRouteWithoutAllowOverwriteParamIsBackwardCompatible(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('compat_route', new Route('/v1', [
            '_controller' => 'V1Controller::action',
        ]));

        // 不传 $allowOverwrite 参数（使用默认值 true）
        $kernel->addRoute('compat_route', new Route('/v2', [
            '_controller' => 'V2Controller::action',
        ]));

        // 不应抛异常
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // 后注册的路由覆盖先注册的
        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/v2', 'GET')
        );
        $this->assertSame('V2Controller::action', $result['_controller']);
    }

    /**
     * 现有调用方不传 $allowOverwrite 给 addRoutes() → 向后兼容。
     *
     * 验证 addRoutes() 不传第二个参数时，行为与修复前一致。
     */
    public function testAddRoutesWithoutAllowOverwriteParamIsBackwardCompatible(): void
    {
        $kernel = $this->createKernel();

        $kernel->addRoute('compat_collection_route', new Route('/original', [
            '_controller' => 'OriginalController::action',
        ]));

        $collection = new RouteCollection();
        $collection->add('compat_collection_route', new Route('/replacement', [
            '_controller' => 'ReplacementController::action',
        ]));

        // 不传 $allowOverwrite 参数（使用默认值 true）
        $kernel->addRoutes($collection);

        // 不应抛异常
        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // 后注册的路由覆盖先注册的
        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/replacement', 'GET')
        );
        $this->assertSame('ReplacementController::action', $result['_controller']);
    }

    // ─── Collection 分支覆盖：先 addRoutes() 再 addRoute() ─────────

    /**
     * addRoute() 覆盖 RouteCollection 中的同名路由（$allowOverwrite = true）。
     *
     * 验证 hasPendingRoute 和 removePendingRoute 的 collection 分支：
     * 先通过 addRoutes() 注册 RouteCollection，再用 addRoute() 覆盖其中的路由。
     */
    public function testAddRouteOverwritesRouteInExistingCollection(): void
    {
        $kernel = $this->createKernel();

        // 先通过 addRoutes() 注册一个 collection
        $collection = new RouteCollection();
        $collection->add('coll_route', new Route('/from-collection', [
            '_controller' => 'CollectionController::action',
        ]));
        $collection->add('other_route', new Route('/other', [
            '_controller' => 'OtherController::action',
        ]));
        $kernel->addRoutes($collection);

        // 用 addRoute() 覆盖 collection 中的路由（$allowOverwrite = true）
        $kernel->addRoute('coll_route', new Route('/overwritten', [
            '_controller' => 'OverwrittenController::action',
        ]), true);

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // 后注册的 addRoute 应覆盖 collection 中的同名路由
        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/overwritten', 'GET')
        );
        $this->assertSame('OverwrittenController::action', $result['_controller']);
    }

    /**
     * addRoute() 检测 RouteCollection 中的同名路由冲突（$allowOverwrite = false）。
     *
     * 验证 hasPendingRoute 的 collection 分支在 fail-fast 模式下正确抛异常。
     */
    public function testAddRouteDetectsConflictInExistingCollection(): void
    {
        $kernel = $this->createKernel();

        // 先通过 addRoutes() 注册一个 collection
        $collection = new RouteCollection();
        $collection->add('coll_conflict', new Route('/in-collection', [
            '_controller' => 'CollectionController::action',
        ]));
        $kernel->addRoutes($collection);

        $this->expectException(\LogicException::class);

        // $allowOverwrite = false，应检测到 collection 中的同名路由并抛异常
        $kernel->addRoute('coll_conflict', new Route('/duplicate', [
            '_controller' => 'DuplicateController::action',
        ]), false);
    }

    /**
     * addRoute() 覆盖 collection 中唯一路由后，空 collection 条目被移除。
     *
     * 验证 removePendingRoute 中 collection 为空时移除整个条目的逻辑。
     */
    public function testAddRouteOverwriteRemovesEmptyCollectionEntry(): void
    {
        $kernel = $this->createKernel();

        // 注册只含一个路由的 collection
        $collection = new RouteCollection();
        $collection->add('sole_route', new Route('/sole', [
            '_controller' => 'SoleController::action',
        ]));
        $kernel->addRoutes($collection);

        // 覆盖该路由 — collection 变空，条目应被移除
        $kernel->addRoute('sole_route', new Route('/replacement', [
            '_controller' => 'ReplacementController::action',
        ]), true);

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $result = $matcher->matchRequest(
            \Symfony\Component\HttpFoundation\Request::create('/replacement', 'GET')
        );
        $this->assertSame('ReplacementController::action', $result['_controller']);
    }

    /**
     * addRoutes() 检测与已有 collection 中同名路由的冲突（$allowOverwrite = false）。
     *
     * 验证 addRoutes() 的 fail-fast 也能检测到 collection 中的路由名。
     */
    public function testAddRoutesDetectsConflictInExistingCollection(): void
    {
        $kernel = $this->createKernel();

        // 先通过 addRoutes() 注册一个 collection
        $firstCollection = new RouteCollection();
        $firstCollection->add('shared_coll_name', new Route('/first', [
            '_controller' => 'FirstController::action',
        ]));
        $kernel->addRoutes($firstCollection);

        // 第二个 collection 含同名路由
        $secondCollection = new RouteCollection();
        $secondCollection->add('shared_coll_name', new Route('/second', [
            '_controller' => 'SecondController::action',
        ]));

        $this->expectException(\LogicException::class);

        // $allowOverwrite = false，应检测到冲突
        $kernel->addRoutes($secondCollection, false);
    }
}
