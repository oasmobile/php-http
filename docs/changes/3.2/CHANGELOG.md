# Changelog — v3.2

> Release date: 2026-05-05

---

## v3.2.0

修复路由子系统两个缺陷：编程式路由注入 API 缺失（ISS-3.0-L01）和 boot 后路由修改静默失效（ISS-3.0-L02）。

### Added

- `MicroKernel::addRoute(string $name, Route $route): void`：boot 前编程式注入单条路由
- `MicroKernel::addRoutes(RouteCollection $routes): void`：boot 前批量注入路由集合
- `FrozenRouteCollection`（`src/ServiceProviders/Routing/FrozenRouteCollection.php`）：继承 Symfony `RouteCollection`，boot 后包装路由集合，拦截 `add()` / `addCollection()` / `remove()` / `addResource()` 写操作并抛出 `LogicException`
- `CacheableRouter::freeze()`：boot 后冻结 `getRouteCollection()` 返回值为 `FrozenRouteCollection`
- 无 YAML `routing` 配置时，`addRoute()` / `addRoutes()` 仍可注入路由（`registerRouting()` 自动初始化空路由基础设施）
- 双层 matcher 架构：编程式路由通过独立内存 `UrlMatcher` 匹配，YAML 路由走 `CacheableRouter` 编译缓存，两者通过 `GroupUrlMatcher` 串联

### Changed

- `MicroKernel::registerRouting()`：采用双层 matcher 架构，编程式路由构建独立 `UrlMatcher`（不参与缓存编译），YAML 路由走 `CacheableRouter` 编译缓存，编程式 matcher 排在前面优先匹配；编译完成后调用 `CacheableRouter::freeze()` 冻结路由集合
- `CacheableRouter::getRouteCollection()`：`freeze()` 后返回 `FrozenRouteCollection` 实例（类型兼容 `RouteCollection`）

### Fixed

- ISS-3.0-L01：MicroKernel 缺少编程式路由注入 API
- ISS-3.0-L02：boot 后 `getRouteCollection()->add()` 静默失效，现抛出 `LogicException`

### 测试覆盖

- PHPStan level 8：零错误
- 全量测试：648 tests, 21788 assertions（全部通过）
