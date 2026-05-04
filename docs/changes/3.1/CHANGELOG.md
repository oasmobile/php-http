# Changelog — v3.1

> Release date: 2026-05-03

---

## v3.1.1 — Hotfix

修复路由子系统两个缺陷：编程式路由注入 API 缺失（ISS-3.0-L01）和 boot 后路由修改静默失效（ISS-3.0-L02）。

### Added

- `MicroKernel::addRoute(string $name, Route $route): void`：boot 前编程式注入单条路由
- `MicroKernel::addRoutes(RouteCollection $routes): void`：boot 前批量注入路由集合
- `FrozenRouteCollection`（`src/ServiceProviders/Routing/FrozenRouteCollection.php`）：继承 Symfony `RouteCollection`，boot 后包装路由集合，拦截 `add()` / `addCollection()` / `remove()` / `addResource()` 写操作并抛出 `LogicException`
- `CacheableRouter::freeze()`：boot 后冻结 `getRouteCollection()` 返回值为 `FrozenRouteCollection`
- 无 YAML `routing` 配置时，`addRoute()` / `addRoutes()` 仍可注入路由（`registerRouting()` 自动初始化空路由基础设施）

### Changed

- `MicroKernel::registerRouting()`：在 `buildRequestMatcher()` 调用前合并 `pendingRoutes`，编译完成后调用 `CacheableRouter::freeze()` 冻结路由集合
- `CacheableRouter::getRouteCollection()`：`freeze()` 后返回 `FrozenRouteCollection` 实例（类型兼容 `RouteCollection`）

### Fixed

- ISS-3.0-L01：MicroKernel 缺少编程式路由注入 API
- ISS-3.0-L02：boot 后 `getRouteCollection()->add()` 静默失效，现抛出 `LogicException`

---

## v3.1.0

> Release date: 2026-05-03

### Summary

Symfony 全系依赖从 `^7.2` 升级到 `^8.0`，适配 Symfony 8.0 的 breaking changes。

### Changed

- `composer.json` 所有 Symfony 组件版本约束：`^7.2` → `^8.0`（含 require 和 require-dev）
- `MicroKernel::isGranted()` 签名适配 Symfony 8.0 `AuthorizationCheckerInterface`：新增第三参数 `?AccessDecision $accessDecision = null`
- `docs/state/architecture.md` 移除 Symfony 版本号硬编码引用

### Removed

- 测试 user 类中的 `eraseCredentials()` 方法（Symfony 8.0 从 `UserInterface` 中移除了该方法）

### 测试覆盖

- PHPStan level 8：零错误
- 全量测试：593 tests, 21093 assertions（全部通过）
