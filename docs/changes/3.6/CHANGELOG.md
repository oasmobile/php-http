# Changelog v3.6

恢复 Silex 第二层便捷方法（Response 工厂 + view handler 注册 + abort）。

---

## Added

- `MicroKernel::view(callable $callback): void` — 注册 view handler，控制器返回非 Response 值时调用
- `MicroKernel::abort(int $statusCode, string $message = '', array $headers = []): never` — 抛出 HttpException 终止请求
- `MicroKernel::redirect(string $url, int $status = 302): RedirectResponse` — 创建重定向响应
- `MicroKernel::json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse` — 创建 JSON 响应
- `MicroKernel::stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse` — 创建流式响应
- `MicroKernel::sendFile(string|\SplFileInfo $file, int $status = 200, array $headers = [], ?string $contentDisposition = null): BinaryFileResponse` — 创建文件下载响应

## Context

v3.4 恢复了第一层 Trait 方法（`render` / `renderView` / `path` / `url`），v3.5 恢复了第一层回调注册方法（`before` / `after` / `error`）。本版本恢复第二层中有实际使用价值的 6 个便捷方法。

分析报告见 `docs/notes/silex-layer2-convenience-methods-analysis.md`。

## Not Restored

以下第二层方法经评估不恢复：

- `on()` / `off()` / `subscribe()` — 暴露底层 EventDispatcher 破坏封装
- `mount()` — 依赖已移除的 ControllerCollection 概念
- `get/post/put/patch/delete/options/match` — 流式路由 DSL，成本高且与 YAML 缓存冲突
- `escape()` — `htmlspecialchars()` 的无附加值包装
- `flush()` — Pimple 专属，不适用
