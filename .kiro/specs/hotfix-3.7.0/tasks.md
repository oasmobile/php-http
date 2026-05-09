# Implementation Plan

## Tasks

- [x] 1. 编写 Bug Condition 探索测试（红色测试，修复前必须失败）

  - [x] 1.1 编写 Security 注入 API 缺失的探索测试
    - **Property 1: Bug Condition** — Security Config Injection API Missing
    - **关键**：此测试必须在未修复代码上失败——失败即证明 bug 存在
    - **不要**在测试失败时尝试修复测试或代码
    - **说明**：此测试编码了期望行为——修复实现后测试通过即验证期望行为已满足
    - **目标**：Surface counterexamples，证明 bug 存在
    - **Scoped PBT 方法**：将 property 限定到具体失败场景——在 MicroKernel 上调用 `addSecurityConfig()`、`addFirewall()`、`addAccessRule()`、`addPolicy()`、`addRoleHierarchy()`、`getSecurityConfig()`
    - 测试文件：`tests/PBT/Security/SecurityInjectionBugConditionTest.php`
    - 测试 `$kernel->addSecurityConfig(['firewalls' => [...]])` 触发 Fatal Error（方法不存在）
    - 测试 `$kernel->addFirewall('api', [...])` 触发 Fatal Error（方法不存在）
    - 测试 `$kernel->getSecurityConfig()` 触发 Fatal Error（方法不存在）
    - 测试即使 Constructor_Config 为空，ServiceProvider 也无法注入 security config——带 `allowed-roles` 的路由返回 403
    - 在未修复代码上运行测试
    - **预期结果**：测试失败（Fatal Error: Call to undefined method）——证明 bug 存在
    - 记录发现的 counterexamples：所有注入 API 调用均产生 Fatal Error
    - Ref: Expected Behavior 1, 2, 3, 4

  - [x] 1.2 Checkpoint：确认测试已编写、运行并记录失败结果，commit
    - 运行 `./vendor/bin/phpunit tests/PBT/Security/SecurityInjectionBugConditionTest.php`
    - 确认测试失败（Fatal Error），记录失败输出
    - commit message: `test: add bug condition exploration test for security injection API`

- [x] 2. 编写 Preservation 属性测试（修复前必须通过）

  - [x] 2.1 编写 Constructor-Only Security Config 不变性属性测试
    - **Property 2: Preservation** — Constructor-Only Security Config Unchanged
    - **重要**：遵循 observation-first 方法论
    - 测试文件：`tests/PBT/Security/SecurityPreservationPropertyTest.php`
    - 在未修复代码上观察：`registerSecurity()` 仅使用 Constructor_Config → SimpleSecurityProvider 正确初始化
    - 在未修复代码上观察：`registerSecurity()` 使用 empty/null security config → early return，不初始化 provider
    - 在未修复代码上观察：`addRoute()`/`addRoutes()` 路由注入独立于 security 工作
    - 编写 property-based tests（Eris generators）：
      - 对所有有效 Constructor_Config（随机 firewalls、access_rules、policies、role_hierarchy 组合），`registerSecurity()` 产生与修复前完全相同的初始化结果
      - 对所有 empty/null security config，`registerSecurity()` early return 无副作用
      - 对所有路由注入调用，路由行为不受 security 变更影响
    - 在未修复代码上验证测试通过
    - **预期结果**：测试通过（确认需要保持的基线行为）
    - Ref: Unchanged Behavior 1, 2, 3, 4, 5

  - [x] 2.2 Checkpoint：确认 preservation 测试已编写、运行并在未修复代码上通过，commit
    - 运行 `./vendor/bin/phpunit tests/PBT/Security/SecurityPreservationPropertyTest.php`
    - 确认所有测试通过
    - commit message: `test: add preservation property tests for constructor-only security config`

- [x] 3. 创建 SecurityTrait 核心注入 API 和合并逻辑

  - [x] 3.1 创建 `src/Kernel/SecurityTrait.php` 最小可用版本
    - 实现 `addSecurityConfig(array $config, bool $allowOverwrite = false): void`
    - 实现 `registerSecurity(): void`（合并 Constructor_Config + Pending_Queue）
    - 实现 `$pendingSecurityConfigs` 属性
    - 实现 boot 后保护（`$this->booted` 检查 → LogicException）
    - 实现顶层 key 校验（仅允许 `firewalls`、`access_rules`、`policies`、`role_hierarchy`）
    - 实现 `mergeSecurityConfigs()` 私有方法，含冲突检测逻辑
    - 实现 `validateSecurityConfigConflicts()` 用于注入时 fail-fast 冲突检测
    - Ref: Expected Behavior 1, 3, 4, 5, 6, 7, 10

  - [x] 3.2 添加细粒度注入 API 到 SecurityTrait
    - 实现 `addFirewall(string $name, array $config, bool $allowOverwrite = false): void`
    - 实现 `addAccessRule(array $rule): void`
    - 实现 `addPolicy(string $name, mixed $config, bool $allowOverwrite = false): void`
    - 实现 `addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false): void`
    - 实现 `getSecurityConfig(): array`（只读查询，返回 Constructor_Config + Pending_Queue 合并视图）
    - 所有方法：boot 后保护 → LogicException
    - 冲突检测：同名 firewall/policy/role → 抛异常（除非 `$allowOverwrite = true`）
    - access_rules：始终按注册顺序追加
    - Ref: Expected Behavior 2, 5, 7, 8, 9, 11

  - [x] 3.3 将 SecurityTrait 集成到 MicroKernel
    - 在 `src/MicroKernel.php` 中添加 `use SecurityTrait`
    - 在 MicroKernel 中添加 `protected array $pendingSecurityConfigs = []` 属性
    - 从 `src/Kernel/ServicesTrait.php` 中移除 `registerSecurity()` 方法
    - 验证无重复方法定义（trait 方法替换 ServicesTrait 方法）
    - Ref: Expected Behavior 1, 2, 3

  - [x] 3.4 验证 Bug Condition 探索测试现在通过
    - **Property 1: Expected Behavior** — Security Config Injection API Works
    - **重要**：重新运行 task 1 中的同一测试——不要编写新测试
    - task 1 的测试编码了期望行为，测试通过即确认期望行为已满足
    - 运行 `./vendor/bin/phpunit tests/PBT/Security/SecurityInjectionBugConditionTest.php`
    - **预期结果**：测试通过（确认 bug 已修复）
    - Ref: Expected Behavior 1, 2, 3, 4

  - [x] 3.5 验证 Preservation 测试仍然通过
    - **Property 2: Preservation** — Constructor-Only Security Config Unchanged
    - **重要**：重新运行 task 2 中的同一测试——不要编写新测试
    - 运行 `./vendor/bin/phpunit tests/PBT/Security/SecurityPreservationPropertyTest.php`
    - **预期结果**：测试通过（确认无回归）

  - [x] 3.6 Checkpoint：确认 SecurityTrait 实现完成且所有测试通过，commit
    - 运行 `./vendor/bin/phpunit` 全量测试
    - 运行 `./vendor/bin/phpstan analyse` 静态分析
    - 确认无失败、无回归
    - 运行 `./vendor/bin/phpunit --coverage-text`，确认 UT 行覆盖率 ≥ 95%；如未达到，补充单元测试直到满足
    - commit message: `feat: add SecurityTrait with pre-boot security config injection API`

- [x] 4. RoutingTrait 对齐改造（Fail-Fast + $allowOverwrite）

  - [x] 4.1 编写 RoutingTrait overwrite 行为的失败测试
    - 测试文件：`tests/Routing/MicroKernelRouteOverwriteTest.php`
    - 测试：`addRoute()` 使用重复路由名 + `$allowOverwrite = true`（默认）→ 静默成功
    - 测试：`addRoute()` 使用重复路由名 + `$allowOverwrite = false` → 抛 LogicException
    - 测试：`addRoutes()` 使用重复路由名 + `$allowOverwrite = true`（默认）→ 成功
    - 测试：`addRoutes()` 使用重复路由名 + `$allowOverwrite = false` → 抛 LogicException
    - 测试：现有调用方不传 `$allowOverwrite` → 向后兼容（默认 `true`）
    - 运行测试 — **预期结果**：测试失败（参数尚不存在）
    - Ref: Design CR Q2 — RoutingTrait 对齐

  - [x] 4.2 在 RoutingTrait 中实现 `$allowOverwrite` 参数
    - 修改 `addRoute(string $name, Route $route, bool $allowOverwrite = true): void`
    - 修改 `addRoutes(RouteCollection $routes, bool $allowOverwrite = true): void`
    - 添加 fail-fast 重复检测：检查 `$this->pendingRoutes` 中是否有同名路由
    - `$allowOverwrite = false` 且发现重复 → 抛 LogicException
    - `$allowOverwrite = true`（默认）→ 静默覆盖（向后兼容）
    - Ref: Design CR Q2 — RoutingTrait 对齐

  - [x] 4.3 Checkpoint：确认 RoutingTrait overwrite 测试通过且无回归，commit
    - 重新运行 4.1 的测试，确认通过
    - 运行现有路由测试套件：`./vendor/bin/phpunit --testsuite routing`
    - 确认现有路由测试无回归
    - 运行 `./vendor/bin/phpunit --coverage-text`，确认 UT 行覆盖率 ≥ 95%；如未达到，补充单元测试直到满足
    - commit message: `feat: add $allowOverwrite parameter to RoutingTrait for fail-fast conflict detection`

- [x] 5. PBT 文件结构重组与额外属性测试

  - [x] 5.1 重组 `tests/PBT/Security/` 目录结构
    - 探索测试（task 1）已在 `tests/PBT/Security/SecurityInjectionBugConditionTest.php`
    - Preservation 测试（task 2）已在 `tests/PBT/Security/SecurityPreservationPropertyTest.php`
    - 移动现有 `tests/PBT/SecurityConfigPropertyTest.php` → `tests/PBT/Security/SecurityConfigPropertyTest.php`
    - 更新 phpunit.xml testsuite `pbt` 目录配置以包含 `tests/PBT/Security/`
    - 验证所有 PBT 测试仍可发现且通过
    - Ref: Design CR Q3 — PBT 文件结构重组

  - [x] 5.2 编写 SecurityTrait 合并逻辑属性测试
    - 测试文件：`tests/PBT/Security/SecurityMergePropertyTest.php`
    - 先编写测试，再验证通过（实现已在 Phase 2 完成）：
    - Property：对所有随机 security config 片段，`mergeSecurityConfigs()` 产生正确的合并输出
    - Property：对所有随机注册顺序，access_rules 保持插入顺序
    - Property：对所有含重复 firewall 名的随机 config，抛出异常（除非 overwrite）
    - Property：对所有含重复 policy 名的随机 config，抛出异常（除非 overwrite）
    - Property：对所有含重复 role_hierarchy 角色的随机 config，抛出异常（除非 overwrite）
    - Property：对所有 `$allowOverwrite = true` 的随机 config，firewalls/policies/roles 采用 last-write-wins
    - 运行测试 — **预期结果**：测试通过（实现已存在）
    - Ref: Expected Behavior 5, 6, 7, 9, 11

  - [x] 5.3 Checkpoint：确认 PBT 重组完成且所有测试通过，commit
    - 运行 `./vendor/bin/phpunit --testsuite pbt`
    - 确认所有 PBT 测试通过
    - commit message: `refactor: reorganize PBT security tests into tests/PBT/Security/`

- [ ] 6. 集成测试

  - [x] 6.1 编写完整 ServiceProvider 流程的集成测试
    - 测试文件：`tests/Integration/SecurityInjectionIntegrationTest.php`
    - 测试：ServiceProvider 在 `register()` 中通过 `addSecurityConfig()` 注册 security config → boot → 带 `allowed-roles` 的路由返回 200（非 403）
    - 测试：多个 ServiceProvider 注入不同 firewalls → boot 时全部正确合并
    - 测试：ServiceProvider 使用 `getSecurityConfig()` 做条件注入
    - 测试：ServiceProvider 在 boot 后调用注入 API → LogicException
    - 运行测试 — 验证通过（实现已在 Phase 2 完成）
    - Ref: Expected Behavior 1, 2, 3, 4, 8

  - [x] 6.2 编写 RoutingTrait + SecurityTrait 共存集成测试
    - 测试文件：`tests/Integration/SecurityRoutingCoexistenceTest.php`
    - 测试：ServiceProvider 同时注入路由和 security config → boot 后两者均正确工作
    - 测试：路由注入 `$allowOverwrite = false` + security 注入 `$allowOverwrite = false` → 两者独立执行冲突检测
    - Ref: Unchanged Behavior 5, Design CR Q2

  - [-] 6.3 Checkpoint：确认集成测试通过且全量测试无回归，commit
    - 运行 `./vendor/bin/phpunit --testsuite integration`
    - 运行 `./vendor/bin/phpunit` 全量测试
    - 运行 `./vendor/bin/phpstan analyse`
    - 确认无失败、无回归
    - 运行 `./vendor/bin/phpunit --coverage-text`，确认 UT 行覆盖率 ≥ 95%（目标 96%+）；如未达到，补充测试直到满足
    - commit message: `test: add integration tests for security injection and routing coexistence`

- [ ] 7. 手工测试

  - [ ] 7.1 验证 ServiceProvider 注入 security config 的完整流程
    - 创建一个测试用 ServiceProvider，在 `register()` 中调用 `addSecurityConfig()` 注入 firewall 配置
    - boot kernel，访问带 `allowed-roles` 的路由
    - 确认返回 200（非 403），确认 firewall listener 已注册
    - 确认 `tokenStorage` 中有 token

  - [ ] 7.2 验证冲突检测的用户体验
    - 创建两个 ServiceProvider 注入同名 firewall（`$allowOverwrite = false`）
    - 确认在 register 阶段即抛出 LogicException，错误信息清晰
    - 确认使用 `$allowOverwrite = true` 时静默覆盖

  - [ ] 7.3 验证 boot 后调用保护
    - boot kernel 后调用 `addSecurityConfig()`
    - 确认抛出 LogicException，错误信息明确指出"cannot add after boot"

  - [ ] 7.4 验证向后兼容性
    - 使用仅通过构造函数传入 security config 的现有用法
    - 确认行为与修复前完全一致，无任何变化

- [ ] 8. 文档收敛

  - [ ] 8.1 更新 state 文档
    - 更新 `docs/state/architecture.md`：补充 SecurityTrait 描述、`Kernel/` 目录树新增 `SecurityTrait.php`、boot 前编程式注入描述补充 security 注入 API
    - 更新 `docs/state/` 中其他受影响文档（如有）
    - 确保 state 反映修复后的系统现状

  - [ ] 8.2 更新 manual 文档
    - 在 `docs/manual/` 中补充 SecurityTrait 使用说明：如何在 ServiceProvider 中使用 `addSecurityConfig()`、细粒度 API、`getSecurityConfig()` 查询
    - 补充 `$allowOverwrite` 参数说明（security 默认 false，routing 默认 true）
    - 补充 boot 后调用限制说明

  - [ ] 8.3 编写 migration guide
    - 在 `docs/changes/` 对应版本目录下编写迁移指南
    - 说明新增 API（addSecurityConfig、addFirewall、addAccessRule、addPolicy、addRoleHierarchy、getSecurityConfig）
    - 说明 RoutingTrait 接口变更（新增 `$allowOverwrite` 参数，默认 true 向后兼容）
    - 说明从 workaround（构造函数前手动获取 config）迁移到新 API 的步骤
    - 说明冲突检测行为和 `$allowOverwrite` 的使用场景

  - [ ] 8.4 Checkpoint：确认文档收敛完成，commit
    - 检查 state、manual、migration-guide 三类文档均已更新
    - commit message: `*(docs) by Kiro: 收敛 state/manual/migration-guide 文档`

- [ ] 9. Code Review
  - 委托给 code-reviewer sub-agent 执行

---

## Notes

- 执行时须遵循 `spec-execution.md` 规范
- commit 随各 task 的 checkpoint sub-task 一起执行，不在中间步骤单独 commit
- Design CR Q1 决策：先做最小可用版本（`addSecurityConfig` + `registerSecurity` 合并 + boot 后保护），验证通过后再添加细粒度 API——已体现在 task 3.1 → 3.2 的拆分中
- Design CR Q2 决策：注入时 fail-fast 冲突检测 + `$allowOverwrite` 参数，RoutingTrait 同步对齐——已体现在 task 4
- SecurityTrait 的 `$pendingSecurityConfigs` 属性定义在 MicroKernel 中（与 `$pendingRoutes` 模式一致），trait 通过 `$this->pendingSecurityConfigs` 访问
- `registerSecurity()` 从 `ServicesTrait` 迁移到 `SecurityTrait`，调用点（`boot()` 中）不变
- 所有注入 API 和 `getSecurityConfig()` 在 boot 后调用均抛 LogicException
- UT 覆盖率要求：行覆盖率 ≥ 95%（目标 96%+），在 task 3.6、4.3、6.3 checkpoint 中验证；未达标时须补充单元测试

---

## Socratic Review

### tasks 是否完整覆盖了 design 中的所有实现项？

是。Design 中的 6 项变更（SecurityTrait 新建、MicroKernel 属性、MicroKernel use trait、ServicesTrait 移除方法、冲突检测逻辑、RoutingTrait 对齐）均有对应 task。Testing Strategy 中的三类测试（Exploratory、Fix Checking、Preservation Checking）均已编排。

### task 之间的依赖顺序是否正确？

正确。Task 1-2（测试先行）→ Task 3（实现）→ Task 4（RoutingTrait 对齐，独立于 SecurityTrait 但逻辑上在其后）→ Task 5（PBT 重组，依赖 task 1-2 的测试文件已存在）→ Task 6（集成测试，依赖 task 3-4 的实现）→ Task 7（手工测试）→ Task 8（Code Review）。

### 每个 task 的粒度是否合适？

合适。Task 3 拆分为 6 个 sub-task（最小可用版本 → 细粒度 API → 集成 → 验证 × 2 → checkpoint），符合 Design CR Q1 的"先最小可用版本再扩展"决策。其他 task 粒度适中，每个 sub-task 可在独立 session 中执行。

### checkpoint 的设置是否覆盖了关键阶段？

是。每个 top-level task 末尾均有 checkpoint sub-task，包含具体验证命令和 commit 动作。

### 手工测试是否覆盖了 requirements 中的关键用户场景？

是。手工测试覆盖了：ServiceProvider 注入流程（核心场景）、冲突检测体验、boot 后保护、向后兼容性。对应 bugfix.md 中的 Expected Behavior 1-4 和 Unchanged Behavior 1。

### graphify 跨模块依赖是否与 task 排序一致？

一致。graphify 显示 MicroKernel 依赖 SimpleSecurityProvider 和 CacheableRouterProvider。SecurityTrait 是新建模块无现有依赖者，RoutingTrait 修改不影响 SecurityTrait。task 排序无隐含的跨模块依赖遗漏。

---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [语体] 全文从英文改为中文，与 bugfix.md 和 design.md 保持语言一致
- [结构] 将 `## Phase 1/2/3/4/5` 结构改为统一的 `## Tasks` section
- [结构] 补充手工测试 top-level task（task 7），覆盖关键用户场景
- [结构] 补充 Code Review top-level task（task 8）
- [结构] 补充 `## Notes` section，包含 spec-execution 引用、commit 时机说明和 Design CR 决策要点
- [结构] 补充 `## Socratic Review` section
- [格式] 统一 sub-task 序号格式为 `N.M`（如 1.1, 1.2, 3.1, 3.2...）
- [格式] 将独立的 Checkpoint top-level task（原 task 7）拆解为每个 top-level task 末尾的 checkpoint sub-task
- [内容] Requirement 引用格式从 `_Requirements: Expected Behavior X_` 统一为 `Ref: Expected Behavior X`
- [内容] 为 task 1 和 task 2 补充 sub-task 分解（实现 + checkpoint）
- [目的] Design CR Q1（最小可用版本优先）已在 task 3.1 → 3.2 拆分中体现
- [目的] Design CR Q2（fail-fast + $allowOverwrite + RoutingTrait 对齐）已在 task 4 中体现
- [目的] Design CR Q3（PBT 文件结构重组）已在 task 5.1 中体现

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review
- [x] 倒数第二个 top-level task 是文档收敛
- [x] 倒数第三个 top-level task 是手工测试
- [x] 自动化实现 task 排在手工测试、文档收敛和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1-9）
- [x] sub-task 有层级序号（N.M 格式）
- [x] 序号连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements 中的每条 Expected Behavior 和 Unchanged Behavior 至少被一个 task 引用
- [x] 引用的 requirement 编号在 bugfix.md 中确实存在
- [x] top-level task 按依赖关系排序
- [x] 无循环依赖
- [x] 已对核心模块执行 graphify 依赖查询
- [x] task 排序与 graphify 揭示的模块依赖一致
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 包含具体验证命令和 commit 动作
- [x] 新增行为的实现遵循 test-first 编排（task 1-2 先于 task 3）
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在
- [x] 手工测试覆盖关键用户场景
- [x] 手工测试场景描述具体可执行
- [x] 文档收敛 top-level task 存在（state、manual、migration-guide）
- [x] Code Review 是最后一个 top-level task
- [x] Code Review 描述为委托给 code-reviewer sub-agent 执行
- [x] `## Notes` section 存在
- [x] Notes 明确提到 spec-execution 规范
- [x] Notes 明确说明 commit 随 checkpoint 执行
- [x] Notes 包含当前 spec 特有的执行要点（Design CR 决策）
- [x] Socratic Review 存在且覆盖充分
- [x] Design CR 决策已在 tasks 编排中体现
- [x] tasks 覆盖 design 中所有模块、接口和实现项
- [x] 每个 sub-task 描述自包含，可独立执行
- [x] checkpoint + 手工测试 + 文档收敛 + code review 构成完整验收闭环
- [x] 执行路径无歧义
- [x] `## Task Dependency Graph` section 存在
- [x] TDG 使用 `{"waves": [...]}` JSON 格式
- [x] 每个 wave 有 `id`（从 0 开始连续）和 `tasks` 数组
- [x] TDG 中的 task ID 与 Tasks section 中的 sub-task 编号一致
- [x] wave 顺序反映正确的依赖关系

---

## Task Dependency Graph

```json
{"waves": [
  { "id": 0, "tasks": ["1.1"] },
  { "id": 1, "tasks": ["1.2", "2.1"] },
  { "id": 2, "tasks": ["2.2"] },
  { "id": 3, "tasks": ["3.1"] },
  { "id": 4, "tasks": ["3.2"] },
  { "id": 5, "tasks": ["3.3"] },
  { "id": 6, "tasks": ["3.4", "3.5"] },
  { "id": 7, "tasks": ["3.6"] },
  { "id": 8, "tasks": ["4.1", "5.1"] },
  { "id": 9, "tasks": ["4.2", "5.2"] },
  { "id": 10, "tasks": ["4.3", "5.3"] },
  { "id": 11, "tasks": ["6.1", "6.2"] },
  { "id": 12, "tasks": ["6.3"] },
  { "id": 13, "tasks": ["7.1", "7.2", "7.3", "7.4"] },
  { "id": 14, "tasks": ["8.1", "8.2", "8.3"] },
  { "id": 15, "tasks": ["8.4"] },
  { "id": 16, "tasks": ["9"] }
]}
```
