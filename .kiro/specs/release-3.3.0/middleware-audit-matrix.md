# Middleware Module Audit Matrix

> Silex Migration Behavior Audit — Middleware 模块
>
> 审计基准：`oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0

---

## 审计方法

- **接口存在性审计**：枚举 v2.5.0 `SilexKernel` + `MiddlewareInterface` + `AbstractMiddleware` 暴露的 middleware 相关 API_Surface，逐项对比 v3.x `MicroKernel` + `MiddlewareInterface` + `AbstractMiddleware` 实现
- **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（event listener 注册方式、priority 映射、short-circuit 机制、after middleware 的 response 修改时机等）

---

## Audit Matrix

### Registration（注册机制）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `SilexKernel::addMiddleware(MiddlewareInterface)` | registration | covered | `MicroKernel::addMiddleware(MiddlewareInterface)` | no-action | 签名一致，行为等价 |
| Bootstrap_Config `middlewares` key 接受 `MiddlewareInterface[]` | registration | covered | `MicroKernel::parseBootstrapConfig()` | no-action | 验证逻辑一致：非 `MiddlewareInterface` 实例抛 `InvalidConfigurationException` |
| `SilexKernel::before($callback, $priority, $masterRequestOnly)` 公共方法 | registration | intentionally-removed | N/A | confirm-documented | Migration_Guide §3 "Kernel API" 和 §8 "Middleware" 已标注移除。v3.x 仅通过 `addMiddleware()` 注册 |
| `SilexKernel::after($callback, $priority, $masterRequestOnly)` 公共方法 | registration | intentionally-removed | N/A | confirm-documented | Migration_Guide §3 "Kernel API" 和 §8 "Middleware" 已标注移除。v3.x 仅通过 `addMiddleware()` 注册 |
| Silex `CallbackResolver` 解析 service reference 字符串（如 `"service:method"`） | registration | intentionally-removed | N/A | confirm-documented | Pimple DI 容器移除，`CallbackResolver` 不再需要。Migration_Guide §4 "DI Container" 已标注 |

### Interface（接口契约）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `MiddlewareInterface::onlyForMasterRequest(): bool` | interface | covered | `MiddlewareInterface::onlyForMasterRequest(): bool` | no-action | 签名一致 |
| `MiddlewareInterface::before(Request, Application): Response\|null` | interface | covered | `MiddlewareInterface::before(Request, MicroKernel): Response\|null` | no-action | 第二参数类型从 `Silex\Application` 变为 `MicroKernel`，Migration_Guide §8 已标注。行为等价 |
| `MiddlewareInterface::after(Request, Response): void` | interface | covered | `MiddlewareInterface::after(Request, Response): void` | no-action | v2.5.0 无返回类型声明，v3.x 显式声明 `void`。见下方行为等价性分析 |
| `MiddlewareInterface::getBeforePriority(): int\|false` | interface | covered | `MiddlewareInterface::getBeforePriority(): int\|false` | no-action | 签名一致（v3.x 使用 union type 语法） |
| `MiddlewareInterface::getAfterPriority(): int\|false` | interface | covered | `MiddlewareInterface::getAfterPriority(): int\|false` | no-action | 签名一致（v3.x 使用 union type 语法） |
| `AbstractMiddleware::onlyForMasterRequest()` 默认返回 `true` | interface | covered | `AbstractMiddleware::onlyForMasterRequest(): bool` 返回 `true` | no-action | 默认值一致 |
| `AbstractMiddleware::getBeforePriority()` 默认返回 `Application::EARLY_EVENT` (512) | interface | covered | `AbstractMiddleware::getBeforePriority(): int\|false` 返回 `MicroKernel::BEFORE_PRIORITY_EARLIEST` (512) | no-action | 数值一致（512），常量名变更已在 Migration_Guide §8 标注 |
| `AbstractMiddleware::getAfterPriority()` 默认返回 `Application::LATE_EVENT` (-512) | interface | covered | `AbstractMiddleware::getAfterPriority(): int\|false` 返回 `MicroKernel::AFTER_PRIORITY_LATEST` (-512) | no-action | 数值一致（-512），常量名变更已在 Migration_Guide §8 标注 |

### Priority（优先级机制）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `Application::EARLY_EVENT` = 512 | priority | covered | `MicroKernel::BEFORE_PRIORITY_EARLIEST` = 512, `MicroKernel::AFTER_PRIORITY_EARLIEST` = 512 | no-action | 数值一致，常量名变更已在 Migration_Guide §8 标注 |
| `Application::LATE_EVENT` = -512 | priority | covered | `MicroKernel::BEFORE_PRIORITY_LATEST` = -512, `MicroKernel::AFTER_PRIORITY_LATEST` = -512 | no-action | 数值一致，常量名变更已在 Migration_Guide §8 标注 |
| Before middleware 按 priority 降序执行（higher priority = earlier execution） | priority | covered | `registerMiddlewares()` 使用 `$dispatcher->addListener(KernelEvents::REQUEST, ..., $priority)` | no-action | Symfony EventDispatcher 按 priority 降序调度，与 Silex `$app->on()` 行为一致 |
| After middleware 按 priority 降序执行 | priority | covered | `registerMiddlewares()` 使用 `$dispatcher->addListener(KernelEvents::RESPONSE, ..., $priority)` | no-action | 同上 |
| `getBeforePriority()` 返回 `false` → 不注册 before listener | priority | covered | `registerMiddlewares()` 中 `if (false !== ($priority = $middleware->getBeforePriority()))` | no-action | 逻辑一致 |
| `getAfterPriority()` 返回 `false` → 不注册 after listener | priority | covered | `registerMiddlewares()` 中 `if (false !== ($priority = $middleware->getAfterPriority()))` | no-action | 逻辑一致 |

### Filtering（请求过滤）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `onlyForMasterRequest() = true` → before middleware 仅对 MASTER_REQUEST 执行 | filtering | covered | `registerMiddlewares()` 中 `if ($middleware->onlyForMasterRequest() && $event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) return;` | no-action | 行为等价。`MASTER_REQUEST` 重命名为 `MAIN_REQUEST`（Symfony 8.x），Migration_Guide §8 已标注 |
| `onlyForMasterRequest() = true` → after middleware 仅对 MASTER_REQUEST 执行 | filtering | covered | 同上，after listener 中同样检查 | no-action | 行为等价 |
| `onlyForMasterRequest() = false` → middleware 对所有请求类型执行（含 SUB_REQUEST） | filtering | covered | `registerMiddlewares()` 中 `onlyForMasterRequest()` 为 `false` 时跳过检查 | no-action | 行为等价 |

### Short-Circuit（短路机制）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| Before middleware 返回 `Response` → `$event->setResponse($ret)` → controller 不执行 | short_circuit | covered | `registerMiddlewares()` 中 `if ($ret instanceof Response) { $event->setResponse($ret); }` | no-action | 行为等价。Symfony `RequestEvent::setResponse()` 会阻止后续 listener 和 controller 执行 |

### After Middleware（后置中间件行为）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| After middleware 可通过 `$response` 对象就地修改 response（如 `$response->headers->set()`） | after | covered | `registerMiddlewares()` 中 `$middleware->after($event->getRequest(), $event->getResponse())` | no-action | Response 对象按引用传递，就地修改生效 |
| v2.5.0 `SilexKernel::after()` 回调返回 `Response` → 替换 event response | after | intentionally-removed | N/A | confirm-documented | v3.x `MiddlewareInterface::after()` 返回 `void`，不支持通过返回值替换 response。此能力仅通过 `SilexKernel::after()` 公共方法可用（已移除），`MiddlewareInterface::after()` 在 v2.5.0 中也无返回类型声明。Migration_Guide §3 已标注 `after()` 方法移除 |
| v2.5.0 `SilexKernel::after()` 回调返回非 null 非 Response → 抛 `RuntimeException` | after | intentionally-removed | N/A | confirm-documented | 同上，`after()` 公共方法已移除 |
| v2.5.0 `SilexKernel::after()` 回调接收 3 个参数 `(Request, Response, Application)` | after | intentionally-removed | N/A | confirm-documented | `MiddlewareInterface::after()` 始终只接收 2 个参数 `(Request, Response)`。v2.5.0 中第 3 个参数 `$app` 通过 `SilexKernel::after()` 包装传入，但 `MiddlewareInterface::after()` 实现会忽略多余参数。Migration_Guide §3 已标注 |

### Exception（异常处理）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| Before middleware 抛异常 → Symfony `KernelEvents::EXCEPTION` 事件触发 → Error_Handler_Chain 处理 | exception | covered | Symfony HttpKernel 的标准异常处理流程，`registerErrorHandlers()` 注册的 listener 处理 | no-action | 行为等价。v2.5.0 通过 Silex 的 `ExceptionHandler` 处理，v3.x 通过 Symfony HttpKernel 的 `ExceptionEvent` 处理，最终效果一致 |

### Event Registration（事件注册方式）

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| v2.5.0 `addMiddleware()` 调用 `$this->before()` / `$this->after()` 注册 listener | event_registration | covered | v3.x `registerMiddlewares()` 直接调用 `$dispatcher->addListener()` | no-action | 注册方式不同但效果等价。v2.5.0 通过 Silex `Application::on()` 间接注册到 EventDispatcher，v3.x 直接注册到 Symfony EventDispatcher |
| v2.5.0 middleware 在 `addMiddleware()` 调用时立即注册 listener | event_registration | covered | v3.x middleware 在 `boot()` → `registerMiddlewares()` 时注册 listener | no-action | 注册时机不同但行为等价——v2.5.0 的 `addMiddleware()` 在 `boot()` 前调用，listener 在 `boot()` 后才生效；v3.x 的 `registerMiddlewares()` 在 `boot()` 中调用，效果一致 |
| v2.5.0 使用 `FilterResponseEvent` / `GetResponseEvent` 事件类 | event_registration | covered | v3.x 使用 `ResponseEvent` / `RequestEvent` 事件类 | no-action | Symfony 8.x 事件类重命名，Migration_Guide §8 已标注 |

---

## 行为等价性分析

### 1. Before Middleware 执行流程

| 步骤 | v2.5.0 行为 | v3.x 行为 | 等价性 |
|------|------------|-----------|--------|
| 注册 | `addMiddleware()` → `$this->before([$middleware, 'before'], $priority, $masterRequestOnly)` → `$this->on(KernelEvents::REQUEST, ...)` | `registerMiddlewares()` → `$dispatcher->addListener(KernelEvents::REQUEST, ..., $priority)` | ✅ 等价 |
| Master-request 过滤 | `if ($masterRequestOnly && MASTER_REQUEST !== $event->getRequestType()) return;` | `if ($middleware->onlyForMasterRequest() && $event->getRequestType() !== MAIN_REQUEST) return;` | ✅ 等价（`MASTER_REQUEST` 重命名为 `MAIN_REQUEST`） |
| 回调调用 | `call_user_func($resolver->resolveCallback($callback), $event->getRequest(), $app)` | `$middleware->before($event->getRequest(), $this)` | ✅ 等价（`$app` → `$this` 即 MicroKernel） |
| Short-circuit | `if ($ret instanceof Response) { $event->setResponse($ret); }` | `if ($ret instanceof Response) { $event->setResponse($ret); }` | ✅ 等价 |

### 2. After Middleware 执行流程

| 步骤 | v2.5.0 行为 | v3.x 行为 | 等价性 |
|------|------------|-----------|--------|
| 注册 | `addMiddleware()` → `$this->after([$middleware, 'after'], $priority, $masterRequestOnly)` → `$this->on(KernelEvents::RESPONSE, ...)` | `registerMiddlewares()` → `$dispatcher->addListener(KernelEvents::RESPONSE, ..., $priority)` | ✅ 等价 |
| Master-request 过滤 | `if ($masterRequestOnly && MASTER_REQUEST !== $event->getRequestType()) return;` | `if ($middleware->onlyForMasterRequest() && $event->getRequestType() !== MAIN_REQUEST) return;` | ✅ 等价 |
| 回调调用 | `call_user_func($resolver->resolveCallback($callback), $event->getRequest(), $event->getResponse(), $app)` — 传 3 个参数 | `$middleware->after($event->getRequest(), $event->getResponse())` — 传 2 个参数 | ✅ 等价（`MiddlewareInterface::after()` 只接受 2 个参数，第 3 个参数在 v2.5.0 中被忽略） |
| Response 就地修改 | 通过 `$response` 对象引用修改 | 通过 `$response` 对象引用修改 | ✅ 等价 |
| Response 替换（返回值） | `if ($response instanceof Response) { $event->setResponse($response); }` | N/A（`after()` 返回 `void`） | ⚠️ 差异——但仅影响 `SilexKernel::after()` 公共方法的回调，不影响 `MiddlewareInterface` 实现 |

### 3. Priority 数值映射

| 常量 | v2.5.0 值 | v3.x 值 | 等价性 |
|------|----------|---------|--------|
| `BEFORE_PRIORITY_EARLIEST` / `EARLY_EVENT` | 512 | 512 | ✅ 等价 |
| `BEFORE_PRIORITY_LATEST` / `LATE_EVENT` | -512 | -512 | ✅ 等价 |
| `AFTER_PRIORITY_EARLIEST` / `EARLY_EVENT` | 512 | 512 | ✅ 等价 |
| `AFTER_PRIORITY_LATEST` / `LATE_EVENT` | -512 | -512 | ✅ 等价 |
| `BEFORE_PRIORITY_ROUTING` | 32 | 32 | ✅ 等价 |
| `BEFORE_PRIORITY_CORS_PREFLIGHT` | 20 | 20 | ✅ 等价 |
| `BEFORE_PRIORITY_FIREWALL` | 8 | 8 | ✅ 等价 |

---

## 审计结论

### 统计

| Coverage Status | 数量 |
|-----------------|------|
| covered | 21 |
| missing-non-breaking | 0 |
| missing-breaking | 0 |
| intentionally-removed | 5 |

### 处置汇总

| Disposition | 数量 | 说明 |
|-------------|------|------|
| no-action | 21 | 行为等价，无需操作 |
| confirm-documented | 5 | 已在 Migration_Guide 中标注 |
| fix-code | 0 | 无需修复 |
| document-only | 0 | 无需补充文档 |

### Intentionally-Removed 项确认

以下 5 项已确认在 Migration_Guide 中有对应文档：

1. **`SilexKernel::before()` 公共方法** → Migration_Guide §3 "🔴 `SilexKernel::before()` / `after()` / `error()` 便捷方法移除" + §8 "Middleware"
2. **`SilexKernel::after()` 公共方法** → 同上
3. **Silex `CallbackResolver`** → Migration_Guide §4 "DI Container"（Pimple 移除）
4. **`after()` 回调返回 Response 替换 event response** → Migration_Guide §3（`after()` 方法移除）
5. **`after()` 回调返回非 null 非 Response 抛 RuntimeException** → 同上

### 结论

Middleware 模块的 v3.x 实现与 v2.5.0 行为完全等价（通过 `MiddlewareInterface` 使用的场景）。所有差异均为有意移除的 Silex 特有 API（`before()` / `after()` 公共方法、`CallbackResolver`），已在 Migration_Guide 中充分文档化。**无需修复代码，无需补充文档。**
