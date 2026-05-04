# ISS-3.0-L02 Route Add After Boot Silently Ineffective

| 字段 | 值 |
|------|-----|
| Severity | `[P2] minor` |
| Status | `closed` |
| Found In | `v3.0` |
| Fixed In | `v3.2.0` |
| Related Test | `ut/Routing/MicroKernelRouteInjectionTest.php`, `ut/Routing/FrozenRouteCollectionTest.php` |

---

## Description

boot 完成后，`getRouter()->getRouteCollection()->add()` 在语法上可以调用且不报错，但新增路由对已编译的 matcher 完全不可见。这是一个静默失败的陷阱——调用方以为路由已注册，实际上请求永远匹配不到。

---

## Steps to Reproduce

1. 构造并 boot `MicroKernel`
2. 调用 `$kernel->getRouter()->getRouteCollection()->add('dynamic_route', new Route('/dynamic', ['_controller' => ...]))`
3. 发送 `GET /dynamic` 请求

---

## Expected Behavior

boot 后对 RouteCollection 的写操作应抛出异常，明确拒绝修改。

---

## Actual Behavior

`add()` 调用成功，无异常、无警告，但请求返回 404。

---

## Analysis

`registerRouting()` 中 `CacheableRouterProvider::buildRequestMatcher()` 调用 `Router::getMatcher()`，该方法首次调用时编译 RouteCollection 为 `CompiledUrlMatcher` 并缓存到 `$this->matcher`。后续调用直接返回缓存的 matcher，不再读取 RouteCollection。

因此 boot 后通过 `getRouteCollection()->add()` 添加的路由只存在于 RouteCollection 对象中，不会反映到已编译的 matcher。

`MicroKernel::getRouter()` 返回的是 Symfony `Router` 实例，其 `getRouteCollection()` 是公开 API，调用方没有理由预期 `add()` 会静默失效。

---

## History

- `2026-05-04T08:00Z` `v3.0` [发现] boot 后路由修改静默无效
- `2026-05-05T00:00Z` `v3.2.0` [修复] boot 后写操作抛出 `LogicException`（双层冻结：MicroKernel 层 + FrozenRouteCollection 层）
