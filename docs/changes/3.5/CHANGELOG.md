# Changelog v3.5

恢复 Silex 时代的 `before()` / `after()` / `error()` 便捷方法。

---

## Added

- `MicroKernel::before(callable $callback, int $priority = 0, bool $masterRequestOnly = true): void` — 注册 before 过滤器回调，等价于 `addMiddleware()` 的语法糖
- `MicroKernel::after(callable $callback, int $priority = 0, bool $masterRequestOnly = true): void` — 注册 after 过滤器回调，回调签名 `(Request, Response, MicroKernel)`
- `MicroKernel::error(callable $callback, int $priority = -8): void` — 注册错误处理器回调，等价于 Bootstrap Config `error_handlers` 的语法糖；支持 boot 后动态注册
- `Oasis\Mlib\Http\Middlewares\CallbackMiddleware` — 内部类，包装 `before()` / `after()` 回调为 `MiddlewareInterface` 实现

## Context

v3.0 迁移时，`SilexKernel::before()` / `after()` / `error()` 因属于 Silex Application 继承方法而被移除，替代方案分别为 `MiddlewareInterface` + `addMiddleware()` 和 Bootstrap Config `error_handlers`。

实际使用中，这三个便捷方法是下游最常用的 API 之一，移除增加了迁移成本。v3.4 已恢复 `render()` / `renderView()` / `path()` / `url()`，本版本延续同一策略，恢复剩余三个便捷方法。

## Migration Impact

- 已迁移到 `MiddlewareInterface` / `error_handlers` 的代码无需修改，两种方式等价共存
- 新代码可直接使用便捷方法，无需实现完整接口
