<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Bug Condition Exploration Test — Property 1
 *
 * 编程式路由注入 API 缺失与 Boot 后写操作静默失效。
 *
 * CRITICAL: This test MUST FAIL on unfixed code — failure confirms the bug exists.
 * DO NOT attempt to fix the test or the code when it fails.
 *
 * Ref: Requirement 1, AC 1–2; Requirement 2, AC 1; Requirement 3, AC 1
 */
class MicroKernelRouteInjectionTest extends TestCase
{
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    // ─── C1: API 缺失 ──────────────────────────────────────────────

    /**
     * Bug Condition C1 — addRoute() 方法不存在。
     *
     * 在未修复代码上，MicroKernel 没有 addRoute() 方法，
     * 调用时 PHP 抛出 Error（Call to undefined method）。
     *
     * 预期修复后：addRoute() 成功暂存路由，boot 后 matchRequest() 返回对应 _controller。
     *
     * Ref: Requirement 1, AC 1
     */
    public function testAddRouteShouldExistAndInjectRouteBeforeBoot(): void
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        // On unfixed code: addRoute() does not exist → PHP Error
        // On fixed code: addRoute() stores the route for later merging
        $kernel->addRoute('test_injected', new Route('/test-injected', [
            '_controller' => 'TestController::injectedAction',
        ]));

        $kernel->boot();

        // Verify the injected route is reachable after boot
        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher, 'Request matcher should be available after boot');

        $request = Request::create('/test-injected', 'GET');
        $result  = $matcher->matchRequest($request);

        $this->assertSame(
            'TestController::injectedAction',
            $result['_controller'],
            'Programmatically injected route should be matched after boot'
        );
    }

    /**
     * Bug Condition C1 — addRoutes() 方法不存在。
     *
     * 在未修复代码上，MicroKernel 没有 addRoutes() 方法，
     * 调用时 PHP 抛出 Error（Call to undefined method）。
     *
     * 预期修复后：addRoutes() 成功暂存 RouteCollection，boot 后所有路由均可匹配。
     *
     * Ref: Requirement 1, AC 2
     */
    public function testAddRoutesShouldExistAndInjectCollectionBeforeBoot(): void
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $collection = new RouteCollection();
        $collection->add('batch_route_a', new Route('/batch-a', [
            '_controller' => 'BatchController::actionA',
        ]));
        $collection->add('batch_route_b', new Route('/batch-b', [
            '_controller' => 'BatchController::actionB',
        ]));

        // On unfixed code: addRoutes() does not exist → PHP Error
        $kernel->addRoutes($collection);

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        $resultA = $matcher->matchRequest(Request::create('/batch-a', 'GET'));
        $this->assertSame('BatchController::actionA', $resultA['_controller']);

        $resultB = $matcher->matchRequest(Request::create('/batch-b', 'GET'));
        $this->assertSame('BatchController::actionB', $resultB['_controller']);
    }

    // ─── C2: Boot 后写操作静默失效 ──────────────────────────────────

    /**
     * Bug Condition C2 — Boot 后通过 getRouteCollection()->add() 添加路由，
     * 调用成功无异常但路由不可达。
     *
     * 在未修复代码上：add() 调用成功，但 /dynamic 返回 ResourceNotFoundException（404）。
     * 这确认了 bug：写操作静默失效。
     *
     * 预期修复后：add() 抛出 LogicException（FrozenRouteCollection 拦截）。
     *
     * Ref: Requirement 3, AC 1
     */
    public function testPostBootRouteCollectionAddShouldThrowLogicException(): void
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router, 'Router should be available after boot with routing config');

        $collection = $router->getRouteCollection();

        // On fixed code: this should throw LogicException (FrozenRouteCollection)
        $this->expectException(\LogicException::class);
        $collection->add('dynamic', new Route('/dynamic', [
            '_controller' => 'DynamicController::action',
        ]));
    }

    /**
     * Bug Condition C2 — Boot 后 addRoute() 应抛出 LogicException。
     *
     * 在未修复代码上：addRoute() 方法不存在（C1 bug），PHP 抛出 Error。
     * 预期修复后：addRoute() 存在但 boot 后调用抛出 LogicException。
     *
     * Ref: Requirement 2, AC 1
     */
    public function testPostBootAddRouteShouldThrowLogicException(): void
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        // On fixed code: addRoute() exists but throws LogicException after boot
        $this->expectException(\LogicException::class);
        $kernel->addRoute('post_boot_route', new Route('/post-boot', [
            '_controller' => 'PostBootController::action',
        ]));
    }

    /**
     * Bug Condition C2 — Boot 后写操作静默失效的直接证据。
     *
     * 在未修复代码上：
     *   1. boot 后 getRouteCollection()->add() 调用成功（无异常）
     *   2. 但匹配 /dynamic 时返回 ResourceNotFoundException
     *   → 写操作静默失效
     *
     * 预期修复后：步骤 1 即抛出 LogicException，不会到达步骤 2。
     *
     * 此测试在未修复代码上验证 bug 的静默失效行为：
     * - 如果 add() 不抛异常（未修复），则验证路由不可达（确认 bug）
     * - 如果 add() 抛出 LogicException（已修复），则测试通过
     *
     * Ref: Requirement 3, AC 1
     */
    public function testPostBootAddSilentlyFailsOnUnfixedCode(): void
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        $router = $kernel->getRouter();
        $this->assertNotNull($router);

        $collection = $router->getRouteCollection();

        try {
            $collection->add('dynamic_silent', new Route('/dynamic-silent', [
                '_controller' => 'DynamicController::silentAction',
            ]));
        } catch (\LogicException $e) {
            // Fixed code: FrozenRouteCollection throws LogicException — this is correct behavior
            $this->assertStringContainsString('frozen', strtolower($e->getMessage()));
            return;
        }

        // If we reach here, add() succeeded without exception (unfixed code).
        // Now verify the route is NOT reachable — confirming the silent failure bug.
        $matcher = $kernel->getRequestMatcher();
        $this->assertNotNull($matcher);

        // On unfixed code: the route was added to RouteCollection but the compiled
        // matcher doesn't see it → ResourceNotFoundException (404)
        $this->expectException(ResourceNotFoundException::class);
        $matcher->matchRequest(Request::create('/dynamic-silent', 'GET'));

        // If this test reaches here and throws ResourceNotFoundException,
        // it CONFIRMS the bug: add() succeeded silently but the route is unreachable.
    }
}
