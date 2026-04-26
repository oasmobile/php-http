# Requirements Document

> PHP 8.5 Upgrade — Phase 1: Framework Replacement — `.kiro/specs/php85-phase1-framework-replacement/`

---

## Introduction

`oasis/http` 的核心框架 `silex/silex` 自 2018 年归档，不兼容 PHP 8.x。Phase 0（PRP-002）已完成 PHP 版本约束升级和 PHPUnit 升级，但 Silex、Symfony 4.x 等框架依赖尚未替换。Phase 1 是整个升级中工作量最大、风险最高的阶段，需要移除 Silex 并用 Symfony MicroKernel 替换，同时将全部 Symfony 组件从 4.x 升级到 7.x。

当前系统的请求处理流程为：`SilexKernel::run()` 创建 Request → `handle()` 处理可信代理 → EventDispatcher 按优先级触发 routing / CORS / firewall / middleware → 路由匹配 → 控制器执行 → View Handler 链处理非 Response 返回值 → Error Handler 链处理异常 → after middleware → Response 发送。这套流程需要在新框架下完整保留。

**不涉及的内容**：

- Twig 本体（`twig/twig`）的模板适配细节（Phase 2 / PRP-004 处理模板层面的 breaking changes）
- Guzzle 升级（Phase 2 / PRP-004）
- Security 组件的 authenticator 系统重写（Phase 3 / PRP-005）；本 Phase 对 Security 组件仅做最小可编译适配
- PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 不引入新功能，仅做框架平迁
- 不保留 Pimple 或 Silex 的任何兼容层

**约束**：

- C-1: Kernel 类重命名为 `MicroKernel`，不保留 `SilexKernel` 类名，下游消费者需要适配
- C-2: 全面迁移到 Symfony DI Container，移除 Pimple，不保留任何 `$app['xxx']` 风格兼容层
- C-3: Security 组件仅做最小可编译适配，authenticator 系统重写留给 Phase 3
- C-4: `twig/twig` 升级到 `^3.0`，`symfony/twig-bridge` 升级到 `^7.2`，`twig/extensions` 移除；Guzzle 升级留给 Phase 2
- C-5: 引入 Eris 1.x 作为 PBT 框架，本 Phase 为路由解析、middleware 链、请求分发编写 property test
- C-6: PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支
- C-7: spec 级 DoD：tasks 全部完成 + PRP-003 中定义的预期通过 suite 实际通过（`cors`、`aws`、`routing`、`cookie`、`middlewares`、`twig`、`SilexKernelTest`、`SilexKernelWebTest`、`FallbackViewHandlerTest`）

---

## Glossary

- **MicroKernel**: 替换 `SilexKernel` 的新核心入口类，继承 Symfony `Kernel` + `MicroKernelTrait`，保持 bootstrap config 数组驱动初始化
- **Bootstrap_Config**: `MicroKernel` 构造函数接受的关联数组，包含 `routing`、`security`、`cors`、`twig`、`middlewares`、`providers`、`view_handlers`、`error_handlers`、`injected_args`、`trusted_proxies`、`trusted_header_set`、`behind_elb`、`trust_cloudfront_ips`、`cache_dir` 等顶层 key
- **Symfony_DI**: Symfony DependencyInjection 组件，替代 Pimple 作为 DI 容器
- **CompilerPass**: Symfony DI 的编译阶段扩展点，用于在容器编译时动态注册或修改 service 定义
- **EventSubscriber**: Symfony EventDispatcher 的事件订阅者接口，用于替代 Silex 的 `before()` / `after()` / `error()` / `view()` 注册方式
- **Middleware_Chain**: before / after middleware 的执行链，按 priority 排序，支持 master-request-only 过滤
- **View_Handler_Chain**: 控制器返回非 Response 值时的处理链，通过 `KernelEvents::VIEW` 事件触发，支持链式调用和 content negotiation
- **Error_Handler_Chain**: 异常处理链，通过 `KernelEvents::EXCEPTION` 事件触发，支持链式调用和优先级
- **CORS_Subscriber**: 替代 `CrossOriginResourceSharingProvider` 的 EventSubscriber，处理 CORS preflight 和响应头
- **Cookie_Subscriber**: 替代 `SimpleCookieProvider` 的 EventSubscriber，在 response 阶段写入 cookie
- **Trusted_Proxy**: AWS ELB / CloudFront 可信代理配置，在 `handle()` 阶段设置
- **CacheableRouter**: 支持 YAML 路由文件加载、路由缓存、参数替换的路由器
- **PBT**: Property-Based Testing，使用 Eris 框架生成随机输入验证系统属性
- **Minimal_Security_Adaptation**: 对 Security 组件仅做最小可编译适配，不重写 authenticator 系统

---

## Requirements

### Requirement 1: P0 — 依赖替换与 Symfony 组件升级

**User Story:** 作为迁移开发者，我希望移除 Silex 和 Pimple 依赖并将所有 Symfony 组件升级到 7.x，以便项目能在 PHP 8.5 上编译运行。

#### Acceptance Criteria

1. THE `composer.json` SHALL remove `silex/silex` and `silex/providers` from `require`.
2. IF `pimple/pimple` exists as a direct dependency THEN THE `composer.json` SHALL remove it from `require`.
3. THE `composer.json` SHALL upgrade all Symfony components (`symfony/http-foundation`, `symfony/routing`, `symfony/config`, `symfony/yaml`, `symfony/expression-language`, `symfony/security-*`, `symfony/twig-bridge`, `symfony/css-selector`, `symfony/browser-kit`) to `^7.2`.
4. THE `composer.json` SHALL add `symfony/http-kernel` `^7.2`, `symfony/dependency-injection` `^7.2`, `symfony/event-dispatcher` `^7.2`, `symfony/framework-bundle` `^7.2` to `require`.
5. THE `composer.json` SHALL replace `symfony/security` with the required Security sub-packages (`symfony/security-core`, `symfony/security-http`, `symfony/security-bundle`) at `^7.2`.
6. THE `composer.json` SHALL add `giorgiosironi/eris` `^1.0` to `require-dev`.
7. THE `composer.json` SHALL upgrade `twig/twig` to `^3.0`, remove `twig/extensions`, and keep `guzzlehttp/guzzle` at `^6.3`.
8. WHEN `composer install` is executed THEN dependency resolution SHALL succeed without conflicts.

### Requirement 2: P0 — MicroKernel 核心入口替换

**User Story:** 作为迁移开发者，我希望用基于 Symfony MicroKernel 的 `MicroKernel` 替换 `SilexKernel`，以便应用入口不再依赖 Silex。

#### Acceptance Criteria

1. THE new MicroKernel class SHALL be created under namespace `Oasis\Mlib\Http`.
2. THE `MicroKernel` SHALL extend Symfony `Kernel` and use `MicroKernelTrait`.
3. THE `MicroKernel` SHALL implement `AuthorizationCheckerInterface`.
4. THE `MicroKernel` constructor SHALL accept `(array $httpConfig, bool $isDebug)` and process configuration via `ConfigurationValidationTrait` + `HttpConfiguration`.
5. THE `MicroKernel` SHALL expose the same public API methods as `SilexKernel`: `run()`, `handle()`, `isGranted()`, `getToken()`, `getUser()`, `getTwig()`, `getParameter()`, `addExtraParameters()`, `addControllerInjectedArg()`, `addMiddleware()`, `getCacheDirectories()`.
6. THE `MicroKernel::run()` SHALL create Request, call `handle()`, send Response, call `terminate()`, and detect slow requests — identical to current `SilexKernel::run()` behavior.
7. THE `MicroKernel::handle()` SHALL process ELB / CloudFront Trusted_Proxy configuration before delegating to parent `handle()`.
8. THE old `SilexKernel` class SHALL be removed from `src/SilexKernel.php`.
9. THE priority constants (`BEFORE_PRIORITY_ROUTING`, `BEFORE_PRIORITY_CORS_PREFLIGHT`, `BEFORE_PRIORITY_FIREWALL`, `BEFORE_PRIORITY_EARLIEST`, `BEFORE_PRIORITY_LATEST`, `AFTER_PRIORITY_EARLIEST`, `AFTER_PRIORITY_LATEST`) SHALL be preserved on `MicroKernel` with the same numeric values.


### Requirement 3: P0 — Symfony DI Container 迁移

**User Story:** 作为迁移开发者，我希望所有 service 注册和获取方式从 Pimple 迁移到 Symfony_DI，以便项目不再依赖 Pimple。

#### Acceptance Criteria

1. THE `MicroKernel` SHALL use Symfony `ContainerBuilder` as DI container, replacing all Pimple `$app['xxx']` style access.
2. THE Bootstrap_Config driven initialization SHALL register services into Symfony_DI via `configureContainer()` or equivalent MicroKernel method.
3. THE built-in service providers (`CacheableRouterProvider`, `CrossOriginResourceSharingProvider`, `SimpleCookieProvider`, `SimpleTwigServiceProvider`, `SimpleSecurityProvider`) SHALL be rewritten as Symfony DI registration (CompilerPass / Extension / direct service definition), removing all `Pimple\ServiceProviderInterface` and `Silex\Api\BootableProviderInterface` dependencies.
4. THE user-provided `providers` from Bootstrap_Config SHALL be supported by accepting Symfony `CompilerPass` or `Extension` instances, replacing `Pimple\ServiceProviderInterface`.
5. ALL `$app['xxx']` style service access in source code SHALL be replaced with Symfony DI `$container->get()` or constructor injection.
6. WHEN the container is compiled THEN all registered services SHALL be resolvable without Pimple.

### Requirement 4: P0 — Middleware 机制迁移

**User Story:** 作为迁移开发者，我希望 before / after middleware 机制迁移到 Symfony EventSubscriber，以便中间件不再依赖 Silex 的 `before()` / `after()` API。

#### Acceptance Criteria

1. THE `MiddlewareInterface` SHALL be updated to remove `Silex\Application` dependency from `before()` method signature, replacing it with `MicroKernel` or a framework-agnostic interface.
2. THE `AbstractMiddleware` SHALL be updated to remove `Silex\Application` dependency, using Symfony event priority constants instead of `Application::EARLY_EVENT` / `Application::LATE_EVENT`.
3. THE `MicroKernel::addMiddleware()` SHALL register middleware as EventSubscriber listeners on `KernelEvents::REQUEST` (before) and `KernelEvents::RESPONSE` (after) with the specified priority.
4. THE before middleware SHALL receive `RequestEvent` and support setting a Response to short-circuit the request.
5. THE after middleware SHALL receive `ResponseEvent` and support modifying or replacing the Response.
6. THE `onlyForMasterRequest()` filtering SHALL be preserved: WHEN `onlyForMasterRequest()` returns true THEN the middleware SHALL only execute for `MAIN_REQUEST` (Symfony 7.x renamed from `MASTER_REQUEST`).
7. THE Middleware_Chain execution order SHALL be identical to the current system: ordered by priority, higher priority executes first.


### Requirement 5: P0 — 路由系统迁移

**User Story:** 作为迁移开发者，我希望路由注册和匹配机制迁移到 Symfony Routing 7.x，以便路由系统不再依赖 Silex 的路由基础设施。

#### Acceptance Criteria

1. THE `CacheableRouterProvider` SHALL be rewritten to register routing services into Symfony_DI, removing `Pimple\Container` and `Pimple\ServiceProviderInterface` dependencies.
2. THE `CacheableRouter` SHALL be updated to accept `MicroKernel` (or a parameter provider interface) instead of `SilexKernel`.
3. THE `CacheableRouter::getRouteCollection()` parameter replacement logic (`%param%` → value) SHALL be preserved.
4. THE `GroupUrlMatcher` SHALL continue to support chained URL matching with fallback behavior.
5. THE `GroupUrlGenerator` SHALL continue to support chained URL generation with fallback behavior.
6. THE `CacheableRouterUrlMatcherWrapper` namespace prefix logic SHALL be preserved.
7. THE `InheritableYamlFileLoader` and `InheritableRouteCollection` SHALL be adapted to Symfony Routing 7.x API changes (if any).
8. THE `RedirectableUrlMatcher` base class reference SHALL be updated from `Silex\Provider\Routing\RedirectableUrlMatcher` to Symfony Routing 7.x equivalent.
9. THE routing configuration from Bootstrap_Config (`routing` key with `path`, `namespaces`, `cache_dir`) SHALL continue to work identically.

### Requirement 6: P0 — CORS 机制迁移

**User Story:** 作为迁移开发者，我希望 CORS 处理迁移到 Symfony EventSubscriber，以便 CORS 逻辑不再依赖 Silex 的 provider 和 middleware API。

#### Acceptance Criteria

1. THE `CrossOriginResourceSharingProvider` SHALL be rewritten as a CORS_Subscriber implementing `EventSubscriberInterface`.
2. THE CORS_Subscriber SHALL subscribe to `KernelEvents::REQUEST` (pre-routing at priority 33, post-routing at priority 20), `KernelEvents::RESPONSE` (late priority), and `KernelEvents::EXCEPTION` (early priority for MethodNotAllowed handling).
3. THE preflight detection logic SHALL be preserved: WHEN request method is OPTIONS AND `Access-Control-Request-Method` header is present AND an active strategy matches THEN a `PrefilightResponse` SHALL be returned.
4. THE `onResponse()` CORS header processing SHALL be preserved for both preflight and normal requests, following the W3C CORS spec processing model.
5. THE `onMethodNotAllowedHttp()` handler SHALL be adapted from Silex error handler signature to Symfony `ExceptionEvent` handling.
6. THE `CrossOriginResourceSharingStrategy` SHALL remain unchanged (no Silex dependency).
7. THE CORS strategies SHALL be initialized from Bootstrap_Config `cors` key, identical to current behavior.


### Requirement 7: P0 — View Handler 链迁移

**User Story:** 作为迁移开发者，我希望 View Handler 链迁移到 Symfony `KernelEvents::VIEW` EventSubscriber，以便视图处理不再依赖 Silex 的 `view()` API。

#### Acceptance Criteria

1. THE View_Handler_Chain SHALL be implemented as an EventSubscriber listening on `KernelEvents::VIEW`.
2. THE View_Handler_Chain SHALL iterate through registered view handlers in order, calling each with `($controllerResult, $request)`.
3. WHEN a view handler returns a `Response` THEN the chain SHALL stop and set that Response on the event.
4. WHEN a view handler returns null THEN the chain SHALL continue to the next handler.
5. THE view handlers SHALL be registered from Bootstrap_Config `view_handlers` key, preserving the current callable-based registration.
6. THE `FallbackViewHandler` SHALL be updated to accept `MicroKernel` instead of `SilexKernel`.
7. THE `ResponseRendererInterface` SHALL be updated to accept `MicroKernel` instead of `SilexKernel`.
8. THE `DefaultHtmlRenderer` and `JsonApiRenderer` SHALL be updated to accept `MicroKernel` instead of `SilexKernel`.

### Requirement 8: P0 — Error Handler 链迁移

**User Story:** 作为迁移开发者，我希望 Error Handler 链迁移到 Symfony `KernelEvents::EXCEPTION` EventSubscriber，以便异常处理不再依赖 Silex 的 `error()` API 和 `ExceptionListenerWrapper`。

#### Acceptance Criteria

1. THE Error_Handler_Chain SHALL be implemented as an EventSubscriber listening on `KernelEvents::EXCEPTION`.
2. THE `ExtendedExceptionListnerWrapper` SHALL be rewritten to work with Symfony 7.x `ExceptionEvent` (replacing `GetResponseForExceptionEvent`).
3. THE error handler behavior SHALL be preserved: WHEN handler returns null AND event has no response THEN the exception SHALL propagate; WHEN handler returns a Response THEN it SHALL be set on the event.
4. THE error handlers SHALL be registered from Bootstrap_Config `error_handlers` key with the same priority mechanism.
5. THE Silex `ExceptionListenerWrapper` base class dependency SHALL be removed.

### Requirement 9: P1 — Cookie Provider 迁移

**User Story:** 作为迁移开发者，我希望 Cookie 管理迁移到 Symfony EventSubscriber，以便 Cookie 处理不再依赖 Silex 的 provider API。

#### Acceptance Criteria

1. THE `SimpleCookieProvider` SHALL be rewritten as a Cookie_Subscriber implementing `EventSubscriberInterface`.
2. THE Cookie_Subscriber SHALL subscribe to `KernelEvents::RESPONSE` to write cookies from `ResponseCookieContainer` to response headers.
3. THE Cookie_Subscriber SHALL register `ResponseCookieContainer` as a controller injected arg via `MicroKernel::addControllerInjectedArg()`.
4. THE `ResponseCookieContainer` SHALL remain unchanged (no Silex dependency).


### Requirement 10: P1 — Twig Provider 迁移

**User Story:** 作为迁移开发者，我希望 Twig 集成迁移到 Symfony 7.x + Twig 3.x，以便 Twig 相关代码在新框架下正常工作。

#### Acceptance Criteria

1. THE `SimpleTwigServiceProvider` SHALL be rewritten to remove `Silex\Provider\TwigServiceProvider` 继承和 Pimple 依赖.
2. THE Twig service registration SHALL use Symfony_DI, reading configuration from Bootstrap_Config `twig` key.
3. THE `twig/twig` SHALL be upgraded to `^3.0`.
4. THE `symfony/twig-bridge` SHALL be upgraded to `^7.2`.
5. THE Twig 1.x deprecated API references (`Twig_Environment`, `Twig_SimpleFunction`, `Twig_Error_Loader`) SHALL be replaced with Twig 3.x equivalents (`\Twig\Environment`, `\Twig\TwigFunction`, `\Twig\Error\LoaderError`).
6. THE `twig/extensions` package SHALL be removed (abandoned, functionality merged into Twig 3.x core or replaced).
7. WHEN Twig configuration is absent THEN Twig services SHALL not be registered.
8. THE `getTwig()` method on `MicroKernel` SHALL return `\Twig\Environment|null`.

### Requirement 11: P1 — Security Provider 最小可编译适配

**User Story:** 作为迁移开发者，我希望 Security 组件做最小可编译适配，以便 Security 相关代码在 Symfony 7.x 下能编译，但 authenticator 系统重写留给 Phase 3。

#### Acceptance Criteria

1. THE `SimpleSecurityProvider` SHALL be rewritten to remove `Silex\Provider\SecurityServiceProvider` 继承和 Pimple 依赖.
2. THE Security service registration SHALL use Symfony_DI with Minimal_Security_Adaptation.
3. THE `AuthenticationPolicyInterface` SHALL be updated to remove `Pimple\Container` dependency from method signatures, replacing with a framework-agnostic container or `MicroKernel`.
4. THE `FirewallInterface`, `AccessRuleInterface`, and related interfaces SHALL remain unchanged where they have no Silex dependency.
5. THE `NullEntryPoint` SHALL remain unchanged (no Silex dependency).
6. WHEN Security configuration is absent THEN Security services SHALL not be registered.
7. THE Security test suite SHALL be expected to fail in Phase 1; only `NullEntryPointTest` and similar non-authenticator tests are expected to pass.

### Requirement 12: P1 — ExtendedArgumentValueResolver 适配

**User Story:** 作为迁移开发者，我希望控制器参数自动注入机制适配 Symfony 7.x，以便 `injected_args` 功能继续工作。

#### Acceptance Criteria

1. THE `ExtendedArgumentValueResolver` SHALL be adapted to Symfony 7.x `ArgumentValueResolverInterface` (or `ValueResolverInterface` if the interface changed in 7.x).
2. THE `ExtendedArgumentValueResolver` SHALL be registered into Symfony_DI as an argument value resolver.
3. THE `injected_args` from Bootstrap_Config SHALL continue to be registered as auto-injection candidates.
4. THE `supports()` and `resolve()` behavior SHALL be preserved: exact class match and `instanceof` match.


### Requirement 13: P1 — Symfony 4.x → 7.x API 适配

**User Story:** 作为迁移开发者，我希望所有使用 Symfony 4.x 已移除 API 的代码适配到 7.x 等价 API，以便代码能在 Symfony 7.x 下编译运行。

#### Acceptance Criteria

1. THE `FilterResponseEvent` references SHALL be replaced with `ResponseEvent` (Symfony 7.x).
2. THE `GetResponseEvent` references SHALL be replaced with `RequestEvent` (Symfony 7.x).
3. THE `GetResponseForExceptionEvent` references SHALL be replaced with `ExceptionEvent` (Symfony 7.x).
4. THE `HttpKernelInterface::MASTER_REQUEST` references SHALL be replaced with `HttpKernelInterface::MAIN_REQUEST` (Symfony 5.3+ rename).
5. THE `Silex\CallbackResolver` usage SHALL be removed, callbacks SHALL be called directly or through Symfony's resolver.
6. THE `RequestMatcher` constructor usage in `CrossOriginResourceSharingStrategy` SHALL be adapted if the API changed in Symfony 7.x.
7. ALL Symfony 4.x deprecated or removed class references SHALL be updated to their 7.x equivalents.

### Requirement 14: P1 — 测试适配

**User Story:** 作为迁移开发者，我希望所有现有测试适配到新框架，以便 PRP-003 定义的预期通过 suite 实际通过。

#### Acceptance Criteria

1. THE test files referencing `SilexKernel` SHALL be updated to reference `MicroKernel`.
2. THE test bootstrap files (`ut/app.php`, `ut/index.cors.php`, `ut/AwsTests/*.php`, etc.) SHALL be updated to use `MicroKernel` and new initialization API.
3. THE `SilexKernelTest` and `SilexKernelWebTest` SHALL be adapted (and optionally renamed) to test `MicroKernel`.
4. THE `FallbackViewHandlerTest` SHALL be adapted to use `MicroKernel`.
5. THE following test suites SHALL pass after migration: `cors`, `aws`, `routing`, `cookie`, `middlewares`, `twig`, and the kernel/view tests (`SilexKernelTest`, `SilexKernelWebTest`, `FallbackViewHandlerTest`).
6. THE following test suites are expected to fail and SHALL NOT block Phase 1 completion: `security` (except `NullEntryPointTest`), `integration`.

### Requirement 15: P2 — Eris PBT 引入与核心 Property Test

**User Story:** 作为迁移开发者，我希望引入 Eris PBT 框架并为核心行为编写 property test，以便通过随机输入验证框架替换后行为不变。

#### Acceptance Criteria

1. THE `composer.json` SHALL include `giorgiosironi/eris` `^1.0` in `require-dev`.
2. THE PBT test directory SHALL be established (e.g., `ut/PBT/` or integrated into existing test directories).
3. THE PBT suite SHALL include property tests for routing resolution covering:
   - GIVEN any valid route path defined in YAML THEN the router SHALL resolve it to the correct controller.
   - GIVEN any undefined route path THEN the router SHALL throw `ResourceNotFoundException`.
   - GIVEN a route with `%param%` placeholders THEN parameter replacement SHALL be idempotent.
4. THE PBT suite SHALL include property tests for Middleware_Chain covering:
   - GIVEN any permutation of middleware priorities THEN execution order SHALL be strictly descending by priority.
   - GIVEN a before middleware that returns a Response THEN subsequent middlewares and the controller SHALL NOT execute.
   - GIVEN `onlyForMasterRequest() = true` THEN the middleware SHALL NOT execute for sub-requests.
5. THE PBT suite SHALL include property tests for request dispatch covering:
   - GIVEN any valid request THEN `MicroKernel::handle()` SHALL return a Response with a valid HTTP status code (100–599).
   - GIVEN a controller that returns a non-Response value THEN the View_Handler_Chain SHALL be invoked.
   - GIVEN a controller that throws an exception THEN the Error_Handler_Chain SHALL be invoked.
6. THE `phpunit.xml` SHALL include a `pbt` test suite for PBT tests.


### Requirement 16: P2 — Bootstrap Config 驱动初始化保持

**User Story:** 作为迁移开发者，我希望 Bootstrap_Config 数组驱动的初始化方式在新框架下完整保留，以便下游消费者只需更换类名即可迁移。

#### Acceptance Criteria

1. THE `MicroKernel` constructor SHALL accept the same Bootstrap_Config structure as `SilexKernel`（所有顶层 key 保持不变）.
2. WHEN `routing` config is provided THEN `CacheableRouterProvider` equivalent SHALL be registered.
3. WHEN `twig` config is provided THEN `SimpleTwigServiceProvider` equivalent SHALL be registered.
4. WHEN `security` config is provided THEN `SimpleSecurityProvider` equivalent SHALL be registered.
5. WHEN `cors` config is provided THEN CORS_Subscriber SHALL be initialized with the strategies.
6. WHEN `middlewares` config is provided THEN each `MiddlewareInterface` instance SHALL be registered as EventSubscriber.
7. WHEN `view_handlers` config is provided THEN each callable SHALL be registered in the View_Handler_Chain.
8. WHEN `error_handlers` config is provided THEN each callable SHALL be registered in the Error_Handler_Chain.
9. WHEN `injected_args` config is provided THEN each object SHALL be registered as auto-injection candidate.
10. WHEN `trusted_proxies` / `trusted_header_set` / `behind_elb` / `trust_cloudfront_ips` config is provided THEN Trusted_Proxy SHALL be configured identically to current behavior.
11. THE `HttpConfiguration` Symfony Config definition SHALL remain unchanged (same validation rules).

---

## Socratic Review

**Q: 为什么选择 Symfony MicroKernel 而不是其他轻量框架？**
A: Silex 官方推荐迁移到 Symfony Flex / MicroKernel。当前项目已大量使用 Symfony 组件（Routing、Config、Security、EventDispatcher），MicroKernel 是最自然的迁移路径，最小化 API 变更。

**Q: `MicroKernel` 是否需要保持 `SilexKernel` 的 `__set()` 魔术方法？**
A: 不需要。`__set()` 是 Silex 时代的遗留设计，用于通过属性赋值注册 service provider / middleware / view handler 等。迁移后这些注册通过构造函数的 Bootstrap_Config 和 Symfony DI 完成，`__set()` 可以移除。

**Q: Security 组件的"最小可编译适配"具体边界是什么？**
A: 移除 Silex `SecurityServiceProvider` 继承和 Pimple 依赖，使 `SimpleSecurityProvider` 能在 Symfony 7.x 下编译。不重写 authenticator 系统（`AuthenticationPolicyInterface` 的 `getAuthenticationProvider()` / `getAuthenticationListener()` 等方法的实现逻辑留给 Phase 3）。`NullEntryPoint` 等不依赖 authenticator 的类应能正常工作。

**Q: Eris PBT 的 property test 是否会与现有 example-based test 重复？**
A: 部分场景会有重叠（如路由匹配），但 PBT 的价值在于通过随机输入发现 example-based test 未覆盖的边界情况。PBT 关注的是系统属性（如"任何有效路由都能解析"），而非具体的输入输出对。

**Q: `twig/twig` 升级到 ^3.0 对 Phase 2 的影响？**
A: Q1 CR 决策将 Twig 升级提前到 Phase 1。这消除了 `twig/twig` ^1.24 与 `symfony/twig-bridge` ^7.2 的兼容性冲突。Phase 2（PRP-004）的 scope 相应缩小，不再需要处理 Twig 版本升级，只需处理 Twig 模板层面的 breaking changes 和 Guzzle 升级。`twig/extensions` 已 abandoned，需在 Phase 1 移除。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: Requirement 1（依赖替换）是所有其他 Requirement 的前提。Requirement 2（MicroKernel）和 Requirement 3（Symfony DI）紧密耦合但关注点不同——R2 关注 Kernel 类本身，R3 关注 DI 容器迁移。Requirement 4–9 是各子系统的独立迁移，互不矛盾。Requirement 13（API 适配）横跨多个子系统但关注的是 Symfony 版本差异而非业务逻辑。Requirement 14（测试适配）依赖所有实现 Requirement 完成。Requirement 15（PBT）独立于其他 Requirement。

**Q: 与 proposal（PRP-003）的 scope 是否一致？**
A: 完全一致。PRP-003 定义的 Goals（移除 Silex、Symfony 7.x 升级、DI 迁移、路由迁移、中间件迁移、service provider 迁移、引入 Eris PBT）和 Non-Goals（不涉及 Twig 本体升级、Guzzle 升级、Security authenticator 重写、PHP 语言 breaking changes）均已体现在 Requirements 中。Scope 覆盖 `src/`、`composer.json`、`ut/`，与 PRP-003 一致。spec 级 DoD 与 PRP-003 定义的预期通过 suite 一致。

**Q: AC 中大量引用了具体的类名（如 `CacheableRouterProvider`、`GroupUrlMatcher`、`ExtendedArgumentValueResolver` 等），是否属于实现细节？**
A: 本 spec 的核心目标是框架迁移，被迁移的现有类是迁移的领域对象——它们定义了"什么需要被迁移"。这与 Phase 0 spec 中引用 PHPUnit 方法名的逻辑一致：迁移 spec 的 AC 需要精确描述"从什么迁移到什么"。Glossary 中已定义了关键的新概念（MicroKernel、CORS_Subscriber、Cookie_Subscriber 等），AC 中引用的现有类名（如 `CacheableRouterProvider`、`MiddlewareInterface`）是被迁移的对象，属于合理使用。判断标准是：这些类名描述的是"迁移什么"（外部可观察的迁移范围），而非"如何迁移"（内部实现策略）。

**Q: Requirement 16（Bootstrap Config 驱动初始化保持）与 Requirement 2–11 是否存在重叠？**
A: 存在部分重叠但关注点不同。R2–R11 各自关注具体子系统的迁移（路由、CORS、中间件等），其中部分 AC 涉及 Bootstrap_Config 的对应 key。R16 从整体视角确保 Bootstrap_Config 的完整性——所有顶层 key 都被保留且行为一致。R16 的价值在于：(1) 作为集成验收条件，确保各子系统迁移后的配置入口没有遗漏；(2) 覆盖了 R2–R11 中未显式提及的 config key（如 `trusted_proxies`、`cache_dir`）。这种"子系统 AC + 整体验收 AC"的结构是合理的。

---

## Gatekeep Log

**校验时间**: 2025-07-17
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [语体] R1 AC2：括号注释 `（如果存在直接依赖）` 改为 IF-THEN 条件句
- [语体] R1 AC5：移除括号注释 `（按实际需要拆分）`，改为行为描述
- [语体] R1 AC7：移除括号注释 `（Phase 2 处理）`，AC 应为纯行为规格，Phase 边界已在 Introduction 中说明
- [语体] R10 AC3：移除括号注释 `（Phase 2 升级）`，同上
- [语体] R11 AC7：`is expected to` 改为 `SHALL be expected to`，保持 SHALL 语体一致性
- [内容] R2 AC1：移除文件路径 `src/MicroKernel.php`，requirements 应描述类的存在和命名空间，不指定文件位置
- [内容] Socratic Review：补充两条 Q&A——(1) AC 中引用具体类名是否属于实现细节；(2) R16 与 R2–R11 的重叠问题

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围和请求处理流程
- [x] Introduction 明确了不涉及的内容（Non-scope）和约束（C-1 至 C-7）
- [x] Glossary 存在且包含 14 个术语
- [x] Requirements section 存在且包含 16 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 术语在 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义（新概念已定义；现有类名作为迁移对象合理引用）
- [x] 术语格式正确（`- **Term**: 定义`）
- [x] 每条 requirement 包含 User Story 和 Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 使用 SHALL / WHEN-THEN / IF-THEN 语体
- [x] AC 编号连续无跳号
- [x] Socratic Review 覆盖充分（技术选型依据、scope 一致性、实现细节边界、requirement 重叠、兼容性风险）
- [x] Goal CR 决策已体现在 requirements 中（C-1 至 C-7 对应 goal.md 的 Q1–Q4 决策）
- [x] Goal 清晰度达标（Introduction 清楚传达了迁移目标和请求处理流程保留要求）
- [x] Non-goal / Scope 边界明确（6 项 Non-scope + 7 项约束）
- [x] 完成标准充分（R14 AC5-6 + C-7 构成 DoD）
- [x] 可 design 性达标（各子系统迁移目标明确，技术风险已在 Socratic Review 中识别）

### Clarification Round

**状态**: 已回答

**Q1:** R1 AC7 要求 `twig/twig` 保持 `^1.24`，同时 R10 AC4 要求 `symfony/twig-bridge` 升级到 `^7.2`。Socratic Review 已识别这是一个兼容性风险（`symfony/twig-bridge` 7.x 要求 `twig/twig` `^3.0`）。Design 阶段需要确定处理策略：
- A) Phase 1 中 `symfony/twig-bridge` 也暂时保持低版本（不升级到 7.x），与 `twig/twig` `^1.24` 保持兼容，两者一起留给 Phase 2 升级
- B) Phase 1 中移除 `symfony/twig-bridge` 依赖，Twig 集成完全留给 Phase 2 重新引入
- C) Phase 1 中将 `twig/twig` 也升级到 `^3.0`（扩大 Phase 1 scope，将部分 Phase 2 工作提前）
- D) 其他（请说明）

**A:** C — 将 `twig/twig` 升级到 `^3.0`，`twig/extensions` 移除。Phase 2 scope 相应缩小，只处理 Twig 模板层面的 breaking changes 和 Guzzle 升级。

**Q2:** R2 AC5 要求 MicroKernel 暴露与 `SilexKernel` 相同的公共 API 方法。其中 `getTwig()` 在 Twig 未完整适配的情况下，其行为应如何定义？
- A) `getTwig()` 在 Phase 1 中保留方法签名但实现为 throw exception
- B) `getTwig()` 在 Phase 1 中保留方法签名，当 Twig 配置存在时正常返回 Twig 实例，配置不存在时 throw exception
- C) `getTwig()` 从 Phase 1 的 MicroKernel 公共 API 中移除，Phase 2 再加回
- D) 其他（请说明）

**A:** D — 既然 Q1 选了 C（Twig 升级到 3.x），`getTwig()` 可以正常工作，返回 `\Twig\Environment|null`。

**Q3:** R4 AC7 要求 Middleware_Chain 执行顺序与当前系统一致。R6 AC2 指定了 CORS_Subscriber 的具体 priority 数值。这些 priority 数值是作为 requirements 级别的行为契约，还是作为参考值？
- A) 精确数值是行为契约——下游消费者可能依赖这些值来注册自己的 middleware，必须保持不变
- B) 相对顺序是行为契约——只要相对顺序正确，具体数值可以调整

**A:** A — 精确数值是行为契约，必须保持不变。

**Q4:** R3 AC4 要求用户提供的 `providers` 通过新机制支持。迁移后的新 provider 接口设计方向：
- A) 定义一个新的 `OasisServiceProviderInterface`，方法签名接受 Symfony `ContainerBuilder`
- B) 要求下游消费者直接提供 Symfony `CompilerPass` 或 `Extension`，不再提供自定义 provider 抽象
- C) 保持 `providers` key 的语义但接受 callable（`function(ContainerBuilder $container)`）
- D) 其他（请说明）

**A:** B — 要求下游直接提供 Symfony `CompilerPass` / `Extension`，不引入自定义抽象。
