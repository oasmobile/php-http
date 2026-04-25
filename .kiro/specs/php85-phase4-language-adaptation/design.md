# Design Document

> PHP 8.5 Phase 4: PHP Language Adaptation — `.kiro/specs/php85-phase4-language-adaptation/`

---

## Overview

本文档描述 `oasis/http` 库从 PHP 7.0 时代的语法风格全面适配到 PHP 8.5 的技术方案。工作分为两大部分：

1. **兼容性修复**（R1–R5）：修复 PHP 7.x → 8.5 之间引入的 breaking changes，确保代码在 PHP 8.5 下无错误和 deprecation notice
2. **代码现代化**（R6–R10）：主动采用 PHP 8.x 新语法改善代码质量，包括 constructor property promotion + readonly、match 表达式、原生类型声明、str 函数、nullsafe operator 等

核心设计原则：

- **语义不变**：所有修改仅改变语法表达，不改变公共 API 的外部行为
- **激进类型声明**（CR Q1=C）：对所有方法（public/protected/private）添加原生类型声明，视为合理 breaking change
- **混合 nullable 语法**（CR Q2=C）：单类型用 `?Type`，多类型用 `Type1|Type2|null`
- **完整审计**（CR Q3=B）：对 `src/` 和 `ut/` 做完整 grep 审计，所有松散比较点列入修复清单
- **一步到位**（CR Q4=B）：constructor property promotion + readonly 同时应用，PBT 验证最终结果等价性

**不涉及**：依赖升级、静态分析工具引入、CI 矩阵配置、新功能引入。

---

## Architecture

本 Phase 不改变系统架构。所有修改均为语法层面的适配和现代化，模块结构、类层次、公共 API 行为保持不变。

`docs/state/architecture.md` 需要更新的内容：

1. **已知不一致**（R12 AC3）：架构文档仍引用 `SilexKernel`（`src/SilexKernel.php`），但 Phase 1 已将核心类替换为 `MicroKernel`（`src/MicroKernel.php`）。模块结构图中的 `SilexKernel.php` 和 Bootstrap Config 中的 `SilexKernel` 引用需修正为 `MicroKernel`
2. **接口签名变化**：本 Phase 为 `FirewallInterface`、`AccessRuleInterface`、`ResponseRendererInterface` 等接口添加返回类型声明，架构文档中的安全模型部分未列出具体方法签名，因此不需要更新方法签名描述
3. **结论**：需在本 Phase 修正 `SilexKernel` → `MicroKernel` 的引用不一致（属于 R12 AC3 的修正范围），其余部分无需更新

---

## Components and Interfaces

### 变更总览

按文件逐一列出所有变更点。每个文件标注适用的 Requirement 编号。

---

### R2 / R4 审计结论

**R2（动态属性修复）**: 通过 grep 搜索 `src/` 和 `ut/` 中所有 `.php` 文件，未发现动态属性使用（所有属性均在类中显式声明）。Phase 1 已移除 Silex/Pimple（动态属性的主要来源），项目自身代码无残留。无需修复，无需添加 `#[AllowDynamicProperties]`。

**R4（内部函数类型严格化）**: 通过代码审查，`src/` 和 `ut/` 中的内部函数调用均传入了正确类型的参数。R8 的类型声明现代化会进一步强化参数类型安全性，使潜在的类型不匹配在编译期而非运行时被捕获。无需额外修复。

---

### 1. `src/Exceptions/UniquenessViolationHttpException.php`

**适用**: R1（隐式 nullable）、R6（constructor promotion）、R8（类型声明）

**当前代码**:
```php
public function __construct($message = null, \Exception $previous = null, $code = 0)
```

**变更**:
- R1: `\Exception $previous = null` → `?\Exception $previous = null`（隐式 nullable → 显式）
- R8: `$message` 添加类型 `?string`，`$code` 添加类型 `int`
- R6: 此构造函数调用 `parent::__construct()`，有转换逻辑，**不适用** promotion

**目标代码**:
```php
public function __construct(?string $message = null, ?\Exception $previous = null, int $code = 0)
```

---

### 2. `src/ErrorHandlers/WrappedExceptionInfo.php`

**适用**: R3（松散比较）、R5（jsonSerialize 返回类型）、R8（类型声明）

**变更清单**:

| 行 | 当前 | 目标 | Requirement |
|----|------|------|-------------|
| 构造函数 `$httpStatusCode` | 无类型 | `int` | R8 |
| L31 | `$this->code == 0` | `$this->code === 0` | R3 AC3 |
| L62 `jsonSerialize()` | 无返回类型 | `mixed` | R5 AC5 |
| L67 `getAttribute($key)` | 无类型 | `getAttribute(string $key): mixed` | R8 |
| L75 `getAttributes()` | `@return array` | `: array` | R8 |
| L83 `getCode()` | `@return int` | `: int` | R8 |
| L91 `setCode($code)` | 无类型 | `setCode(int $code): void` | R8 |
| L99 `getException()` | `@return \Exception` | `: \Exception` | R8 |
| L107 `getOriginalCode()` | `@return int` | `: int` | R8 |
| L115 `getShortExceptionType()` | `@return string` | `: string` | R8 |
| L120 `setAttribute($key, $value)` | 无类型 | `setAttribute(string $key, mixed $value): void` | R8 |
| L125 `serializeException(\Exception $e)` | 无返回类型 | `: array` | R8 |
| L129 | `$e->getCode() != 0` | `$e->getCode() !== 0` | R3 AC3 |
| L38 `toArray($rich = false)` | 无类型 | `toArray(bool $rich = false): array` | R8 |

**属性类型声明**（R8）:
```php
protected \Exception $exception;
protected string $shortExceptionType;
protected int $code;
protected int $originalCode;
protected array $attributes = [];
```

**设计决策**: `WrappedExceptionInfo` 的属性有 `setCode()` 方法可修改 `$code`，因此 `$code` 和 `$originalCode` **不适用** readonly。`$exception`、`$shortExceptionType` 在构造后不再修改，但考虑到子类 `ExceptionWrapper.furtherProcessException()` 可能通过 `setCode()` 修改，且 `$exception` 是 protected 可被子类访问，保守起见不加 readonly。

---

### 3. `src/ErrorHandlers/ExceptionWrapper.php`

**适用**: R8（类型声明）

**变更**:
- `__invoke(\Exception $e, Request $request, $httpStatusCode)` → 添加 `int $httpStatusCode` 类型和 `WrappedExceptionInfo` 返回类型
- `furtherProcessException(WrappedExceptionInfo $info, \Exception $e)` → 添加 `: void` 返回类型

**注意**: `switch (true)` 模式含 fall-through 语义（`case` 按顺序匹配 `instanceof`），**不适用** match 表达式替换（R7 AC3）。

---

### 4. `src/ErrorHandlers/JsonErrorHandler.php`

**适用**: R8（类型声明）

**变更**:
- `__invoke(\Exception $e, $code)` → `__invoke(\Exception $e, int $code): array`

---

### 5. `src/Views/FallbackViewHandler.php`

**适用**: R3（松散比较）、R6（constructor promotion）、R8（类型声明）、R10（readonly）

**变更**:
- R3 AC4: `$rendererResolver == null` → `$rendererResolver === null`
- R6 + R10: `$kernel` 和 `$rendererResolver` 在构造后不再修改，但 `$rendererResolver` 有默认值逻辑（`=== null` 时创建默认实例），**不适用** promotion（构造函数有条件逻辑）
- R8: `__invoke($result, Request $request)` → `__invoke(mixed $result, Request $request): Response`
- R8: 属性添加类型声明

**目标构造函数**:
```php
public function __construct(
    protected readonly MicroKernel $kernel,
    ?ResponseRendererResolverInterface $rendererResolver = null
) {
    if ($rendererResolver === null) {
        $rendererResolver = new RouteBasedResponseRendererResolver();
    }
    $this->rendererResolver = $rendererResolver;
}
```

**修正**: `$kernel` 可以 promotion + readonly（直接赋值无逻辑），`$rendererResolver` 不能 promotion（有条件逻辑），需保留为显式属性。

---

### 6. `src/Views/RouteBasedResponseRendererResolver.php`

**适用**: R7（match 表达式）、R8（类型声明）

**变更**:
- R7 AC1: `switch ($format)` → `match ($format)` 表达式
- R8: `resolveRequest(Request $request)` → `: ResponseRendererInterface`

**目标代码**:
```php
public function resolveRequest(Request $request): ResponseRendererInterface
{
    $format = $request->attributes->get(
        'format',
        $request->attributes->get('_format', 'html')
    );

    return match ($format) {
        'html', 'page' => new DefaultHtmlRenderer(),
        'api', 'json' => new JsonApiRenderer(),
        default => throw new InvalidConfigurationException(
            sprintf("Unsupported response format %s", $format)
        ),
    };
}
```

---

### 7. `src/Views/AbstractSmartViewHandler.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3 AC8: 所有 `==` → `===`（5 处）:
  - `$acceptedType == "*/*"` → `$acceptedType === "*/*"`
  - `$acceptedGroup == "*"` → `$acceptedGroup === "*"`
  - `$acceptedGroup == $group` → `$acceptedGroup === $group`
  - `$acceptedSubtype == "*"` → `$acceptedSubtype === "*"`
  - `$acceptedSubtype == $subtype` → `$acceptedSubtype === $subtype`
- R8: `shouldHandle(Request $request)` → `: bool`
- R8: `getCompatibleTypes()` → `: array`

---

### 8. `src/Views/PrefilightResponse.php`

**适用**: R8（类型声明）、R10（readonly）

**变更**:
- R8: 属性类型声明 `protected array $allowedMethods = []`、`protected bool $frozen = false`
- R8: `getAllowedMethods()` → `: array`、`addAllowedMethod($method)` → `addAllowedMethod(string $method): void`、`isFrozen()` → `: bool`、`freeze()` → `: void`
- R10: `$frozen` 可被 `freeze()` 修改，**不适用** readonly。`$allowedMethods` 可被 `addAllowedMethod()` 修改，**不适用** readonly。

---

### 9. `src/Views/DefaultHtmlRenderer.php`

**适用**: R8（类型声明）

**变更**:
- `renderOnSuccess($result, MicroKernel $kernel)` → `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`
- `renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel)` → `: Response`

---

### 10. `src/Views/JsonApiRenderer.php`

**适用**: R8（类型声明）

**变更**:
- `renderOnSuccess($result, MicroKernel $kernel)` → `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`
- `renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel)` → `: Response`

---

### 11. `src/Views/ResponseRendererInterface.php`

**适用**: R8（类型声明）

**变更**:
- `renderOnSuccess($result, MicroKernel $kernel)` → `renderOnSuccess(mixed $result, MicroKernel $kernel): Response`
- `renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel)` → `: Response`

**注意**: 接口方法添加类型声明后，所有实现类（`DefaultHtmlRenderer`、`JsonApiRenderer`）必须同步更新。

---

### 12. `src/Views/ResponseRendererResolverInterface.php`

**适用**: R8（类型声明）

**变更**:
- `resolveRequest(Request $request)` → `: ResponseRendererInterface`

---

### 13. `src/ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3 AC5: `$pattern == "*"` → `$pattern === "*"`
- R8: 属性类型声明、方法返回类型声明
- R8: `__construct(array $configuration)` 添加 `function` → 规范化为 `public function`
- R8: `matches(Request $request)` → `: bool`
- R8: `isOriginAllowed($origin)` → `isOriginAllowed(string $origin): bool`
- R8: `isWildcardOriginAllowed()` → `: bool`
- R8: `isHeaderAllowed($header)` → `isHeaderAllowed(string $header): bool`
- R8: `isCredentialsAllowed()` → `: bool`
- R8: `getMaxAge()` → `: int`
- R8: `getAllowedHeaders()` → `: string`
- R8: `getExposedHeaders()` → `: string`

---

### 14. `src/ServiceProviders/Cors/CrossOriginResourceSharingProvider.php`

**适用**: R8（类型声明）

**变更**: 方法已有类型声明（Phase 3 已更新），仅需检查属性类型声明是否完整。

---

### 15. `src/ServiceProviders/Routing/GroupUrlMatcher.php`

**适用**: R3（松散比较）、R6（constructor promotion）、R8（类型声明）、R10（readonly）

**变更**:
- R3 AC6: `$matched == $total` → `$matched === $total`
- R6 + R10: `$context` 可被 `setContext()` 修改，**不适用** readonly/promotion。`$matchers` 在构造后不再修改 → 适用 promotion + readonly
- R8: 属性类型声明

**目标构造函数**:
```php
public function __construct(
    protected RequestContext $context,
    protected readonly array $matchers
) {
}
```

**修正**: `$context` 有 setter，不能 readonly，但可以 promotion。

---

### 16. `src/ServiceProviders/Routing/GroupUrlGenerator.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3: `$found == $total` → `$found === $total`（与 GroupUrlMatcher 同类模式）
- R8: 属性类型声明

**注意**: `$context` 和 `$contextExplicitlySet` 有 setter，不适用 readonly。`$generators` 在构造后不再修改，可考虑 promotion + readonly，但构造函数同时初始化 `$context = new RequestContext()`，promotion 仅适用于 `$generators`。

---

### 17. `src/ServiceProviders/Routing/CacheableRouterUrlMatcherWrapper.php`

**适用**: R6（constructor promotion）、R8（类型声明）、R9（str_contains）、R10（readonly）

**变更**:
- R9 AC1: `strpos($result['_controller'], "::") !== false` → `str_contains($result['_controller'], "::")`
- R6 + R10: `$other` 无 setter 但 `setContext()` 委托给 `$other`，`$namespaces` 在构造后不再修改 → 两者均可 promotion + readonly

**目标构造函数**:
```php
public function __construct(
    protected readonly UrlMatcherInterface $other,
    protected readonly array $namespaces
) {
}
```

---

### 18. `src/ServiceProviders/Routing/CacheableRouterProvider.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3 AC7: `strcasecmp(...) == 0` → `strcasecmp(...) === 0`
- R8: 属性类型声明、方法返回类型声明
- R8: `getConfigDataProvider()` → `: DataProviderInterface`
- R8: `getRouter(RequestContext $requestContext)` → `: Router`

---

### 19. `src/ServiceProviders/Routing/CacheableRouter.php`

**适用**: R8（类型声明）

**变更**:
- R8: 属性类型声明 `private MicroKernel $kernel`、`private bool $isParamReplaced = false`
- 构造函数参数 `$resource` 添加类型 `mixed`

---

### 20. `src/ServiceProviders/Routing/InheritableRouteCollection.php`

**适用**: R8（类型声明）

**变更**:
- `addDefaults(array $defaults): void` — 已有类型声明，无需修改

---

### 21. `src/ServiceProviders/Routing/InheritableYamlFileLoader.php`

**适用**: 无变更需要（已有完整类型声明）

---

### 22. `src/ServiceProviders/Security/SimpleAccessRule.php`

**适用**: R6（constructor promotion）、R8（类型声明）、R10（readonly）

**变更**:
- R8: 方法参数和返回类型声明
- R6: 构造函数调用 `processConfiguration()` 后赋值，有转换逻辑，**不适用** promotion
- R10: `$pattern`、`$requiredRoles`、`$requiredChannel` 均有 setter 方法，**不适用** readonly
- R8: `getPattern()` → `: string|RequestMatcherInterface`、`setPattern($pattern)` → `setPattern(string|RequestMatcherInterface $pattern): void`
- R8: `getRequiredRoles()` → `: array`、`setRequiredRoles($requiredRoles)` → `setRequiredRoles(array $requiredRoles): void`
- R8: `getRequiredChannel()` → `: ?string`、`setRequiredChannel($requiredChannel)` → `setRequiredChannel(?string $requiredChannel): void`

---

### 23. `src/ServiceProviders/Security/SimpleFirewall.php`

**适用**: R6（constructor promotion）、R8（类型声明）

**变更**:
- R6: 构造函数调用 `processConfiguration()` 后赋值，有转换逻辑，**不适用** promotion
- R8: 属性类型声明、方法返回类型声明
- R8: `getPattern()` → `: string|RequestMatcherInterface`
- R8: `isStateless()` → `: bool`
- R8: `getPolicies()` → `: array`
- R8: `getUserProvider()` → `: array|UserProviderInterface`
- R8: `getOtherSettings()` → `: array`

---

### 24. `src/ServiceProviders/Security/FirewallInterface.php`

**适用**: R8（类型声明）

**变更**: 接口方法添加返回类型声明
- `getPattern()` → `: string|RequestMatcherInterface`
- `isStateless()` → `: bool`
- `getPolicies()` → `: array`
- `getUserProvider()` → `: array|UserProviderInterface`
- `getOtherSettings()` → `: array`

---

### 25. `src/ServiceProviders/Security/AccessRuleInterface.php`

**适用**: R8（类型声明）

**变更**: 接口方法添加返回类型声明
- `getPattern()` → `: string|RequestMatcherInterface`
- `getRequiredRoles()` → `: string|array`
- `getRequiredChannel()` → `: ?string`

---

### 26. `src/ServiceProviders/Security/AbstractSimplePreAuthenticateUserProvider.php`

**适用**: R3（松散比较）、R6（constructor promotion）、R8（类型声明）、R10（readonly）

**变更**:
- R3 AC11: `$class == $this->supportedUserClassname` → `$class === $this->supportedUserClassname`
- R6 + R10: `$supportedUserClassname` 在构造后不再修改 → promotion + readonly
- R8: `__construct($supportedUserClassname)` → `__construct(private readonly string $supportedUserClassname)`
- R8: `authenticateAndGetUser($credentials)` 在接口 `SimplePreAuthenticateUserProviderInterface` 中定义，需同步

**目标构造函数**:
```php
public function __construct(
    private readonly string $supportedUserClassname
) {
}
```

---

### 27. `src/ServiceProviders/Security/SimplePreAuthenticateUserProviderInterface.php`

**适用**: R8（类型声明）

**变更**:
- `authenticateAndGetUser($credentials)` → `authenticateAndGetUser(mixed $credentials): UserInterface`

---

### 28. `src/ServiceProviders/Security/SimpleSecurityProvider.php`

**适用**: R8（类型声明）

**变更**: 方法参数和返回类型声明补全。大部分方法在 Phase 3 已有类型声明，需检查遗漏项：
- `addAccessRule($rule)` → `addAccessRule(AccessRuleInterface|array $rule): void`
- `addAuthenticationPolicy($policyName, ...)` → `addAuthenticationPolicy(string $policyName, ...): void`
- `addFirewall($firewallName, $firewall)` → `addFirewall(string $firewallName, FirewallInterface|array $firewall): void`
- `addRoleHierarchy($role, $children)` → `addRoleHierarchy(string $role, string|array $children): void`
- `register(MicroKernel $kernel, array $securityConfig = [])` → `: void`
- `getConfigDataProvider()` → `: DataProviderInterface`
- `parseFirewall(FirewallInterface $firewall)` → `: array`

---

### 29. `src/ServiceProviders/Security/NullEntryPoint.php`

**适用**: 无变更需要（已有完整类型声明）

---

### 30. `src/ServiceProviders/Security/AbstractPreAuthenticator.php`

**适用**: 无变更需要（Phase 3 新建，已有完整类型声明）

---

### 31. `src/ServiceProviders/Security/AbstractSimplePreAuthenticationPolicy.php`

**适用**: 无变更需要（Phase 3 新建，已有完整类型声明）

---

### 32. `src/ServiceProviders/Security/AuthenticationPolicyInterface.php`

**适用**: 无变更需要（Phase 3 新建，已有完整类型声明）

---

### 33. `src/ServiceProviders/Security/AbstractSimplePreAuthenticator.php`（deprecated）

**适用**: R8（类型声明）

**变更**: 已标记 `@deprecated`，方法体抛出 `LogicException`。添加返回类型声明以消除 deprecation notice：
- `createToken(Request $request, $providerKey)` → `createToken(Request $request, string $providerKey): TokenInterface`
- `authenticateToken(...)` → 添加返回类型 `: TokenInterface`
- `supportsToken(...)` → 添加返回类型 `: bool`
- `getCredentialsFromRequest(Request $request)` → `: mixed`

---

### 34. `src/MicroKernel.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3 AC9: `$awsResponse->getStatusCode() != Response::HTTP_OK` → `$awsResponse->getStatusCode() !== Response::HTTP_OK`
- R3 AC10: `$info['service'] == "CLOUDFRONT"` → `$info['service'] === "CLOUDFRONT"`
- R8: 补全缺少返回类型的方法（大部分在 Phase 1/3 已有类型声明，需检查遗漏）

---

### 35. `src/ChainedParameterBagDataProvider.php`

**适用**: R3（松散比较）、R8（类型声明）

**变更**:
- R3: `count($value) == 1` → `count($value) === 1`
- R3: `count($value) == 0` → `count($value) === 0`
- R8: `getValue($key)` → `getValue(string $key): mixed`

---

### 36. `src/ServiceProviders/Cookie/SimpleCookieProvider.php`

**适用**: R6（constructor promotion）、R10（readonly）

**变更**:
- 构造函数有条件逻辑（`$cookieContainer ?? new ResponseCookieContainer()`），**不适用** 直接 promotion
- `$cookieContainer` 在构造后不再修改，可考虑 readonly

---

### 37. `src/ServiceProviders/Cookie/ResponseCookieContainer.php`

**适用**: R8（类型声明）

**变更**:
- `addCookie(Cookie $cookie)` → `: void`
- `getCookies()` → `: array`

---

### 38. `src/EventSubscribers/ViewHandlerSubscriber.php`

**适用**: R6（constructor promotion）、R10（readonly）

**变更**:
- R6 + R10: `$handlers` 在构造后不再修改 → promotion + readonly

**目标构造函数**:
```php
public function __construct(private readonly array $handlers)
{
}
```

---

### 39. `src/ExtendedArgumentValueResolver.php`

**适用**: R8（类型声明）

**变更**:
- `$mappingParameters` 属性类型声明 `protected array $mappingParameters = []`
- `__construct($autoParameters)` → `__construct(array $autoParameters)`

---

### 40. `src/ExtendedExceptionListnerWrapper.php`

**适用**: R8（类型声明）

**变更**:
- `ensureResponse($response, ExceptionEvent $event)` → `ensureResponse(mixed $response, ExceptionEvent $event): void`

---

### 41. `src/Middlewares/MiddlewareInterface.php`

**适用**: R8（类型声明）

**变更**:
- `before(Request $request, MicroKernel $kernel)` → `: Response|null`（或 `mixed`，取决于现有实现）
- `after(Request $request, Response $response)` → `: void`

---

### 42. `src/Middlewares/AbstractMiddleware.php`

**适用**: 无变更需要（已有完整类型声明）

---

### 43. `src/Configuration/ConfigurationValidationTrait.php`

**适用**: R8（类型声明）

**变更**:
- `processConfiguration(array $configArray, ConfigurationInterface $configurationInterface)` → `: ArrayDataProvider`

---

### 44. `src/ServiceProviders/Twig/SimpleTwigServiceProvider.php`

**适用**: 无变更需要（Phase 2 新建，已有完整类型声明）



---

### 45. `ut/Helpers/Security/TestApiUser.php`

**适用**: R6（constructor promotion）、R8（类型声明）、R10（readonly）

**变更**:
- R6 + R10: `$username` 和 `$roles` 在构造后不再修改 → promotion + readonly
- R8: 构造函数参数类型 `string $username, array $roles`

**目标构造函数**:
```php
public function __construct(
    protected readonly string $username,
    protected readonly array $roles
) {
}
```

---

### 46. `ut/Helpers/Security/TestApiUserProvider.php`

**适用**: R8（类型声明）

**变更**:
- `authenticateAndGetUser($credentials)` → `authenticateAndGetUser(mixed $credentials): UserInterface`（匹配接口变更）

---

### 47. `ut/Helpers/Security/TestApiUserPreAuthenticator.php`

**适用**: R6（constructor promotion）、R10（readonly）

**变更**:
- R6 + R10: `$userProvider` 在构造后不再修改 → promotion + readonly

**目标构造函数**:
```php
public function __construct(
    private readonly SimplePreAuthenticateUserProviderInterface $userProvider
) {
}
```

---

### 48. `ut/Helpers/Security/TestAccessRule.php`

**适用**: R1（隐式 nullable）、R8（类型声明）

**变更**:
- R1: `$channel = null` → `?string $channel = null`（隐式 nullable 修复）
- R8: `$pattern` → `string|RequestMatcherInterface $pattern`、`$roles` → `string|array $roles`

---

### 49. `ut/Helpers/Security/TestAuthenticationPolicy.php`

**适用**: 无变更需要（Phase 3 新建，已有完整类型声明）

---

### 50. `ut/AwsTests/ElbTrustedProxyTest.php`

**适用**: R3（松散比较）

**变更**:
- 3 处 `$info['service'] == "CLOUDFRONT"` → `$info['service'] === "CLOUDFRONT"`（与 `src/MicroKernel.php` 同类模式）

---

### 51. `composer.json`

**适用**: R11

**变更**:
- `"description"` 从 `"An extension to Silex, for HTTP related routing, middleware, and so on."` 改为 `"A Symfony MicroKernel-based HTTP framework for routing, middleware, security, and more."`

---

## 松散比较完整审计清单（CR Q3=B）

以下是对 `src/` 和 `ut/` 中所有 `==` 和 `!=` 松散比较的完整 grep 审计结果。

### `src/` 中的松散比较

| # | 文件 | 行 | 当前代码 | 修复 | 风险评估 |
|---|------|-----|---------|------|---------|
| S1 | `ErrorHandlers/WrappedExceptionInfo.php` | 31 | `$this->code == 0` | → `=== 0` | 高：整数与 0 比较，松散比较下字符串 `"0"` 也为 true |
| S2 | `ErrorHandlers/WrappedExceptionInfo.php` | 129 | `$e->getCode() != 0` | → `!== 0` | 高：同上 |
| S3 | `Views/FallbackViewHandler.php` | 34 | `$rendererResolver == null` | → `=== null` | 中：松散比较下 `false`、`0`、`""` 也为 true |
| S4 | `Views/AbstractSmartViewHandler.php` | 24 | `$acceptedType == "*/*"` | → `=== "*/*"` | 低：字符串比较，但统一使用严格比较 |
| S5 | `Views/AbstractSmartViewHandler.php` | 30 | `$acceptedGroup == "*"` | → `=== "*"` | 低：同上 |
| S6 | `Views/AbstractSmartViewHandler.php` | 30 | `$acceptedGroup == $group` | → `=== $group` | 低：同上 |
| S7 | `Views/AbstractSmartViewHandler.php` | 31 | `$acceptedSubtype == "*"` | → `=== "*"` | 低：同上 |
| S8 | `Views/AbstractSmartViewHandler.php` | 31 | `$acceptedSubtype == $subtype` | → `=== $subtype` | 低：同上 |
| S9 | `ServiceProviders/Routing/GroupUrlMatcher.php` | 84 | `$matched == $total` | → `=== $total` | 低：整数比较，但统一使用严格比较 |
| S10 | `ServiceProviders/Routing/GroupUrlGenerator.php` | 100 | `$found == $total` | → `=== $total` | 低：同上 |
| S11 | `ServiceProviders/Routing/CacheableRouterProvider.php` | 77 | `strcasecmp(...) == 0` | → `=== 0` | 中：`strcasecmp()` 返回 int，但松散比较下 `false == 0` 为 true |
| S12 | `ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php` | 57 | `$pattern == "*"` | → `=== "*"` | 低：字符串比较 |
| S13 | `MicroKernel.php` | 796 | `$awsResponse->getStatusCode() != Response::HTTP_OK` | → `!== Response::HTTP_OK` | 中：HTTP 状态码为整数 |
| S14 | `MicroKernel.php` | 821 | `$info['service'] == "CLOUDFRONT"` | → `=== "CLOUDFRONT"` | 低：字符串比较 |
| S15 | `ChainedParameterBagDataProvider.php` | 51 | `count($value) == 1` | → `=== 1` | 低：整数比较 |
| S16 | `ChainedParameterBagDataProvider.php` | 54 | `count($value) == 0` | → `=== 0` | 低：整数比较 |
| S17 | `Security/AbstractSimplePreAuthenticateUserProvider.php` | 71 | `$class == $this->supportedUserClassname` | → `=== $this->supportedUserClassname` | 低：字符串比较 |

### `ut/` 中的松散比较

| # | 文件 | 行 | 当前代码 | 修复 | 风险评估 |
|---|------|-----|---------|------|---------|
| T1 | `AwsTests/ElbTrustedProxyTest.php` | 110 | `$info['service'] == "CLOUDFRONT"` | → `=== "CLOUDFRONT"` | 低：字符串比较 |
| T2 | `AwsTests/ElbTrustedProxyTest.php` | 286 | `$info['service'] == "CLOUDFRONT"` | → `=== "CLOUDFRONT"` | 低：同上 |
| T3 | `AwsTests/ElbTrustedProxyTest.php` | 374 | `$info['service'] == "CLOUDFRONT"` | → `=== "CLOUDFRONT"` | 低：同上 |

### 排除项

以下匹配项不需要修复：
- `ut/Cors/app.cors-advanced.php` 中的 `==`：出现在注释中（`//` 行），不是实际代码
- `ut/cache/` 目录中的 `==`：Symfony 自动生成的缓存文件，不属于项目代码
- `ut/Misc/ChainedParameterBagDataProviderTest.php` 中的 `==`：出现在注释中

**总计**: `src/` 17 处 + `ut/` 3 处 = **20 处**松散比较需要修复为严格比较。

---

## Data Models

本 Phase 不引入新的数据模型。所有变更均为现有类的语法层面修改：

- 属性类型声明从 `@var` 注释迁移到原生 PHP 类型
- 方法参数和返回类型从 `@param` / `@return` 注释迁移到原生类型声明
- Constructor property promotion 合并属性声明和构造函数赋值
- Readonly 修饰符标记不可变属性

这些变更不改变数据的结构或语义。

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Status code normalization invariant

*For any* integer HTTP status code passed to `WrappedExceptionInfo` constructor, `getCode()` SHALL return 500 when the input code is 0, and return the original input code when it is non-zero.

**Validates: Requirements 15.1**

### Property 2: Serialization code field metamorphic property

*For any* `Exception` object with an arbitrary `getCode()` return value, `serializeException()` output SHALL contain a `code` key if and only if the exception's code is non-zero. When code is 0, the `code` key SHALL be absent from the output array.

**Validates: Requirements 15.2**

### Property 3: WrappedExceptionInfo JSON round-trip

*For any* `WrappedExceptionInfo` instance constructed with a valid exception and HTTP status code, `json_decode(json_encode(toArray()))` SHALL produce an array structure equivalent to `toArray()`. All keys (`code`, `exception`, `extra`) SHALL be preserved, and nested exception serialization SHALL maintain structure through the round-trip.

**Validates: Requirements 15.3, 17.4**

### Property 4: MIME type matching strict comparison invariance

*For any* valid MIME type string (including wildcards `*/*`, `text/*`, `*/html`) and any list of compatible types, the `shouldHandle()` method of `AbstractSmartViewHandler` SHALL return the same boolean result under strict comparison (`===`) as it would under loose comparison (`==`). Since all compared values are strings, the behavior SHALL be identical.

**Validates: Requirements 16.1**

### Property 5: Format-to-renderer mapping correctness

*For any* format string, `RouteBasedResponseRendererResolver::resolveRequest()` SHALL return a `DefaultHtmlRenderer` for `html` or `page`, a `JsonApiRenderer` for `api` or `json`, and SHALL throw `InvalidConfigurationException` for any other format string.

**Validates: Requirements 16.2, 16.3**

### Property 6: SimpleAccessRule configuration round-trip

*For any* valid access rule configuration (pattern string, roles array, optional channel string or null), constructing a `SimpleAccessRule` and calling `getPattern()`, `getRequiredRoles()`, `getRequiredChannel()` SHALL return values equivalent to the input configuration (after configuration processing normalization).

**Validates: Requirements 17.1**

### Property 7: SimpleFirewall configuration round-trip

*For any* valid firewall configuration (pattern string, policies array, users array, stateless boolean, misc array), constructing a `SimpleFirewall` and calling `getPattern()`, `getPolicies()`, `isStateless()`, `getUserProvider()`, `getOtherSettings()` SHALL return values equivalent to the input configuration (after configuration processing normalization).

**Validates: Requirements 17.2**

### Property 8: UniquenessViolationHttpException construction round-trip

*For any* valid combination of message (string or null), previous exception (Exception or null), and integer code, constructing `UniquenessViolationHttpException` SHALL satisfy: `getStatusCode()` is always 400, `getMessage()` matches the input message (empty string when null), `getPrevious()` matches the input previous exception, and `getCode()` matches the input code.

**Validates: Requirements 17.3**

---

## Error Handling

本 Phase 不引入新的错误处理逻辑。所有变更均为语法层面的修改，错误处理行为保持不变。

需要注意的潜在错误场景：

| 场景 | 处理方式 | 对应 Requirement |
|------|---------|-----------------|
| 类型声明添加后，下游代码传入不兼容类型 | PHP 抛出 `TypeError` | R8 AC4（CR Q1=C：视为合理 breaking change） |
| 严格比较替换后，原本松散匹配的值不再匹配 | 行为变更——需通过 PBT 验证无意外影响 | R3, R15, R16 |
| Constructor promotion 重构后，属性访问行为变化 | 不应发生——PBT 验证等价性 | R6, R17 |
| Readonly 属性被尝试修改 | PHP 抛出 `Error` | R10（仅对确认不可变的属性添加 readonly） |
| `jsonSerialize()` 添加 `mixed` 返回类型后，子类不兼容 | PHP 抛出 `TypeError`——需检查是否有子类 | R5 AC5 |

---

## Testing Strategy

### 测试分层

| 层次 | 目的 | 工具 | 对应 Requirements |
|------|------|------|------------------|
| **Property tests** | 验证松散比较修复、类型声明、constructor promotion 在随机输入下的正确性 | Eris 1.x + PHPUnit 13.x | R15–R17 |
| **Existing test suites** | 验证所有现有功能在语法修改后行为不变 | PHPUnit 13.x | R13, R14 |

### Dual Testing Approach

- **Property tests**（新增）：验证 R15–R17 定义的 8 个 correctness properties，每个 property 最少 100 次迭代
- **Existing unit/integration tests**（不新增，仅适配）：现有测试覆盖了所有功能行为，语法修改后全量通过即证明行为不变

### Property-Based Tests

**PBT 库**: Eris 1.x（`giorgiosironi/eris`，已在 `composer.json` 的 `require-dev` 中声明）

**配置**: 每个 property test 最少 100 次迭代（Eris 默认）

**新增 PBT 文件**:

| 文件 | 覆盖 Property | 覆盖 Requirements |
|------|--------------|------------------|
| `ut/PBT/WrappedExceptionInfoPropertyTest.php` | P1, P2, P3 | R15 |
| `ut/PBT/ViewHandlerPropertyTest.php` | P4, P5 | R16 |
| `ut/PBT/ConstructorPromotionPropertyTest.php` | P6, P7, P8 | R17 |

**Tag 格式**: 每个 PBT 方法的 docblock 中标注：

```php
/**
 * Feature: php85-phase4-language-adaptation, Property 1: Status code normalization invariant
 * For any integer HTTP status code, getCode() returns 500 when input is 0, otherwise returns original.
 *
 * Ref: Requirements 15.1
 */
```

**PBT 注册**: 所有 PBT 文件放在 `ut/PBT/` 目录，已被 `phpunit.xml` 的 `pbt` suite 自动包含。

### 验证标准

1. `phpunit --testsuite all` 全量通过（R14 AC1）
2. `phpunit --testsuite pbt` PBT 测试通过（R14 AC2）
3. 零 deprecation notice（R13 AC1）
4. 所有现有 suite（`security`、`integration`、`cors`、`twig`、`routing`、`configuration`、`views`、`error-handlers`、`cookie`、`middlewares`、`misc`、`aws`、`exceptions`）继续通过（R14 AC3）

### 测试互补关系

- **Property tests** 验证修改的正确性——松散比较修复不改变语义、类型声明不改变行为、constructor promotion 保持等价
- **Existing tests** 验证功能完整性——所有现有行为在语法修改后保持不变
- 两者互补：property tests 覆盖随机输入空间，existing tests 覆盖具体业务场景

---

## Impact Analysis

### 受影响的文件

#### `src/` 目录（按变更类型分组）

**兼容性修复（R1–R5）**:

| 文件 | 变更类型 | 说明 |
|------|---------|------|
| `src/Exceptions/UniquenessViolationHttpException.php` | R1 | 隐式 nullable → 显式 |
| `src/ErrorHandlers/WrappedExceptionInfo.php` | R3, R5 | 松散比较 → 严格；`jsonSerialize()` 添加 `mixed` 返回类型 |
| `src/Views/FallbackViewHandler.php` | R3 | 松散比较 → 严格 |
| `src/Views/AbstractSmartViewHandler.php` | R3 | 松散比较 → 严格（5 处） |
| `src/ServiceProviders/Routing/GroupUrlMatcher.php` | R3 | 松散比较 → 严格 |
| `src/ServiceProviders/Routing/GroupUrlGenerator.php` | R3 | 松散比较 → 严格 |
| `src/ServiceProviders/Routing/CacheableRouterProvider.php` | R3 | 松散比较 → 严格 |
| `src/ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php` | R3 | 松散比较 → 严格 |
| `src/MicroKernel.php` | R3 | 松散比较 → 严格（2 处） |
| `src/ChainedParameterBagDataProvider.php` | R3 | 松散比较 → 严格（2 处） |
| `src/ServiceProviders/Security/AbstractSimplePreAuthenticateUserProvider.php` | R3 | 松散比较 → 严格 |

**代码现代化（R6–R10）**:

| 文件 | 变更类型 | 说明 |
|------|---------|------|
| `src/Views/RouteBasedResponseRendererResolver.php` | R7, R8 | switch → match；添加返回类型 |
| `src/ServiceProviders/Routing/CacheableRouterUrlMatcherWrapper.php` | R6, R9, R10 | promotion + readonly；strpos → str_contains |
| `src/ServiceProviders/Security/AbstractSimplePreAuthenticateUserProvider.php` | R6, R10 | promotion + readonly |
| `src/EventSubscribers/ViewHandlerSubscriber.php` | R6, R10 | promotion + readonly |
| `src/ServiceProviders/Routing/GroupUrlMatcher.php` | R6, R10 | 部分 promotion + readonly |
| `src/ServiceProviders/Routing/GroupUrlGenerator.php` | R8 | 类型声明 |

**类型声明现代化（R8）** — 几乎所有 `src/` 文件都需要添加原生类型声明，此处仅列出接口变更（影响实现类）:

| 接口文件 | 影响的实现类 |
|---------|------------|
| `src/Views/ResponseRendererInterface.php` | `DefaultHtmlRenderer`、`JsonApiRenderer` |
| `src/Views/ResponseRendererResolverInterface.php` | `RouteBasedResponseRendererResolver` |
| `src/ServiceProviders/Security/FirewallInterface.php` | `SimpleFirewall` |
| `src/ServiceProviders/Security/AccessRuleInterface.php` | `SimpleAccessRule`、`TestAccessRule` |
| `src/ServiceProviders/Security/SimplePreAuthenticateUserProviderInterface.php` | `AbstractSimplePreAuthenticateUserProvider`、`TestApiUserProvider` |
| `src/Middlewares/MiddlewareInterface.php` | `AbstractMiddleware` 及下游子类 |

#### `ut/` 目录

| 文件 | 变更类型 | 说明 |
|------|---------|------|
| `ut/AwsTests/ElbTrustedProxyTest.php` | R3 | 松散比较 → 严格（3 处） |
| `ut/Helpers/Security/TestApiUser.php` | R6, R8, R10 | promotion + readonly；类型声明 |
| `ut/Helpers/Security/TestApiUserProvider.php` | R8 | 类型声明（匹配接口变更） |
| `ut/Helpers/Security/TestApiUserPreAuthenticator.php` | R6, R10 | promotion + readonly |
| `ut/Helpers/Security/TestAccessRule.php` | R1, R8 | 隐式 nullable 修复；类型声明 |
| `ut/PBT/WrappedExceptionInfoPropertyTest.php` | **新增** | P1, P2, P3 |
| `ut/PBT/ViewHandlerPropertyTest.php` | **新增** | P4, P5 |
| `ut/PBT/ConstructorPromotionPropertyTest.php` | **新增** | P6, P7, P8 |

#### 其他文件

| 文件 | 变更类型 | 说明 |
|------|---------|------|
| `composer.json` | R11 | description 更新 |
| `docs/state/architecture.md` | R12 | 修正 `SilexKernel` → `MicroKernel` 引用不一致（核心类、模块结构图、Bootstrap Config section） |

### 变更统计

- `src/` 修改文件数: ~30（几乎所有源文件需要类型声明现代化）
- `ut/` 修改文件数: ~5（测试辅助类 + 松散比较修复）
- `ut/` 新增文件数: 3（PBT 测试文件）
- 其他修改文件数: 2（`composer.json`、`docs/state/architecture.md`）

---

## Socratic Review

**Q1: 松散比较审计是否完整？**
A: 是。通过 `grep` 对 `src/` 和 `ut/` 中所有 `.php` 文件搜索 `==`（排除 `===`、`!==`、`<==`、`>==`）和 `!=`（排除 `!==`），共发现 20 处需要修复的松散比较（`src/` 17 处 + `ut/` 3 处）。注释中的 `==` 和 Symfony 缓存文件中的 `==` 已排除。审计结果与 R3 AC3–AC11 列出的具体修复点一致，并补充了 `ut/` 中的 3 处。

**Q2: Constructor property promotion 的适用性判断是否正确？**
A: 是。判断标准：(1) 构造函数直接赋值无额外逻辑 → 适用 promotion；(2) 构造函数有条件逻辑或转换 → 不适用。例如 `SimpleAccessRule` 和 `SimpleFirewall` 的构造函数调用 `processConfiguration()` 后赋值，有转换逻辑，不适用 promotion。`CacheableRouterUrlMatcherWrapper` 的构造函数直接赋值，适用 promotion + readonly。

**Q3: Readonly 的适用性判断是否正确？**
A: 是。判断标准：属性在构造后不再被修改（无 setter、无内部修改）→ 适用 readonly。例如 `SimpleAccessRule` 的 `$pattern` 有 `setPattern()` setter → 不适用 readonly。`CacheableRouterUrlMatcherWrapper` 的 `$other` 和 `$namespaces` 无 setter 且内部不修改 → 适用 readonly。

**Q4: 类型声明策略是否与 CR Q1=C 一致？**
A: 是。CR Q1=C 要求激进策略——对所有方法添加原生类型声明。设计中对所有 public/protected/private 方法和接口方法都添加了类型声明。接口方法的类型声明变更会影响所有实现类，这是预期的 breaking change。

**Q5: PBT 的 property 选择是否覆盖了 R15–R17 的所有 AC？**
A: 是。R15 的 3 个 AC 对应 P1、P2、P3。R16 的 3 个 AC 对应 P4、P5（16.2 和 16.3 合并为 P5）。R17 的 4 个 AC 对应 P6、P7、P8（17.4 被 P3 subsume）。8 个 property 覆盖了 10 个 AC。

**Q6: 是否存在遗漏的兼容性修复点（R2、R4、R5）？**
A: 通过 grep 和代码审查确认：R2（动态属性）— `src/` 和 `ut/` 中无动态属性使用，Phase 1 已移除 Silex/Pimple，无残留；R4（内部函数类型严格化）— 内部函数调用均传入正确类型，R8 的类型声明现代化进一步强化类型安全；R5 — `each()` 无使用、`create_function()` 无使用、`${var}` 字符串插值无使用、`Serializable` 接口无使用（仅 `JsonSerializable`）。R5 AC5（`jsonSerialize()` 返回类型）已在 `WrappedExceptionInfo` 中列出。当前代码库中 R2、R4、R5 均无额外修复点。

**Q7: `docs/state/architecture.md` 是否需要更新？**
A: 是。架构文档仍引用 `SilexKernel`（`src/SilexKernel.php`），但 Phase 1 已将核心类替换为 `MicroKernel`（`src/MicroKernel.php`）。这属于 R12 AC3（修正与当前代码不一致的描述）的范围，需在本 Phase 修正。具体涉及：核心类 section 的类名和文件名、模块结构图中的 `SilexKernel.php`、Bootstrap Config section 中的 `SilexKernel` 引用。接口方法签名方面，架构文档未列出具体方法签名，因此不需要更新。

**Q8: 与 Phase 3 design 的风格是否一致？**
A: 是。遵循了相同的 section 结构（Overview → Architecture → Components and Interfaces → Data Models → Correctness Properties → Error Handling → Testing Strategy → Impact Analysis → Socratic Review），使用了相同的表格和代码块格式，中文行文配合英文技术术语。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] R2（动态属性修复）和 R4（内部函数类型严格化）在 design 中完全缺失——补充了 `### R2 / R4 审计结论` section，明确记录审计结果（均无需修复）及理由
- [内容] Architecture section 对 `docs/state/architecture.md` 的处理过于模糊（"可能需要更新"）——修正为明确结论：架构文档仍引用 `SilexKernel`，属于 R12 AC3 的修正范围，需在本 Phase 修正
- [内容] Impact Analysis 中 `docs/state/architecture.md` 标注为"条件更新"——修正为明确的修正内容描述
- [内容] Socratic Review Q6 仅覆盖 R5，遗漏了 R2 和 R4 的审计结论——扩展为覆盖 R2、R4、R5 三个排查类 requirement
- [内容] Socratic Review Q7 对架构文档更新的结论含糊（"可能需要"、"执行阶段需确认"）——修正为明确结论，列出具体需修正的 section
- [内容] 变更统计中 `architecture.md` 标注为"可能的"——修正为确定性描述

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1–R17 编号、P1–P8 property 编号、CR Q1–Q4 决策引用）
- [x] 代码块语法正确（PHP 语言标注、闭合完整）
- [x] 无 markdown 格式错误

**结构校验**
- [x] 一级标题 `# Design Document` 存在
- [x] 技术方案主体存在（Components and Interfaces，51 个文件逐一列出变更）
- [x] 接口签名 / 数据模型有明确定义（每个文件的当前代码 → 目标代码，含参数类型和返回类型）
- [x] `## Impact Analysis` 存在
- [x] `## Socratic Review` 存在（8 个问题，修正后覆盖充分）
- [x] 各 section 之间使用 `---` 分隔
- [○] 无 `## Alternatives Considered`——本 Phase 为语法层面的适配和现代化，技术方案由 PHP 版本和 CR 决策确定，无实质性的方案比选

**Requirements 覆盖校验**
- [x] R1（隐式 nullable）→ §1 UniquenessViolationHttpException、§48 TestAccessRule
- [x] R2（动态属性）→ R2/R4 审计结论 section（修正后补充）
- [x] R3（松散比较）→ 松散比较完整审计清单（20 处）
- [x] R4（内部函数类型严格化）→ R2/R4 审计结论 section（修正后补充）
- [x] R5（其他 breaking changes）→ §2 WrappedExceptionInfo jsonSerialize + Socratic Q6 审计结论
- [x] R6（Constructor promotion）→ §5, §15, §17, §26, §36, §38, §45, §47 等
- [x] R7（Match 表达式）→ §6 RouteBasedResponseRendererResolver
- [x] R8（类型声明）→ 几乎所有 §1–§51 均涉及
- [x] R9（str_contains/nullsafe）→ §17 CacheableRouterUrlMatcherWrapper
- [x] R10（Readonly）→ §5, §8, §15, §17, §26, §38, §45, §47 等
- [x] R11（composer.json）→ §51
- [x] R12（架构文档）→ Architecture section + Impact Analysis（修正后明确）
- [x] R13（零 deprecation）→ Testing Strategy 验证标准
- [x] R14（全量测试）→ Testing Strategy 验证标准
- [x] R15–R17（PBT）→ Correctness Properties P1–P8 + Testing Strategy

**Impact Analysis 校验**
- [x] 受影响的 state 文档条目：`docs/state/architecture.md`（修正后明确列出具体 section）
- [x] graphify 辅助：God Node `MicroKernel`（41 edges）确认其为核心抽象，跨 6 个 community；接口变更影响的实现类已在 Impact Analysis 表格中列出
- [x] 现有 model / service / CLI 行为的变化：明确声明"语义不变"，类型声明为合理 breaking change（CR Q1=C）
- [x] 数据模型变更：不涉及（Data Models section 明确说明）
- [x] 外部系统交互变化：不涉及
- [x] 配置项变更：不涉及

**技术方案质量校验**
- [x] 技术选型有明确理由：CR Q1=C（激进类型声明）、CR Q2=C（混合 nullable 语法）、CR Q3=B（完整审计）、CR Q4=B（一步到位 promotion + readonly）
- [x] 接口签名足够清晰：每个文件的变更均列出了参数类型、返回类型
- [x] 模块间依赖关系清晰：接口变更 → 实现类同步更新的关系在 Impact Analysis 中明确列出
- [x] 无过度设计：所有变更均为 requirements 要求的范围
- [x] 与 state 文档中描述的现有架构一致（修正后明确了 architecture.md 的不一致需修正）

**目的性审查**
- [x] Requirements CR 回应：Q1=C（激进类型声明）→ Overview 核心设计原则 + 全文类型声明方案；Q2=C（混合 nullable）→ Overview + §1 等具体示例；Q3=B（完整审计）→ 松散比较完整审计清单；Q4=B（一步到位）→ Overview + §17, §26 等具体示例
- [x] 技术选型明确：所有关键决策均有明确结论，无"待定"或含糊选型
- [x] 接口定义可执行：每个文件的当前代码 → 目标代码均有具体的类型签名
- [x] Requirements 全覆盖：R1–R17 均有对应技术方案（修正后 R2、R4 补充了审计结论）
- [x] Impact 充分评估：修正后覆盖了 state 文档、行为变化、数据模型、外部系统、配置项
- [x] 可 task 化：51 个文件的变更清单 + 3 个 PBT 文件 + 松散比较审计清单，足以拆分为可独立执行的 task

### Clarification Round

**状态**: 已完成

**Q1:** Design 中列出了 51 个文件的变更，涉及兼容性修复（R1–R5）和代码现代化（R6–R10）两大类。Tasks 拆分时，这两类变更的执行顺序是什么？
- A) 先完成所有兼容性修复（R1–R5），再做代码现代化（R6–R10）——分两轮，每轮按文件逐一修改
- B) 按文件逐一修改，每个文件一次性完成所有适用的变更（R1–R10）——减少文件重复打开，但单个 task 涉及多种变更类型
- C) 按模块分组（Security、Routing、Views、ErrorHandlers 等），每个模块一个 task，模块内一次性完成所有变更
- D) 其他（请说明）

**A:** B — 按文件逐一修改，每个文件一次性完成所有适用的变更（R1–R10），减少文件重复打开。

**Q2:** 松散比较审计清单列出了 20 处修复点（src/ 17 处 + ut/ 3 处）。这些修复点分散在多个文件中，部分文件同时有其他变更（如类型声明、constructor promotion）。松散比较修复应如何组织到 task 中？
- A) 松散比较修复作为独立 task，一次性修复所有 20 处——便于集中验证，但与其他文件级 task 有重叠
- B) 松散比较修复合并到各文件的 task 中——每个文件的 task 包含该文件的所有变更（含松散比较），避免重叠
- C) 高风险松散比较（S1, S2, S3, S11, S13）作为独立 task 优先修复，低风险的合并到文件级 task 中
- D) 其他（请说明）

**A:** B — 松散比较修复合并到各文件的 task 中，每个文件的 task 包含该文件的所有变更（含松散比较），避免重叠。

**Q3:** PBT 测试（3 个文件，8 个 property）应在什么时机编写和执行？
- A) 先编写 PBT 测试（基于当前代码验证 property 成立），再执行代码修改——PBT 作为回归保护，确保修改不破坏 property
- B) 代码修改完成后再编写 PBT 测试——PBT 验证最终结果的正确性
- C) PBT 测试与代码修改交替进行——每完成一组相关修改（如松散比较修复），立即编写对应的 PBT 验证
- D) 其他（请说明）

**A:** A — 先编写 PBT 测试（基于当前代码验证 property 成立），再执行代码修改。PBT 作为回归保护，确保修改不破坏 property。

**Q4:** `docs/state/architecture.md` 的 `SilexKernel` → `MicroKernel` 修正（R12 AC3）应在什么时机执行？
- A) 作为本 Phase 的第一个 task——先修正 SSOT，再基于正确的 SSOT 执行代码修改
- B) 作为本 Phase 的最后一个 task——代码修改全部完成后，统一更新文档
- C) 与 `composer.json` description 更新（R11）合并为一个"元数据和文档更新" task
- D) 其他（请说明）

**A:** C — 与 `composer.json` description 更新（R11）合并为一个"元数据和文档更新" task。
