# CORS Module Audit Matrix

> 审计基准：`oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0
> 审计对象：v3.x `CrossOriginResourceSharingProvider`（`EventSubscriberInterface` 实现）
> 审计时间：2025-07-16

---

## API_Surface 对比

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `CrossOriginResourceSharingProvider` 作为 `ServiceProviderInterface` + `BootableProviderInterface` 注册 | registration | covered | `EventSubscriberInterface` 实现，通过 `MicroKernel::registerCors()` 注册 | no-action | 注册方式从 Pimple ServiceProvider 变为 EventSubscriber，行为等价 |
| `$app['cors.strategies']` 配置读取 | registration | covered | 构造函数直接接受 strategies 数组 | no-action | 配置传递方式从 Pimple container 变为构造函数参数 |
| Strategy 数组/对象混合构造 | registration | covered | `__construct()` 中 `is_array` / `instanceof` 判断 | no-action | 逻辑完全一致 |
| `onPreRouting` — Origin header 检测 | preflight | covered | `onPreRouting(RequestEvent $event)` | no-action | 逻辑一致：无 Origin → 早返回 |
| `onPreRouting` — strategy matching（first-match-wins） | strategy | covered | `foreach ($this->strategies ...)` + `break` | no-action | 逻辑一致 |
| `onPreRouting` — preflight 检测（OPTIONS + Request-Method header） | preflight | covered | 同上方法内 | no-action | 逻辑一致 |
| `onPreRouting` 注册优先级 = `BEFORE_PRIORITY_ROUTING + 1` = 33 | priority | covered | `getSubscribedEvents()` 中 `['onPreRouting', 33]` | no-action | 优先级一致 |
| `onPostRouting` — preflight response 设置 + 短路 | preflight | covered | `onPostRouting(RequestEvent $event)` 调用 `$event->setResponse()` | no-action | v2.5.0 通过 `return $this->preFlightResponse` 短路（Silex before middleware 返回 Response 即短路），v3.x 通过 `$event->setResponse()` 短路（Symfony RequestEvent 机制），行为等价 |
| `onPostRouting` 注册优先级 = `BEFORE_PRIORITY_CORS_PREFLIGHT` = 20 | priority | covered | `getSubscribedEvents()` 中 `['onPostRouting', 20]` | no-action | 优先级一致 |
| `onMethodNotAllowedHttp` — MethodNotAllowed 异常处理 | exception | covered | `onException(ExceptionEvent $event)` | no-action | v2.5.0 通过 Silex `$app->error()` 注册（`EARLY_EVENT` = 512），v3.x 通过 `KernelEvents::EXCEPTION` 注册（priority 512），行为等价 |
| `onMethodNotAllowedHttp` — 从 Allow header 提取允许方法 | exception | covered | `explode(', ', $exception->getHeaders()['Allow'])` | no-action | 逻辑一致 |
| `onMethodNotAllowedHttp` — `allowCustomResponseCode()` + 返回 preflight response | exception | covered | `$event->allowCustomResponseCode()` + `$event->setResponse()` + `$event->stopPropagation()` | no-action | v3.x 额外调用 `stopPropagation()` 防止后续 exception listener 处理，行为更严谨 |
| `onResponse` — preflight path: Origin header 检测 | preflight | covered | `onResponse(ResponseEvent $event)` 内 preflight 分支 | no-action | 逻辑一致 |
| `onResponse` — preflight path: origin 验证 | preflight | covered | `$this->activeStrategy->isOriginAllowed($requestOrigin)` | no-action | 逻辑一致 |
| `onResponse` — preflight path: Request-Method header 检测 | preflight | covered | 同上 | no-action | 逻辑一致 |
| `onResponse` — preflight path: request headers 解析 | preflight | covered | `explode(",", ...)` | no-action | 逻辑一致 |
| `onResponse` — preflight path: method 允许检查 | preflight | covered | `in_array($requestMethod, $methodsAllowed)` | no-action | 逻辑一致 |
| `onResponse` — preflight path: header 允许检查 | preflight | covered | `$this->activeStrategy->isHeaderAllowed($header)` | no-action | 逻辑一致 |
| `onResponse` — preflight path: credentials → Allow-Credentials + Allow-Origin=origin | preflight | covered | credentials 分支逻辑 | no-action | 逻辑一致 |
| `onResponse` — preflight path: wildcard origin → Allow-Origin=* | preflight | covered | `isWildcardOriginAllowed()` 分支 | no-action | 逻辑一致 |
| `onResponse` — preflight path: specific origin → Allow-Origin=origin + Vary=Origin | preflight | covered | else 分支 | no-action | 逻辑一致 |
| `onResponse` — preflight path: Max-Age header | preflight | covered | `$this->activeStrategy->getMaxAge()` | no-action | 逻辑一致 |
| `onResponse` — preflight path: non-simple method → Allow-Methods | preflight | covered | `!in_array($requestMethod, static::SIMPLE_METHODS)` | no-action | 逻辑一致 |
| `onResponse` — preflight path: Allow-Headers | preflight | covered | `$this->activeStrategy->getAllowedHeaders()` | no-action | 逻辑一致 |
| `onResponse` — normal path: Origin header 检测 | headers | covered | normal request 分支 | no-action | 逻辑一致 |
| `onResponse` — normal path: origin 验证 | headers | covered | `isOriginAllowed()` | no-action | 逻辑一致 |
| `onResponse` — normal path: credentials → Allow-Credentials + Allow-Origin=origin | headers | covered | credentials 分支 | no-action | 逻辑一致 |
| `onResponse` — normal path: wildcard origin → Allow-Origin=* | headers | covered | `isWildcardOriginAllowed()` 分支 | no-action | 逻辑一致 |
| `onResponse` — normal path: specific origin → Allow-Origin=origin + Vary=Origin | headers | covered | else 分支 | no-action | 逻辑一致 |
| `onResponse` — normal path: Expose-Headers | headers | covered | `$this->activeStrategy->getExposedHeaders()` | no-action | 逻辑一致 |
| `onResponse` 注册优先级 = `Application::LATE_EVENT` = -512 | priority | covered | `getSubscribedEvents()` 中 `['onResponse', -512]` | no-action | 优先级一致 |
| `CrossOriginResourceSharingStrategy` — pattern 配置（string / RequestMatcher） | strategy | covered | pattern 支持 string / `RequestMatcherInterface` | no-action | v2.5.0 接受 `RequestMatcher`，v3.x 接受 `RequestMatcherInterface`（更宽泛），向后兼容 |
| `CrossOriginResourceSharingStrategy` — wildcard pattern `*` → match all | strategy | covered | `$pattern === "*"` → `PathRequestMatcher('.*')` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — origins 配置 | origin | covered | `$this->originsAllowed` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — origin 验证（domain matching regex） | origin | covered | `DOMAIN_MATCHING_PATTERN` + `preg_match` | no-action | 正则完全一致 |
| `CrossOriginResourceSharingStrategy` — wildcard origin `*` | origin | covered | `isWildcardOriginAllowed()` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — headers 配置 | headers | covered | `$this->headersAllowed` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — header 验证（case-insensitive + simple headers） | headers | covered | `isHeaderAllowed()` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — headers_exposed 配置 | headers | covered | `$this->headersExposed` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingStrategy` — max_age 配置（default 86400） | config | covered | `getOptional('max_age', ..., 86400)` | no-action | 默认值一致 |
| `CrossOriginResourceSharingStrategy` — credentials_allowed 配置（default false） | credentials | covered | `getOptional('credentials_allowed', ..., false)` | no-action | 默认值一致 |
| `CrossOriginResourceSharingStrategy::matches()` — RequestMatcher 委托 | strategy | covered | `$this->matcher->matches($request)` | no-action | 逻辑一致 |
| `CrossOriginResourceSharingConfiguration` — TreeBuilder 定义 | config | covered | 完全一致的 TreeBuilder 结构 | no-action | 配置 schema 未变 |
| `PrefilightResponse` — HTTP 204 + X-Status-Code header | preflight | covered | `PrefilightResponse` 类未变 | no-action | 实现一致 |
| `PrefilightResponse::addAllowedMethod()` | preflight | covered | 方法签名和行为一致 | no-action | — |
| `PrefilightResponse::getAllowedMethods()` | preflight | covered | 方法签名和行为一致 | no-action | — |
| Security interaction: preflight 绕过 firewall | interaction | covered | CORS `onPostRouting` priority 20 > Firewall priority 8，preflight 在 firewall 前短路 | no-action | v2.5.0 中 `BEFORE_PRIORITY_CORS_PREFLIGHT` = 20 > `BEFORE_PRIORITY_FIREWALL` = 8，v3.x 保持相同优先级关系 |
| `HEADER_*` 常量定义 | headers | covered | 所有常量名和值完全一致 | no-action | — |
| `SIMPLE_METHODS` 常量 | headers | covered | `['HEAD', 'POST', 'GET']` | no-action | — |

---

## 行为等价性总结

### 架构变更（不影响行为）

| 维度 | v2.5.0 | v3.x | 行为影响 |
|------|--------|------|----------|
| 注册方式 | `ServiceProviderInterface` + `BootableProviderInterface`（Pimple/Silex） | `EventSubscriberInterface`（Symfony） | 无——最终都是注册 event listener |
| 策略传递 | `$app['cors.strategies']` Pimple container key | 构造函数参数 | 无——MicroKernel 在 `registerCors()` 中传递 |
| before middleware 短路 | Silex `before()` 返回 Response | `RequestEvent::setResponse()` | 无——Symfony 等价机制 |
| error handler | Silex `$app->error()` + `GetResponseForExceptionEvent` | `KernelEvents::EXCEPTION` + `ExceptionEvent` | 无——Symfony 等价机制 |
| after middleware | Silex `$app->after()` | `KernelEvents::RESPONSE` | 无——Symfony 等价机制 |
| RequestMatcher 类型 | `Symfony\Component\HttpFoundation\RequestMatcher` | `RequestMatcherInterface`（接受 `ChainRequestMatcher` 等） | 无——更宽泛的接口，向后兼容 |

### 优先级对比

| Event | v2.5.0 Priority | v3.x Priority | 一致性 |
|-------|----------------|---------------|--------|
| onPreRouting (KernelEvents::REQUEST) | 33 (`BEFORE_PRIORITY_ROUTING + 1`) | 33 | ✅ |
| onPostRouting (KernelEvents::REQUEST) | 20 (`BEFORE_PRIORITY_CORS_PREFLIGHT`) | 20 | ✅ |
| onResponse (KernelEvents::RESPONSE) | -512 (`LATE_EVENT`) | -512 | ✅ |
| onException (KernelEvents::EXCEPTION) | 512 (`EARLY_EVENT`) | 512 | ✅ |

### 审计结论

**CORS 模块审计结果：全部 covered，无 missing 能力。**

v3.x 的 `CrossOriginResourceSharingProvider` 完整覆盖了 v2.5.0 的所有 API_Surface 项。核心 CORS 处理逻辑（preflight 检测、origin 验证、header 处理、credentials 支持、strategy matching、Security 交互）在代码层面几乎逐行等价，仅在框架适配层（Pimple → Symfony EventSubscriber）有架构变更，不影响运行时行为。

所有事件优先级完全一致，确保 CORS 处理在请求 pipeline 中的位置与 v2.5.0 相同。

无需修复代码，无需更新 Migration_Guide。
