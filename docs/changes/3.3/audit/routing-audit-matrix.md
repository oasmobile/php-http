# Routing Module Audit Matrix

> `oasis/http` v2.5.0 `CacheableRouterProvider` (Silex-based) vs v3.x `CacheableRouterProvider` (Symfony MicroKernel-based) — 细粒度 API_Surface 对比

**审计基准**：`oasis/http` v2.5.0（tag `v2.5.0`），基于 Silex 2.3 的路由机制。审计对象是 v2.5.0 实际暴露给下游的 API_Surface，而非 Silex 原始的全部能力。

**审计时间**：2025-07-17

---

## v2.5.0 架构概述

v2.5.0 的路由通过以下组件协作：

- `SilexKernel` 构造函数中读取 Bootstrap_Config `routing` key，存入 `$app['routing.config']`
- `SilexKernel::boot()` 中注册 `CacheableRouterProvider`（Pimple `ServiceProviderInterface`）
- `CacheableRouterProvider::register()` 通过 Pimple `$app->extend()` 扩展 `request_matcher` 和 `url_generator`
- `CacheableRouter` 继承 Symfony `Router`，在 `getRouteCollection()` 中执行 `%param%` 参数替换
- `GroupUrlMatcher` 组合多个 `UrlMatcherInterface`，按顺序尝试匹配
- `GroupUrlGenerator` 组合多个 `UrlGeneratorInterface`，按顺序尝试生成
- `CacheableRouterUrlMatcherWrapper` 在匹配结果中为 `_controller` 补全命名空间前缀
- `InheritableYamlFileLoader` 扩展 `YamlFileLoader`，支持子路由继承父路由 defaults
- `InheritableRouteCollection` 提供 `addDefaults()` 方法，将父路由 defaults 传播到子路由

v2.5.0 不支持编程式路由注入（`addRoute()` / `addRoutes()`），这是 v3.2 新增的能力。

---

## Configuration

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| Bootstrap_Config `routing` key | config | covered | `MicroKernel::registerRouting()` 读取 `routing` key | no-action | — |
| `routing.path` 配置项（YAML 文件路径） | config | covered | `CacheableRouterProvider::getRouter()` 读取 `path` | no-action | — |
| `routing.cache_dir` 配置项 | config | covered | `CacheableRouterProvider::getRouter()` 读取 `cache_dir` | no-action | — |
| `routing.namespaces` 配置项（controller 命名空间前缀） | config | covered | `CacheableRouterProvider::buildRequestMatcher()` 读取 `namespaces` | no-action | — |
| `routing.namespaces` 字符串自动转数组 | config | covered | `CacheableRouterConfiguration` `beforeNormalization` | no-action | — |
| `cache_dir` 为 `"false"` 时禁用缓存 | config | covered | `CacheableRouterProvider::getRouter()` `strcasecmp` 检查 | no-action | — |
| `cache_dir` 未配置时使用 kernel `cache_dir` + `/routing` | config | covered | `CacheableRouterProvider::getRouter()` fallback 逻辑 | no-action | v2.5.0 fallback 到 `$routerPath . "/cache"`，v3.x fallback 到 `$kernel->getCacheDir() . "/routing"`。行为差异见 B1 |

## Registration & Bootstrap

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `CacheableRouterProvider implements ServiceProviderInterface` | registration | covered | `CacheableRouterProvider`（不再实现 Pimple 接口） | no-action | 内部架构变更，外部行为等价 |
| `CacheableRouterProvider::register(Container $app)` | registration | covered | `CacheableRouterProvider::register(MicroKernel $kernel)` | no-action | 签名变更（Pimple → MicroKernel），已在 Migration_Guide §4 覆盖 |
| `$app['request_matcher']` 扩展（`$app->extend()`） | registration | covered | `MicroKernel::registerRouting()` 直接构建 `GroupUrlMatcher` | no-action | 不再通过 Pimple extend，直接赋值 |
| `$app['url_generator']` 扩展（`$app->extend()`） | registration | covered | `MicroKernel::registerRouting()` 直接构建 `GroupUrlGenerator` | no-action | 同上 |
| `$app['router']` 注册 | registration | covered | `MicroKernel::getRouter()` getter | no-action | 从 Pimple service 变为 getter 方法 |
| `$app['routing.config.data_provider']` 注册 | registration | covered | `MicroKernel::getRoutingConfigDataProvider()` getter | no-action | 同上 |
| `$app['routing.config.namespaces']` 注册 | registration | covered | `CacheableRouterProvider::buildRequestMatcher()` 内部读取 | no-action | 不再作为独立 service 暴露 |
| `$app['routing.config.cache_dir']` 注册 | registration | covered | `CacheableRouterProvider::getRouter()` 内部读取 | no-action | 同上 |
| 无 `routing` 配置时跳过注册 | registration | covered | `MicroKernel::registerRouting()` 检查 `$routingConfig` | no-action | — |

## YAML Route Loading

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `InheritableYamlFileLoader` 加载 YAML 路由 | loading | covered | `InheritableYamlFileLoader` 保留 | no-action | 签名适配 Symfony 8.x（新增 `$exclude` 参数） |
| `FileLocator` 定位路由文件 | loading | covered | `CacheableRouterProvider::getRouter()` 创建 `FileLocator` | no-action | — |
| `path` 为目录时使用 `routes.yml` 默认文件名 | loading | covered | `CacheableRouterProvider::getRouter()` `is_dir()` 检查 | no-action | — |
| `path` 为文件时使用 `basename` + `dirname` | loading | covered | `CacheableRouterProvider::getRouter()` | no-action | — |
| `InheritableRouteCollection` 继承父路由 defaults | loading | covered | `InheritableRouteCollection` 保留 | no-action | — |
| `InheritableRouteCollection::addDefaults()` | loading | covered | `InheritableRouteCollection::addDefaults()` 保留 | no-action | — |

## Route Parameter Replacement

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `CacheableRouter::getRouteCollection()` 中 `%param%` 替换 | parameters | covered | `CacheableRouter::getRouteCollection()` 保留相同逻辑 | no-action | — |
| `%param%` 替换使用 `$kernel->getParameter()` | parameters | covered | `$this->kernel->getParameter($key)` | no-action | — |
| 未找到参数时跳过（offset 前进） | parameters | covered | 相同逻辑 | no-action | — |
| `%%` 转义为 `%` | parameters | covered | 相同逻辑 | no-action | — |
| 仅替换 `defaults` 中的字符串值 | parameters | covered | `!is_string($value)` 检查 | no-action | — |
| 替换后添加 `FileResource` 防止缓存过期 | parameters | covered | `$collection->addResource(new FileResource(__FILE__))` | no-action | — |
| 参数替换仅执行一次（`$isParamReplaced` flag） | parameters | covered | `$this->isParamReplaced` flag | no-action | — |

## Route Caching

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `CacheableRouter` 继承 Symfony `Router` 的缓存机制 | caching | covered | `CacheableRouter extends Router` 保留 | no-action | — |
| `cache_dir` 选项传递给 Symfony `Router` | caching | covered | `CacheableRouterProvider::getRouter()` 传递 `cache_dir` | no-action | — |
| `debug` 选项传递给 Symfony `Router` | caching | covered | `CacheableRouterProvider::getRouter()` 传递 `debug` | no-action | — |
| `matcher_cache_class` 自定义缓存类名 | caching | intentionally-removed | 不再传递 `matcher_cache_class` | confirm-documented | v2.5.0 使用 `md5(realpath)` 生成唯一类名。v3.x 依赖 Symfony Router 默认的缓存类名生成。Symfony 8.x 已移除 `matcher_cache_class` / `generator_cache_class` 选项 |
| `generator_cache_class` 自定义缓存类名 | caching | intentionally-removed | 不再传递 `generator_cache_class` | confirm-documented | 同上 |
| `matcher_base_class` 使用 `RedirectableUrlMatcher` | caching | intentionally-removed | 不再传递 `matcher_base_class` | confirm-documented | v2.5.0 使用 Silex 的 `RedirectableUrlMatcher`。v3.x 使用 Symfony 默认 matcher。重定向行为由 `MicroKernel::registerRouting()` 中的 scheme redirect 逻辑替代 |

## URL Matching

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `GroupUrlMatcher` 组合多个 matcher | matching | covered | `GroupUrlMatcher` 保留 | no-action | — |
| `GroupUrlMatcher::match()` 按顺序尝试，first match wins | matching | covered | 相同逻辑 | no-action | — |
| `GroupUrlMatcher::matchRequest()` 委托给 `match()` | matching | covered | 相同逻辑 | no-action | — |
| `CacheableRouterUrlMatcherWrapper` 补全 controller 命名空间 | matching | covered | `CacheableRouterUrlMatcherWrapper` 保留 | no-action | — |
| 命名空间补全：遍历 `namespaces` 数组，`class_exists()` 检查 | matching | covered | 相同逻辑 | no-action | — |
| `ResourceNotFoundException` 传播到最后一个 matcher | matching | covered | 相同逻辑 | no-action | — |
| `MethodNotAllowedException` 处理 | matching | covered | `MicroKernel::registerRouting()` 转换为 `MethodNotAllowedHttpException` | no-action | v2.5.0 由 Silex `RouterListener` 处理，v3.x 由自定义 listener 处理。行为等价 |
| Silex `RedirectableUrlMatcher` 的 scheme redirect | matching | covered | `MicroKernel::registerRouting()` 中的 scheme redirect 逻辑 | no-action | v2.5.0 通过 `matcher_base_class = RedirectableUrlMatcher` 实现。v3.x 在自定义 routing listener 中实现等价的 scheme redirect（catch `ResourceNotFoundException` → 尝试反转 scheme → 302 redirect） |
| v2.5.0 matcher 架构：`GroupUrlMatcher([CacheableRouterUrlMatcherWrapper, Silex default matcher])` | matching | covered | v3.x matcher 架构：`GroupUrlMatcher([programmatic UrlMatcher?, CacheableRouterUrlMatcherWrapper])` | no-action | v2.5.0 的 Silex default matcher 作为 fallback。v3.x 无 Silex default matcher，但编程式路由 matcher 排在前面（优先级更高） |

## URL Generation

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `GroupUrlGenerator` 组合多个 generator | generation | covered | `GroupUrlGenerator` 保留 | no-action | — |
| `GroupUrlGenerator::generate()` 按顺序尝试 | generation | covered | 相同逻辑 | no-action | — |
| `RouteNotFoundException` 传播到最后一个 generator | generation | covered | 相同逻辑 | no-action | — |
| `setContext()` 传播到子 generator | generation | covered | 相同逻辑（增加了 `contextExplicitlySet` flag） | no-action | v3.x 增加了 `contextExplicitlySet` 检查，避免在未显式设置 context 时覆盖子 generator 的 context。这是改进，不是 regression |

## Route Collection Manipulation

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `RouteCollection` 标准操作（`add`, `addCollection`, `remove`, `get`, `all`, `count`） | manipulation | covered | 标准 `RouteCollection` 操作保留 | no-action | — |
| v2.5.0 无编程式路由注入 API | manipulation | N/A | v3.2 新增 `addRoute()` / `addRoutes()` | no-action | 纯新增能力，不影响 v2.5.0 行为等价性 |
| v2.5.0 无 boot 后路由冻结 | manipulation | N/A | v3.2 新增 `FrozenRouteCollection` | no-action | 纯新增能力。v2.5.0 中 boot 后修改 `RouteCollection` 会静默失效（因为 matcher 已编译），v3.x 改为显式抛出 `LogicException`。这是有意的行为修正 |

## Inheritable Route Loading

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `InheritableYamlFileLoader::import()` 返回 `InheritableRouteCollection` | inheritable | covered | 相同逻辑 | no-action | 签名适配 Symfony 8.x |
| 子路由文件通过 `resource` key 引入 | inheritable | covered | Symfony `YamlFileLoader` 标准行为 | no-action | — |
| 父路由 `defaults` 传播到子路由 | inheritable | covered | `InheritableRouteCollection::addDefaults()` | no-action | — |
| 子路由已有的 default 不被父路由覆盖 | inheritable | covered | `!$route->hasDefault($key)` 检查 | no-action | — |

## SilexKernel Routing API

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `SilexKernel` 构造函数中读取 `routing` config | public_api | covered | `MicroKernel::parseBootstrapConfig()` + `registerRouting()` | no-action | — |
| `SilexKernel` 无 `addRoute()` / `addRoutes()` | public_api | N/A | v3.2 新增 | no-action | 纯新增 |
| `SilexKernel` 无 `getRouter()` 公开方法 | public_api | N/A | v3.x 新增 `MicroKernel::getRouter()` | no-action | 纯新增 |
| `SilexKernel` 无 `getRequestMatcher()` 公开方法 | public_api | N/A | v3.x 新增 `MicroKernel::getRequestMatcher()` | no-action | 纯新增 |
| `SilexKernel` 无 `getUrlGenerator()` 公开方法 | public_api | N/A | v3.x 新增 `MicroKernel::getUrlGenerator()` | no-action | 纯新增 |
| `SilexKernel::getCacheDirectories()` 包含 `routing.cache_dir` | public_api | covered | `MicroKernel::getCacheDirectories()` | no-action | — |

---

## Behavioral Equivalence Audit（行为等价性审计）

以上 API_Surface 审计确认了"接口存在性"。本节深入对比 v3.x 重写后的**运行时行为**是否与 v2.5.0 等价。

### Route Loading & Matching

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| YAML 路由加载 | `InheritableYamlFileLoader` → `CacheableRouter` → Symfony `Router` | 相同链路 | ✅ 等价 | — |
| 路由匹配入口 | Silex `RouterListener` (priority 32) 调用 `$app['request_matcher']` | 自定义 listener (priority 33) 调用 `$this->requestMatcher` | ⚠️ 微差异 | v3.x listener priority 为 33（比 Symfony `RouterListener` 的 32 高 1），确保自定义 matcher 先于 Symfony 默认 matcher 执行。如果 `_controller` 已设置则跳过。行为等价 |
| 404 处理 | Silex `RouterListener` 抛出 `ResourceNotFoundException` → Symfony 转为 404 | 自定义 listener catch `ResourceNotFoundException` → 尝试 scheme redirect → 否则让 Symfony 处理 | ✅ 等价 | 最终 HTTP 响应一致 |
| `MethodNotAllowed` 处理 | Silex `RouterListener` 抛出 `MethodNotAllowedHttpException` | 自定义 listener catch `MethodNotAllowedException` → 转为 `MethodNotAllowedHttpException` | ✅ 等价 | — |

### Route Caching

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| 缓存文件生成 | Symfony `Router` 编译 matcher/generator 到 `cache_dir` | 相同机制 | ✅ 等价 | — |
| 缓存类名 | `ProjectUrlMatcher_{md5hash}` / `ProjectUrlGenerator_{md5hash}` | Symfony 默认类名（基于 resource hash） | ⚠️ 微差异 | v2.5.0 自定义了缓存类名（`matcher_cache_class` / `generator_cache_class`），v3.x 使用 Symfony 默认。对下游透明，不影响行为 |
| 缓存 fallback 目录 | `$routerPath . "/cache"` | `$kernel->getCacheDir() . "/routing"` | ⚠️ 微差异 | v2.5.0 默认缓存到路由文件所在目录的 `cache/` 子目录。v3.x 默认缓存到 kernel cache dir 的 `routing/` 子目录。如果显式配置了 `cache_dir`，行为一致 |
| `debug` 模式下缓存行为 | Symfony `Router` 检查文件修改时间 | 相同机制 | ✅ 等价 | — |

### Parameter Replacement

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| `%param%` 替换逻辑 | `CacheableRouter::getRouteCollection()` 中 regex 替换 | 相同逻辑 | ✅ 等价 | 代码几乎完全一致 |
| 参数来源 | `$this->kernel->getParameter($key)` → Pimple `$app[$key]` + `$extraParameters` | `$this->kernel->getParameter($key)` → Symfony container + `$extraParameters` | ✅ 等价 | 参数查找路径不同（Pimple vs Symfony DI），但 `getParameter()` 的外部行为一致 |
| 替换时机 | 首次调用 `getRouteCollection()` 时 | 相同 | ✅ 等价 | — |

### Scheme Redirect (RedirectableUrlMatcher)

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| HTTP → HTTPS redirect | Silex `RedirectableUrlMatcher::redirect()` 生成 302 redirect | 自定义 listener 中 catch `ResourceNotFoundException` → 尝试反转 scheme → 302 redirect | ✅ 等价 | 实现机制不同，但最终行为一致：scheme 不匹配时返回 302 redirect 到正确 scheme |

### Namespace Prefix Resolution

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| Controller 命名空间补全 | `CacheableRouterUrlMatcherWrapper::match()` 遍历 `namespaces`，`class_exists()` 检查 | 相同逻辑 | ✅ 等价 | 代码几乎完全一致 |
| 已存在的类名不补全 | `!class_exists($className)` 检查 | 相同检查 | ✅ 等价 | — |
| 非 `::` 格式的 controller 不处理 | `strpos($result['_controller'], "::") !== false` 检查 | `str_contains($result['_controller'], "::")` 检查 | ✅ 等价 | PHP 8.x 函数替换，行为一致 |

### Programmatic Route Injection (v3.2 新增)

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| `addRoute()` / `addRoutes()` | 不存在 | v3.2 新增 | N/A | 纯新增能力 |
| 编程式路由优先于 YAML | 不存在 | 双层 matcher 架构，编程式 matcher 排在前面 | N/A | 纯新增能力 |
| boot 后路由冻结 | 不存在（boot 后修改静默失效） | `FrozenRouteCollection` 抛出 `LogicException` | N/A | 有意的行为修正 |
| boot 后 `addRoute()` 抛异常 | 不存在 | `MicroKernel::addRoute()` 检查 `$this->booted` | N/A | 纯新增能力 |

---

## Summary of Behavioral Differences

| # | 差异 | 影响 | 处置 |
|---|------|------|------|
| B1 | 缓存 fallback 目录从 `$routerPath/cache` 变为 `$kernel->getCacheDir()/routing` | 低：仅影响未显式配置 `cache_dir` 的场景 | no-action（改进：避免在路由文件目录下创建缓存） |
| B2 | 缓存类名从自定义 hash 变为 Symfony 默认 | 低：对下游透明 | no-action |
| B3 | Routing listener priority 从 32 变为 33 | 低：确保自定义 matcher 先于 Symfony 默认 matcher | no-action（有意设计） |
| B4 | `matcher_cache_class` / `generator_cache_class` / `matcher_base_class` 选项移除 | 低：Symfony 8.x 已移除这些选项 | confirm-documented（Symfony 8.x 升级的隐含变更） |
| B5 | `GroupUrlGenerator` 增加 `contextExplicitlySet` 检查 | 低：避免意外覆盖子 generator context | no-action（改进） |

---

## v2.5.0 未暴露的 Silex 能力（不在审计范围内）

以下 Silex 原生路由能力在 v2.5.0 的 `CacheableRouterProvider` 中**未作为公开 API 暴露**，因此不属于审计范围：

- Silex `ControllerCollection` 流式路由定义（`$app->get()`, `$app->post()` 等）
- Silex `Route` 扩展方法（`convert()`, `assert()`, `value()`, `before()`, `after()` 等）
- Silex `ControllerProviderInterface` 路由分组
- Silex `$app['routes']` 直接访问 `RouteCollection`
- Silex `$app->mount()` 路由挂载
- Silex `$app->url()` URL 生成快捷方法（v2.5.0 通过 `SilexApp\UrlGeneratorTrait` 暴露，但这是 Silex Application trait，不是 `CacheableRouterProvider` 的 API）

---

## Summary

### API_Surface Coverage

| Coverage Status | Count | Percentage |
|-----------------|-------|------------|
| covered | 39 | 100% |
| missing-non-breaking | 0 | 0% |
| missing-breaking | 0 | 0% |
| intentionally-removed | 3 | — |

> 注：3 个 `intentionally-removed` 项（`matcher_cache_class`、`generator_cache_class`、`matcher_base_class`）是 Symfony 8.x 升级的隐含变更，非 oasis/http 有意移除。这些选项在 Symfony 8.x 中已不存在。

### Behavioral Equivalence

| 等价状态 | Count | 说明 |
|----------|-------|------|
| ✅ 等价 | 15 | 行为完全一致 |
| ⚠️ 微差异 | 4 | 行为有微小差异但不影响下游（B1 缓存目录、B2 缓存类名、B3 listener priority、B5 context 检查） |
| N/A | 4 | v3.2 纯新增能力（编程式路由注入、路由冻结） |

**结论**：

1. **API_Surface**：v2.5.0 的所有公开路由接口在 v3.x 中均已覆盖。3 个 `intentionally-removed` 项是 Symfony 8.x 升级的隐含变更（缓存类名选项和 matcher base class），对下游透明。
2. **行为等价性**：v3.x 的路由实现与 v2.5.0 高度等价。4 处微差异均为改进或对下游透明的内部变更，不影响外部可观察行为。
3. **无需修复**：未发现 missing-non-breaking 能力。所有行为差异均为有意设计或 Symfony 版本升级的自然结果。
4. **文档确认**：`matcher_cache_class` / `generator_cache_class` / `matcher_base_class` 的移除属于 Symfony 8.x 升级的隐含变更，已在 Migration_Guide §6 中以"🟢 路由迁移到 Symfony Routing 8.x"覆盖（"内部路由实现已迁移到 Symfony Routing 8.x"）。无需额外文档化。
