# Implementation Plan: PHP 8.5 Phase 4 — PHP Language Adaptation

## Overview

将 `oasis/http` 库从 PHP 7.0 时代的语法风格全面适配到 PHP 8.5。工作分为兼容性修复（R1–R5）和代码现代化（R6–R10）两大部分，涉及 51 个文件的变更。

执行策略基于 Design CR 决策：
- Q1=B: 按文件逐一修改，每个文件一次性完成所有适用的变更（R1–R10），减少文件重复打开
- Q2=B: 松散比较修复合并到各文件的 task 中，每个文件的 task 包含该文件的所有变更（含松散比较），避免重叠
- Q3=A: PBT 测试先行——先编写 PBT 测试（基于当前代码验证 property 成立），再执行代码修改，PBT 作为回归保护
- Q4=C: `docs/state/architecture.md` 修正 + `composer.json` description 更新合并为一个"元数据和文档更新" task

模块分组：按功能模块将文件分组为 top-level task，模块内 sub-task 为 per-file 修改。PBT 测试作为第一个 top-level task（test-first），代码修改按模块依次执行。

## Tasks

- [x] 1. PBT 测试先行：编写 Property-Based Tests
  - [x] 1.1 新建 `ut/PBT/WrappedExceptionInfoPropertyTest.php`
    - **Property 1: Status code normalization invariant** — 对任意整数 HTTP status code，`getCode()` 在输入为 0 时返回 500，非 0 时返回原值
    - **Property 2: Serialization code field metamorphic property** — 对任意 Exception，`serializeException()` 输出仅在 code 非 0 时包含 `code` 字段
    - **Property 3: WrappedExceptionInfo JSON round-trip** — `json_decode(json_encode(toArray()))` 产生等价数组结构
    - 使用 Eris 1.x 生成随机 status code 和 Exception 对象
    - 基于当前代码验证 property 成立，作为后续修改的回归保护
    - _Requirements: 15.1, 15.2, 15.3, 17.4_
  - [x] 1.2 新建 `ut/PBT/ViewHandlerPropertyTest.php`
    - **Property 4: MIME type matching strict comparison invariance** — 对任意有效 MIME type 字符串，`shouldHandle()` 在严格比较下返回与松散比较相同的结果
    - **Property 5: Format-to-renderer mapping correctness** — `html`/`page` → `DefaultHtmlRenderer`，`api`/`json` → `JsonApiRenderer`，其他 → `InvalidConfigurationException`
    - 使用 Eris 1.x 生成随机 MIME type 和 format 字符串
    - _Requirements: 16.1, 16.2, 16.3_
  - [x] 1.3 新建 `ut/PBT/ConstructorPromotionPropertyTest.php`
    - **Property 6: SimpleAccessRule configuration round-trip** — 构造后 getter 返回等价于输入配置的值
    - **Property 7: SimpleFirewall configuration round-trip** — 构造后 getter 返回等价于输入配置的值
    - **Property 8: UniquenessViolationHttpException construction round-trip** — `getStatusCode()` 始终 400，`getMessage()`/`getPrevious()`/`getCode()` 匹配输入
    - 使用 Eris 1.x 生成随机配置参数
    - _Requirements: 17.1, 17.2, 17.3_
  - [x] 1.4 Checkpoint: 运行 `phpunit --testsuite pbt` 确认所有 PBT 在当前代码上通过，commit

- [x] 2. ErrorHandlers 模块
  - [x] 2.1 修改 `src/ErrorHandlers/WrappedExceptionInfo.php`
    - R3: `$this->code == 0` → `=== 0`（S1）；`$e->getCode() != 0` → `!== 0`（S2）
    - R5 AC5: `jsonSerialize()` 添加 `mixed` 返回类型
    - R8: 构造函数 `$httpStatusCode` 添加 `int` 类型；`getAttribute(string $key): mixed`；`getAttributes(): array`；`getCode(): int`；`setCode(int $code): void`；`getException(): \Exception`；`getOriginalCode(): int`；`getShortExceptionType(): string`；`setAttribute(string $key, mixed $value): void`；`serializeException(\Exception $e): array`；`toArray(bool $rich = false): array`
    - R8: 属性类型声明 — `protected \Exception $exception`、`protected string $shortExceptionType`、`protected int $code`、`protected int $originalCode`、`protected array $attributes = []`
    - 不加 readonly（`$code` 有 setter，其他属性 protected 可被子类访问）
    - _Requirements: 3.3, 5.5, 8.1, 8.2, 8.3_
  - [x] 2.2 修改 `src/ErrorHandlers/ExceptionWrapper.php`
    - R8: `__invoke(\Exception $e, Request $request, int $httpStatusCode): WrappedExceptionInfo`
    - R8: `furtherProcessException(WrappedExceptionInfo $info, \Exception $e): void`
    - 注意：`switch (true)` 含 fall-through 语义，不替换为 match（R7 AC3）
    - _Requirements: 8.1, 8.2_
  - [x] 2.3 修改 `src/ErrorHandlers/JsonErrorHandler.php`
    - R8: `__invoke(\Exception $e, int $code): array`
    - _Requirements: 8.1, 8.2_
  - [x] 2.4 Checkpoint: 运行 `phpunit --testsuite error-handlers --testsuite pbt` 确认通过，commit

- [x] 3. Views 模块
  - [x] 3.1 修改 `src/Views/ResponseRendererInterface.php`
    - R8: `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`；`renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response`
    - 接口变更，实现类（DefaultHtmlRenderer、JsonApiRenderer）必须同步更新
    - _Requirements: 8.1, 8.2_
  - [x] 3.2 修改 `src/Views/ResponseRendererResolverInterface.php`
    - R8: `resolveRequest(Request $request): ResponseRendererInterface`
    - _Requirements: 8.1, 8.2_
  - [x] 3.3 修改 `src/Views/DefaultHtmlRenderer.php`
    - R8: `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`；`renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response`
    - _Requirements: 8.1, 8.2_
  - [x] 3.4 修改 `src/Views/JsonApiRenderer.php`
    - R8: `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`；`renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response`
    - _Requirements: 8.1, 8.2_
  - [x] 3.5 修改 `src/Views/RouteBasedResponseRendererResolver.php`
    - R7 AC1: `switch ($format)` → `match ($format)` 表达式
    - R8: `resolveRequest(Request $request): ResponseRendererInterface`
    - _Requirements: 7.1, 7.2, 8.1, 8.2_
  - [x] 3.6 修改 `src/Views/AbstractSmartViewHandler.php`
    - R3 AC8: 5 处松散比较 → 严格比较（S4–S8）
    - R8: `shouldHandle(Request $request): bool`；`getCompatibleTypes(): array`
    - _Requirements: 3.8, 8.1, 8.2_
  - [x] 3.7 修改 `src/Views/FallbackViewHandler.php`
    - R3 AC4: `$rendererResolver == null` → `=== null`（S3）
    - R6: `$kernel` 适用 promotion + readonly（直接赋值无逻辑）
    - R10: `$kernel` 声明为 `protected readonly MicroKernel`
    - R8: `__invoke(mixed $result, Request $request): Response`；属性类型声明
    - 注意：`$rendererResolver` 有条件逻辑（`=== null` 时创建默认实例），不适用 promotion，需保留为显式属性
    - _Requirements: 3.4, 6.1, 6.3, 6.4, 8.1, 8.2, 10.1_
  - [x] 3.8 修改 `src/Views/PrefilightResponse.php`
    - R8: 属性类型声明 `protected array $allowedMethods = []`、`protected bool $frozen = false`
    - R8: `getAllowedMethods(): array`；`addAllowedMethod(string $method): void`；`isFrozen(): bool`；`freeze(): void`
    - 不加 readonly（`$frozen` 和 `$allowedMethods` 均有修改方法）
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 3.9 Checkpoint: 运行 `phpunit --testsuite views --testsuite pbt` 确认通过，commit

- [x] 4. Routing 模块
  - [x] 4.1 修改 `src/ServiceProviders/Routing/CacheableRouterUrlMatcherWrapper.php`
    - R9 AC1: `strpos($result['_controller'], "::") !== false` → `str_contains($result['_controller'], "::")`
    - R6 + R10: `$other` 和 `$namespaces` 均可 promotion + readonly
    - R8: 属性类型声明
    - _Requirements: 6.1, 6.3, 6.4, 8.1, 8.3, 9.1, 10.1_
  - [x] 4.2 修改 `src/ServiceProviders/Routing/GroupUrlMatcher.php`
    - R3 AC6: `$matched == $total` → `=== $total`（S9）
    - R6: `$matchers` 可 promotion + readonly；`$context` 有 setter，可 promotion 但不加 readonly
    - R8: 属性类型声明
    - _Requirements: 3.6, 6.1, 6.3, 8.1, 8.3, 10.1_
  - [x] 4.3 修改 `src/ServiceProviders/Routing/GroupUrlGenerator.php`
    - R3: `$found == $total` → `=== $total`（S10）
    - R8: 属性类型声明
    - 注意：`$context` 和 `$contextExplicitlySet` 有 setter，不适用 readonly
    - _Requirements: 3.1, 8.1, 8.3_
  - [x] 4.4 修改 `src/ServiceProviders/Routing/CacheableRouterProvider.php`
    - R3 AC7: `strcasecmp(...) == 0` → `=== 0`（S11）
    - R8: `getConfigDataProvider(): DataProviderInterface`；`getRouter(RequestContext $requestContext): Router`；属性类型声明
    - _Requirements: 3.7, 8.1, 8.2_
  - [x] 4.5 修改 `src/ServiceProviders/Routing/CacheableRouter.php`
    - R8: 属性类型声明 `private MicroKernel $kernel`、`private bool $isParamReplaced = false`
    - R8: 构造函数 `$resource` 添加 `mixed` 类型
    - _Requirements: 8.1, 8.3_
  - [x] 4.6 确认 `src/ServiceProviders/Routing/InheritableRouteCollection.php` 和 `src/ServiceProviders/Routing/InheritableYamlFileLoader.php` 无需修改
    - `InheritableRouteCollection` 已有类型声明
    - `InheritableYamlFileLoader` 已有完整类型声明
    - _Requirements: 8.5_
  - [x] 4.7 Checkpoint: 运行 `phpunit --testsuite routing --testsuite pbt` 确认通过，commit

- [x] 5. Security 模块
  - [x] 5.1 修改 `src/ServiceProviders/Security/AccessRuleInterface.php`
    - R8: `getPattern(): string|RequestMatcherInterface`；`getRequiredRoles(): string|array`；`getRequiredChannel(): ?string`
    - 接口变更，实现类（SimpleAccessRule、TestAccessRule）必须同步更新
    - _Requirements: 8.1, 8.2_
  - [x] 5.2 修改 `src/ServiceProviders/Security/FirewallInterface.php`
    - R8: `getPattern(): string|RequestMatcherInterface`；`isStateless(): bool`；`getPolicies(): array`；`getUserProvider(): array|UserProviderInterface`；`getOtherSettings(): array`
    - 接口变更，实现类（SimpleFirewall）必须同步更新
    - _Requirements: 8.1, 8.2_
  - [x] 5.3 修改 `src/ServiceProviders/Security/SimplePreAuthenticateUserProviderInterface.php`
    - R8: `authenticateAndGetUser(mixed $credentials): UserInterface`
    - 接口变更，实现类（AbstractSimplePreAuthenticateUserProvider、TestApiUserProvider）必须同步更新
    - _Requirements: 8.1, 8.2_
  - [x] 5.4 修改 `src/ServiceProviders/Security/SimpleAccessRule.php`
    - R8: `getPattern(): string|RequestMatcherInterface`；`setPattern(string|RequestMatcherInterface $pattern): void`；`getRequiredRoles(): array`；`setRequiredRoles(array $requiredRoles): void`；`getRequiredChannel(): ?string`；`setRequiredChannel(?string $requiredChannel): void`
    - 不适用 promotion（构造函数调用 `processConfiguration()` 后赋值，有转换逻辑）
    - 不适用 readonly（属性均有 setter）
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 5.5 修改 `src/ServiceProviders/Security/SimpleFirewall.php`
    - R8: `getPattern(): string|RequestMatcherInterface`；`isStateless(): bool`；`getPolicies(): array`；`getUserProvider(): array|UserProviderInterface`；`getOtherSettings(): array`；属性类型声明
    - 不适用 promotion（构造函数调用 `processConfiguration()` 后赋值）
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 5.6 修改 `src/ServiceProviders/Security/AbstractSimplePreAuthenticateUserProvider.php`
    - R3 AC11: `$class == $this->supportedUserClassname` → `=== $this->supportedUserClassname`（S17）
    - R6 + R10: `$supportedUserClassname` 在构造后不再修改 → `private readonly string $supportedUserClassname` promotion
    - R8: `authenticateAndGetUser(mixed $credentials): UserInterface`（匹配接口变更）
    - _Requirements: 3.11, 6.1, 6.3, 6.4, 8.1, 8.2, 10.1_
  - [x] 5.7 修改 `src/ServiceProviders/Security/SimpleSecurityProvider.php`
    - R8: `addAccessRule(AccessRuleInterface|array $rule): void`；`addAuthenticationPolicy(string $policyName, ...): void`；`addFirewall(string $firewallName, FirewallInterface|array $firewall): void`；`addRoleHierarchy(string $role, string|array $children): void`；`register(MicroKernel $kernel, array $securityConfig = []): void`；`getConfigDataProvider(): DataProviderInterface`；`parseFirewall(FirewallInterface $firewall): array`
    - _Requirements: 8.1, 8.2_
  - [x] 5.8 修改 `src/ServiceProviders/Security/AbstractSimplePreAuthenticator.php`（deprecated）
    - R8: `createToken(Request $request, string $providerKey): TokenInterface`；`authenticateToken(...): TokenInterface`；`supportsToken(...): bool`；`getCredentialsFromRequest(Request $request): mixed`
    - 已标记 `@deprecated`，添加返回类型声明以消除 deprecation notice
    - _Requirements: 8.1, 8.2_
  - [x] 5.9 确认无需修改的 Security 文件
    - `AbstractPreAuthenticator.php`（Phase 3 新建，已有完整类型声明）
    - `AbstractSimplePreAuthenticationPolicy.php`（Phase 3 新建，已有完整类型声明）
    - `AuthenticationPolicyInterface.php`（Phase 3 新建，已有完整类型声明）
    - `NullEntryPoint.php`（已有完整类型声明）
    - _Requirements: 8.5_
  - [x] 5.10 Checkpoint: 运行 `phpunit --testsuite security --testsuite pbt` 确认通过，commit

- [x] 6. CORS 模块
  - [x] 6.1 修改 `src/ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php`
    - R3 AC5: `$pattern == "*"` → `=== "*"`（S12）
    - R8: 属性类型声明；`matches(Request $request): bool`；`isOriginAllowed(string $origin): bool`；`isWildcardOriginAllowed(): bool`；`isHeaderAllowed(string $header): bool`；`isCredentialsAllowed(): bool`；`getMaxAge(): int`；`getAllowedHeaders(): string`；`getExposedHeaders(): string`
    - _Requirements: 3.5, 8.1, 8.2, 8.3_
  - [x] 6.2 确认 `src/ServiceProviders/Cors/CrossOriginResourceSharingProvider.php` 无需修改
    - Phase 3 已更新，方法已有类型声明，仅需确认属性类型声明是否完整
    - _Requirements: 8.5_
  - [x] 6.3 Checkpoint: 运行 `phpunit --testsuite cors --testsuite pbt` 确认通过，commit

- [-] 7. Core 模块（MicroKernel、Middleware、Configuration、Cookie、EventSubscribers、Exceptions）
  - [x] 7.1 修改 `src/MicroKernel.php`
    - R3 AC9: `$awsResponse->getStatusCode() != Response::HTTP_OK` → `!== Response::HTTP_OK`（S13）
    - R3 AC10: `$info['service'] == "CLOUDFRONT"` → `=== "CLOUDFRONT"`（S14）
    - R8: 补全缺少返回类型的方法（大部分在 Phase 1/3 已有类型声明，需检查遗漏）
    - _Requirements: 3.9, 3.10, 8.1, 8.2_
  - [x] 7.2 修改 `src/ChainedParameterBagDataProvider.php`
    - R3: `count($value) == 1` → `=== 1`（S15）；`count($value) == 0` → `=== 0`（S16）
    - R8: `getValue(string $key): mixed`
    - _Requirements: 3.1, 3.2, 8.1, 8.2_
  - [x] 7.3 修改 `src/Exceptions/UniquenessViolationHttpException.php`
    - R1 AC3: `\Exception $previous = null` → `?\Exception $previous = null`（隐式 nullable → 显式）
    - R8: `$message` 添加 `?string` 类型，`$code` 添加 `int` 类型
    - 不适用 promotion（构造函数调用 `parent::__construct()`，有转换逻辑）
    - _Requirements: 1.3, 8.1, 8.2_
  - [x] 7.4 修改 `src/Middlewares/MiddlewareInterface.php`
    - R8: `before(Request $request, MicroKernel $kernel): Response|null`；`after(Request $request, Response $response): void`
    - 接口变更，实现类（AbstractMiddleware）必须同步更新
    - _Requirements: 8.1, 8.2_
  - [x] 7.5 确认 `src/Middlewares/AbstractMiddleware.php` 无需修改
    - 已有完整类型声明
    - _Requirements: 8.5_
  - [x] 7.6 修改 `src/EventSubscribers/ViewHandlerSubscriber.php`
    - R6 + R10: `$handlers` 在构造后不再修改 → `private readonly array $handlers` promotion
    - _Requirements: 6.1, 6.3, 6.4, 10.1_
  - [x] 7.7 修改 `src/ServiceProviders/Cookie/ResponseCookieContainer.php`
    - R8: `addCookie(Cookie $cookie): void`；`getCookies(): array`
    - _Requirements: 8.1, 8.2_
  - [x] 7.8 修改 `src/ServiceProviders/Cookie/SimpleCookieProvider.php`
    - R6: 构造函数有条件逻辑（`$cookieContainer ?? new ResponseCookieContainer()`），不适用直接 promotion
    - R10: `$cookieContainer` 在构造后不再修改，可考虑 readonly
    - _Requirements: 6.2, 10.1_
  - [x] 7.9 修改 `src/Configuration/ConfigurationValidationTrait.php`
    - R8: `processConfiguration(array $configArray, ConfigurationInterface $configurationInterface): ArrayDataProvider`
    - _Requirements: 8.1, 8.2_
  - [x] 7.10 修改 `src/ExtendedArgumentValueResolver.php`
    - R8: `$mappingParameters` 属性类型声明 `protected array $mappingParameters = []`
    - R8: `__construct(array $autoParameters)`
    - _Requirements: 8.1, 8.3_
  - [x] 7.11 修改 `src/ExtendedExceptionListnerWrapper.php`
    - R8: `ensureResponse(mixed $response, ExceptionEvent $event): void`
    - _Requirements: 8.1, 8.2_
  - [x] 7.12 确认无需修改的文件
    - `src/ServiceProviders/Twig/SimpleTwigServiceProvider.php`（Phase 2 新建，已有完整类型声明）
    - _Requirements: 8.5_
  - [-] 7.13 Checkpoint: 运行 `phpunit --testsuite all` 确认 `src/` 全部修改后测试通过，commit

- [~] 8. 测试辅助类和测试文件修改（`ut/`）
  - [ ] 8.1 修改 `ut/Helpers/Security/TestAccessRule.php`
    - R1: `$channel = null` → `?string $channel = null`（隐式 nullable 修复）
    - R8: `$pattern` → `string|RequestMatcherInterface $pattern`；`$roles` → `string|array $roles`
    - _Requirements: 1.1, 1.2, 8.1_
  - [ ] 8.2 修改 `ut/Helpers/Security/TestApiUser.php`
    - R6 + R10: `$username` 和 `$roles` 在构造后不再修改 → promotion + readonly
    - R8: 构造函数参数类型 `string $username, array $roles`
    - _Requirements: 6.1, 6.3, 6.4, 8.1, 10.1_
  - [ ] 8.3 修改 `ut/Helpers/Security/TestApiUserProvider.php`
    - R8: `authenticateAndGetUser(mixed $credentials): UserInterface`（匹配接口变更）
    - _Requirements: 8.1, 8.2_
  - [ ] 8.4 修改 `ut/Helpers/Security/TestApiUserPreAuthenticator.php`
    - R6 + R10: `$userProvider` 在构造后不再修改 → `private readonly SimplePreAuthenticateUserProviderInterface $userProvider` promotion
    - _Requirements: 6.1, 6.3, 6.4, 10.1_
  - [ ] 8.5 确认 `ut/Helpers/Security/TestAuthenticationPolicy.php` 无需修改
    - Phase 3 新建，已有完整类型声明
    - _Requirements: 8.5_
  - [ ] 8.6 修改 `ut/AwsTests/ElbTrustedProxyTest.php`
    - R3: 3 处 `$info['service'] == "CLOUDFRONT"` → `=== "CLOUDFRONT"`（T1–T3）
    - _Requirements: 3.1_
  - [ ] 8.7 Checkpoint: 运行 `phpunit --testsuite all` 确认全部通过，无 deprecation notice，commit

- [~] 9. 元数据和文档更新（Design CR Q4=C）
  - [ ] 9.1 修改 `composer.json`
    - R11: `"description"` 从 `"An extension to Silex, for HTTP related routing, middleware, and so on."` 改为 `"A Symfony MicroKernel-based HTTP framework for routing, middleware, security, and more."`
    - 其他字段保持不变
    - _Requirements: 11.1, 11.2, 11.3_
  - [ ] 9.2 修改 `docs/state/architecture.md`
    - R12 AC3: 修正 `SilexKernel` → `MicroKernel` 引用不一致
    - 具体涉及：核心类 section 的类名和文件名、模块结构图中的 `SilexKernel.php`、Bootstrap Config section 中的 `SilexKernel` 引用
    - _Requirements: 12.1, 12.3_
  - [ ] 9.3 Checkpoint: 运行 `phpunit --testsuite all` 确认全量通过，无 deprecation notice，commit

- [~] 10. 手工测试
  - [ ] 10.1 验证兼容性修复完整性
    - 确认 `src/` 和 `ut/` 中无隐式 nullable 参数残留（grep 验证 `Type $param = null` 模式不存在）
    - 确认 `src/` 和 `ut/` 中无松散比较残留（grep 验证 `==` 和 `!=` 仅出现在注释或排除项中）
    - 确认 `src/` 和 `ut/` 中无动态属性使用
    - 确认 `src/` 和 `ut/` 中内部函数调用无类型不匹配（Design 审计结论为无需修复，此处做最终确认）
    - _Requirements: 1.1, 1.2, 2.1, 2.3, 3.1, 3.2, 4.1_
  - [ ] 10.2 验证代码现代化应用
    - 确认 `RouteBasedResponseRendererResolver` 使用 `match` 表达式
    - 确认 `CacheableRouterUrlMatcherWrapper` 使用 `str_contains()`
    - 确认 constructor promotion + readonly 已应用于所有适用的类（`CacheableRouterUrlMatcherWrapper`、`GroupUrlMatcher`、`AbstractSimplePreAuthenticateUserProvider`、`ViewHandlerSubscriber`、`TestApiUser`、`TestApiUserPreAuthenticator`）
    - 确认所有接口方法和实现类方法已添加原生类型声明
    - _Requirements: 6.1, 7.1, 8.1, 9.1, 10.1_
  - [ ] 10.3 验证零 deprecation notice
    - 运行 `phpunit --testsuite all`，确认输出中无 deprecation notice（来自 `src/` 和 `ut/` 的代码）
    - 如有第三方依赖产生的 deprecation notice，记录但不要求修复
    - _Requirements: 13.1, 13.2_
  - [ ] 10.4 验证元数据更新
    - 确认 `composer.json` 的 `description` 不再引用 Silex
    - 确认 `docs/state/architecture.md` 中 `SilexKernel` 引用已全部修正为 `MicroKernel`
    - _Requirements: 11.1, 11.2, 12.3_
  - [ ] 10.5 Checkpoint: 手工测试全部通过，commit

- [~] 11. Code Review
  - [ ] 11.1 委托给 code-reviewer agent 执行
  - [ ] 11.2 Checkpoint: Code review 通过，处理所有 review 意见，commit

## Socratic Review

**Q1: tasks 是否完整覆盖了 design 中的所有变更文件？有无遗漏？**
A: Design 列出 51 个文件的变更。逐一核对：§1 UniquenessViolationHttpException → Task 7.3；§2 WrappedExceptionInfo → Task 2.1；§3 ExceptionWrapper → Task 2.2；§4 JsonErrorHandler → Task 2.3；§5 FallbackViewHandler → Task 3.7；§6 RouteBasedResponseRendererResolver → Task 3.5；§7 AbstractSmartViewHandler → Task 3.6；§8 PrefilightResponse → Task 3.8；§9 DefaultHtmlRenderer → Task 3.3；§10 JsonApiRenderer → Task 3.4；§11 ResponseRendererInterface → Task 3.1；§12 ResponseRendererResolverInterface → Task 3.2；§13 CrossOriginResourceSharingStrategy → Task 6.1；§14 CrossOriginResourceSharingProvider → Task 6.2；§15 GroupUrlMatcher → Task 4.2；§16 GroupUrlGenerator → Task 4.3；§17 CacheableRouterUrlMatcherWrapper → Task 4.1；§18 CacheableRouterProvider → Task 4.4；§19 CacheableRouter → Task 4.5；§20 InheritableRouteCollection → Task 4.6；§21 InheritableYamlFileLoader → Task 4.6；§22 SimpleAccessRule → Task 5.4；§23 SimpleFirewall → Task 5.5；§24 FirewallInterface → Task 5.2；§25 AccessRuleInterface → Task 5.1；§26 AbstractSimplePreAuthenticateUserProvider → Task 5.6；§27 SimplePreAuthenticateUserProviderInterface → Task 5.3；§28 SimpleSecurityProvider → Task 5.7；§29 NullEntryPoint → Task 5.9；§30 AbstractPreAuthenticator → Task 5.9；§31 AbstractSimplePreAuthenticationPolicy → Task 5.9；§32 AuthenticationPolicyInterface → Task 5.9；§33 AbstractSimplePreAuthenticator(deprecated) → Task 5.8；§34 MicroKernel → Task 7.1；§35 ChainedParameterBagDataProvider → Task 7.2；§36 SimpleCookieProvider → Task 7.8；§37 ResponseCookieContainer → Task 7.7；§38 ViewHandlerSubscriber → Task 7.6；§39 ExtendedArgumentValueResolver → Task 7.10；§40 ExtendedExceptionListnerWrapper → Task 7.11；§41 MiddlewareInterface → Task 7.4；§42 AbstractMiddleware → Task 7.5；§43 ConfigurationValidationTrait → Task 7.9；§44 SimpleTwigServiceProvider → Task 7.12；§45 TestApiUser → Task 8.2；§46 TestApiUserProvider → Task 8.3；§47 TestApiUserPreAuthenticator → Task 8.4；§48 TestAccessRule → Task 8.1；§49 TestAuthenticationPolicy → Task 8.5；§50 ElbTrustedProxyTest → Task 8.6；§51 composer.json → Task 9.1。加上 architecture.md → Task 9.2，PBT 3 个新文件 → Task 1.1–1.3。全部覆盖，无遗漏。

**Q2: Design CR 决策是否已体现在 task 编排中？**
A: Q1=B（按文件逐一修改）→ 每个 sub-task 对应单个文件，一次性完成所有适用变更；Q2=B（松散比较合并到文件 task）→ 松散比较修复点（S1–S17, T1–T3）分散在各文件的 sub-task 中；Q3=A（PBT 先行）→ Task 1 为 PBT 编写，排在所有代码修改之前；Q4=C（元数据+文档合并）→ Task 9 合并了 composer.json 和 architecture.md 更新。四个决策均已体现。

**Q3: task 之间的依赖顺序是否正确？**
A: Task 1（PBT 先行）→ Task 2–8（代码修改，PBT 作为回归保护）→ Task 9（元数据更新）→ Task 10（手工测试）→ Task 11（Code Review）。模块间依赖：Task 3（Views）中接口变更（3.1, 3.2）必须先于实现类（3.3, 3.4, 3.5）；Task 5（Security）中接口变更（5.1, 5.2, 5.3）必须先于实现类（5.4–5.8）；Task 7（Core）中 MiddlewareInterface（7.4）必须先于 AbstractMiddleware（7.5）。这些依赖在 sub-task 排序中已体现。

**Q4: 松散比较审计清单的 20 处修复点是否全部被 task 覆盖？**
A: S1–S2 → Task 2.1；S3 → Task 3.7；S4–S8 → Task 3.6；S9 → Task 4.2；S10 → Task 4.3；S11 → Task 4.4；S12 → Task 6.1；S13–S14 → Task 7.1；S15–S16 → Task 7.2；S17 → Task 5.6；T1–T3 → Task 8.6。20 处全部覆盖。

**Q5: PBT 的 8 个 property 是否全部有对应 task？**
A: P1–P3 → Task 1.1；P4–P5 → Task 1.2；P6–P8 → Task 1.3。8 个 property 全部覆盖。

**Q6: Requirements R1–R17 是否全部被 task 引用？**
A: R1 → Task 7.3, 8.1；R2 → Task 10.1（grep 验证无动态属性）；R3 → Task 2.1, 3.6, 3.7, 4.2, 4.3, 4.4, 5.6, 6.1, 7.1, 7.2, 8.6；R4 → Design 审计结论为无需修复，Task 10.1 做最终确认，R8 类型声明进一步强化；R5 → Task 2.1（jsonSerialize 返回类型）；R6 → Task 3.7, 4.1, 4.2, 5.6, 7.6, 7.8, 8.2, 8.4；R7 → Task 3.5；R8 → Task 2.1–2.3, 3.1–3.8, 4.1–4.5, 5.1–5.8, 6.1, 7.1–7.11, 8.1–8.3；R9 → Task 4.1；R10 → Task 3.7, 4.1, 4.2, 5.6, 7.6, 7.8, 8.2, 8.4；R11 → Task 9.1；R12 → Task 9.2；R13 → Task 8.7, 10.3；R14 → Task 7.13, 8.7, 9.3；R15 → Task 1.1；R16 → Task 1.2；R17 → Task 1.3。全部覆盖。

**Q7: checkpoint 的设置是否覆盖了关键阶段？**
A: 每个 top-level task 末尾都有 checkpoint：Task 1（PBT 通过）→ Task 2（error-handlers suite）→ Task 3（views suite）→ Task 4（routing suite）→ Task 5（security suite）→ Task 6（cors suite）→ Task 7（all suite，src/ 全部修改后）→ Task 8（all suite，ut/ 修改后，零 deprecation）→ Task 9（all suite，元数据更新后）→ Task 10（手工测试）→ Task 11（Code Review）。覆盖了 PBT 基线、各模块回归、全量验证、手工验证、Code Review 等关键阶段。

**Q8: 手工测试是否覆盖了 requirements 中的关键验证场景？**
A: 手工测试覆盖了：兼容性修复完整性（R1–R3 grep 验证）、代码现代化应用（R6–R10 关键变更确认）、零 deprecation notice（R13）、元数据更新（R11–R12）。这些是自动化测试难以覆盖的"全局一致性"验证。

## Notes

- 按 spec-execution 规范执行各 task
- commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint + commit
- **PBT 先行**（Design CR Q3=A）：Task 1 编写 PBT 测试基于当前代码验证 property 成立，后续代码修改（Task 2–8）以 PBT 作为回归保护
- **接口先于实现**：模块内 sub-task 排序确保接口变更先于实现类变更（如 Task 3.1/3.2 先于 3.3/3.4/3.5，Task 5.1/5.2/5.3 先于 5.4–5.8）
- **模块间无严格依赖**：Task 2–8 的模块间无严格依赖关系，但建议按序号顺序执行以保持一致性
- Task 2–6 各自运行对应 suite + pbt suite 验证；Task 7 运行 `--testsuite all` 作为 `src/` 全量验证；Task 8 运行 `--testsuite all` 作为最终全量验证
- Design 中 R2（动态属性）和 R4（内部函数类型严格化）审计结论为无需修复，通过手工测试 Task 10.1 的 grep 验证确认
- 松散比较 20 处修复点（src/ 17 + ut/ 3）分散在各模块 task 中，Socratic Q4 已逐一核对覆盖


## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] Task 7.2（ChainedParameterBagDataProvider）Requirement 引用 `3.6` 修正为 `3.1, 3.2`——R3 AC6 是 GroupUrlMatcher 的专属 AC，ChainedParameterBagDataProvider 的松散比较修复应引用 R3 AC1（通用规则）和 AC2（涉及数字 0 的比较）
- [内容] Task 4.3（GroupUrlGenerator）Requirement 引用 `3.6` 修正为 `3.1`——R3 AC6 是 GroupUrlMatcher 的专属 AC，GroupUrlGenerator 的松散比较修复应引用 R3 AC1（通用规则）
- [内容] Task 8.6（ElbTrustedProxyTest）Requirement 引用 `3.10` 修正为 `3.1`——R3 AC10 是 MicroKernel 的专属 AC，测试文件的松散比较修复应引用 R3 AC1（通用规则）
- [内容] Task 10.1（手工测试-兼容性修复完整性）补充 R4（内部函数类型严格化）的验证项——Design 审计结论为无需修复，但手工测试应做最终确认，确保 R4 被 task 覆盖

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1–R17 编号、P1–P8 property 编号、S1–S17/T1–T3 松散比较编号、Design CR Q1–Q4 决策引用）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误

**结构校验**
- [x] `## Tasks` section 存在
- [x] 最后一个 top-level task（11）是 Code Review
- [x] 倒数第二个 top-level task（10）是手工测试
- [x] 自动化实现 task（1–9）排在手工测试和 Code Review 之前

**Task 格式校验**
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–11）
- [x] sub-task 有层级序号（N.1, N.2...）
- [x] 序号连续，无跳号

**Requirement 追溯校验**
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款（修正后引用准确）
- [x] requirements.md 中的 R1–R17 均被至少一个 task 引用（Socratic Q6 逐一核对）
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在（修正后无悬空引用）
- [○] 引用格式为 `_Requirements: X.Y, X.Y_` 而非 steering 推荐的 `Ref: Requirement X, AC Y`——功能等价且全文一致，对于 51 个文件的大型 spec 紧凑格式更可读，不做修正

**依赖与排序校验**
- [x] top-level task 按依赖关系排序：PBT 先行（1）→ 代码修改（2–8）→ 元数据更新（9）→ 手工测试（10）→ Code Review（11）
- [x] 无循环依赖
- [x] 模块内 sub-task 排序正确：接口变更先于实现类（Task 3.1/3.2 → 3.3–3.7，Task 5.1–5.3 → 5.4–5.8，Task 7.4 → 7.5）

**Graphify 跨模块依赖校验**
- [x] 已对 ResponseRendererInterface、MiddlewareInterface 执行 graphify 依赖查询
- [x] task 排序与 graphify 揭示的模块依赖一致：接口变更 task 排在实现类 task 之前
- [x] 未发现遗漏的隐含跨模块依赖

**Checkpoint 校验**
- [x] checkpoint 作为每个 top-level task 的最后一个 sub-task（非独立 top-level task）
- [x] 每个 top-level task 末尾都有 checkpoint（1.4, 2.4, 3.9, 4.7, 5.10, 6.3, 7.13, 8.7, 9.3, 10.5, 11.2）
- [x] checkpoint 包含具体验证命令（`phpunit --testsuite X`）和 commit 动作
- [x] checkpoint 验证范围逐步扩大：模块 suite → all suite → 手工测试 → Code Review

**Test-first 校验**
- [x] Task 1（PBT 先行）体现了 test-first 策略：先编写 PBT 测试验证 property 在当前代码上成立，再执行代码修改
- [x] PBT 作为回归保护贯穿 Task 2–8 的所有 checkpoint（每个 checkpoint 都运行 pbt suite）

**Task 粒度校验**
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗的 task（每个 sub-task 对应单个文件或单个确认项）
- [x] 无过细的 task（"确认无需修改"类 sub-task 按模块合并，如 Task 5.9 合并了 4 个无需修改的文件）
- [x] 所有 task 均为 mandatory

**手工测试 Task 校验**
- [x] 手工测试 top-level task（10）存在
- [x] 覆盖了兼容性修复完整性（10.1）、代码现代化应用（10.2）、零 deprecation notice（10.3）、元数据更新（10.4）
- [x] 场景描述具体，可执行（含 grep 验证命令和具体检查项）

**Code Review Task 校验**
- [x] Code Review 是最后一个 top-level task（11）
- [x] 描述为"委托给 code-reviewer agent 执行"
- [x] 未展开 review checklist 或 fix policy

**执行注意事项校验**
- [x] `## Notes` section 存在
- [x] 明确提到按 spec-execution 规范执行
- [x] 明确说明 commit 随 checkpoint 一起执行
- [x] 包含 spec 特有的执行要点：PBT 先行策略、接口先于实现、模块间无严格依赖、松散比较修复点分散说明

**Socratic Review 校验**
- [x] 存在且覆盖充分（8 个问题）
- [x] 覆盖了 design 全覆盖（Q1）、CR 决策体现（Q2）、依赖顺序（Q3）、松散比较覆盖（Q4）、PBT 覆盖（Q5）、Requirement 覆盖（Q6，已修正）、checkpoint 覆盖（Q7）、手工测试覆盖（Q8）

**目的性审查**
- [x] Design CR 回应：Q1=B（按文件逐一修改）→ 每个 sub-task 对应单个文件；Q2=B（松散比较合并）→ 分散在各文件 task 中；Q3=A（PBT 先行）→ Task 1 排在最前；Q4=C（元数据+文档合并）→ Task 9 合并
- [x] Design 全覆盖：51 个文件 + 3 个 PBT 新文件 + architecture.md 全部有对应 task（Socratic Q1 逐一核对）
- [x] 可独立执行：每个 sub-task 包含文件路径、具体变更内容、适用的 Requirement 引用
- [x] 验收闭环：checkpoint（自动化验证）+ 手工测试（全局一致性验证）+ Code Review 构成完整闭环
- [x] 执行路径无歧义：top-level task 按序执行，模块内 sub-task 按序执行，Notes 中说明了模块间无严格依赖但建议按序号顺序
