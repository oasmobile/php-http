# Bugfix Requirements Document

> 路由子系统编程式注入与 Boot 后冻结 — `.kiro/specs/hotfix-3.1.1/`

---

## Introduction

`oasis/http` v3.0 的路由子系统存在两个相关缺陷（ISS-3.0-L01、ISS-3.0-L02）：

1. **缺少编程式路由注入 API**：MicroKernel 已有 `addMiddleware()`、`addControllerInjectedArg()` 等 boot 前编程式注入方法，但路由没有对应的 `addRoute()` / `addRoutes()` API。路由只能通过 YAML 文件声明，编程式组装路由的场景没有合法入口。
2. **Boot 后路由修改静默失效**：`registerRouting()` 中 Request_Matcher 首次编译时缓存 Route_Collection。boot 完成后通过 `getRouter()->getRouteCollection()->add()` 添加路由不报错，但新路由对已编译的 Request_Matcher 不可见——调用方以为路由已注册，实际请求永远返回 404。

本修复将在 MicroKernel 上提供编程式路由注入 API，并在 boot 后冻结路由表以消除静默失败陷阱。

**不涉及的内容**：

- 不支持 boot 后动态添加或修改路由
- 不改变现有 YAML 路由加载机制
- 不引入 RouteLoader 接口或 compiler pass 级别的路由扩展点

**约束**：

- C-1: API 风格与现有 `addMiddleware()` 一致：boot 前调用，暂存到内部属性，boot 时消费
- C-2: 编程式路由优先于 YAML 路由匹配（编程式 matcher 排在 YAML cached matcher 之前），同名路由时编程式路由胜出
- C-3: boot 后冻结双层实施：MicroKernel 方法级 + Route_Collection 包装器级
- C-4: Frozen_Route_Collection 拦截 `add()`、`addCollection()`、`remove()` 等写操作，抛出 `LogicException`

---

## Glossary

- **MicroKernel**: `oasis/http` 的核心入口类，继承 Symfony HttpKernel，通过 Bootstrap Config 数组驱动初始化
- **Route_Collection**: Symfony Routing 组件的路由集合对象，持有所有已注册的 Route 实例
- **Route**: Symfony Routing 组件的单条路由定义，包含路径、默认值、约束等
- **Frozen_Route_Collection**: boot 后包装 Route_Collection 的只读代理，拦截所有写操作并抛出 `LogicException`
- **Request_Matcher**: 由 Route_Collection 编译生成的请求匹配器，首次编译后缓存，后续调用直接返回缓存结果
- **Bootstrap_Config**: MicroKernel 构造函数接受的关联数组，包含 `routing`、`middlewares`、`view_handlers` 等顶层 key

---

## Requirements

### Requirement 1: 编程式路由注入 API

**User Story:** 作为 MicroKernel 的调用方，我希望在 boot 前通过编程式 API 注入路由，以便不依赖 YAML 文件也能注册路由。

#### Acceptance Criteria

1. WHEN 调用方在 boot 前调用 `addRoute(string $name, Route $route)` THEN THE MicroKernel SHALL 暂存该 Route，在 `registerRouting()` 中、Request_Matcher 编译前合并到 Route_Collection
2. WHEN 调用方在 boot 前调用 `addRoutes(RouteCollection $routes)` THEN THE MicroKernel SHALL 暂存该 Route_Collection，在 `registerRouting()` 中、Request_Matcher 编译前批量合并到 Route_Collection
3. WHEN 编程式路由与 YAML 路由存在同名路由 THEN THE MicroKernel SHALL 优先匹配编程式路由（编程式 matcher 排在 YAML cached matcher 之前，先命中即返回）
4. WHEN Bootstrap_Config 中未配置 `routing` 但存在暂存的编程式路由 THEN THE MicroKernel SHALL 在 `registerRouting()` 中初始化空 Route_Collection 并合并编程式路由

### Requirement 2: Boot 后路由冻结（MicroKernel 层）

**User Story:** 作为 MicroKernel 的调用方，我希望 boot 后调用路由注入 API 时得到明确的错误，以便不会误以为路由已注册而实际静默失效。

#### Acceptance Criteria

1. WHEN 调用方在 boot 完成后调用 `addRoute()` THEN THE MicroKernel SHALL 抛出 `LogicException`，明确拒绝修改
2. WHEN 调用方在 boot 完成后调用 `addRoutes()` THEN THE MicroKernel SHALL 抛出 `LogicException`，明确拒绝修改

### Requirement 3: Boot 后路由冻结（Route_Collection 层）

**User Story:** 作为 MicroKernel 的调用方，我希望 boot 后通过 `getRouter()->getRouteCollection()` 获取的集合拒绝写操作，以便不会绕过 MicroKernel 层的冻结保护。

#### Acceptance Criteria

1. WHEN 调用方在 boot 完成后调用 `getRouter()->getRouteCollection()->add()` THEN THE Frozen_Route_Collection SHALL 抛出 `LogicException`，明确拒绝修改
2. WHEN 调用方在 boot 完成后调用 `getRouter()->getRouteCollection()->addCollection()` THEN THE Frozen_Route_Collection SHALL 抛出 `LogicException`，明确拒绝修改
3. WHEN 调用方在 boot 完成后调用 `getRouter()->getRouteCollection()->remove()` THEN THE Frozen_Route_Collection SHALL 抛出 `LogicException`，明确拒绝修改
4. WHEN 调用方在 boot 完成后对 Route_Collection 执行只读操作（如 `get()`、`all()`、`count()`、`getIterator()`） THEN THE Frozen_Route_Collection SHALL 正常返回结果，不抛异常

### Requirement 4: 回归防护

**User Story:** 作为 MicroKernel 的调用方，我希望现有的 YAML 路由加载和其他 boot 前注入机制不受本次修复影响，以便升级到 hotfix 版本后无需修改现有代码。

#### Acceptance Criteria

1. WHEN 路由仅通过 YAML 文件声明且未使用编程式注入 THEN THE MicroKernel SHALL CONTINUE TO 正常加载 YAML 路由并匹配请求
2. WHEN Bootstrap_Config 中未配置 `routing` 且无暂存的编程式路由 THEN THE MicroKernel SHALL CONTINUE TO 跳过路由注册，`getRouter()` 返回 `null`
3. WHEN boot 前调用 `addMiddleware()` 注入中间件 THEN THE MicroKernel SHALL CONTINUE TO 正常注册中间件，行为不受路由 API 变更影响
4. WHEN 路由缓存已启用 THEN THE MicroKernel SHALL CONTINUE TO 正常使用缓存的 Request_Matcher，YAML 路由的缓存行为不受编程式路由影响

### Requirement 5: 编程式路由与 YAML 路由缓存隔离

**User Story:** 作为 MicroKernel 的调用方，我希望在 YAML 路由启用缓存的同时使用 Closure 作为编程式路由的 controller，以便编程式路由不受序列化限制。

#### Acceptance Criteria

1. WHEN YAML 路由缓存已启用且调用方通过 `addRoute()` 注入了含 Closure controller 的 Route THEN THE MicroKernel SHALL 正常 boot 且该路由可匹配，不因 Closure 无法序列化而报错
2. WHEN YAML 路由缓存已启用且存在编程式路由 THEN THE MicroKernel SHALL 将 YAML 路由通过 CacheableRouter 编译缓存的 matcher 匹配，编程式路由通过独立的内存 matcher 匹配，两者通过 GroupUrlMatcher 串联
3. WHEN 请求路径同时匹配编程式路由和 YAML 路由（同名路由） THEN THE MicroKernel SHALL 优先匹配编程式路由（编程式 matcher 排在 YAML cached matcher 之前）
4. WHEN YAML 路由缓存已启用且存在编程式路由 THEN THE 路由缓存文件 SHALL 仅包含 YAML 路由，不包含编程式路由（编程式路由不参与缓存编译）
5. WHEN YAML 路由缓存已启用 THEN THE 缓存 SHALL 在 `debug: true` 时根据 YAML 文件变更自动失效，在 `debug: false` 时持久有效直到手动清除

---

## Bug Condition

### Bug Condition Function

```pascal
FUNCTION isBugCondition(X)
  INPUT: X of type RouteInjectionAttempt
  OUTPUT: boolean

  // C1: 需要编程式注入路由但无 API 可用
  // C2: boot 后对 Route_Collection 执行写操作（add / addCollection / remove）
  RETURN X.needsProgrammaticRouteInjection
      OR (X.isAfterBoot AND X.isWriteOperation)
END FUNCTION
```

### Fix Checking Property

```pascal
// Property: Fix Checking — 编程式路由注入
FOR ALL X WHERE X.needsProgrammaticRouteInjection DO
  kernel ← MicroKernel'(config)
  kernel.addRoute(X.name, X.route)   // boot 前调用
  kernel.boot()
  result ← kernel.getRequestMatcher().matchRequest(X.request)
  ASSERT result._controller = X.route.getDefault('_controller')
END FOR

// Property: Fix Checking — Boot 后写操作冻结（MicroKernel 层）
FOR ALL X WHERE X.isAfterBoot AND X.isWriteOperation DO
  kernel ← MicroKernel'(config)
  kernel.boot()
  ASSERT kernel.addRoute(X.name, X.route) THROWS LogicException
  ASSERT kernel.addRoutes(X.routes) THROWS LogicException
END FOR

// Property: Fix Checking — Boot 后写操作冻结（Route_Collection 层）
FOR ALL X WHERE X.isAfterBoot AND X.isWriteOperation DO
  kernel ← MicroKernel'(config)
  kernel.boot()
  collection ← kernel.getRouter().getRouteCollection()
  ASSERT collection.add(X.name, X.route) THROWS LogicException
  ASSERT collection.addCollection(X.routes) THROWS LogicException
  ASSERT collection.remove(X.name) THROWS LogicException
END FOR
```

### Preservation Checking Property

```pascal
// Property: Preservation Checking — 非 bug 条件下行为不变
FOR ALL X WHERE NOT isBugCondition(X) DO
  ASSERT F(X) = F'(X)
END FOR
```

即：对于仅使用 YAML 路由、不涉及编程式注入、不在 boot 后执行写操作的场景，修复前后行为完全一致。

---

## Socratic Review

**Q: 编程式路由注入 API 是否应该支持 RouteLoader 或 callable 形式？**
A: 不需要。goal.md 的 Q1 已明确选择"直接在 MicroKernel 上加 `addRoute()` 方法，与 `addMiddleware()` 等一致"。Non-Goals 中也明确排除了 RouteLoader 接口。`addRoute()` + `addRoutes()` 已覆盖单条和批量注入场景。

**Q: Frozen_Route_Collection 是否需要拦截 `setHost()`、`setCondition()` 等单条 Route 的修改方法？**
A: 不需要。Frozen_Route_Collection 的冻结粒度是集合级写操作（`add()`、`addCollection()`、`remove()`），这些是导致静默失效的操作。单条 Route 对象的属性修改不影响已编译的 Request_Matcher（Route 对象在编译时已被读取），且拦截粒度过细会增加不必要的复杂度。

**Q: 如果 Bootstrap_Config 中未配置 `routing`，但调用方在 boot 前调用了 `addRoute()`，应如何处理？**
A: 这是一个边界条件。当前 requirements 中 R4 AC2 规定未配置 `routing` 时跳过路由注册、`getRouter()` 返回 `null`。如果调用方在此场景下调用了 `addRoute()`，编程式路由无处合并。合理的行为是：`addRoute()` 本身不检查 routing 配置（boot 前无法确定最终配置），但 `registerRouting()` 在发现有暂存的编程式路由但无 routing 配置时，应仍然初始化一个空的 Route_Collection 并合并编程式路由。这一行为需要在 design 阶段明确。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: R1（编程式注入）和 R2（MicroKernel 层冻结）关注同一 API 的不同生命周期阶段，互补而非重叠。R2 和 R3 分别覆盖 MicroKernel 层和 Route_Collection 层的冻结，对应 goal.md Q2 的"双层冻结"决策。R4（回归防护）确保 R1–R3 的变更不影响现有行为。无矛盾。

**Q: 与 goal.md 的 scope 是否一致？**
A: 完全一致。goal.md 定义的目标（编程式注入 API + boot 后冻结）、Non-Goals（不支持动态路由、不改 YAML 机制、不引入 RouteLoader）、约束（C-1 至 C-4）和 Clarification 决策（Q1–Q4）均已体现在 Requirements 中。

---

## Gatekeep Log

**校验时间**: 2025-07-18
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Glossary` section，定义 6 个领域术语：MicroKernel、Route_Collection、Route、Frozen_Route_Collection、Request_Matcher、Bootstrap_Config
- [结构] 将 `## Bug Analysis` 重构为 `## Requirements`，拆分为 4 条标准 Requirement（编程式注入 API / MicroKernel 层冻结 / Route_Collection 层冻结 / 回归防护），每条包含 User Story 和 AC
- [结构] 补充 `## Socratic Review` section，覆盖 API 形态、冻结粒度、边界条件、requirement 关系、scope 一致性
- [结构] 补充各 section 之间的 `---` 分隔符
- [语体] AC 统一为 `THE <Subject> SHALL` / `WHEN...THEN THE <Subject> SHALL` 格式，Subject 使用 Glossary 定义的术语
- [内容] Introduction 补充"不涉及的内容"（Non-Goals）和"约束"（C-1 至 C-4），来源于 goal.md
- [内容] 一级标题下方补充定位说明行，标注所属 spec 目录
- [内容] R3 补充 AC4（只读操作不受冻结影响），原文 3.4 条内容保留
- [内容] Socratic Review 中识别一个边界条件：未配置 `routing` 但调用了 `addRoute()` 的场景，标记为 design 阶段待明确

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 bugfix 范围
- [x] Introduction 明确了不涉及的内容（Non-Goals）和约束（C-1 至 C-4）
- [x] Glossary 存在且包含 6 个术语
- [x] Requirements section 存在且包含 4 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 术语在 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 术语格式正确（`- **Term**: 定义`）
- [x] 每条 requirement 包含 User Story 和 Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 使用 SHALL / WHEN-THEN 语体
- [x] AC 编号连续无跳号
- [x] Socratic Review 覆盖充分（API 形态、冻结粒度、边界条件、requirement 关系、scope 一致性）
- [x] Goal CR 决策已体现（Q1→addRoute API 风格、Q2→双层冻结、Q3→addRoutes 批量、Q4→合并顺序）
- [x] Goal 清晰度达标
- [x] Non-goal / Scope 边界明确
- [x] 完成标准充分（4 条 requirement 的 AC 覆盖注入、冻结、回归）
- [x] 可 design 性达标（Socratic Review 已识别需 design 阶段明确的边界条件）
- [○] Bug Condition section 保留（bugfix spec 特有的形式化验证内容，非标准 requirements 结构但有价值）

### Clarification Round

**状态**: 已完成

**Q1:** R1 AC1–AC2 要求编程式路由在 `registerRouting()` 中合并到 Route_Collection。如果 Bootstrap_Config 中未配置 `routing`（即无 YAML 路由源），但调用方在 boot 前调用了 `addRoute()`，系统应如何处理？
- A) `registerRouting()` 检测到有暂存的编程式路由时，即使无 `routing` 配置也初始化一个空的 Route_Collection 并合并编程式路由
- B) `addRoute()` 在调用时检查 `routing` 配置是否存在，未配置则抛出 `LogicException`
- C) 忽略此场景——未配置 `routing` 时编程式路由静默丢弃（与当前 YAML-only 行为一致）
- D) 其他（请说明）

**A:** A — `registerRouting()` 检测到有暂存的编程式路由时，即使无 `routing` 配置也初始化空 Route_Collection 并合并编程式路由

**Q2:** Frozen_Route_Collection 作为 Route_Collection 的只读代理，其实现方式会影响 design 选型。`getRouter()->getRouteCollection()` 返回的对象类型会从 Symfony 原生 `RouteCollection` 变为 `FrozenRouteCollection`。如果下游代码对返回类型有 type hint（如 `RouteCollection $routes`），可能产生类型不兼容。design 阶段应如何处理？
- A) Frozen_Route_Collection 继承 Symfony `RouteCollection`，override 写方法抛异常——类型兼容但违反 LSP
- B) Frozen_Route_Collection 实现与 `RouteCollection` 相同的接口（如 `\IteratorAggregate`、`\Countable`），不继承——类型不兼容但语义正确
- C) 不引入新类型，直接在 `registerRouting()` 结束后通过 reflection 或 decorator 拦截写操作
- D) 其他（请说明）

**A:** A — 继承 Symfony `RouteCollection`，override 写方法抛异常。Symfony `Router::getRouteCollection()` 返回类型声明为 `RouteCollection`，不继承则类型不兼容。LSP 违反在此场景下是合理的 trade-off。

**Q3:** R1 AC3 规定编程式路由在 YAML 之后合并（后入覆盖先入）。如果调用方多次调用 `addRoute()` 注入同名路由，多次注入之间的覆盖顺序应如何定义？
- A) 按调用顺序，后调用覆盖先调用（与 Route_Collection 默认行为一致）
- B) 同名重复注入时抛出异常，要求调用方保证唯一性
- C) 不做特殊处理，完全委托给 Symfony Route_Collection 的默认行为
- D) 其他（请说明）

**A:** C — 不做特殊处理，完全委托给 Symfony Route_Collection 的默认行为
