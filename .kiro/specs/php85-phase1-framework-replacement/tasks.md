# Implementation Plan: PHP 8.5 Phase 1 — Framework Replacement

## Overview

将 `oasis/http` 从 Silex 2.x + Pimple + Symfony 4.x 迁移到 Symfony MicroKernel + Symfony DI + Symfony 7.x + Twig 3.x。按 CR 决策（请求流程优先），先跑通基本请求链路（composer 依赖 → MicroKernel → Middleware → Routing），再补全 CORS、View Handler、Error Handler、Cookie、Twig、Security 等子系统，最后完成测试适配和 PBT。

## Tasks

- [x] 1. composer.json 依赖替换
  - [x] 1.1 移除 Silex/Pimple 依赖，升级 Symfony 组件到 ^7.2，新增 MicroKernel 所需包
    - 移除 `silex/silex`、`silex/providers`、`twig/extensions`
    - 升级 `symfony/http-foundation`、`symfony/routing`、`symfony/config`、`symfony/yaml`、`symfony/expression-language`、`symfony/twig-bridge`、`symfony/css-selector`、`symfony/browser-kit` 到 `^7.2`
    - 将 `symfony/security` 替换为 `symfony/security-core` `^7.2` + `symfony/security-http` `^7.2`
    - 新增 `symfony/http-kernel` `^7.2`、`symfony/dependency-injection` `^7.2`、`symfony/event-dispatcher` `^7.2`、`symfony/framework-bundle` `^7.2`
    - 升级 `twig/twig` 到 `^3.0`
    - 新增 `giorgiosironi/eris` `^1.0` 到 `require-dev`
    - 保持 `guzzlehttp/guzzle` `^6.3` 不变
    - 执行 `composer update` 确认依赖解析成功
    - _Ref: Requirement 1, AC 1–8_
  - [x] 1.2 Checkpoint: 执行 `composer install` 确认依赖解析成功，无冲突。Commit。

- [x] 2. MicroKernel 核心入口与 Middleware 机制（请求链路骨架）
  - [x] 2.1 创建 MicroKernel 类，实现核心公共 API
    - 在 `src/MicroKernel.php` 创建 `Oasis\Mlib\Http\MicroKernel`，继承 Symfony `Kernel` + `MicroKernelTrait`，实现 `AuthorizationCheckerInterface`
    - 保留 priority 常量（`BEFORE_PRIORITY_ROUTING` = 32、`BEFORE_PRIORITY_CORS_PREFLIGHT` = 20、`BEFORE_PRIORITY_FIREWALL` = 8、`BEFORE_PRIORITY_EARLIEST` = 512、`BEFORE_PRIORITY_LATEST` = -512、`AFTER_PRIORITY_EARLIEST` = 512、`AFTER_PRIORITY_LATEST` = -512），精确数值是行为契约
    - 构造函数接受 `(array $httpConfig, bool $isDebug)`，通过 `ConfigurationValidationTrait` + `HttpConfiguration` 处理配置
    - 实现 `run()`、`handle()`、`isGranted()`、`getToken()`、`getUser()`、`getTwig()`、`getParameter()`、`addExtraParameters()`、`addControllerInjectedArg()`、`addMiddleware()`、`getCacheDirectories()` 公共方法
    - `run()` 创建 Request → `handle()` → send Response → `terminate()` → 慢请求检测
    - `handle()` 处理 ELB / CloudFront Trusted_Proxy 配置后委托 parent `handle()`
    - 实现 `configureContainer()` 将 Bootstrap_Config 转化为 Symfony DI service 定义
    - 不保留 `__set()` 魔术方法
    - _Ref: Requirement 2, AC 1–7/9; Requirement 3, AC 1/2/5; Requirement 16, AC 1/10/11_
  - [x] 2.2 迁移 MiddlewareInterface 和 AbstractMiddleware
    - 更新 `MiddlewareInterface::before()` 签名：`Application $application` → `MicroKernel $kernel`
    - 更新 `AbstractMiddleware`：移除 `Silex\Application` 依赖，`getBeforePriority()` 返回 `MicroKernel::BEFORE_PRIORITY_EARLIEST`，`getAfterPriority()` 返回 `MicroKernel::AFTER_PRIORITY_LATEST`
    - 为 `onlyForMasterRequest()`、`getBeforePriority()`、`getAfterPriority()` 添加 `bool`/`int|false` 返回类型
    - _Ref: Requirement 4, AC 1/2_
  - [x] 2.3 实现 Middleware 注册与 EventDispatcher 集成
    - `MicroKernel::addMiddleware()` 存储 middleware 实例
    - 在 `boot()` 阶段通过 `addListener()` 注册到 `KernelEvents::REQUEST`（before）和 `KernelEvents::RESPONSE`（after）
    - before middleware 接收 `RequestEvent`，支持 `setResponse()` 短路
    - after middleware 接收 `ResponseEvent`
    - `onlyForMasterRequest()` 过滤使用 `HttpKernelInterface::MAIN_REQUEST`
    - Bootstrap_Config `middlewares` key 中的每个 `MiddlewareInterface` 实例注册为 listener
    - _Ref: Requirement 4, AC 3–7; Requirement 16, AC 6_
  - [x] 2.4 删除旧 SilexKernel 类
    - 删除 `src/SilexKernel.php`
    - _Ref: Requirement 2, AC 8_
  - [x] 2.5 Checkpoint: 确认 MicroKernel 类可编译，MiddlewareInterface / AbstractMiddleware 无语法错误。Commit。

- [x] 3. 路由系统迁移
  - [x] 3.1 重写 CacheableRouterProvider 为 DI 注册方式
    - 移除 `Pimple\ServiceProviderInterface` 实现
    - 提供 `register(MicroKernel $kernel)` 方法
    - 路由 service 通过 MicroKernel getter 暴露
    - `RedirectableUrlMatcher` 基类从 `Silex\Provider\Routing\RedirectableUrlMatcher` 更新为 Symfony Routing 7.x 等价类
    - _Ref: Requirement 5, AC 1/8/9; Requirement 3, AC 3; Requirement 16, AC 2_
  - [x] 3.2 适配 CacheableRouter 接受 MicroKernel
    - 构造函数 `SilexKernel` → `MicroKernel`（或 parameter provider interface）
    - 保留 `getRouteCollection()` 的 `%param%` 参数替换逻辑
    - _Ref: Requirement 5, AC 2/3_
  - [x] 3.3 适配 InheritableYamlFileLoader 到 Symfony Routing 7.x
    - 检查并适配 `YamlFileLoader::import()` 签名变化
    - 确保 `InheritableRouteCollection` 兼容 Symfony Routing 7.x
    - _Ref: Requirement 5, AC 7_
  - [x] 3.4 验证 GroupUrlMatcher / GroupUrlGenerator / CacheableRouterUrlMatcherWrapper 无需修改
    - 确认这些类无 Silex 依赖，在 Symfony 7.x 下正常工作
    - _Ref: Requirement 5, AC 4/5/6_
  - [x] 3.5 Checkpoint: 运行 `routing` 和 `middlewares` test suite，确认请求链路基本跑通（MicroKernel 能启动、路由能匹配、middleware 能执行）。如有问题请与用户沟通。Commit。

- [x] 4. Symfony 4.x → 7.x API 适配与 ArgumentValueResolver
  - [x] 4.1 全局替换 Symfony 4.x 已移除 API
    - `FilterResponseEvent` → `ResponseEvent`
    - `GetResponseEvent` → `RequestEvent`
    - `GetResponseForExceptionEvent` → `ExceptionEvent`
    - `HttpKernelInterface::MASTER_REQUEST` → `HttpKernelInterface::MAIN_REQUEST`
    - 移除 `Silex\CallbackResolver` 用法
    - 检查 `RequestMatcher` 构造函数在 Symfony 7.x 中的变化并适配
    - _Ref: Requirement 13, AC 1–7_
  - [x] 4.2 适配 ExtendedArgumentValueResolver 到 Symfony 7.x
    - 将 `ArgumentValueResolverInterface` 替换为 `ValueResolverInterface`
    - 移除 `supports()` 方法，将逻辑合并到 `resolve()` 中（不支持时返回空数组）
    - 注册到 Symfony DI 作为 argument value resolver
    - Bootstrap_Config `injected_args` 继续作为 auto-injection 候选
    - _Ref: Requirement 12, AC 1–4; Requirement 16, AC 9_
  - [x] 4.3 Checkpoint: 确认所有 Symfony 4.x API 引用已替换，代码可编译。Commit。

- [x] 5. CORS 机制迁移
  - [x] 5.1 重写 CrossOriginResourceSharingProvider 为 EventSubscriber
    - 创建 `CrossOriginResourceSharingSubscriber` 实现 `EventSubscriberInterface`
    - `getSubscribedEvents()` 注册：`KernelEvents::REQUEST` [onPreRouting, 33] + [onPostRouting, 20]、`KernelEvents::RESPONSE` [onResponse, -512]、`KernelEvents::EXCEPTION` [onException, 512]
    - `onPreRouting()`、`onPostRouting()` 签名适配 `RequestEvent`
    - `onResponse()` 签名从 `(Request, Response)` 改为 `(ResponseEvent $event)`
    - `onMethodNotAllowedHttp()` 改为 `onException(ExceptionEvent $event)`
    - 保留 preflight 检测逻辑和 W3C CORS 处理模型
    - `CrossOriginResourceSharingStrategy` 保持不变
    - Bootstrap_Config `cors` key 初始化 strategies
    - _Ref: Requirement 6, AC 1–7; Requirement 16, AC 5_
  - [x] 5.2 Checkpoint: 运行 `cors` test suite，确认 CORS 机制可用。Commit。

- [x] 6. View Handler 链与 Error Handler 链迁移
  - [x] 6.1 创建 ViewHandlerSubscriber
    - 在 `src/EventSubscribers/ViewHandlerSubscriber.php` 创建 EventSubscriber
    - 监听 `KernelEvents::VIEW`，遍历 handler 链，handler 返回 Response 时停止链
    - Bootstrap_Config `view_handlers` key 注册 callable
    - _Ref: Requirement 7, AC 1–5; Requirement 16, AC 7_
  - [x] 6.2 更新 View 相关类的 SilexKernel 引用
    - `FallbackViewHandler`：`SilexKernel` → `MicroKernel`
    - `ResponseRendererInterface`：`SilexKernel` → `MicroKernel`
    - `DefaultHtmlRenderer`：`SilexKernel` → `MicroKernel`，同时更新 Twig 1.x API 引用（`\Twig_Error_Loader` → `\Twig\Error\LoaderError`）
    - `JsonApiRenderer`：`SilexKernel` → `MicroKernel`
    - _Ref: Requirement 7, AC 6/7/8_
  - [x] 6.3 实现 Error Handler 链注册
    - 在 `MicroKernel` 中通过 `addListener()` 逐个注册 error handler 到 `KernelEvents::EXCEPTION`
    - 内联 `ExtendedExceptionListnerWrapper` 的核心行为（handler 返回 null 且 event 无 response 时不设置 response）
    - 删除或重写 `ExtendedExceptionListnerWrapper`（移除 Silex `ExceptionListenerWrapper` 基类依赖）
    - Bootstrap_Config `error_handlers` key 注册 handler，保留 priority 机制
    - _Ref: Requirement 8, AC 1–5; Requirement 16, AC 8_
  - [x] 6.4 Checkpoint: 确认 ViewHandlerSubscriber 和 Error Handler 链可编译，无语法错误。Commit。

- [x] 7. Cookie Provider 迁移
  - [x] 7.1 重写 SimpleCookieProvider 为 CookieSubscriber
    - 创建 `CookieSubscriber` 实现 `EventSubscriberInterface`，监听 `KernelEvents::RESPONSE`
    - 从 `ResponseCookieContainer` 读取 cookie 写入 response headers
    - 通过 `MicroKernel::addControllerInjectedArg()` 注册 `ResponseCookieContainer`
    - `ResponseCookieContainer` 保持不变
    - _Ref: Requirement 9, AC 1–4_
  - [x] 7.2 Checkpoint: 运行 `cookie` test suite，确认 Cookie 机制可用。Commit。

- [x] 8. Twig Provider 迁移
  - [x] 8.1 重写 SimpleTwigServiceProvider
    - 移除 `Silex\Provider\TwigServiceProvider` 继承和 Pimple 依赖
    - 在 `MicroKernel::boot()` 中直接创建 `\Twig\Environment` 实例
    - 从 Bootstrap_Config `twig` key 读取配置（template_dir、cache_dir、asset_base、globals）
    - Twig 1.x API 替换：`Twig_Environment` → `\Twig\Environment`、`Twig_SimpleFunction` → `\Twig\TwigFunction`、`Twig_Error_Loader` → `\Twig\Error\LoaderError`
    - Twig 配置不存在时不注册 Twig services
    - `MicroKernel::getTwig()` 返回 `\Twig\Environment|null`
    - _Ref: Requirement 10, AC 1–8; Requirement 16, AC 3_
  - [x] 8.2 Checkpoint: 运行 `twig` test suite，确认 Twig 集成可用。Commit。

- [x] 9. Security Provider 最小可编译适配
  - [x] 9.1 重写 SimpleSecurityProvider
    - 移除 `Silex\Provider\SecurityServiceProvider` 继承和 Pimple 依赖
    - 保留 `addFirewall()`、`addAccessRule()`、`addAuthenticationPolicy()`、`addRoleHierarchy()` 配置方法
    - `register()` 改为接受 `MicroKernel`
    - Security 配置不存在时不注册 Security services
    - _Ref: Requirement 11, AC 1/2/6; Requirement 16, AC 4_
  - [x] 9.2 适配 AuthenticationPolicyInterface 和 Security abstract 类
    - `AuthenticationPolicyInterface`：方法签名 `Container $app` → `MicroKernel $kernel`，移除 `Pimple\Container` 和 `ListenerInterface` 引用
    - `AbstractSimplePreAuthenticationPolicy`：改为 abstract stub，移除对 `SimpleAuthenticationProvider`、`SimplePreAuthenticationListener`、`ListenerInterface` 的依赖，所有依赖已移除 API 的方法改为 abstract
    - `AbstractSimplePreAuthenticator`：改为 abstract stub，移除 `SimplePreAuthenticatorInterface` 的 `implements`，所有依赖已移除 API 的方法改为 abstract
    - `AbstractSimplePreAuthenticateUserProvider`：检查并适配（如有 Silex/Pimple 依赖）
    - _Ref: Requirement 11, AC 3/4/5_
  - [x] 9.3 Checkpoint: 确认 Security 相关类可编译（abstract stub 策略）。Security suite 预期失败（除 `NullEntryPointTest`）。Commit。

- [x] 10. Provider 机制迁移（Bootstrap_Config `providers` key）
  - [x] 10.1 实现 providers 接受 CompilerPass / Extension
    - Bootstrap_Config `providers` key 改为接受 `CompilerPassInterface` 或 `ExtensionInterface` 实例数组
    - 在 `configureContainer()` 中注册 CompilerPass / Extension
    - 非法类型抛出 `InvalidConfigurationException`
    - _Ref: Requirement 3, AC 4/6_
  - [x] 10.2 Checkpoint: 运行 `cors`、`aws`、`routing`、`cookie`、`middlewares`、`twig` test suite，确认全部子系统迁移完成且基本可用。如有问题请与用户沟通。Commit。

- [-] 11. 测试适配
  - [x] 11.1 更新测试 bootstrap 文件
    - 更新 `ut/app.php`、`ut/index.cors.php`、`ut/index.security.php`、`ut/index.twig.php`、`ut/index.zxc.php`、`ut/test.php` 中的 `SilexKernel` → `MicroKernel`
    - 更新 `ut/AwsTests/*.php`（`elb.php`、`elb-only.php`、`cloudfront-only.php`、`no-aws.php`）
    - 更新 `ut/Cors/app.cors.php`、`ut/Cors/app.cors-advanced.php`
    - 更新 `ut/Twig/app.twig*.php`
    - 更新 `ut/Security/app.security*.php`
    - 更新 `ut/Integration/app.integration-*.php`
    - 所有 `new SilexKernel(...)` → `new MicroKernel(...)`
    - 适配 WebTestCase（Silex WebTestCase → Symfony WebTestCase 或自定义 test client）
    - _Ref: Requirement 14, AC 2_
  - [x] 11.2 适配 SilexKernelTest 和 SilexKernelWebTest
    - 更新内部 `SilexKernel` 引用为 `MicroKernel`（保持文件名不变）
    - 适配测试中的 `$app['xxx']` Pimple 风格访问
    - 适配 `$app->service_providers = [...]` 为 Bootstrap_Config `providers` key
    - _Ref: Requirement 14, AC 1/3_
  - [x] 11.3 适配 FallbackViewHandlerTest
    - 更新 `SilexKernel` mock 为 `MicroKernel` mock
    - _Ref: Requirement 14, AC 4_
  - [x] 11.4 适配其他受影响的测试文件
    - 更新 `CacheableRouterProviderTest`、`SimpleCookieProviderTest`、`CrossOriginResourceSharingTest`、`CrossOriginResourceSharingAdvancedTest`、`TwigServiceProviderTest`、`SecurityServiceProviderTest`、`AbstractMiddlewareTest`、`ExtendedExceptionListnerWrapperTest`、`ExtendedArgumentValueResolverTest` 等
    - 更新 `ElbTrustedProxyTest`
    - 更新 `DefaultHtmlRendererTest`、`JsonApiRendererTest`
    - _Ref: Requirement 14, AC 1/5_
  - [-] 11.5 Checkpoint: 运行以下 test suite 并确认全部通过：`cors`、`aws`、`routing`、`cookie`、`middlewares`、`twig`、`SilexKernelTest`、`SilexKernelWebTest`、`FallbackViewHandlerTest`。Security suite 预期失败（除 `NullEntryPointTest`）。如有问题请与用户沟通。Commit。
    - _Ref: Requirement 14, AC 5/6_

- [ ] 12. Eris PBT 引入与核心 Property Test
  - [ ] 12.1 更新 phpunit.xml 新增 pbt test suite
    - 添加 `<testsuite name="pbt"><directory>ut/PBT</directory></testsuite>`
    - _Ref: Requirement 15, AC 6_
  - [ ] 12.2 编写路由解析 property test
    - 创建 `ut/PBT/RoutingPropertyTest.php`
    - **Property CP1: 路由解析幂等性** — 对于任何已定义路由 path，`router->match()` 结果在多次调用间一致
    - 测试未定义路由抛出 `ResourceNotFoundException`
    - 测试 `%param%` 参数替换幂等性
    - 集成级：启动 MicroKernel 实例，使用真实路由配置
    - _Ref: Requirement 15, AC 1/2/3_
  - [ ] 12.3 编写 Middleware 链 property test
    - 创建 `ut/PBT/MiddlewareChainPropertyTest.php`
    - **Property CP2: Middleware 优先级排序** — 任何 middleware 集合的执行顺序严格按 priority 降序
    - **Property CP3: Before middleware 短路** — before middleware 返回 Response 时后续 middleware 和 controller 不执行
    - 测试 `onlyForMasterRequest()` 对 sub-request 的过滤
    - 集成级：启动 MicroKernel 实例
    - _Ref: Requirement 15, AC 4_
  - [ ] 12.4 编写请求分发 property test
    - 创建 `ut/PBT/RequestDispatchPropertyTest.php`
    - **Property CP4: 请求分发完整性** — 任何有效请求的 `handle()` 返回 Response 状态码在 100–599 范围内
    - **Property CP5: View Handler 链传递** — 控制器返回非 Response 值时 View_Handler_Chain 被调用
    - 测试控制器抛出异常时 Error_Handler_Chain 被调用
    - 集成级：启动 MicroKernel 实例
    - _Ref: Requirement 15, AC 5_
  - [ ] 12.5 Checkpoint: 运行全量测试（`phpunit`），确认 C-7 定义的预期通过 suite 全部通过，PBT suite 通过。确认 Security suite 预期失败（除 `NullEntryPointTest`）不阻塞。如有问题请与用户沟通。Commit。

- [ ] 13. 手工测试
  - [ ] 13.1 编写手工测试场景
    - 场景 1: MicroKernel 启动与基本请求 — 使用 Bootstrap_Config 创建 MicroKernel 实例，发送 GET 请求到已定义路由，确认返回正确 Response
    - 场景 2: Middleware 链执行 — 配置 before/after middleware，发送请求，确认 middleware 按 priority 顺序执行，before middleware 返回 Response 时短路
    - 场景 3: CORS preflight — 发送 OPTIONS 请求带 `Access-Control-Request-Method` header，确认返回 PrefilightResponse 和正确的 CORS headers
    - 场景 4: View Handler 链 — 控制器返回非 Response 值，确认 View Handler 链被调用并生成 Response
    - 场景 5: Error Handler 链 — 控制器抛出异常，确认 Error Handler 链被调用，handler 返回 null 时异常继续传播
    - 场景 6: Cookie 写入 — 通过 `ResponseCookieContainer` 添加 cookie，确认 Response headers 中包含 cookie
    - 场景 7: Twig 渲染 — 配置 Twig，控制器返回模板渲染结果，确认 HTML 输出正确
    - 场景 8: Bootstrap_Config 完整性 — 使用包含所有顶层 key 的 Bootstrap_Config 创建 MicroKernel，确认各子系统正常初始化
  - [ ] 13.2 执行手工测试并记录结果

- [ ] 14. Code Review
  - [ ] 14.1 委托给 code-reviewer sub-agent 执行

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有实现项？**
A: 是。D1→task 1, D2→task 2, D3→task 2, D4→task 5, D5→task 6, D6→task 6, D7→task 7, D8→task 3, D9→task 8, D10→task 9, D11→task 4, D12→task 4, D13→task 11, D14→task 12, D15→task 10。所有 design section 均有对应 task。

**Q: task 之间的依赖顺序是否正确？**
A: 是。遵循 CR 决策 C（请求流程优先）：composer 依赖（task 1）→ MicroKernel + Middleware 骨架（task 2）→ 路由（task 3）→ API 适配（task 4）→ CORS（task 5）→ View/Error Handler（task 6）→ Cookie（task 7）→ Twig（task 8）→ Security（task 9）→ Provider 机制（task 10）→ 测试适配（task 11）→ PBT（task 12）。每个 task 依赖前序 task 的产出。

**Q: 每个 task 的粒度是否合适？**
A: 基本合适。大多数 top-level task 包含 2–5 个 sub-task，每个 sub-task 可在独立 session 中完成。task 2（MicroKernel 核心）是最大的 task，包含 5 个 sub-task，但这是因为 MicroKernel 是核心入口，拆分到不同 top-level task 会破坏内聚性。

**Q: checkpoint 的设置是否覆盖了关键阶段？**
A: 是。每个 top-level task 末尾都有 checkpoint。关键阶段的 checkpoint 包含具体的 test suite 运行命令（task 3 的路由+中间件、task 10 的全子系统、task 11 的预期通过 suite、task 12 的全量测试）。

**Q: 手工测试是否覆盖了 requirements 中的关键用户场景？**
A: 是。8 个手工测试场景覆盖了 MicroKernel 启动、Middleware 链、CORS、View Handler、Error Handler、Cookie、Twig、Bootstrap_Config 完整性，对应 R2–R10 和 R16 的核心用户场景。

**Q: PBT task 是否与 design 中的 Correctness Properties 对齐？**
A: 是。task 12.2 对应 CP1（路由解析幂等性），task 12.3 对应 CP2（Middleware 优先级排序）和 CP3（Before middleware 短路），task 12.4 对应 CP4（请求分发完整性）和 CP5（View Handler 链传递）。CP6（Bootstrap_Config 完整性）通过 example-based test 覆盖，不在 PBT scope 内。

## Notes

- 按 `spec-execution.md` 规范执行所有 task
- Commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint，通过后 commit
- 实现顺序遵循 CR 决策 C：请求流程优先（D1+D2+D3+D8 → 其他子系统）
- Security 组件仅做最小可编译适配（abstract stub 策略，CR Q2: C），authenticator 重写留给 Phase 3
- 测试文件保持原文件名不变（CR Q3: B），仅更新内部引用
- PBT 采用集成级策略（CR Q4: A），直接启动 MicroKernel 实例
- 所有 priority 数值是行为契约（CR Q3: A），必须保持精确值不变
- `providers` key 改为接受 `CompilerPassInterface` / `ExtensionInterface`（CR Q4: B）
- spec 级 DoD：tasks 全部完成 + C-7 定义的预期通过 suite 实际通过

## Gatekeep Log

**校验时间**: 2025-07-17
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] Checkpoint 从独立 top-level task（原 task 4/12/14/16）改为每个 top-level task 的最后一个 sub-task，符合 steering 要求
- [结构] 补充手工测试 top-level task（task 13），作为倒数第二个 top-level task
- [结构] 补充 Code Review top-level task（task 14），作为最后一个 top-level task，描述为委托给 code-reviewer sub-agent 执行
- [结构] 补充 `## Socratic Review` section，覆盖 design 全覆盖、依赖顺序、粒度、checkpoint 覆盖、手工测试覆盖、PBT 对齐 6 个维度
- [格式] Requirement 引用格式从 `_Requirements: 1.1, 1.2_` 统一为 `_Ref: Requirement X, AC Y_` 格式，与 steering 要求一致
- [格式] 移除 PBT sub-task 的 `*` optional 标记（`- [ ]*`），所有 task 均为 mandatory
- [内容] 补充 R16 AC 2（routing config → CacheableRouterProvider 注册）的引用到 task 3.1
- [内容] Notes section 补充 `spec-execution.md` 引用和 commit 时机说明
- [内容] 移除 Notes 中 "Tasks marked with `*` are optional" 说明（与 mandatory 要求矛盾）
- [目的] 重新编号所有 top-level task（1–14），确保序号连续无跳号

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review
- [x] 倒数第二个 top-level task 是手工测试
- [x] 自动化实现 task 排在手工测试和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–14），连续无跳号
- [x] sub-task 有层级序号（N.1, N.2...），连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements.md 中的每条 requirement（R1–R16）至少被一个 task 引用
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序（请求流程优先）
- [x] 无循环依赖
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 描述中包含具体的验证命令和 commit 动作
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在，覆盖 8 个关键用户场景
- [x] Code Review 是最后一个 top-level task，描述为委托给 code-reviewer sub-agent
- [x] `## Notes` section 存在
- [x] Notes 明确提到 `spec-execution.md`
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（CR 决策、priority 契约、DoD 等）
- [x] `## Socratic Review` section 存在且覆盖充分
- [x] Design CR 决策已体现（Q1: C 请求流程优先、Q2: C abstract stub、Q3: B 保持文件名、Q4: A 集成级 PBT）
- [x] Design 全覆盖（D1–D15 均有对应 task）
- [x] 每个 sub-task 可独立执行
- [x] 验收闭环完整（checkpoint + 手工测试 + code review）
- [x] 执行路径无歧义
