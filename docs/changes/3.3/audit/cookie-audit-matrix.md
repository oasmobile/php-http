# Cookie Module Audit Matrix

> 审计基准：`oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0
> 审计对象：v3.x `SimpleCookieProvider` + `MicroKernel::registerCookie()`

---

## API Surface 审计

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `SimpleCookieProvider` 作为 `ServiceProviderInterface` 注册 | registration | covered | `MicroKernel::registerCookie()` 直接实例化并注册 | no-action | v2.5.0 通过 `$app->register(new SimpleCookieProvider())` 注册；v3.x 在 `boot()` 中直接调用 `registerCookie()`，效果等价 |
| `SimpleCookieProvider::boot()` 调用 `addControllerInjectedArg($cookieContainer)` | injection | covered | `MicroKernel::registerCookie()` 调用 `$this->addControllerInjectedArg($cookieContainer)` | no-action | 行为完全等价 |
| `SimpleCookieProvider::boot()` 注册 `$app->after()` callback 写入 cookie | writing | covered | `SimpleCookieProvider` 实现 `EventSubscriberInterface`，订阅 `KernelEvents::RESPONSE` | no-action | v2.5.0 通过 Silex `after()` 注册（底层为 `KernelEvents::RESPONSE` listener）；v3.x 通过 `EventSubscriberInterface` 直接订阅同一事件，行为等价 |
| `ResponseCookieContainer::addCookie(Cookie $cookie)` | container | covered | `ResponseCookieContainer::addCookie(Cookie $cookie): void` | no-action | 签名从无返回类型声明升级为 `void`，行为不变 |
| `ResponseCookieContainer::getCookies(): array` | container | covered | `ResponseCookieContainer::getCookies(): array` | no-action | 行为完全等价 |
| Cookie 写入时机：`KernelEvents::RESPONSE` 阶段 | writing | covered | `SimpleCookieProvider::onResponse(ResponseEvent $event)` | no-action | v2.5.0 的 `after()` callback 等价于 `KernelEvents::RESPONSE` listener；v3.x 直接订阅该事件 |
| Cookie 写入 priority：默认 priority | writing | covered | `getSubscribedEvents()` 返回 priority 0 | no-action | v2.5.0 的 `after()` 默认 priority 为 0（Silex `Application::LATE_EVENT` 未使用）；v3.x 显式设置 priority 0，等价 |
| Cookie 写入仅限 main request（`$masterRequestOnly = true`） | writing | ~~missing-non-breaking~~ → fixed | `SimpleCookieProvider::onResponse()` 添加 `$event->isMainRequest()` 检查 | fix-code | v2.5.0 的 `after()` 默认 `$masterRequestOnly = true`，sub-request 不写入 cookie；v3.x 原实现缺少此检查，已修复 |
| `ResponseCookieContainer` 每请求生命周期 | lifecycle | covered | `MicroKernel::registerCookie()` 在 `boot()` 中创建新实例 | no-action | v2.5.0 在 `SimpleCookieProvider` 构造函数中创建；v3.x 在 `registerCookie()` 中创建。两者都是 kernel 生命周期内单例，每次 boot 创建新实例 |
| `SimpleCookieProvider::$cookieContainer` 属性访问 | container | covered | `SimpleCookieProvider::getCookieContainer(): ResponseCookieContainer` | no-action | v2.5.0 为 `protected` 属性无 getter；v3.x 添加了 `getCookieContainer()` public getter，是非 breaking 增强 |
| Cookie container 通过 `ExtendedArgumentValueResolver` 注入 controller | injection | covered | `ExtendedArgumentValueResolver::resolve()` 按类名匹配 `ResponseCookieContainer` | no-action | v2.5.0 通过 Silex 的 `ControllerResolver` + `addControllerInjectedArg()` 注入；v3.x 通过 Symfony `ValueResolverInterface` + `addControllerInjectedArg()` 注入，行为等价 |
| 无 cookie 时不修改 response headers | writing | covered | `onResponse()` 遍历空数组，不调用 `setCookie()` | no-action | 行为等价——空 container 不产生副作用 |

---

## 行为等价性审计

### 注册方式差异

| 维度 | v2.5.0 (Silex) | v3.x (MicroKernel) | 等价性 |
|------|----------------|--------------------|---------| 
| 注册入口 | `$app->register(new SimpleCookieProvider())` | `MicroKernel::registerCookie()` 在 `boot()` 中调用 | ✅ 等价 |
| Provider 接口 | `ServiceProviderInterface` + `BootableProviderInterface` | `EventSubscriberInterface` | ✅ 等价（不同接口，相同效果） |
| Container 创建时机 | `SimpleCookieProvider` 构造函数 | `MicroKernel::registerCookie()` | ✅ 等价 |
| Injected arg 注册 | `boot()` 中调用 `$app->addControllerInjectedArg()` | `registerCookie()` 中调用 `$this->addControllerInjectedArg()` | ✅ 等价 |

### Cookie 写入行为差异

| 维度 | v2.5.0 (Silex) | v3.x (MicroKernel) | 等价性 |
|------|----------------|--------------------|---------| 
| 事件类型 | `KernelEvents::RESPONSE`（通过 Silex `after()`） | `KernelEvents::RESPONSE`（通过 `EventSubscriberInterface`） | ✅ 等价 |
| Priority | 0（Silex `after()` 默认） | 0（显式声明） | ✅ 等价 |
| Main request 过滤 | `$masterRequestOnly = true`（Silex `after()` 默认值） | `$event->isMainRequest()` 检查（**已修复**） | ✅ 修复后等价 |
| 写入逻辑 | `$response->headers->setCookie($cookie)` | `$event->getResponse()->headers->setCookie($cookie)` | ✅ 等价 |
| 遍历方式 | `foreach ($this->cookieContainer->getCookies() as $cookie)` | 同上 | ✅ 等价 |

### Container 生命周期差异

| 维度 | v2.5.0 (Silex) | v3.x (MicroKernel) | 等价性 |
|------|----------------|--------------------|---------| 
| 创建时机 | `new SimpleCookieProvider()` 构造时 | `registerCookie()` 调用时（boot 阶段） | ✅ 等价（都是 kernel 生命周期内一次性创建） |
| 共享方式 | 同一 `SimpleCookieProvider` 实例持有 | `MicroKernel` 通过 `addControllerInjectedArg()` 共享 | ✅ 等价 |
| 多请求隔离 | 同一 kernel 实例处理多请求时 container 不重置 | 同上 | ✅ 等价（两者行为一致：container 不自动清空） |

---

## 审计结论

**Cookie 模块审计结果：发现 1 项 missing-non-breaking，已修复。**

v3.x 的 Cookie 模块实现与 v2.5.0 存在一处行为差异：`SimpleCookieProvider::onResponse()` 缺少 main request 过滤，导致 sub-request 的 response 也会被写入 cookie。v2.5.0 通过 Silex `after()` 的 `$masterRequestOnly = true` 默认值实现了此过滤。已修复。

- missing-non-breaking: 1（已修复：sub-request cookie 写入过滤）
- missing-breaking: 0
- intentionally-removed: 0
- covered: 11（含修复后的 1 项）

修复内容：`SimpleCookieProvider::onResponse()` 添加 `$event->isMainRequest()` 前置检查。
回归测试：`tests/Cookie/CookieFixRegressionTest.php`（3 个测试方法）。
