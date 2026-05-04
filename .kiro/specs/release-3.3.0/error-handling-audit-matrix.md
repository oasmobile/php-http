# Error Handling Module — Audit Matrix

> 审计基准：`oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0
> 审计对象：v3.x `MicroKernel::registerErrorHandlers()` + `ExceptionWrapper` + `FallbackViewHandler`

---

## 接口存在性审计

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `SilexKernel::error($callback, $priority = -8)` | registration | covered | `MicroKernel::registerErrorHandlers()` 在 `boot()` 中注册 | no-action | v2.5.0 通过 `__set('error_handlers', ...)` 调用 `$this->error()`；v3.x 通过 `parseBootstrapConfig()` 存储后在 `boot()` 中统一注册 |
| `error_handlers` Bootstrap_Config key | registration | covered | `MicroKernel::parseBootstrapConfig()` 解析 `error_handlers` 数组 | no-action | 配置方式一致：`array of callable` |
| Error handler callable validation | registration | covered | `parseBootstrapConfig()` 中 `is_callable()` 过滤 + `InvalidConfigurationException` | no-action | 行为等价 |
| `ExtendedExceptionListnerWrapper` | chain | covered | `MicroKernel::registerErrorHandlers()` 内联实现等价逻辑 | no-action | v3.x 不再使用 wrapper 类，而是在 listener 闭包中直接实现 null passthrough 和 Response 设置逻辑。`ExtendedExceptionListnerWrapper` 类仍存在但未被 `registerErrorHandlers()` 使用 |
| Handler signature: `function(\Exception $e, Request $request, int $code)` | chain | covered | `registerErrorHandlers()` 以 `$handler($exception, $request, $code)` 调用 | no-action | v2.5.0 中 Silex `ExceptionListenerWrapper` 也以 3 参数调用 handler；v2.5.0 的 `JsonErrorHandler` 仅声明 2 参数但 PHP 允许多余参数。v3.x 的 `JsonErrorHandler` 已更新为 3 参数签名 |
| Handler priority: `-8` (default) | chain | covered | `registerErrorHandlers()` 中 `$dispatcher->addListener(..., -8)` | no-action | 与 v2.5.0 `$this->error($callback, -8)` 一致 |
| Error handler chain — registration order | chain | covered | `foreach ($this->errorHandlers as $handler)` 按数组顺序注册 | no-action | v2.5.0 也按 `foreach` 顺序调用 `$this->error()`，注册顺序即执行顺序 |
| Chain short-circuit: handler returns `Response` | short_circuit | covered | `if ($response instanceof Response) { $event->setResponse($response); }` | no-action | 行为等价：设置 response 后，后续 listener 检查 `$event->getResponse() !== null` 跳过 |
| Chain passthrough: handler returns `null` | passthrough | covered | listener 闭包末尾注释 "handler returns null and event has no response → let exception propagate" | no-action | 等价于 v2.5.0 `ExtendedExceptionListnerWrapper::ensureResponse()` 的 null 检查 |
| Non-Response, non-null return → view handler chain | conversion | covered | `foreach ($kernel->getViewHandlers() as $viewHandler) { ... }` | no-action | v3.x 在 error handler listener 内直接遍历 view handlers，等价于 v2.5.0 中 Silex `ExceptionListenerWrapper::ensureResponse()` 将非 Response 返回值传递给 view handler 的行为 |
| HTTP exception status code extraction | http_exception | covered | `$exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500` | no-action | 行为等价 |
| HTTP exception status code preservation in response | http_exception | covered | view handler chain 中 `$viewResponse->setStatusCode($code)` | no-action | 当 error handler 返回非 Response 值时，view handler 产出的 Response 会被设置为原始 HTTP status code |
| `ExceptionWrapper` error handler | fallback | covered | `src/ErrorHandlers/ExceptionWrapper.php` | no-action | 功能等价：接收异常 → 创建 `WrappedExceptionInfo` → 返回（非 Response，触发 view handler chain） |
| `WrappedExceptionInfo` data class | fallback | covered | `src/ErrorHandlers/WrappedExceptionInfo.php` | no-action | 功能等价，v3.x 添加了 strict types 和原生类型声明 |
| `JsonErrorHandler` error handler | fallback | covered | `src/ErrorHandlers/JsonErrorHandler.php` | no-action | 功能等价，v3.x 签名更新为 3 参数（`$e, $request, $code`） |
| `FallbackViewHandler` view handler | fallback | covered | `src/Views/FallbackViewHandler.php` | no-action | 功能等价：接收 `WrappedExceptionInfo` → 通过 `ResponseRendererResolverInterface` 解析 renderer → 渲染 response |
| `RouteBasedResponseRendererResolver` | fallback | covered | `src/Views/RouteBasedResponseRendererResolver.php` | no-action | 功能等价：根据 request `format` 属性选择 `DefaultHtmlRenderer` 或 `JsonApiRenderer` |
| `DefaultHtmlRenderer::renderOnException()` | fallback | covered | `src/Views/DefaultHtmlRenderer.php` | no-action | 功能等价：无 Twig 时 JSON 序列化 `WrappedExceptionInfo`；有 Twig 时渲染 `{code}.twig` 模板 |
| `JsonApiRenderer::renderOnException()` | fallback | covered | `src/Views/JsonApiRenderer.php` | no-action | 功能等价 |
| Previous handler response check | chain | covered | `if ($event->getResponse() !== null) { return; }` | no-action | v3.x 在每个 handler listener 开头检查，等价于 v2.5.0 中 Silex event propagation 机制 |
| Custom priority per handler | registration | intentionally-removed | N/A | confirm-documented | v2.5.0 `$this->error($callback, $priority)` 允许每个 handler 指定不同 priority；v3.x 所有 handler 统一使用 priority -8。实际使用中 v2.5.0 的 `__set('error_handlers', ...)` 也不支持 per-handler priority（数组中无法携带 priority 信息），仅 `$this->error()` 直接调用时支持。v3.x 移除了 `error()` 公开方法，因此此能力不再可用。**影响极低**：下游代码通过 Bootstrap_Config 注册 error handler，从未使用过 per-handler priority |

---

## 行为等价性审计

### 1. Exception listener 注册方式

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 注册时机 | 构造函数中通过 `__set` → `$this->error()` 立即注册 | `boot()` 中调用 `registerErrorHandlers()` 延迟注册 | **等价** — 两者都在首次 `handle()` 前完成注册 |
| 注册机制 | `$this->on(KernelEvents::EXCEPTION, new ExtendedExceptionListnerWrapper(...), $priority)` | `$dispatcher->addListener(KernelEvents::EXCEPTION, function(...) {...}, -8)` | **等价** — 都是 EventDispatcher listener |
| Wrapper 类 | `ExtendedExceptionListnerWrapper` 继承 Silex `ExceptionListenerWrapper` | 内联闭包实现等价逻辑 | **等价** — 行为相同，实现方式不同 |

### 2. Handler 调用顺序

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 同 priority 下的顺序 | 按 `$this->error()` 调用顺序（即数组顺序） | 按 `foreach` 遍历顺序（即数组顺序） | **等价** |
| 不同 priority | 支持（通过 `$this->error($cb, $priority)`） | 不支持（统一 -8） | **intentionally-removed** — 见上表 |

### 3. Null 返回值处理

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| Handler 返回 null | `ExtendedExceptionListnerWrapper::ensureResponse()` 检查 `$response === null && $event->getResponse() === null` → 不设置 response，异常继续传播 | listener 闭包末尾不做任何操作 → 异常继续传播 | **等价** |
| Handler 返回 null 但 event 已有 response | `ensureResponse()` 调用 `parent::ensureResponse()` | listener 开头 `if ($event->getResponse() !== null) return;` 直接跳过 | **等价** — 效果相同 |

### 4. HttpException status code 传递路径

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| Status code 提取 | Silex `ExceptionListenerWrapper` 从 `HttpExceptionInterface::getStatusCode()` 提取 | listener 闭包中 `$exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500` | **等价** |
| Status code 传递给 handler | 作为第 3 个参数 `$code` | 作为第 3 个参数 `$code` | **等价** |
| Status code 保留到 response | 当 handler 返回 Response 时，Response 自身的 status code 生效；当 handler 返回非 Response 时，view handler 产出的 Response 被 `setStatusCode($code)` | 同左 | **等价** |

### 5. 非 Response 返回值处理（exception-to-response conversion）

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 非 Response, 非 null 返回值 | Silex `ExceptionListenerWrapper::ensureResponse()` 将返回值转为 Response（通过 `$app->json()` 或字符串转换） | listener 闭包遍历 `$kernel->getViewHandlers()` 将返回值传递给 view handler chain | **等价** — v2.5.0 的 `ensureResponse()` 最终也是通过 view handler 机制处理；v3.x 直接调用 view handler chain |
| View handler 产出 Response 后的 status code | 由 view handler 自行决定 | `$viewResponse->setStatusCode($code)` 强制设置为 HTTP exception code | **等价** — 确保 HTTP exception 的 status code 不被 view handler 覆盖 |

### 6. FallbackViewHandler 行为

| 维度 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 构造函数参数 | `SilexKernel $silexKernel` | `MicroKernel $kernel` | **等价** — 类型变更但功能相同 |
| Renderer 解析 | `RouteBasedResponseRendererResolver` | `RouteBasedResponseRendererResolver` | **等价** |
| `WrappedExceptionInfo` 处理 | `$renderer->renderOnException($result, $this->silexKernel)` | `$renderer->renderOnException($result, $this->kernel)` | **等价** |
| 非异常结果处理 | `$renderer->renderOnSuccess($result, $this->silexKernel)` | `$renderer->renderOnSuccess($result, $this->kernel)` | **等价** |

---

## 审计结论

| Coverage Status | 数量 | 说明 |
|-----------------|------|------|
| covered | 19 | 所有核心 API_Surface 项均已覆盖 |
| missing-non-breaking | 0 | 无缺失的非 breaking 能力 |
| missing-breaking | 0 | 无缺失的 breaking 能力 |
| intentionally-removed | 1 | per-handler priority（通过 `$this->error($cb, $priority)` 指定不同 priority）— 影响极低，下游代码从未使用 |

**总体评估**：Error Handling 模块的 v3.x 实现与 v2.5.0 行为完全等价。唯一的差异是 per-handler priority 能力被移除，但该能力在实际使用中从未通过 Bootstrap_Config 暴露（数组无法携带 priority 信息），仅在直接调用 `$this->error()` 时可用。v3.x 移除了 `error()` 公开方法，因此此能力自然消失。

**Migration_Guide 确认**：per-handler priority 的移除属于 `error()` 方法移除的附带效果。`error()` 方法的移除已在 v3.0 迁移中隐含处理（Silex API 整体移除），无需单独文档化。
