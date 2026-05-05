# SilexKernel Public API Surface Audit

本文件审计 v2.5.0 `SilexKernel` 对外暴露的全部公共方法（含 Trait 方法），逐一确认在 v3.x `MicroKernel` 中的对等实现状态。

**审计口径**：SilexKernel 对外暴露的所有 public 方法均视为期望保留的 API surface，包括通过 Trait 引入的方法。

---

## SilexKernel 公共 API 来源

| 来源 | 方法 |
|------|------|
| `SilexKernel` 自身 | `__construct`, `addControllerInjectedArg`, `addExtraParameters`, `addMiddleware`, `after`, `before`, `boot`, `error`, `handle`, `isGranted`, `run`, `getCacheDirectories`, `getParameter`, `getToken`, `getTwig`, `getUser` |
| `SilexApp\TwigTrait` | `render`, `renderView` |
| `SilexApp\UrlGeneratorTrait` | `path`, `url` |
| `Silex\Application` 继承 | `on`, `off`, `subscribe`, `mount`, `get`, `post`, `put`, `patch`, `delete`, `options`, `match`, `flush`, `redirect`, `stream`, `escape`, `json`, `sendFile`, `abort` |

---

## 审计范围说明

- **第一层（SilexKernel 自身 + Trait）**：oasis/http 主动 use 的方法，属于核心 API surface，必须逐一审计
- **第二层（Silex\Application 继承）**：Silex 框架层方法，SilexKernel 未 override 但下游可能使用，列出但标注为框架层

---

## 第一层审计：SilexKernel 自身 + Trait 方法

| # | v2.5.0 方法 | 来源 | v3.x 状态 | v3.x 对等实现 | 判定 |
|---|-------------|------|-----------|---------------|------|
| 1 | `__construct(array $httpConfig, $isDebug)` | self | ✅ 保留 | `MicroKernel::__construct(array $httpConfig, bool $isDebug)` | covered |
| 2 | `addControllerInjectedArg($object)` | self | ✅ 保留 | `MicroKernel::addControllerInjectedArg(object $object): void` | covered |
| 3 | `addExtraParameters($extras)` | self | ✅ 保留 | `MicroKernel::addExtraParameters(array $extras): void` | covered |
| 4 | `addMiddleware(MiddlewareInterface $middleware)` | self | ✅ 保留 | `MicroKernel::addMiddleware(MiddlewareInterface $middleware): void` | covered |
| 5 | `after($callback, $priority, $masterRequestOnly)` | self | ❌ 移除 | Bootstrap Config `middlewares` + `MiddlewareInterface` | intentionally-removed / documented |
| 6 | `before($callback, $priority, $masterRequestOnly)` | self | ❌ 移除 | Bootstrap Config `middlewares` + `MiddlewareInterface` | intentionally-removed / documented |
| 7 | `boot()` | self | ✅ 保留 | `MicroKernel::boot(): void`（内部实现重写） | covered |
| 8 | `error($callback, $priority)` | self | ❌ 移除 | Bootstrap Config `error_handlers` | intentionally-removed / documented |
| 9 | `handle(Request, $type, $catch)` | self | ✅ 保留 | `MicroKernel::handle(Request, int, bool): Response` | covered |
| 10 | `isGranted($attributes, $object)` | self | ✅ 保留 | `MicroKernel::isGranted(mixed, mixed, ?AccessDecision): bool` | covered |
| 11 | `run(?Request)` | self | ✅ 保留 | `MicroKernel::run(?Request): void` | covered |
| 12 | `getCacheDirectories()` | self | ✅ 保留 | `MicroKernel::getCacheDirectories(): array` | covered |
| 13 | `getParameter($key, $default)` | self | ✅ 保留 | `MicroKernel::getParameter(string, mixed): mixed` | covered |
| 14 | `getToken()` | self | ✅ 保留 | `MicroKernel::getToken(): ?TokenInterface` | covered |
| 15 | `getTwig()` | self | ✅ 保留 | `MicroKernel::getTwig(): ?TwigEnvironment` | covered |
| 16 | `getUser()` | self | ✅ 保留 | `MicroKernel::getUser(): ?UserInterface` | covered |
| 17 | `render($view, $parameters, $response)` | TwigTrait | ❌ **缺失** | 无对等实现 | **missing — 需补充** |
| 18 | `renderView($view, $parameters)` | TwigTrait | ❌ **缺失** | 无对等实现（`getTwig()->render()` 可替代但非便捷方法） | **missing — 需补充** |
| 19 | `path($route, $parameters)` | UrlGeneratorTrait | ❌ **缺失** | 无对等实现（`getUrlGenerator()->generate()` 可替代但非便捷方法） | **missing — 需补充** |
| 20 | `url($route, $parameters)` | UrlGeneratorTrait | ❌ **缺失** | 无对等实现 | **missing — 需补充** |

---

## 第二层参考：Silex\Application 继承方法

以下方法来自 Silex\Application（继承自 Pimple + EventDispatcher），SilexKernel 未 override。v3.x 因移除 Silex 框架而全部不可用，但这些属于框架层能力而非 oasis/http 自身 API。

| 方法 | 用途 | v3.x 状态 | 说明 |
|------|------|-----------|------|
| `get/post/put/patch/delete/options/match` | 流式路由定义 | 移除 | 替代：YAML 路由 + `addRoute()` / `addRoutes()` |
| `mount` | 路由分组挂载 | 移除 | 替代：`addRoutes(RouteCollection)` |
| `on/off/subscribe` | EventDispatcher 快捷方法 | 移除 | 替代：通过 `MiddlewareInterface` 或 Symfony EventSubscriber |
| `redirect` | 生成 RedirectResponse | 移除 | 替代：直接 `new RedirectResponse(...)` |
| `json` | 生成 JsonResponse | 移除 | 替代：直接 `new JsonResponse(...)` |
| `stream` | 生成 StreamedResponse | 移除 | 替代：直接 `new StreamedResponse(...)` |
| `sendFile` | 生成 BinaryFileResponse | 移除 | 替代：直接 `new BinaryFileResponse(...)` |
| `abort` | 抛出 HttpException | 移除 | 替代：直接 `throw new HttpException(...)` |
| `escape` | HTML 转义 | 移除 | 替代：`htmlspecialchars()` |
| `flush` | 刷新 Pimple 容器 | 移除 | 不适用（无 Pimple） |

---

## 审计结论

### 第一层 Coverage

| 状态 | 数量 | 百分比 |
|------|------|--------|
| covered | 14 | 70% |
| intentionally-removed / documented | 3 | 15% |
| **missing — 需补充** | **4** | **20%** → 降为 **0%** 后达标 |

### 缺失方法清单

| 方法 | 原始签名 | 建议实现 |
|------|----------|----------|
| `render` | `render($view, array $parameters = [], Response $response = null): Response` | 委托 `$this->twigEnvironment->render()` + StreamedResponse 支持 |
| `renderView` | `renderView($view, array $parameters = []): string` | 委托 `$this->twigEnvironment->render()` |
| `path` | `path($route, $parameters = []): string` | 委托 `$this->urlGenerator->generate(..., ABSOLUTE_PATH)` |
| `url` | `url($route, $parameters = []): string` | 委托 `$this->urlGenerator->generate(..., ABSOLUTE_URL)` |

### 第二层评估

第二层方法（Silex\Application 继承）均为框架层便捷方法，v3.x 已通过以下方式提供替代：
- 路由定义 → YAML + `addRoute()` / `addRoutes()`
- Response 工厂 → 直接使用 Symfony HttpFoundation 类
- EventDispatcher → `MiddlewareInterface` + Bootstrap Config

这些方法的移除已在 Migration Guide 中文档化，不建议在 MicroKernel 上重新实现（会引入不必要的 API 膨胀）。

---

## Action Items

1. **实现 `path()` / `url()` / `render()` / `renderView()`** — 作为 MicroKernel 公共方法
2. **更新 Migration Guide** — 将这 4 个方法从 "移除" 改为 "保留"，移除迁移指引
3. **更新公共 API 方法列表** — 在 Migration Guide §3 的方法表中补充这 4 个方法
