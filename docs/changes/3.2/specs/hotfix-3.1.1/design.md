# Programmatic Route Injection & Post-Boot Freeze — Bugfix Design

> 路由子系统编程式注入与 boot 后冻结的设计文档 — `.kiro/specs/hotfix-3.1.1/`

---

## Overview

`oasis/http` v3.0 的路由子系统存在两个缺陷：

1. **无编程式路由注入 API**（ISS-3.0-L01）：MicroKernel 已有 `addMiddleware()` 等 boot 前注入方法，但路由缺少对应的 `addRoute()` / `addRoutes()`，编程式组装路由无合法入口。
2. **Boot 后路由修改静默失效**（ISS-3.0-L02）：`registerRouting()` 中 `getMatcher()` 首次调用时编译并缓存 RouteCollection，此后通过 `getRouteCollection()->add()` 添加的路由对 matcher 不可见，但调用不报错。

修复策略：

- 在 MicroKernel 上新增 `addRoute()` / `addRoutes()` 方法，遵循与 `addMiddleware()` 一致的"boot 前暂存 → boot 时消费"模式
- 在 `registerRouting()` 中、`buildRequestMatcher()` 调用前，将暂存的编程式路由合并到 RouteCollection
- boot 完成后双层冻结：MicroKernel 方法级检查 + `FrozenRouteCollection` 包装器拦截写操作

---

## Glossary

- **MicroKernel**: `oasis/http` 的核心入口类（`src/MicroKernel.php`），继承 Symfony HttpKernel，通过 Bootstrap Config 数组驱动初始化
- **Route_Collection**: Symfony `RouteCollection`，持有所有已注册的 Route 实例
- **Route**: Symfony `Route`，单条路由定义（路径、默认值、约束等）
- **Frozen_Route_Collection**: boot 后包装 Route_Collection 的只读子类，override 写方法抛出 `LogicException`
- **Request_Matcher**: 由 Route_Collection 编译生成的请求匹配器（`CompiledUrlMatcher`），首次编译后缓存
- **Bootstrap_Config**: MicroKernel 构造函数接受的关联数组，包含 `routing`、`middlewares` 等顶层 key
- **CacheableRouter**: `src/ServiceProviders/Routing/CacheableRouter.php`，继承 Symfony `Router`，在 `getRouteCollection()` 中做参数替换
- **CacheableRouterProvider**: `src/ServiceProviders/Routing/CacheableRouterProvider.php`，创建 CacheableRouter 并构建 Request_Matcher 和 UrlGenerator
- **InheritableRouteCollection**: `src/ServiceProviders/Routing/InheritableRouteCollection.php`，继承 RouteCollection 并添加 `addDefaults()` 方法
- **pendingRoutes**: MicroKernel 新增的内部属性，暂存 boot 前通过 `addRoute()` / `addRoutes()` 注入的路由

---

## Bug Details

### Bug Condition

Bug 在以下两种场景中触发：

**C1 — 无编程式注入 API**：调用方需要在代码中编程式组装路由，但 MicroKernel 没有提供 `addRoute()` 方法，只能通过 YAML 文件声明路由。

**C2 — Boot 后写操作静默失效**：boot 完成后，调用方通过 `getRouter()->getRouteCollection()->add()` 添加路由，调用成功无异常，但新路由对已编译的 Request_Matcher 不可见，请求返回 404。

**Formal Specification:**
```
FUNCTION isBugCondition(X)
  INPUT: X of type RouteInjectionAttempt
  OUTPUT: boolean

  // C1: 需要编程式注入路由但无 API 可用
  // C2: boot 后对 Route_Collection 执行写操作（add / addCollection / remove）
  RETURN X.needsProgrammaticRouteInjection
      OR (X.isAfterBoot AND X.isWriteOperation)
END FUNCTION
```

### Examples

- **C1 示例**：调用方希望在 boot 前注入 `GET /health-check` 路由指向 `HealthController::check`，但 MicroKernel 无 `addRoute()` 方法，只能手动修改 YAML 文件
- **C1 示例**：调用方希望批量注入一组 API 路由（RouteCollection），但无 `addRoutes()` 方法
- **C2 示例**：boot 后调用 `$kernel->getRouter()->getRouteCollection()->add('dynamic', new Route('/dynamic', [...]))`，调用成功无异常，但 `GET /dynamic` 返回 404
- **C2 示例**：boot 后调用 `getRouteCollection()->remove('existing_route')`，调用成功无异常，但路由仍可匹配（已编译的 matcher 不受影响）
- **边界条件**：Bootstrap_Config 中未配置 `routing`，但调用方在 boot 前调用了 `addRoute()`——当前 `registerRouting()` 直接 return，编程式路由被丢弃

---

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- 仅通过 YAML 文件声明路由的场景，路由加载和请求匹配行为不变
- Bootstrap_Config 中未配置 `routing` 且无编程式路由时，`getRouter()` 继续返回 `null`
- `addMiddleware()`、`addControllerInjectedArg()` 等现有 boot 前注入方法行为不变
- 路由缓存机制不变，YAML 路由的编译缓存行为不受编程式路由影响
- CacheableRouter 的参数替换逻辑不变
- CacheableRouterProvider 的 YAML 加载逻辑不变
- boot 后只读操作（`get()`、`all()`、`count()`、`getIterator()`）正常返回

**Scope:**
所有不涉及编程式路由注入、不在 boot 后执行 Route_Collection 写操作的场景，修复前后行为完全一致。包括：
- 纯 YAML 路由配置
- 现有 middleware / view_handler / error_handler 注入
- 安全、CORS、Twig 等其他子系统
- 路由匹配、URL 生成的运行时行为

---

## Hypothesized Root Cause

基于代码分析，两个缺陷的根因如下：

1. **API 缺失**：MicroKernel 在 v3.0 迁移时，`addMiddleware()` 模式（boot 前暂存 → boot 时消费）未扩展到路由。`registerRouting()` 直接从 `CacheableRouterProvider` 获取 YAML 路由并编译，没有合并编程式路由的步骤。

2. **编译缓存导致静默失效**：`CacheableRouterProvider::buildRequestMatcher()` 调用 `getRouter()->getMatcher()`，Symfony `Router::getMatcher()` 首次调用时编译 RouteCollection 为 `CompiledUrlMatcher` 并缓存到 `$this->matcher`。后续调用直接返回缓存的 matcher，不再读取 RouteCollection。因此 boot 后通过 `getRouteCollection()->add()` 添加的路由只存在于 RouteCollection 对象中，不会反映到已编译的 matcher。

3. **无冻结机制**：Symfony `RouteCollection` 的 `add()`、`addCollection()`、`remove()` 是公开方法，没有生命周期限制。MicroKernel 也没有在 boot 后标记路由表为只读，导致调用方无法感知写操作已失效。

**关键调用链**（`registerRouting()` 内部）：

```
registerRouting()
  → CacheableRouterProvider::register($this)
  → CacheableRouterProvider::buildRequestMatcher($requestContext)
    → getRouter($requestContext)
      → new CacheableRouter(...)          // 创建 Router
    → getRouter()->getMatcher()           // ← 首次调用，编译 RouteCollection
      → CacheableRouter::getRouteCollection()  // 参数替换
      → 编译为 CompiledUrlMatcher 并缓存
  → CacheableRouterProvider::buildUrlGenerator($requestContext)
```

编程式路由必须在 `getRouter()->getMatcher()` 调用前合并到 RouteCollection。具体注入点：在 `buildRequestMatcher()` 调用前，通过 `getRouter()->getRouteCollection()->addCollection()` 合并。

---

## Correctness Properties

Property 1: Bug Condition — 编程式路由注入后可匹配

_For any_ input where a Route is added via `addRoute(name, route)` before boot, the fixed MicroKernel SHALL include that route in the compiled Request_Matcher, and `matchRequest()` SHALL return the route's `_controller` default for a matching request.

**Validates: Requirements 1.1, 1.2**

Property 2: Bug Condition — Boot 后写操作抛出异常

_For any_ input where `addRoute()`, `addRoutes()`, or Route_Collection write methods (`add()`, `addCollection()`, `remove()`) are called after boot, the fixed code SHALL throw `LogicException`, preventing silent failure.

**Validates: Requirements 2.1, 2.2, 3.1, 3.2, 3.3**

Property 3: Preservation — 非 bug 条件下行为不变

_For any_ input where the bug condition does NOT hold (no programmatic route injection needed, no post-boot write operations), the fixed MicroKernel SHALL produce the same behavior as the original MicroKernel, preserving YAML route loading, request matching, URL generation, and all other subsystem behaviors.

**Validates: Requirements 4.1, 4.2, 4.3, 4.4**

Property 4: Preservation — Frozen_Route_Collection 只读操作正常

_For any_ Route_Collection obtained via `getRouter()->getRouteCollection()` after boot, read-only operations (`get()`, `all()`, `count()`, `getIterator()`) SHALL return the same results as the underlying Route_Collection, without throwing exceptions.

**Validates: Requirements 3.4**

Property 5: 缓存隔离 — 编程式路由不参与 YAML 缓存编译

_For any_ configuration where YAML routing cache is enabled and programmatic routes (including Closure controllers) are injected via `addRoute()`, the CacheableRouter's compiled cache SHALL contain only YAML routes, and the programmatic routes SHALL be matched via a separate in-memory UrlMatcher. Boot SHALL succeed without serialization errors.

**Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5**

---

### Changes Required

假设根因分析正确，需要修改以下文件：

**File**: `src/MicroKernel.php`

**Changes**:

1. **新增属性 `$pendingRoutes`**：在 `$routerProvider` 属性附近（第 90 行左右）新增 `protected array $pendingRoutes = [];`，暂存 boot 前注入的路由。每个元素为 `['name' => string, 'route' => Route]` 或 `['collection' => RouteCollection]`。

2. **新增属性 `$booted`（复用）**：Symfony `Kernel` 基类已有 `$this->booted` 属性，可直接用于判断 boot 状态，无需新增。

3. **新增 `addRoute(string $name, Route $route): void`**：
   - 检查 `$this->booted`，若已 boot 则抛出 `LogicException`
   - 否则将 `['name' => $name, 'route' => $route]` 追加到 `$pendingRoutes`

4. **新增 `addRoutes(RouteCollection $routes): void`**：
   - 检查 `$this->booted`，若已 boot 则抛出 `LogicException`
   - 否则将 `['collection' => $routes]` 追加到 `$pendingRoutes`

5. **修改 `registerRouting()`**：
   - 在当前逻辑的开头，检查是否有 `$pendingRoutes`。若有但无 `routing` 配置，初始化空的路由基础设施
   - **双层 matcher 架构**：编程式路由构建独立的内存 `UrlMatcher`（不参与缓存编译），YAML 路由走 CacheableRouter 编译缓存。两者通过 `GroupUrlMatcher` 串联，编程式 matcher 排在前面（优先匹配）
   - 编程式路由不再合并到 YAML 的 RouteCollection，而是构建独立的 `programmaticCollection`
   - 在 `buildRequestMatcher()` 和 `buildUrlGenerator()` 调用后，调用 `CacheableRouter::freeze()` 冻结 RouteCollection
   - `GroupUrlGenerator` 同样按编程式优先的顺序组合

**File**: `src/ServiceProviders/Routing/FrozenRouteCollection.php`（新建）

**Changes**:

1. **继承 Symfony `RouteCollection`**：`class FrozenRouteCollection extends RouteCollection`
2. **构造函数**：接受一个 `RouteCollection`，通过 `parent::addCollection($wrapped)` 复制所有路由和资源
3. **Override 写方法**：`add()`、`addCollection()`、`remove()`、`addResource()` 均抛出 `LogicException('Route collection is frozen after boot. Routes cannot be modified at this point.')`
4. **只读方法不 override**：`get()`、`all()`、`count()`、`getIterator()`、`getResources()` 等继承自父类，正常工作

**File**: `src/ServiceProviders/Routing/CacheableRouter.php`

**Changes**:

1. **新增属性 `$frozen`**：`private bool $frozen = false;`
2. **新增 `freeze(): void`**：将 `$frozen` 设为 `true`。由 MicroKernel 的 `registerRouting()` 在 matcher 编译完成后调用。
3. **修改 `getRouteCollection()`**：在现有参数替换逻辑完成后，如果 `$frozen` 为 `true`，将结果包装为 `FrozenRouteCollection` 返回。缓存 FrozenRouteCollection 实例以避免重复创建。

**File**: `src/ServiceProviders/Routing/CacheableRouterProvider.php`

**Changes**: 无修改。合并逻辑在 MicroKernel 的 `registerRouting()` 中通过 `getRouter()->getRouteCollection()` 公开 API 实现，冻结逻辑在 CacheableRouter 中通过 `freeze()` 方法实现。CacheableRouterProvider 的职责（创建 Router、构建 matcher/generator）不变。

### registerRouting() 修改后的伪代码

```
FUNCTION registerRouting()
  routingConfig ← httpDataProvider.getOptional('routing')
  hasPendingRoutes ← NOT empty(pendingRoutes)

  IF NOT routingConfig AND NOT hasPendingRoutes THEN
    RETURN  // 无路由配置且无编程式路由，跳过
  END IF

  requestContext ← new RequestContext()
  matchers ← []
  generators ← []

  // ── 编程式路由（内存 matcher，不参与缓存编译）──
  IF hasPendingRoutes THEN
    programmaticCollection ← new RouteCollection()
    FOR EACH entry IN pendingRoutes DO
      IF entry HAS 'collection' THEN
        programmaticCollection.addCollection(entry.collection)
      ELSE
        programmaticCollection.add(entry.name, entry.route)
      END IF
    END FOR
    programmaticMatcher ← new UrlMatcher(programmaticCollection, requestContext)
    matchers.append(programmaticMatcher)  // 编程式 matcher 排在前面，优先匹配
    generators.append(new UrlGenerator(programmaticCollection, requestContext))
  END IF

  // ── YAML 路由（CacheableRouter，走编译缓存）──
  IF routingConfig THEN
    routingConfigDataProvider ← processConfiguration(routingConfig)
    routerProvider ← new CacheableRouterProvider()
    routerProvider.register(this)
    // 注意：不再将 pendingRoutes 合并到 YAML RouteCollection
    yamlMatcher ← routerProvider.buildRequestMatcher(requestContext)
    matchers.append(yamlMatcher)
    generators.append(routerProvider.buildUrlGenerator(requestContext))
    // 冻结 CacheableRouter 的 RouteCollection
    router ← routerProvider.getRouter(requestContext)
    router.freeze()
  END IF

  // ── 无 YAML 配置但有编程式路由 ──
  IF NOT routingConfig AND hasPendingRoutes THEN
    // 创建 directRouter 以支持 getRouter() 返回有效 Router
    // directRouter 使用 programmaticCollection 作为其 RouteCollection
    directRouter ← new CacheableRouter(this, ClosureLoader, emptyCollection, {cache_dir: null})
    directRouter.getRouteCollection().addCollection(programmaticCollection)
    directRouter.freeze()
    this.directRouter ← directRouter
  END IF

  // ── 组合 matcher 和 generator ──
  requestMatcher ← new GroupUrlMatcher(requestContext, matchers)
  urlGenerator ← new GroupUrlGenerator(generators)

  // 注册路由监听器（现有逻辑）
  ...
END FUNCTION
```

### 双层 Matcher 架构

YAML + 编程式路由共存时，采用双层 matcher 架构：

```
GroupUrlMatcher
  ├── UrlMatcher(programmaticCollection)   ← 内存，不缓存，支持 Closure
  └── CacheableRouterUrlMatcherWrapper     ← 编译缓存，仅 YAML 路由
        └── CompiledUrlMatcher (cached)
```

**匹配顺序**：编程式 matcher 排在前面。`GroupUrlMatcher.match()` 按顺序尝试，第一个命中即返回。这保证了编程式路由优先于 YAML 同名路由（与原来"后入覆盖先入"的语义一致）。

**缓存隔离**：编程式路由不参与 CacheableRouter 的编译缓存。YAML 路由的缓存文件仅包含 YAML 路由，不受编程式路由影响。Closure controller 因此不会触发序列化错误。

**URL 生成**：`GroupUrlGenerator` 同样按顺序查找，编程式路由的 generator 排在前面。

**FrozenRouteCollection 覆盖**：
- YAML 路由的 CacheableRouter 在 `freeze()` 后，`getRouteCollection()` 返回 FrozenRouteCollection
- 编程式路由的 `programmaticCollection` 在 boot 后不再暴露可写引用（通过 `directRouter.freeze()` 或不暴露引用实现）

**对 R1 AC3（同名路由覆盖）的影响**：原设计中编程式路由合并到 YAML RouteCollection 后由 Symfony 默认语义处理（后入覆盖先入）。新设计中同名路由通过 matcher 优先级实现覆盖——编程式 matcher 先匹配到即返回，YAML matcher 不再被调用。效果等价，但机制不同。

### 路由缓存测试策略

当前项目对路由缓存的生效和失效缺乏专门测试。本次修改需补充以下缓存相关测试：

**自动化测试**（在 Task 10 中实现）：
1. **缓存生效验证**：配置 `cache_dir` → boot → 验证缓存目录下生成了路由缓存文件
2. **缓存命中验证**：首次 boot 生成缓存 → 第二次 boot 使用缓存 → 验证路由匹配结果一致
3. **YAML 路由文件内容变更后缓存失效（debug 模式）**：`debug: true` → boot → 向 YAML 文件追加一条新路由 → 再次 boot → 验证新路由可匹配（缓存重新编译）。这是用户最常见的场景——修改路由表后期望新路由立即生效
4. **YAML 路由文件 mtime 变更后缓存失效（debug 模式）**：`debug: true` → boot → touch YAML 文件（仅更新 mtime，不改内容）→ 再次 boot → 验证缓存重新编译（Symfony `ConfigCache` 通过 `FileResource.isFresh()` 检查 mtime）
5. **PHP resource 文件变更后缓存失效（debug 模式）**：`debug: true` → boot → touch `CacheableRouter.php`（已通过 `addResource(new FileResource(__FILE__))` 注册为 resource）→ 再次 boot → 验证缓存重新编译。这对应框架升级场景
6. **`debug: false` 时缓存持久不失效**：`debug: false` → boot → 修改 YAML 文件内容 → 再次 boot → 验证仍使用旧缓存（新路由不可匹配）。这验证生产环境下缓存的持久性
7. **YAML + Closure 编程式路由共存**：配置 `cache_dir` + `addRoute()` 注入 Closure controller → boot → 验证两者均可匹配且无序列化错误
8. **缓存隔离验证**：配置 `cache_dir` + `addRoute()` → boot → 检查缓存文件不包含编程式路由名

**手工测试**（在 Task 11 中覆盖）：
- Closure controller 端到端验证
- 缓存目录清理后重新 boot 验证

### FrozenRouteCollection 注入策略

Symfony `Router::getRouteCollection()` 返回内部 `$this->collection`，无法从外部替换。有两种方案：

**方案 A**：在 CacheableRouter 中 override `getRouteCollection()`，boot 后返回 FrozenRouteCollection 包装。CacheableRouter 已经 override 了此方法（做参数替换），可以在参数替换完成后、返回前包装为 FrozenRouteCollection。需要 CacheableRouter 持有一个 `frozen` 标志。

**方案 B**：不在 Router 层面冻结，而是在 MicroKernel 的 `getRouter()` 方法中返回一个代理 Router。复杂度过高，不推荐。

**选择方案 A**：在 CacheableRouter 中新增 `freeze()` 方法和 `$frozen` 标志。`getRouteCollection()` 在 `$frozen` 为 true 时返回 FrozenRouteCollection 包装。`registerRouting()` 在编译完成后调用 `freeze()`。

---

## Impact Analysis

### 受影响的源文件

| 文件 | 变更类型 | 说明 |
|------|----------|------|
| `src/MicroKernel.php` | 修改 | 新增 `$pendingRoutes` 属性、`addRoute()`、`addRoutes()` 方法；修改 `registerRouting()` |
| `src/ServiceProviders/Routing/FrozenRouteCollection.php` | 新建 | 继承 `RouteCollection`，override 写方法抛异常 |
| `src/ServiceProviders/Routing/CacheableRouter.php` | 修改 | 新增 `freeze()` 方法和 `$frozen` 标志，`getRouteCollection()` 冻结后返回 FrozenRouteCollection |

### 受影响的 state 文档

| 文件 | Section | 说明 |
|------|---------|------|
| `docs/state/architecture.md` | Bootstrap Config 结构 | 无需修改——编程式路由通过方法注入，不新增 Bootstrap Config key |
| `docs/state/architecture.md` | 请求处理流程 | 无需修改——编程式路由在 boot 阶段合并，不改变运行时请求处理流程 |
| `docs/state/architecture.md` | 模块结构 | 需补充 `FrozenRouteCollection` 到 `ServiceProviders/Routing/` 模块说明 |

### 受影响的 manual 文档

| 文件 | 说明 |
|------|------|
| `docs/manual/routing.md` | 需补充编程式路由注入 API 的使用说明（`addRoute()` / `addRoutes()`） |
| `docs/manual/bootstrap-configuration.md` | 无需修改——不新增 Bootstrap Config key |

### 现有行为变化

- **MicroKernel 公开 API**：新增 `addRoute()` 和 `addRoutes()` 两个公开方法。不修改、不删除任何现有方法。
- **`getRouter()->getRouteCollection()` 返回类型**：boot 后返回 `FrozenRouteCollection`（继承自 `RouteCollection`），类型兼容。写操作从静默无效变为抛出 `LogicException`——这是有意的行为变更（breaking change for buggy code, not for correct code）。
- **`registerRouting()` 内部逻辑**：新增编程式路由合并步骤和冻结步骤。对仅使用 YAML 路由的场景，合并步骤为空操作，冻结步骤仅影响 boot 后的写操作。

### 数据模型变更

不涉及。本修复不改变任何持久化数据结构、配置文件格式或缓存格式。

### 外部系统交互变化

不涉及。本修复仅影响 MicroKernel 内部的路由注册生命周期。

### 配置项变更

不涉及。不新增、不删除、不修改任何 Bootstrap Config key 或 YAML 路由配置项。

### Graphify 辅助分析

基于 graphify GRAPH_REPORT，MicroKernel 是 god node（42 edges），跨越 Bootstrap & Routing Integration、Security Auth Controllers、Kernel Handle & Middleware Chain 等多个 community。本次修改集中在 MicroKernel 的路由注册路径，涉及的 community 为：

- **Community 1 (Bootstrap & Routing Integration)**：MicroKernel、CacheableRouterProvider、CacheableRouterProviderTest——直接受影响
- **Community 24 (CacheableRouter)**：CacheableRouter、CacheableRouterTest——CacheableRouter 需新增 `freeze()` 方法
- **Community 6 (Cacheable Router URL Matching)**：GroupUrlMatcher、GroupUrlGenerator——不受影响（编程式路由在编译前合并，不改变 matcher/generator 的行为）

其他 community（Security、CORS、Twig、Middleware 等）不受影响，因为本修复不改变 boot 流程中其他 `register*()` 方法的调用顺序和行为。

---

## Alternatives Considered

### 合并与冻结逻辑的放置位置

**方案 A（选用）**：合并逻辑在 MicroKernel 的 `registerRouting()` 中实现，冻结逻辑在 CacheableRouter 中通过 `freeze()` 方法实现。

- 优点：合并逻辑与其他 `register*()` 方法风格一致；冻结逻辑在 Router 层面拦截，覆盖所有通过 `getRouter()->getRouteCollection()` 的访问路径
- 缺点：CacheableRouter 需要新增 `freeze()` 方法和 `$frozen` 标志

**方案 B（落选）**：合并和冻结逻辑都在 CacheableRouterProvider 中实现（新增 `mergeRoutes()` 和 `freezeRouteCollection()` 方法）。

- 优点：MicroKernel 变更更小
- 缺点：CacheableRouterProvider 的职责是创建 Router 和构建 matcher/generator，不应承担路由合并和冻结的职责；且冻结仍需 CacheableRouter 配合

**方案 C（落选）**：在 MicroKernel 的 `getRouter()` 方法中返回代理 Router。

- 优点：不修改 CacheableRouter
- 缺点：复杂度过高，需要代理 Router 的所有公开方法；且 `getRouter()` 在 boot 前后都可能被调用，代理逻辑复杂

### FrozenRouteCollection 的实现方式

**方案 A（选用）**：继承 Symfony `RouteCollection`，override 写方法抛异常。

- 优点：类型兼容（`Router::getRouteCollection()` 返回类型为 `RouteCollection`）；实现简单
- 缺点：违反 LSP（子类拒绝父类允许的操作）
- 决策依据：bugfix.md CR Q2→A 已确认此 trade-off 可接受

**方案 B（落选）**：实现与 `RouteCollection` 相同的接口（`\IteratorAggregate`、`\Countable`），不继承。

- 优点：语义正确，不违反 LSP
- 缺点：类型不兼容，`Router::getRouteCollection()` 返回类型声明为 `RouteCollection`，不继承则无法作为返回值

---

## Socratic Review

**Q: design 是否完整覆盖了 requirements 中的每条需求？**
A: 是。R1（编程式注入 API）→ Fix Implementation 中 MicroKernel 的 `addRoute()` / `addRoutes()` 和 `registerRouting()` 修改。R2（MicroKernel 层冻结）→ `addRoute()` / `addRoutes()` 中的 `$this->booted` 检查。R3（Route_Collection 层冻结）→ FrozenRouteCollection + CacheableRouter 的 `freeze()` 方法。R4（回归防护）→ Expected Behavior / Preservation Requirements + Testing Strategy 中的 Preservation Checking。

**Q: 技术选型是否合理？是否有更简单的替代方案？**
A: FrozenRouteCollection 继承 RouteCollection 是最简单的类型兼容方案（bugfix.md CR Q2→A 已确认）。CacheableRouter 的 `freeze()` 方法是最小侵入的冻结注入点（方案 A vs B vs C 已在 Alternatives Considered 中分析）。`pendingRoutes` 数组暂存模式与现有 `$middlewares` 数组一致，无额外复杂度。

**Q: 接口签名和数据模型是否足够清晰，能让 task 独立执行？**
A: `addRoute(string $name, Route $route): void` 和 `addRoutes(RouteCollection $routes): void` 签名明确。`FrozenRouteCollection` 的 override 方法列表明确（`add()`、`addCollection()`、`remove()`、`addResource()`）。`CacheableRouter::freeze(): void` 签名明确。`$pendingRoutes` 的数据结构（`['name' => string, 'route' => Route]` 或 `['collection' => RouteCollection]`）明确。

**Q: 模块间的依赖关系是否会引入循环依赖？**
A: 不会。依赖方向为 MicroKernel → CacheableRouterProvider → CacheableRouter → FrozenRouteCollection。FrozenRouteCollection 仅依赖 Symfony RouteCollection，不依赖项目内其他类。与 graphify 分析的 Community 1 和 Community 24 的依赖方向一致。

**Q: 是否有过度设计的部分？**
A: 没有。最终决策是合并逻辑在 MicroKernel 中实现、冻结逻辑在 CacheableRouter 中实现，不修改 CacheableRouterProvider 的公开 API。这是最小变更范围，不引入额外抽象。

**Q: 无 `routing` 配置但有编程式路由的边界条件如何处理？**
A: bugfix.md CR Q1→A 已确认：`registerRouting()` 检测到有暂存的编程式路由时，即使无 `routing` 配置也初始化空 Route_Collection 并合并编程式路由。registerRouting() 伪代码中的 `ELSE` 分支已体现此逻辑，但具体实现（如何创建不依赖 YAML 的最小 RouterProvider）需在 tasks 阶段细化。

**Q: Impact Analysis 是否充分？**
A: 已覆盖受影响的源文件、state 文档、manual 文档、现有行为变化、数据模型、外部系统、配置项。通过 graphify 辅助确认了跨 community 的影响范围。

---

## Testing Strategy

### Failing Test First 原则

本 bugfix 遵循 **failing test first** 工作流：

1. **先写 failing test**：在未修复的代码上编写测试用例，验证 bug 确实存在（测试应当失败或展示 bug 行为）
2. **实施修复**：编写修复代码
3. **验证 test 通过**：运行同一批测试，确认修复后测试全部通过

这一流程确保每个 bug 都有对应的回归测试，且修复的正确性通过测试状态的翻转（fail → pass）得到验证。

### Phase 1: Failing Tests（修复前编写，预期失败）

在实施任何修复代码之前，先编写以下测试。这些测试在未修复代码上应当失败或展示 bug 行为，从而确认 bug 存在。

#### Exploratory Bug Condition Tests

**Goal**: 验证 bug 确实存在，确认根因分析。

**Test Cases**:
1. **API 缺失验证**：尝试在 MicroKernel 上调用 `addRoute()`——方法不存在，PHP Fatal Error（will fail on unfixed code）
2. **Boot 后静默失效验证**：boot 后调用 `getRouter()->getRouteCollection()->add()`，发送匹配请求，验证返回 404（will fail on unfixed code — 即 add 成功但路由不可达）
3. **Boot 后无异常验证**：boot 后调用 `getRouteCollection()->add()`，验证不抛异常（will fail on unfixed code — 即当前确实不抛异常）

**Expected Counterexamples**:
- `addRoute()` 方法不存在，调用产生 Fatal Error
- boot 后 `add()` 调用成功但路由不可达，请求返回 404
- 根因确认：`getMatcher()` 编译后缓存，后续 RouteCollection 修改不影响 matcher

#### Fix Checking Tests（预期修复后通过）

编写以下测试，在未修复代码上预期失败，修复后预期通过：

1. **编程式路由注入后可匹配**：`addRoute()` 注入路由 → boot → `matchRequest()` 返回对应 controller
2. **批量注入后可匹配**：`addRoutes()` 注入 RouteCollection → boot → 所有路由均可匹配
3. **Boot 后 `addRoute()` 抛异常**：boot 后调用 `addRoute()` → 预期 `LogicException`
4. **Boot 后 `addRoutes()` 抛异常**：boot 后调用 `addRoutes()` → 预期 `LogicException`
5. **Boot 后 `getRouteCollection()->add()` 抛异常**：boot 后调用 → 预期 `LogicException`
6. **Boot 后 `getRouteCollection()->addCollection()` 抛异常**：boot 后调用 → 预期 `LogicException`
7. **Boot 后 `getRouteCollection()->remove()` 抛异常**：boot 后调用 → 预期 `LogicException`

**Pseudocode:**
```
// Fix Checking — 编程式路由注入
FOR ALL X WHERE X.needsProgrammaticRouteInjection DO
  kernel ← MicroKernel'(config)
  kernel.addRoute(X.name, X.route)
  kernel.boot()
  result ← kernel.getRequestMatcher().matchRequest(X.request)
  ASSERT result._controller = X.route.getDefault('_controller')
END FOR

// Fix Checking — Boot 后写操作冻结
FOR ALL X WHERE X.isAfterBoot AND X.isWriteOperation DO
  kernel ← MicroKernel'(config)
  kernel.boot()
  ASSERT kernel.addRoute(X.name, X.route) THROWS LogicException
  ASSERT kernel.addRoutes(X.routes) THROWS LogicException
  collection ← kernel.getRouter().getRouteCollection()
  ASSERT collection.add(X.name, X.route) THROWS LogicException
  ASSERT collection.addCollection(X.routes) THROWS LogicException
  ASSERT collection.remove(X.name) THROWS LogicException
END FOR
```

### Phase 2: 实施修复

按 Fix Implementation section 的方案实施代码修改。

### Phase 3: 验证（修复后运行，预期全部通过）

运行 Phase 1 中编写的所有测试，确认：
- Exploratory tests 中的 bug 行为已消除
- Fix Checking tests 全部通过（fail → pass 翻转）
- 下方的 Preservation tests 全部通过

#### Preservation Checking

**Goal**: 验证对所有不满足 bug condition 的输入，修复后的函数与原函数行为一致。

**Pseudocode:**
```
FOR ALL X WHERE NOT isBugCondition(X) DO
  ASSERT F(X) = F'(X)
END FOR
```

**Testing Approach**: Property-based testing 适用于 preservation checking，因为：
- 自动生成大量测试用例覆盖输入域
- 捕获手动单元测试可能遗漏的边界条件
- 对非 bug 输入提供强保证

**Test Cases**:
1. **YAML 路由加载 Preservation**：验证仅使用 YAML 路由时，路由加载和匹配行为与修复前一致
2. **无路由配置 Preservation**：验证未配置 `routing` 且无编程式路由时，`getRouter()` 返回 `null`
3. **Middleware 注入 Preservation**：验证 `addMiddleware()` 在路由 API 变更后仍正常工作
4. **只读操作 Preservation**：验证 boot 后 `getRouteCollection()` 的只读操作（`get()`、`all()`、`count()`、`getIterator()`）返回正确结果

### Unit Tests

- `FrozenRouteCollectionTest`：测试 `add()`、`addCollection()`、`remove()` 抛出 `LogicException`；测试 `get()`、`all()`、`count()`、`getIterator()` 正常返回
- `MicroKernelAddRouteTest`：测试 boot 前 `addRoute()` 暂存、boot 后 `addRoute()` 抛异常
- `MicroKernelAddRoutesTest`：测试 boot 前 `addRoutes()` 暂存、boot 后 `addRoutes()` 抛异常
- 同名路由覆盖测试：编程式路由覆盖 YAML 同名路由
- 无 routing 配置 + 编程式路由测试：验证空 RouteCollection 初始化并合并

### Property-Based Tests

- 生成随机 Route（随机路径、随机 controller），验证 `addRoute()` 后 boot，`matchRequest()` 返回对应 controller
- 生成随机 RouteCollection，验证 `addRoutes()` 后 boot，所有路由均可匹配
- 生成随机已注册路由集合，验证 FrozenRouteCollection 的只读操作结果与原始 RouteCollection 一致
- Preservation：生成随机 YAML 路由配置，验证修复前后匹配结果一致

### Integration Tests

- 完整 boot 流程 + 编程式路由注入 + 请求匹配端到端测试
- 编程式路由 + YAML 路由混合场景端到端测试
- Boot 后冻结 + `getRouter()->getRouteCollection()` 写操作拒绝端到端测试
- 路由缓存启用场景下编程式路由合并测试


---

## Gatekeep Log

**校验时间**: 2025-07-18
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Impact Analysis` section，覆盖受影响的源文件、state 文档、manual 文档、现有行为变化、数据模型、外部系统、配置项、graphify 辅助分析
- [结构] 补充 `## Alternatives Considered` section，记录合并/冻结逻辑放置位置的三种方案和 FrozenRouteCollection 实现方式的两种方案，含选用理由和落选原因
- [结构] 补充 `## Socratic Review` section，覆盖 requirements 全覆盖、技术选型合理性、接口清晰度、循环依赖、过度设计、边界条件、Impact 充分性
- [内容] Testing Strategy 重构为 **failing test first** 三阶段结构（Phase 1: Failing Tests → Phase 2: 实施修复 → Phase 3: 验证），明确先写 failing test 验证 bug 存在，再实施修复，最后验证 test 通过
- [内容] Fix Implementation 中 CacheableRouterProvider 的变更从"新增 mergeRoutes/freezeRouteCollection 方法"修正为"无修改"，与最终技术决策（方案 A）一致
- [内容] Fix Implementation 中补充 CacheableRouter 的变更（新增 `$frozen` 属性、`freeze()` 方法、修改 `getRouteCollection()`）
- [内容] registerRouting() 伪代码末尾补充 `router.freeze()` 调用，替换原来的注释式冻结说明
- [内容] registerRouting() 伪代码中"无 YAML 配置但有编程式路由"分支补充说明，标注具体实现方式在 tasks 阶段细化

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] 技术方案主体存在，承接 requirements 中的需求
- [x] 接口签名 / 数据模型有明确定义（`addRoute()`、`addRoutes()`、`FrozenRouteCollection`、`CacheableRouter::freeze()`）
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应的实现描述（R1→addRoute/addRoutes+registerRouting、R2→booted 检查、R3→FrozenRouteCollection+freeze、R4→Preservation）
- [x] 无遗漏的 requirement
- [x] design 中的方案不超出 requirements 的范围
- [x] Impact Analysis 覆盖受影响的 state 文档条目
- [x] Impact Analysis 利用 graphify 查询结果辅助识别受影响范围
- [x] Impact Analysis 覆盖现有行为变化
- [x] Impact Analysis 确认不涉及数据模型变更
- [x] Impact Analysis 确认不涉及外部系统交互变化
- [x] Impact Analysis 确认不涉及配置项变更
- [x] 技术选型有明确理由
- [x] 接口签名足够清晰（参数类型、返回类型、异常类型）
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致
- [x] Socratic Review 存在且覆盖充分
- [x] Requirements CR 回应：bugfix.md Gatekeep Log 中 Q1→A（无 routing 配置 + 编程式路由）、Q2→A（FrozenRouteCollection 继承 RouteCollection）、Q3→C（同名路由委托 Symfony 默认行为）均已在 design 中体现
- [x] 技术选型明确，无含糊的"可以用 A 或 B"
- [x] 接口定义可执行，能让 task 执行者直接编码
- [x] Requirements 全覆盖
- [x] Impact 充分评估
- [x] 可 task 化：design 中的模块划分和接口定义足够清晰，可拆出独立 task

### Clarification Round

**状态**: 已完成

**Q1:** Testing Strategy 中 Phase 1 的 failing tests 应放在哪个测试目录和 suite 中？
- A) 在 `ut/Routing/` 目录下新建测试文件（如 `FrozenRouteCollectionTest.php`、`MicroKernelRouteInjectionTest.php`），加入现有 `routing` suite
- B) 在 `ut/Routing/` 目录下新建测试文件，但创建新的 `route-injection` suite 与现有 routing 测试分离
- C) 在 `ut/Integration/` 目录下新建集成测试文件，加入 `integration` suite；单元测试仍放 `ut/Routing/`
- D) 其他（请说明）

**A:** A — 在 `ut/Routing/` 目录下新建测试文件，加入现有 `routing` suite

**Q2:** "无 `routing` 配置但有编程式路由"的边界条件（bugfix.md CR Q1→A），在 `registerRouting()` 中需要初始化空路由基础设施。具体实现方式有多种选择：
- A) 创建一个最小的 CacheableRouterProvider，传入一个空的临时 YAML 文件路径（或内存中的空资源）
- B) 在 MicroKernel 中直接创建空 RouteCollection + GroupUrlMatcher + GroupUrlGenerator，绕过 CacheableRouterProvider
- C) 修改 CacheableRouterProvider 使其支持无 YAML 配置的模式（如 `getRouter()` 在无配置时返回空 RouteCollection 的 Router）
- D) 其他（请说明）

**A:** B — 在 MicroKernel 中直接创建空 RouteCollection + GroupUrlMatcher + GroupUrlGenerator，绕过 CacheableRouterProvider

**Q3:** Fix Implementation 中 CacheableRouter 的 `freeze()` 方法和 `$frozen` 标志是否应该通过接口抽象（如 `FreezableRouteCollectionProviderInterface`），还是直接在 CacheableRouter 上实现？
- A) 直接在 CacheableRouter 上实现 `freeze()` 方法，不引入接口——最小变更，且 `freeze()` 仅由 MicroKernel 内部调用
- B) 引入 `FreezableInterface`（含 `freeze(): void`），CacheableRouter 实现之——为未来其他可冻结组件预留扩展点
- C) 不在 CacheableRouter 上加 `freeze()`，改为在 MicroKernel 的 `getRouter()` 中判断 `$this->booted` 后包装返回值
- D) 其他（请说明）

**A:** A — 直接在 CacheableRouter 上实现 `freeze()` 方法，不引入接口。最小变更，且 `freeze()` 仅由 MicroKernel 内部调用。

**Q4:** Task 拆分策略：本 bugfix 涉及 MicroKernel（addRoute/addRoutes + registerRouting 修改）、FrozenRouteCollection（新建）、CacheableRouter（freeze 方法）三个模块。拆分方式偏好：
- A) 按模块拆分：Task 1 = FrozenRouteCollection、Task 2 = CacheableRouter freeze、Task 3 = MicroKernel addRoute/addRoutes + registerRouting、Task 4 = 测试
- B) 按功能切片拆分：Task 1 = 编程式注入 API（addRoute + addRoutes + registerRouting 合并逻辑 + 对应测试）、Task 2 = 冻结机制（FrozenRouteCollection + CacheableRouter freeze + 对应测试）
- C) 按 failing-test-first 流程拆分：Task 1 = 所有 failing tests、Task 2 = 所有修复代码、Task 3 = 验证 + preservation tests
- D) 其他（请说明）

**A:** A — 按模块拆分：Task 1 = FrozenRouteCollection、Task 2 = CacheableRouter freeze、Task 3 = MicroKernel addRoute/addRoutes + registerRouting、Task 4 = 测试
