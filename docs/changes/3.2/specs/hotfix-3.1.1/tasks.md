# Implementation Plan

## Tasks

- [x] 1. Write bug condition exploration test
  - [x] 1.1 Update `phpunit.xml` — 将所有新测试文件加入 `routing` suite
    - 添加 `<file>ut/Routing/MicroKernelRouteInjectionTest.php</file>`
    - 添加 `<file>ut/Routing/MicroKernelRoutePreservationTest.php</file>`
    - 添加 `<file>ut/Routing/FrozenRouteCollectionTest.php</file>`
    - 添加 `<file>ut/Routing/MicroKernelRouteInjectionIntegrationTest.php</file>`
    - Ref: Requirement 1, AC 1; Requirement 2, AC 1; Requirement 3, AC 1; Requirement 4, AC 1
  - [x] 1.2 Create `ut/Routing/MicroKernelRouteInjectionTest.php`
    - **Property 1: Bug Condition** — 编程式路由注入 API 缺失与 Boot 后写操作静默失效
    - **CRITICAL**: This test MUST FAIL on unfixed code — failure confirms the bug exists
    - **DO NOT attempt to fix the test or the code when it fails**
    - **NOTE**: This test encodes the expected behavior — it will validate the fix when it passes after implementation
    - **GOAL**: Surface counterexamples that demonstrate the bug exists
    - **Scoped PBT Approach**: Bug 是确定性的，将 property 限定到具体的失败场景以确保可复现
    - **Bug Condition C1 — API 缺失**:
      - 创建 MicroKernel 实例（含 routing 配置）
      - 尝试调用 `$kernel->addRoute('test', new Route('/test', ['_controller' => 'TestController::action']))`
      - 在未修复代码上，`addRoute()` 方法不存在，PHP 抛出 `Error`（method not found）
      - 预期修复后：`addRoute()` 成功暂存路由，boot 后 `matchRequest()` 返回对应 `_controller`
    - **Bug Condition C2 — Boot 后写操作静默失效**:
      - 创建 MicroKernel 实例（含 routing 配置），boot
      - 调用 `$kernel->getRouter()->getRouteCollection()->add('dynamic', new Route('/dynamic', [...]))`
      - 发送匹配 `/dynamic` 的请求，验证返回 404（`ResourceNotFoundException`）
      - 在未修复代码上，`add()` 调用成功无异常但路由不可达——确认 bug 存在
      - 预期修复后：`add()` 抛出 `LogicException`（FrozenRouteCollection 拦截）
    - Ref: Requirement 1, AC 1–2; Requirement 2, AC 1; Requirement 3, AC 1
  - [x] 1.3 Run test on UNFIXED code, document results
    - **EXPECTED OUTCOME**: Test FAILS (this is correct — it proves the bug exists)
    - Document counterexamples found to understand root cause
    - Mark task complete when test is written, run, and failure is documented
  - [x] 1.4 Checkpoint: 确认测试文件已创建、phpunit.xml 已更新、测试在未修复代码上失败并记录了失败原因；commit

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - [x] 2.1 Observe baseline behavior on UNFIXED code
    - **Property 2: Preservation** — 非 Bug 条件下路由行为不变
    - **IMPORTANT**: Follow observation-first methodology
    - **测试文件**: `ut/Routing/MicroKernelRoutePreservationTest.php`
    - Observe: 仅 YAML 路由配置时，`MicroKernel` boot 后 `getRouter()` 返回非 null，`matchRequest()` 正确匹配 YAML 路由
    - Observe: 未配置 `routing` 且无编程式路由时，`getRouter()` 返回 `null`
    - Observe: `addMiddleware()` 在 boot 前调用后，boot 后中间件正常注册（`KernelEvents::REQUEST` listener 存在）
    - Observe: boot 后 `getRouteCollection()` 的只读操作（`get()`、`all()`、`count()`、`getIterator()`）返回正确结果
    - Ref: Requirement 4, AC 1–4; Requirement 3, AC 4
  - [x] 2.2 Write property-based and unit tests
    - PBT: 对于随机选取的已定义 YAML 路由，`matchRequest()` 返回对应 `_controller`（与修复前行为一致）
    - PBT: 对于随机生成的未定义路径，`match()` 抛出 `ResourceNotFoundException`（与修复前行为一致）
    - Unit: 无 `routing` 配置 + 无编程式路由 → `getRouter()` 返回 `null`
    - Unit: `addMiddleware()` 注入的中间件在 boot 后正常工作
    - Unit: boot 后 `getRouteCollection()` 只读操作返回正确结果（`get()` 返回已知路由、`all()` 返回全部、`count()` 返回正确数量、`getIterator()` 可遍历）
    - Ref: Requirement 4, AC 1–4; Requirement 3, AC 4
  - [x] 2.3 Verify tests PASS on UNFIXED code
    - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
    - Mark task complete when tests are written, run, and passing on unfixed code
  - [x] 2.4 Checkpoint: 确认 preservation 测试文件已创建、所有测试在未修复代码上通过；commit

- [x] 3. Implement FrozenRouteCollection
  - [x] 3.1 Create `src/ServiceProviders/Routing/FrozenRouteCollection.php`
    - 继承 Symfony `RouteCollection`
    - 构造函数接受 `RouteCollection $wrapped`，通过 `parent::addCollection($wrapped)` 复制所有路由和资源
    - Override `add()` → 抛出 `LogicException('Route collection is frozen after boot. Routes cannot be modified at this point.')`
    - Override `addCollection()` → 抛出同样的 `LogicException`
    - Override `remove()` → 抛出同样的 `LogicException`
    - Override `addResource()` → 抛出同样的 `LogicException`
    - 只读方法（`get()`、`all()`、`count()`、`getIterator()`、`getResources()`）继承自父类，不 override
    - Ref: Requirement 3, AC 1–4
  - [x] 3.2 Create `ut/Routing/FrozenRouteCollectionTest.php`，加入 `routing` suite
    - 测试 `add()` 抛出 `LogicException`
    - 测试 `addCollection()` 抛出 `LogicException`
    - 测试 `remove()` 抛出 `LogicException`
    - 测试 `addResource()` 抛出 `LogicException`
    - 测试 `get()` 返回已知路由
    - 测试 `all()` 返回全部路由
    - 测试 `count()` 返回正确数量
    - 测试 `getIterator()` 可遍历且内容正确
    - 测试构造函数正确复制 wrapped collection 的所有路由
    - Ref: Requirement 3, AC 1–4
  - [x] 3.3 Checkpoint: 运行 `./vendor/bin/phpunit --filter FrozenRouteCollectionTest` 确认通过；commit

- [x] 4. Implement CacheableRouter freeze
  - [x] 4.1 Modify `src/ServiceProviders/Routing/CacheableRouter.php`
    - 新增 `private bool $frozen = false;`
    - 新增 `private ?FrozenRouteCollection $frozenCollection = null;`（缓存 FrozenRouteCollection 实例）
    - 新增 `public function freeze(): void` — 将 `$frozen` 设为 `true`，清空 `$frozenCollection` 缓存
    - 修改 `getRouteCollection()` — 在现有参数替换逻辑完成后，如果 `$frozen` 为 `true`，将结果包装为 `FrozenRouteCollection` 返回（使用 `$frozenCollection` 缓存避免重复创建）
    - Ref: Requirement 3, AC 1–4
  - [x] 4.2 Update `ut/Routing/CacheableRouterTest.php`
    - 新增测试：`freeze()` 后 `getRouteCollection()` 返回 `FrozenRouteCollection` 实例
    - 新增测试：`freeze()` 后 `getRouteCollection()->add()` 抛出 `LogicException`
    - 新增测试：未 `freeze()` 时 `getRouteCollection()` 返回普通 `RouteCollection`（现有行为不变）
    - 新增测试：`freeze()` 后只读操作（`get()`、`all()`）正常返回
    - 新增测试：多次调用 `getRouteCollection()` 返回同一 `FrozenRouteCollection` 实例（缓存验证）
    - Ref: Requirement 3, AC 1; Requirement 3, AC 4
  - [x] 4.3 Checkpoint: 运行 `./vendor/bin/phpunit --filter CacheableRouterTest` 确认通过（含新增测试）；commit

- [x] 5. Implement MicroKernel addRoute/addRoutes + registerRouting modification
  - [x] 5.1 Modify `src/MicroKernel.php` — 新增属性和方法
    - 新增 `protected array $pendingRoutes = [];`（在 `$routerProvider` 属性附近）
    - 新增 `use Symfony\Component\Routing\Route;` import（如尚未存在）
    - 新增 `use Symfony\Component\Routing\RouteCollection;` import（如尚未存在）
    - 新增 `addRoute(string $name, Route $route): void` — 检查 `$this->booted`，已 boot 则抛 `LogicException('Cannot add routes after the kernel has been booted.')`，否则追加 `['name' => $name, 'route' => $route]` 到 `$pendingRoutes`
    - 新增 `addRoutes(RouteCollection $routes): void` — 检查 `$this->booted`，已 boot 则抛 `LogicException('Cannot add routes after the kernel has been booted.')`，否则追加 `['collection' => $routes]` 到 `$pendingRoutes`
    - Ref: Requirement 1, AC 1–2; Requirement 2, AC 1–2
  - [x] 5.2 Modify `src/MicroKernel.php` — 修改 `registerRouting()`
    - 在方法开头检查 `$hasPendingRoutes = !empty($this->pendingRoutes)`
    - 如果 `!$routingConfig && !$hasPendingRoutes` 则 return（现有行为不变）
    - 如果有 `$routingConfig`：执行现有 YAML 路由加载逻辑（不变）
    - 如果 `!$routingConfig && $hasPendingRoutes`：绕过 CacheableRouterProvider，直接创建空 `RouteCollection` + `GroupUrlMatcher` + `GroupUrlGenerator`，设置 `$this->requestMatcher` 和 `$this->urlGenerator`
    - 在 `buildRequestMatcher()` 调用前（或 matcher 构建前），遍历 `$pendingRoutes` 合并编程式路由到 RouteCollection
    - 在 matcher 和 generator 构建完成后，如果有 CacheableRouter 则调用 `freeze()`
    - 注册路由监听器（现有逻辑不变）
    - Ref: Requirement 1, AC 1–4; Requirement 4, AC 1–2; Requirement 4, AC 4
  - [x] 5.3 Modify `src/MicroKernel.php` — 修改 `getRouter()` 以支持无 CacheableRouterProvider 场景
    - 当 `$this->routerProvider` 为 null 但存在编程式路由时，需要一种方式让 `getRouter()` 返回有效的 Router
    - 新增 `protected ?Router $directRouter = null;` 属性，用于无 YAML 配置时存储直接创建的 Router
    - 修改 `getRouter()` — 如果 `$this->routerProvider` 为 null 且 `$this->directRouter` 不为 null，返回 `$this->directRouter`
    - Ref: Requirement 1, AC 4
  - [x] 5.4 Checkpoint: 运行 `./vendor/bin/phpunit --testsuite routing` 确认通过；运行 `./vendor/bin/phpstan analyse` 确认无新增错误；commit

- [x] 6. Write integration tests for route injection
  - [x] 6.1 Create `ut/Routing/MicroKernelRouteInjectionIntegrationTest.php`，加入 `routing` suite
    - **编程式路由注入后可匹配**：`addRoute()` 注入路由 → boot → `matchRequest()` 返回对应 `_controller`
    - **批量注入后可匹配**：`addRoutes()` 注入 RouteCollection → boot → 所有路由均可匹配
    - **编程式路由覆盖 YAML 同名路由**：YAML 定义 `route_a` → `addRoute('route_a', ...)` 覆盖 → boot → `matchRequest()` 返回编程式路由的 `_controller`
    - **无 routing 配置 + 编程式路由**：Bootstrap_Config 无 `routing` key → `addRoute()` 注入路由 → boot → `matchRequest()` 返回对应 `_controller`
    - **Boot 后 `addRoute()` 抛异常**：boot 后调用 `addRoute()` → 预期 `LogicException`
    - **Boot 后 `addRoutes()` 抛异常**：boot 后调用 `addRoutes()` → 预期 `LogicException`
    - **Boot 后 `getRouteCollection()->add()` 抛异常**：boot 后调用 → 预期 `LogicException`
    - **Boot 后 `getRouteCollection()->addCollection()` 抛异常**：boot 后调用 → 预期 `LogicException`
    - **Boot 后 `getRouteCollection()->remove()` 抛异常**：boot 后调用 → 预期 `LogicException`
    - **Boot 后只读操作正常**：boot 后 `getRouteCollection()->get()`、`all()`、`count()`、`getIterator()` 正常返回
    - Ref: Requirement 1, AC 1–4; Requirement 2, AC 1–2; Requirement 3, AC 1–4
  - [x] 6.2 Checkpoint: 运行 `./vendor/bin/phpunit --filter MicroKernelRouteInjectionIntegrationTest` 确认通过；commit

- [x] 7. Verify bug condition exploration test now passes
  - [x] 7.1 Re-run bug condition exploration test
    - **Property 1: Expected Behavior** — 编程式路由注入 API 可用且 Boot 后写操作抛异常
    - **IMPORTANT**: Re-run the SAME test from task 1 — do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run `./vendor/bin/phpunit --filter MicroKernelRouteInjectionTest`
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Ref: Requirement 1, AC 1–2; Requirement 2, AC 1; Requirement 3, AC 1
  - [x] 7.2 Re-run preservation tests
    - **Property 2: Preservation** — 非 Bug 条件下路由行为不变
    - **IMPORTANT**: Re-run the SAME tests from task 2 — do NOT write new tests
    - Run `./vendor/bin/phpunit --filter MicroKernelRoutePreservationTest`
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all preservation tests still pass after fix (no regressions)
    - Ref: Requirement 4, AC 1–4; Requirement 3, AC 4
  - [x] 7.3 Checkpoint: 确认 task 1 的 failing test 已翻转为 pass、task 2 的 preservation test 仍然 pass；commit

- [x] 8. Full verification
  - [x] 8.1 Run `./vendor/bin/phpunit --testsuite routing` — 确认 routing suite 全部通过
  - [x] 8.2 Run `./vendor/bin/phpunit` — 确认全量测试通过，无回归
  - [x] 8.3 Run `./vendor/bin/phpstan analyse` — 确认静态分析无新增错误
  - [x] 8.4 Checkpoint: 确认全量测试和静态分析均通过；如有问题，修复后重新验证；commit

- [x] 9. Update documentation
  - [x] 9.1 Update `docs/state/architecture.md`
    - 在模块结构中补充 `FrozenRouteCollection` 到 `ServiceProviders/Routing/` 说明
  - [x] 9.2 Update `docs/manual/routing.md`
    - 补充编程式路由注入 API 的使用说明（`addRoute()` / `addRoutes()`）
    - 补充 boot 后冻结行为说明
  - [x] 9.3 Update `docs/changes/3.1/CHANGELOG.md`
    - 记录 hotfix 3.1.1 的变更内容
  - [x] 9.4 Checkpoint: 确认文档更新完整、无遗漏；commit

- [x] 10. Dual-matcher architecture for cache isolation (red → green → refactor)
  - [x] 10.1 Update `phpunit.xml` — 将新测试文件加入 `routing` suite
    - 添加 `<file>ut/Routing/RouteCacheIsolationTest.php</file>`
    - Ref: Requirement 5, AC 1–5
  - [x] 10.2 Write failing tests — `ut/Routing/RouteCacheIsolationTest.php`
    - **CRITICAL**: 以下测试在当前代码上预期失败，证明缺陷存在
    - **YAML + Closure 编程式路由共存（FAIL）**：配置真实 `cache_dir` + `addRoute()` 注入 Closure controller → boot → 当前代码将 Closure 合并到 YAML RouteCollection 再编译缓存，Closure 无法序列化，预期抛出序列化错误
    - **缓存隔离验证（FAIL）**：配置真实 `cache_dir` + `addRoute()` → boot → 读取缓存文件内容，当前代码将编程式路由合并到 YAML collection 一起编译，缓存文件包含编程式路由名——预期失败
    - **编程式路由优先匹配（FAIL）**：YAML 定义 `route_a` → `addRoute('route_a', ...)` 覆盖 → boot → 当前代码合并到同一 collection 后由 Symfony 默认语义决定顺序，可能不符合"编程式优先"预期
    - 以下测试在当前代码上预期通过（preservation / 缓存基线）：
    - **缓存生效验证（PASS）**：配置真实 `cache_dir` → boot → 验证缓存目录下生成了路由缓存文件
    - **缓存命中验证（PASS）**：首次 boot 生成缓存 → 第二次创建新 kernel boot → 验证路由匹配结果一致
    - **YAML 路由文件内容变更后缓存失效（PASS）**：`debug: true` → boot → 向 YAML 文件追加一条新路由 → 再次创建新 kernel boot → 验证新路由可匹配（缓存重新编译）。使用临时 YAML 文件副本，测试后清理
    - **YAML 路由文件 mtime 变更后缓存失效（PASS）**：`debug: true` → boot → touch YAML 文件（仅更新 mtime，不改内容）→ 再次创建新 kernel boot → 验证缓存重新编译
    - **PHP resource 文件变更后缓存失效（PASS）**：`debug: true` → boot → touch `CacheableRouter.php`（已通过 `addResource(new FileResource(__FILE__))` 注册为 resource）→ 再次创建新 kernel boot → 验证缓存重新编译。测试后恢复原 mtime
    - **`debug: false` 时缓存持久不失效（PASS）**：`debug: false` → boot → 修改 YAML 文件内容（追加新路由）→ 再次创建新 kernel boot → 验证仍使用旧缓存（新路由不可匹配）
    - **纯编程式路由 + 缓存配置（PASS）**：有 `cache_dir` 但无 `routing` key + `addRoute()` → boot → 路由可匹配（当前代码已支持此场景）
    - Ref: Requirement 5, AC 1–5; Requirement 1, AC 3; Requirement 4, AC 4
  - [x] 10.3 Run tests on CURRENT code, document results
    - **EXPECTED**: 缓存隔离相关测试失败（红），缓存基线测试通过（绿）
    - 记录失败的测试和错误信息
  - [x] 10.4 Implement dual-matcher — Modify `src/MicroKernel.php` `registerRouting()`
    - 编程式路由构建独立的 `programmaticCollection`（`RouteCollection`）+ `UrlMatcher`，不合并到 YAML RouteCollection
    - YAML 路由继续走 CacheableRouter 编译缓存路径（不变）
    - `GroupUrlMatcher` 组合时编程式 matcher 排在前面（优先匹配）
    - `GroupUrlGenerator` 同样按编程式优先顺序组合
    - 无 YAML 配置 + 有编程式路由的分支：`directRouter` 仍需创建以支持 `getRouter()`
    - 有 YAML 配置 + 有编程式路由的分支：编程式路由不再调用 `router->getRouteCollection()->add()`，而是构建独立 matcher
    - 编程式路由的 `programmaticCollection` 在 boot 后通过 `directRouter.freeze()` 或不暴露可写引用实现冻结
    - Ref: Requirement 5, AC 1–4; Requirement 1, AC 1–3
  - [x] 10.5 Verify failing tests now pass (red → green)
    - Run `./vendor/bin/phpunit --filter RouteCacheIsolationTest` — 确认所有测试通过
    - 确认 10.2 中标记为 FAIL 的测试已翻转为 PASS
  - [x] 10.6 Re-run existing tests to verify no regressions
    - Run `./vendor/bin/phpunit --testsuite routing` — 确认所有现有 routing 测试仍通过
    - Run `./vendor/bin/phpunit` — 确认全量测试通过
    - Run `./vendor/bin/phpstan analyse` — 确认静态分析无新增错误
    - Ref: Requirement 4, AC 1–4
  - [x] 10.7 Checkpoint: 确认 failing tests 翻转为 pass、缓存基线测试仍 pass、全量测试和静态分析通过；commit

- [x] 11. Manual testing
  - [x] 11.1 编程式路由注入端到端验证
    - 使用 `addRoute()` 注入路由，boot 后发送 HTTP 请求，确认路由可达且返回正确 controller 响应
  - [x] 11.2 Boot 后冻结行为验证
    - boot 后尝试调用 `addRoute()` 和 `getRouteCollection()->add()`，确认抛出 `LogicException` 且异常消息清晰
  - [x] 11.3 YAML + 编程式路由混合场景验证
    - 同时配置 YAML 路由和编程式路由，确认两者均可匹配，同名路由编程式优先
  - [x] 11.4 无 routing 配置 + 编程式路由场景验证
    - Bootstrap_Config 中不配置 `routing`，仅通过 `addRoute()` 注入路由，确认路由可达
  - [x] 11.5 Closure controller 端到端验证
    - 配置 YAML 路由（启用缓存）+ `addRoute()` 注入 Closure controller → boot → 发送请求确认 Closure 路由可达且 YAML 路由也可达
  - [x] 11.6 缓存目录清理后重新 boot 验证
    - 删除缓存目录 → 重新 boot → 确认路由缓存重新生成且路由正常匹配
  - [x] 11.7 Checkpoint: 确认所有手工测试场景通过；记录测试结果

- [x] 12. Code review
  - 委托给 `code-reviewer` sub-agent 执行
  - 基于当前分支的 diff 进行 code review

---

## Notes

- 按 `spec-execution.md` 规范执行各 task
- Commit 随 checkpoint 一起执行，每个 top-level task 完成时在 checkpoint sub-task 中 commit
- **Failing-test-first 流程**：Task 1–2 在未修复代码上编写并运行测试（task 1 预期失败，task 2 预期通过），task 3–5 实施修复，task 7 验证 task 1 的测试翻转为通过。严格遵循此顺序，不要在 task 1–2 阶段修改源代码
- **按模块拆分实现**（design.md CR Q4→A）：Task 3 = FrozenRouteCollection、Task 4 = CacheableRouter freeze、Task 5 = MicroKernel addRoute/addRoutes + registerRouting
- **phpunit.xml 更新时机**：在 task 1.1 中统一更新，确保后续所有测试文件都能通过 `--testsuite routing` 运行
- **无 routing 配置 + 编程式路由**（bugfix.md CR Q1→A）：task 5.2 中 `registerRouting()` 需处理此边界条件，绕过 CacheableRouterProvider 直接创建空路由基础设施
- **双层 matcher 架构**（Requirement 5）：Task 10 遵循先红后绿流程——先写 failing test 证明当前代码的缓存隔离缺陷（YAML+Closure 序列化错误等），再实施 `registerRouting()` 重构为双层 matcher 架构，最后验证 failing tests 翻转为 pass。同时补充路由缓存生效/失效的基线测试（包括 YAML 文件变更、PHP resource 文件变更、debug:false 持久缓存）

---

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有实现项？**
A: 是。design 中 Fix Implementation 涉及三个文件：MicroKernel.php（task 5、10）、FrozenRouteCollection.php（task 3）、CacheableRouter.php（task 4）。Testing Strategy 中的 Phase 1 failing tests（task 1–2）、unit tests（task 3.2、4.2）、integration tests（task 6）、Phase 3 验证（task 7–8）均已覆盖。Impact Analysis 中的文档更新（task 9）也已覆盖。新增的双层 matcher 架构和缓存隔离测试在 task 10 中覆盖。

**Q: task 之间的依赖顺序是否正确？**
A: 是。Task 1–2（failing-test-first）→ Task 3–5（按模块实现）→ Task 6（集成测试）→ Task 7（验证 failing test 翻转）→ Task 8（全量验证）→ Task 9（文档）→ Task 10（先写 failing test 证明缓存隔离缺陷 → 实施双层 matcher → 验证翻转）→ Task 11（手工测试）→ Task 12（code review）。Task 10 遵循与 Task 1–7 相同的先红后绿流程。

**Q: 每个 task 的粒度是否合适？**
A: 合适。每个 top-level task 对应一个独立模块或阶段。Task 10 虽然涉及 registerRouting() 重构和新测试，但聚焦于单一关注点（缓存隔离），粒度合理。

**Q: checkpoint 的设置是否覆盖了关键阶段？**
A: 是。每个 top-level task 的最后一个 sub-task 都是 checkpoint。Task 10 的 checkpoint 包含全量测试、静态分析和缓存隔离测试验证。

**Q: 手工测试是否覆盖了 requirements 中的关键用户场景？**
A: 是。Task 11 覆盖六个关键场景：编程式注入端到端（R1）、boot 后冻结（R2+R3）、YAML + 编程式混合（R1 AC3）、无 routing 配置 + 编程式路由（R1 AC4）、Closure controller 端到端（R5 AC1）、缓存清理后重新 boot（R5 AC5）。

**Q: 缓存隔离测试是否充分？**
A: 是。Task 10.2 覆盖了三类场景：(1) 缓存隔离缺陷的 failing tests（YAML+Closure 序列化错误、缓存文件包含编程式路由、同名路由优先级），(2) 缓存基线 preservation tests（缓存生效、缓存命中、YAML 文件变更后失效、PHP resource 文件变更后失效、debug:false 时持久不失效），(3) 纯编程式路由 + 缓存配置。Task 11.5–11.6 补充了手工测试覆盖。

**Q: phpunit.xml 更新时机是否合理？**
A: 是。Task 1.1 统一更新已有测试文件，Task 10.2 补充新增的 `RouteCacheIsolationTest.php`。

---

## Gatekeep Log

**校验时间**: 2025-07-18
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Tasks` section header，原文直接以 `# Implementation Plan` 开始后列出 task，缺少二级标题包裹
- [结构] 补充 `## Notes` section，包含 spec-execution.md 引用、commit 时机说明、failing-test-first 流程要点、按模块拆分策略、phpunit.xml 更新时机、边界条件提醒
- [结构] 补充 `## Socratic Review` section，覆盖 design 全覆盖、依赖顺序、task 粒度、checkpoint 覆盖、手工测试覆盖、phpunit.xml 时机
- [结构] 补充手工测试 top-level task（Task 10），覆盖编程式注入端到端、boot 后冻结、YAML + 编程式混合、无 routing 配置 + 编程式路由四个关键场景
- [结构] 原 Task 8（Checkpoint — Ensure all tests pass）从独立 top-level task 重构为 Task 8（Full verification）含 sub-task 和 checkpoint
- [格式] Task 1 和 Task 2 从纯 bullet point 列表重构为标准 sub-task 格式（`- [ ] 1.1`、`- [ ] 1.2` 等），每个 sub-task 有明确的可执行描述
- [格式] 所有 top-level task 补充 checkpoint 作为最后一个 sub-task，包含具体验证命令和 commit 动作
- [格式] Requirement 引用格式从 `_Requirements: 1.1, 1.2_` 统一为 `Ref: Requirement X, AC Y` 标准格式
- [内容] Task 6.2（phpunit.xml 更新）从 Task 6 提前到 Task 1.1，确保 task 1–2 编写的测试文件在创建后即可通过 `--testsuite routing` 运行
- [内容] Code Review task（Task 11）描述精简，移除展开的 review checklist（由 code-reviewer agent 自身定义）
- [目的] design.md CR Q4→A（按模块拆分）在 Notes section 中明确标注，确保执行者理解 task 3–5 的拆分策略来源

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 中的模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（Task 12）
- [x] 倒数第二个 top-level task 是手工测试（Task 11）
- [x] 自动化实现 task 排在手工测试和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–12）
- [x] sub-task 有层级序号（1.1、1.2 等）
- [x] 序号连续，无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款（`Ref: Requirement X, AC Y` 格式）
- [x] bugfix.md 中的每条 requirement（R1–R4，13 条 AC）至少被一个 task 引用
- [x] 引用的 requirement 编号和 AC 编号在 bugfix.md 中确实存在
- [x] top-level task 按依赖关系排序（failing tests → 按模块实现 → 集成测试 → 验证 → 全量验证 → 文档 → 手工测试 → code review）
- [x] 无循环依赖
- [x] 已对核心模块执行 graphify 依赖查询（MicroKernel、CacheableRouter、FrozenRouteCollection）
- [x] task 排序与 graphify 揭示的模块依赖一致（FrozenRouteCollection → CacheableRouter → MicroKernel）
- [x] checkpoint 不作为独立的 top-level task
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 描述中包含具体的验证命令和 commit 动作
- [x] task 顺序体现 failing-test-first 原则（Task 1–2 先写测试 → Task 3–5 实施修复 → Task 7 验证翻转）
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在（Task 10）
- [x] 手工测试覆盖了 requirements 中的关键用户场景
- [x] 手工测试场景描述具体，可执行
- [x] Code Review 是最后一个 top-level task（Task 12）
- [x] Code Review 描述为"委托给 code-reviewer sub-agent 执行"
- [x] Code Review 未展开 review checklist
- [x] `## Notes` section 存在
- [x] Notes 明确提到 spec-execution.md
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（failing-test-first 流程、按模块拆分、phpunit.xml 时机、边界条件）
- [x] `## Socratic Review` section 存在且覆盖充分
- [x] Design CR 回应：design.md Gatekeep Log 中 Q1→A（测试目录和 suite）、Q2→A（绕过 CacheableRouterProvider）、Q3→A（直接在 CacheableRouter 实现 freeze）、Q4→A（按模块拆分）均在 tasks 编排中体现
- [x] Design 全覆盖：tasks 覆盖了 design 中所有模块（MicroKernel、FrozenRouteCollection、CacheableRouter）和所有实现项
- [x] 可独立执行：每个 sub-task 描述自包含，执行者可凭 task 描述 + Ref 完成实现
- [x] 验收闭环：checkpoint + 手工测试 + code review 构成完整验收闭环
- [x] 执行路径无歧义：task 排序和依赖关系清晰