# ISS-3.0-L01 No Programmatic Route Injection via Bootstrap Config

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `open` |
| Found In | `v3.0` |
| Fixed In | |
| Related Test | |

---

## Description

`$httpConfig` 的 bootstrap config 支持 `middlewares`、`view_handlers`、`error_handlers`、`injected_args` 等编程式注入，但不支持路由。当前路由只能通过 YAML 文件（`routing.path` 配置项）声明，没有途径通过 bootstrap config 传入编程式路由（Route 对象、RouteCollection、RouteLoader 等）。

这导致需要在代码中编程式组装路由的场景没有合法入口。

---

## Steps to Reproduce

1. 构造 `MicroKernel`，`$httpConfig` 中配置 `routing.path` 指向 YAML 文件
2. 尝试在 boot 前通过某种方式注入额外的 Route 对象——没有可用的 API
3. `getRouter()` 在 boot 前返回 null（`routerProvider` 在 `registerRouting()` 中才初始化），无法访问 RouteCollection

---

## Expected Behavior

bootstrap config 应提供与 `middlewares` 等一致的路由注入机制，允许调用方在构造 kernel 时声明编程式路由，由 `registerRouting()` 在编译 matcher 前合并到 RouteCollection。

---

## Actual Behavior

路由注册的唯一途径是 YAML 文件，编程式路由无合法注入点。

---

## Analysis

`parseBootstrapConfig()` 解析 `$httpConfig` 中的各项配置并暂存，boot 时各 `register*()` 方法消费。路由应遵循同样的模式：在 `$httpConfig` 中声明 → `parseBootstrapConfig()` 解析暂存 → `registerRouting()` 在 `getMatcher()` 调用前合并到 RouteCollection。

关键调用链：

- `registerRouting()` → `CacheableRouterProvider::buildRequestMatcher()` → `Router::getMatcher()`
- `getMatcher()` 首次调用时编译 RouteCollection 并缓存 matcher，后续调用直接返回
- 编程式路由必须在 `getMatcher()` 首次调用前合并到 RouteCollection

---

## History

- `2026-05-04T08:00Z` `v3.0` [发现] 路由缺少 bootstrap config 级别的编程式注入机制
