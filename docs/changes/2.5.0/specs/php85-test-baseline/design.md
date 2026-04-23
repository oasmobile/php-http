# Design Document

> PHP 8.5 升级前测试基线补全 — `.kiro/specs/php85-test-baseline/`

---

## Introduction

本文档描述测试基线补全的技术方案，覆盖 requirements.md 中 13 个 Requirement 的所有 AC。

Design 阶段只规划测试文件结构和 helper 依赖，具体 test case 在 task 执行阶段由实现者分析源代码后确定（CR Q1 = B）。Integration_Test 按链路选择 bootstrap 策略：Bootstrap_Configuration 链路用独立构造，Security_Authentication_Flow 用 WebTestCase（CR Q2 = C）。外部异常类直接引用 composer 依赖（CR Q3 = A）。

---

## Test File Structure

### 新增测试文件

| Requirement | 测试文件路径 | 被测模块 |
|-------------|-------------|---------|
| R1 | `ut/ErrorHandlers/WrappedExceptionInfoTest.php` | `WrappedExceptionInfo` |
| R1 | `ut/ErrorHandlers/ExceptionWrapperTest.php` | `ExceptionWrapper` |
| R1 | `ut/ErrorHandlers/JsonErrorHandlerTest.php` | `JsonErrorHandler` |
| R2 | `ut/Configuration/HttpConfigurationTest.php` | `HttpConfiguration` |
| R2 | `ut/Configuration/SecurityConfigurationTest.php` | `SecurityConfiguration` |
| R2 | `ut/Configuration/CrossOriginResourceSharingConfigurationTest.php` | `CrossOriginResourceSharingConfiguration` |
| R2 | `ut/Configuration/TwigConfigurationTest.php` | `TwigConfiguration` |
| R2 | `ut/Configuration/CacheableRouterConfigurationTest.php` | `CacheableRouterConfiguration` |
| R2 | `ut/Configuration/SimpleAccessRuleConfigurationTest.php` | `SimpleAccessRuleConfiguration` |
| R2 | `ut/Configuration/SimpleFirewallConfigurationTest.php` | `SimpleFirewallConfiguration` |
| R2 | `ut/Configuration/ConfigurationValidationTraitTest.php` | `ConfigurationValidationTrait` |
| R3 | `ut/Views/AbstractSmartViewHandlerTest.php` | `AbstractSmartViewHandler` |
| R3 | `ut/Views/JsonViewHandlerTest.php` | `JsonViewHandler` |
| R3 | `ut/Views/DefaultHtmlRendererTest.php` | `DefaultHtmlRenderer` |
| R3 | `ut/Views/JsonApiRendererTest.php` | `JsonApiRenderer` |
| R3 | `ut/Views/PrefilightResponseTest.php` | `PrefilightResponse` |
| R3 | `ut/Views/RouteBasedResponseRendererResolverTest.php` | `RouteBasedResponseRendererResolver` |
| R4 | `ut/Routing/GroupUrlMatcherTest.php` | `GroupUrlMatcher` |
| R4 | `ut/Routing/GroupUrlGeneratorTest.php` | `GroupUrlGenerator` |
| R4 | `ut/Routing/CacheableRouterUrlMatcherWrapperTest.php` | `CacheableRouterUrlMatcherWrapper` |
| R4 | `ut/Routing/InheritableRouteCollectionTest.php` | `InheritableRouteCollection` |
| R4 | `ut/Routing/InheritableYamlFileLoaderTest.php` | `InheritableYamlFileLoader` |
| R4 | `ut/Routing/CacheableRouterTest.php` | `CacheableRouter` |
| R4 | `ut/Routing/CacheableRouterProviderTest.php` | `CacheableRouterProvider` |
| R5 | `ut/Cookie/ResponseCookieContainerTest.php` | `ResponseCookieContainer` |
| R5 | `ut/Cookie/SimpleCookieProviderTest.php` | `SimpleCookieProvider` |
| R6 | `ut/Middlewares/AbstractMiddlewareTest.php` | `AbstractMiddleware` |
| R7 | `ut/Security/NullEntryPointTest.php` | `NullEntryPoint` |
| R8 | `ut/Misc/ExtendedArgumentValueResolverTest.php` | `ExtendedArgumentValueResolver` |
| R8 | `ut/Misc/ExtendedExceptionListnerWrapperTest.php` | `ExtendedExceptionListnerWrapper` |
| R8 | `ut/Misc/ChainedParameterBagDataProviderTest.php` | `ChainedParameterBagDataProvider` |
| R8 | `ut/Misc/UniquenessViolationHttpExceptionTest.php` | `UniquenessViolationHttpException` |
| R9 | `ut/Integration/BootstrapConfigurationIntegrationTest.php` | Bootstrap_Configuration 链路 |
| R10 | `ut/Integration/SecurityAuthenticationFlowIntegrationTest.php` | Security_Authentication_Flow |
| R11 | `ut/Integration/SilexKernelCrossCommunityIntegrationTest.php` | SilexKernel 跨社区集成 |

### 现有测试文件补充（R12）

| AC | 现有测试文件 | 补充方向 |
|----|------------|---------|
| R12.1 | `ut/SilexKernelTest.php` + `ut/SilexKernelWebTest.php` | magic properties、ELB/CloudFront、boot、getters、isGranted、getCacheDirectories |
| R12.2 | `ut/Cors/CrossOriginResourceSharingTest.php` + `ut/Cors/CrossOriginResourceSharingAdvancedTest.php` | 多策略优先级、credentials、headers_exposed、非 preflight |
| R12.3 | `ut/Security/SecurityServiceProviderTest.php` | 认证失败路径、AccessRule 边界、Role Hierarchy 多层 |
| R12.4 | `ut/Twig/TwigServiceProviderTest.php` | globals 变体、asset_base、无 cache_dir |
| R12.5 | `ut/AwsTests/ElbTrustedProxyTest.php` | behind_elb、trust_cloudfront_ips、两者同时 |

### 新增 Helper 文件

| 文件路径 | 用途 |
|---------|------|
| `ut/Helpers/Middlewares/TestMiddleware.php` | `AbstractMiddleware` 的 concrete Test_Double，实现 `before()` / `after()` 方法 |
| `ut/Helpers/Views/ConcreteSmartViewHandler.php` | `AbstractSmartViewHandler` 的 concrete Test_Double，暴露 `shouldHandle()` 为 public |

---

## Test Base Classes and Patterns

### 单元测试基类

所有新增单元测试继承 `PHPUnit\Framework\TestCase`（PHPUnit 5.x 兼容）。

### Integration_Test 基类策略（CR Q2 = C）

| 链路 | 基类 | 理由 |
|------|------|------|
| Bootstrap_Configuration（R9） | `PHPUnit\Framework\TestCase` | 在测试方法内直接构造 `SilexKernel`，精确控制每个配置项 |
| Security_Authentication_Flow（R10） | `Silex\WebTestCase` | 需要完整 HTTP 请求生命周期，复用 `createApplication()` + `createClient()` 模式 |
| SilexKernel 跨社区集成（R11） | `Silex\WebTestCase` | Cookie 和 Middleware 交互需要完整请求/响应周期 |

### Mock 策略

| 被测类 | 依赖 | Mock 方式 |
|--------|------|----------|
| `DefaultHtmlRenderer` | `SilexKernel` | PHPUnit mock：控制 `getTwig()` 返回值和 `$app['debug']` |
| `ExceptionWrapper` | `ExistenceViolationException`、`DataValidationException` | 直接引用外部依赖类（CR Q3 = A） |
| `CacheableRouterUrlMatcherWrapper` | `UrlMatcherInterface` | PHPUnit mock：控制 `match()` 返回值 |
| `GroupUrlMatcher` | `UrlMatcherInterface` | PHPUnit mock：控制 `match()` 行为（成功/抛异常） |
| `GroupUrlGenerator` | `UrlGeneratorInterface` | PHPUnit mock：控制 `generate()` 行为 |
| `CacheableRouter` | `SilexKernel`、`LoaderInterface` | PHPUnit mock |
| `ExtendedExceptionListnerWrapper` | `GetResponseForExceptionEvent` | PHPUnit mock：控制 `getResponse()` 返回值 |
| `SimpleCookieProvider` | `SilexKernel` / `Application` | 直接构造 `SilexKernel` 实例（boot 需要真实 Application） |

### 外部异常类处理（CR Q3 = A）

`ExceptionWrapper` 测试直接 `use` 外部异常类：

```php
use Oasis\Mlib\Utils\Exceptions\ExistenceViolationException;
use Oasis\Mlib\Utils\Exceptions\DataValidationException;
```

这些类来自 `oasis/utils` 包，已在 `composer.json` 的 `require` 中声明。

---

## phpunit.xml Suite Design（R13）

新增 suite 定义，每个 suite 对应一个模块目录。所有新增文件同时添加到 `all` suite。现有 suite 结构不变。

```xml
<testsuite name="error-handlers">
    <file>ut/ErrorHandlers/WrappedExceptionInfoTest.php</file>
    <file>ut/ErrorHandlers/ExceptionWrapperTest.php</file>
    <file>ut/ErrorHandlers/JsonErrorHandlerTest.php</file>
</testsuite>
<testsuite name="configuration">
    <file>ut/Configuration/HttpConfigurationTest.php</file>
    <file>ut/Configuration/SecurityConfigurationTest.php</file>
    <file>ut/Configuration/CrossOriginResourceSharingConfigurationTest.php</file>
    <file>ut/Configuration/TwigConfigurationTest.php</file>
    <file>ut/Configuration/CacheableRouterConfigurationTest.php</file>
    <file>ut/Configuration/SimpleAccessRuleConfigurationTest.php</file>
    <file>ut/Configuration/SimpleFirewallConfigurationTest.php</file>
    <file>ut/Configuration/ConfigurationValidationTraitTest.php</file>
</testsuite>
<testsuite name="views">
    <file>ut/Views/AbstractSmartViewHandlerTest.php</file>
    <file>ut/Views/JsonViewHandlerTest.php</file>
    <file>ut/Views/DefaultHtmlRendererTest.php</file>
    <file>ut/Views/JsonApiRendererTest.php</file>
    <file>ut/Views/PrefilightResponseTest.php</file>
    <file>ut/Views/RouteBasedResponseRendererResolverTest.php</file>
</testsuite>
<testsuite name="routing">
    <file>ut/Routing/GroupUrlMatcherTest.php</file>
    <file>ut/Routing/GroupUrlGeneratorTest.php</file>
    <file>ut/Routing/CacheableRouterUrlMatcherWrapperTest.php</file>
    <file>ut/Routing/InheritableRouteCollectionTest.php</file>
    <file>ut/Routing/InheritableYamlFileLoaderTest.php</file>
    <file>ut/Routing/CacheableRouterTest.php</file>
    <file>ut/Routing/CacheableRouterProviderTest.php</file>
</testsuite>
<testsuite name="cookie">
    <file>ut/Cookie/ResponseCookieContainerTest.php</file>
    <file>ut/Cookie/SimpleCookieProviderTest.php</file>
</testsuite>
<testsuite name="middlewares">
    <file>ut/Middlewares/AbstractMiddlewareTest.php</file>
</testsuite>
<testsuite name="misc">
    <file>ut/Misc/ExtendedArgumentValueResolverTest.php</file>
    <file>ut/Misc/ExtendedExceptionListnerWrapperTest.php</file>
    <file>ut/Misc/ChainedParameterBagDataProviderTest.php</file>
    <file>ut/Misc/UniquenessViolationHttpExceptionTest.php</file>
</testsuite>
<testsuite name="integration">
    <file>ut/Integration/BootstrapConfigurationIntegrationTest.php</file>
    <file>ut/Integration/SecurityAuthenticationFlowIntegrationTest.php</file>
    <file>ut/Integration/SilexKernelCrossCommunityIntegrationTest.php</file>
</testsuite>
```

`all` suite 追加所有新增文件（35 个）。

---

## Integration Test App Configuration Files

Security_Authentication_Flow 和 SilexKernel 跨社区集成使用 `WebTestCase`，需要 app 配置文件：

| 文件路径 | 用途 |
|---------|------|
| `ut/Integration/app.integration-security.php` | Security_Authentication_Flow 集成测试的 SilexKernel 配置 |
| `ut/Integration/app.integration-kernel.php` | SilexKernel 跨社区集成测试的 SilexKernel 配置（含 Cookie + Middleware） |
| `ut/Integration/integration.routes.yml` | 集成测试路由定义 |

Bootstrap_Configuration 链路测试（R9）在测试方法内直接构造 `SilexKernel`，不需要外部配置文件。

---

## Requirement Coverage Matrix

| Requirement | AC | 测试文件 | 覆盖方式 |
|-------------|-----|---------|---------|
| R1 | AC 1 | `WrappedExceptionInfoTest` | 单元测试 |
| R1 | AC 2 | `ExceptionWrapperTest` | 单元测试 |
| R1 | AC 3 | `JsonErrorHandlerTest` | 单元测试 |
| R2 | AC 1 | `HttpConfigurationTest` | 单元测试 |
| R2 | AC 2 | `SecurityConfigurationTest` | 单元测试 |
| R2 | AC 3 | `CrossOriginResourceSharingConfigurationTest` | 单元测试 |
| R2 | AC 4 | `TwigConfigurationTest` | 单元测试 |
| R2 | AC 5 | `CacheableRouterConfigurationTest` | 单元测试 |
| R2 | AC 6 | `SimpleAccessRuleConfigurationTest` | 单元测试 |
| R2 | AC 7 | `SimpleFirewallConfigurationTest` | 单元测试 |
| R2 | AC 8 | `ConfigurationValidationTraitTest` | 单元测试 |
| R3 | AC 1 | `AbstractSmartViewHandlerTest` | 单元测试（Test_Double） |
| R3 | AC 2 | `JsonViewHandlerTest` | 单元测试 |
| R3 | AC 3 | `DefaultHtmlRendererTest` | 单元测试（mock SilexKernel） |
| R3 | AC 4 | `JsonApiRendererTest` | 单元测试 |
| R3 | AC 5 | `PrefilightResponseTest` | 单元测试 |
| R3 | AC 6 | `RouteBasedResponseRendererResolverTest` | 单元测试 |
| R4 | AC 1 | `GroupUrlMatcherTest` | 单元测试（mock matchers） |
| R4 | AC 2 | `GroupUrlGeneratorTest` | 单元测试（mock generators） |
| R4 | AC 3 | `CacheableRouterUrlMatcherWrapperTest` | 单元测试（mock matcher） |
| R4 | AC 4 | `InheritableRouteCollectionTest` | 单元测试 |
| R4 | AC 5 | `InheritableYamlFileLoaderTest` | 单元测试（真实 YAML 文件） |
| R4 | AC 6 | `CacheableRouterTest` | 单元测试（mock SilexKernel） |
| R4 | AC 7 | `CacheableRouterProviderTest` | 单元测试 |
| R5 | AC 1 | `ResponseCookieContainerTest` | 单元测试 |
| R5 | AC 2 | `SimpleCookieProviderTest` | 单元测试 |
| R6 | AC 1 | `AbstractMiddlewareTest` | 单元测试（Test_Double） |
| R7 | AC 1 | `NullEntryPointTest` | 单元测试 |
| R8 | AC 1 | `ExtendedArgumentValueResolverTest` | 单元测试 |
| R8 | AC 2 | `ExtendedExceptionListnerWrapperTest` | 单元测试（mock event） |
| R8 | AC 3 | `ChainedParameterBagDataProviderTest` | 单元测试 |
| R8 | AC 4 | `UniquenessViolationHttpExceptionTest` | 单元测试 |
| R9 | AC 1–5 | `BootstrapConfigurationIntegrationTest` | 集成测试（直接构造 SilexKernel） |
| R10 | AC 1–4 | `SecurityAuthenticationFlowIntegrationTest` | 集成测试（WebTestCase） |
| R11 | AC 1–3 | `SilexKernelCrossCommunityIntegrationTest` | 集成测试（WebTestCase） |
| R12 | AC 1 | `SilexKernelTest` + `SilexKernelWebTest` | 补充 test method |
| R12 | AC 2 | `CrossOriginResourceSharingTest` + `CrossOriginResourceSharingAdvancedTest` | 补充 test method |
| R12 | AC 3 | `SecurityServiceProviderTest` | 补充 test method |
| R12 | AC 4 | `TwigServiceProviderTest` | 补充 test method |
| R12 | AC 5 | `ElbTrustedProxyTest` | 补充 test method |
| R13 | AC 1–3 | `phpunit.xml` | 配置变更 |

---

## Impact Analysis

### 受影响的文件

| 文件 / 目录 | 变更类型 | 说明 |
|-------------|---------|------|
| `phpunit.xml` | 修改 | 新增 8 个 Test_Suite 定义，`all` suite 追加 35 个文件 |
| `ut/` 目录 | 新增 | 35 个新增测试文件 + 2 个 Helper 文件 + 3 个集成测试配置文件 |
| `ut/SilexKernelTest.php` 等 5 个现有测试文件 | 修改 | 补充 test method（R12） |

### State 文档影响

- 不涉及。本 spec 仅新增测试，不修改 `docs/state/` 中的任何文档。

### 现有 model / service / CLI 行为变化

- 不涉及。约束 C-2 明确规定不修改现有业务逻辑。

### 数据模型变更

- 不涉及。

### 外部系统交互变化

- 不涉及。

### 配置项变更

- `phpunit.xml` 新增 8 个 `<testsuite>` 定义。现有 suite（`all`、`exceptions`、`cors`、`security`、`twig`、`aws`）结构不变，`all` suite 追加新文件。
- 新增 3 个集成测试 app 配置文件（`ut/Integration/app.integration-security.php`、`ut/Integration/app.integration-kernel.php`、`ut/Integration/integration.routes.yml`），仅供测试使用，不影响生产配置。

### 风险点

- 新增 35 个测试文件 + 补充现有测试，总测试执行时间会增加。集成测试需要启动 `SilexKernel` 实例，耗时相对较长。
- `InheritableYamlFileLoader` 测试依赖真实 YAML 文件（R4 AC 5），需确保测试用 YAML 文件路径正确。

---

## Alternatives Considered

### Integration_Test 基类策略

| 方案 | 描述 | 落选理由 |
|------|------|---------|
| A) 全部用 `WebTestCase` | 所有集成测试统一使用 `Silex\WebTestCase` | Bootstrap_Configuration 链路不需要完整 HTTP 请求，`WebTestCase` 引入不必要的开销和复杂度 |
| B) 全部用独立构造 | 所有集成测试在方法内直接构造 `SilexKernel` | Security_Authentication_Flow 需要完整请求生命周期（Firewall 在 `KernelEvents::REQUEST` 触发），手动模拟请求处理链容易遗漏步骤 |
| **C) 按链路选择**（采用） | Bootstrap_Configuration 用独立构造，Security/跨社区用 `WebTestCase` | 各取所长：精确控制 + 完整生命周期 |

### Mock 策略

| 方案 | 描述 | 落选理由 |
|------|------|---------|
| A) 全部 mock | 所有依赖都用 PHPUnit mock | `SimpleCookieProvider.boot()` 需要真实 `SilexKernel`（注册 after middleware），mock 无法覆盖 Pimple 容器行为 |
| **B) 按需选择**（采用） | 简单依赖用 mock，需要容器行为的用真实实例 | 平衡隔离性和可行性 |

---

## Socratic Review

**Q: 35 个新增测试文件是否过多？能否合并？**
A: 每个被测类对应一个测试文件是 PHPUnit 的标准实践，便于独立运行和定位失败。合并会降低可维护性。

**Q: `DefaultHtmlRenderer` mock `SilexKernel` 是否可行？`SilexKernel` 继承 `Silex\Application`（`Pimple\Container`），mock 可能有限制。**
A: PHPUnit 5.x 的 `getMockBuilder()` 可以 mock 具体类。关键是 mock `getTwig()` 和 `$app['debug']`。如果 mock 不可行，可以构造一个最小配置的真实 `SilexKernel` 实例。实现时根据实际情况选择。

**Q: `ExtendedExceptionListnerWrapper` 继承 `Silex\ExceptionListenerWrapper`，测试 `ensureResponse()` 需要调用 protected 方法，如何处理？**
A: 可以通过 `ReflectionMethod` 调用 protected 方法，或创建一个 test subclass 暴露该方法。PHPUnit 5.x 不支持 `setAccessible` 的简化 API，使用 Reflection 是标准做法。

**Q: `CacheableRouter` 测试需要 `LoaderInterface` 和路由文件，复杂度如何控制？**
A: 可以使用 mock `LoaderInterface` 返回预定义的 `RouteCollection`，避免依赖文件系统。只需验证 `getRouteCollection()` 的占位符替换逻辑。

**Q: Integration_Test 的 app 配置文件是否会与现有 `app.security.php` 等重复？**
A: 集成测试的配置文件专注于验证特定链路，配置内容会有差异。例如 `app.integration-security.php` 会包含更完整的 Policy → Firewall → AccessRule 配置，而现有 `app.security.php` 侧重功能验证。文件独立避免互相影响。

**Q: R12 场景补充的具体 test case 不在 design 中列出，是否影响 task 编排？**
A: 不影响。Task 编排按模块分组，每个 task 包含"分析源代码分支 → 编写补充 test case"两步。具体 test case 数量在执行时确定，不影响 task 粒度。

**Q: design 是否完整覆盖了 requirements 中的每条需求？**
A: Requirement Coverage Matrix 逐条映射了 R1–R13 的所有 AC 到具体测试文件和覆盖方式，无遗漏。

**Q: 是否存在未经确认的重大技术选型？**
A: 三个关键技术决策（test case 规划时机、Integration_Test 基类策略、外部异常类处理）均已通过 requirements CR 确认（Q1=B, Q2=C, Q3=A）。Mock 策略和 Integration_Test 基类策略的备选方案已在 Alternatives Considered 中记录。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] 新增 `## Impact Analysis` section，覆盖受影响文件、state 文档、行为变化、数据模型、外部系统、配置项变更和风险点。原文档完全缺少此 section。
- [结构] 新增 `## Alternatives Considered` section，记录 Integration_Test 基类策略和 Mock 策略的备选方案及落选理由。原文档中这些决策散落在各处但未集中对比。
- [内容] Socratic Review 补充两个问题：requirements 全覆盖确认、未经确认的重大技术选型检查。原 SR 侧重实现细节，缺少对 design 整体完整性的审视。

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1–R13 编号、CR Q1/Q2/Q3 引用与 requirements.md 一致）
- [x] 代码块语法正确（PHP、XML 代码块均有语言标注和闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在：`# Design Document`
- [x] 技术方案主体存在，承接了 requirements 中的 13 条需求
- [x] 接口签名 / 数据模型有明确定义（测试文件路径、基类、Mock 方式、phpunit.xml suite 结构）
- [x] 各 section 之间使用 `---` 分隔
- [x] `## Impact Analysis` 存在，覆盖所有必要维度
- [x] `## Alternatives Considered` 存在，记录了关键方案比选
- [x] `## Socratic Review` 存在且覆盖充分
- [x] Requirement Coverage Matrix 完整覆盖 R1–R13 所有 AC，无遗漏
- [x] Design 不超出 requirements 范围
- [x] Requirements CR 决策（Q1=B, Q2=C, Q3=A）均在 design 中体现
- [x] 技术选型明确，无含糊或待定的选型
- [x] 接口定义可执行，task 执行者可直接编码
- [x] 与 `docs/state/architecture.md` 描述的现有架构一致
- [x] 可 task 化：模块划分清晰，依赖关系明确

### Clarification Round

**状态**: 已回答

**Q1:** R12（现有测试场景补充）涉及 5 个已有测试文件的修改。Task 拆分时，这些补充是作为独立 task（每个文件一个 task），还是合并到对应模块的新增测试 task 中？
- A) 独立 task：每个现有测试文件的补充作为单独 task（如"补充 SilexKernelTest 场景"、"补充 CorsTest 场景"），与新增测试 task 分开
- B) 合并到模块 task：将补充工作合并到对应模块的 task 中（如 Security 模块的 task 同时包含 NullEntryPointTest 新增和 SecurityServiceProviderTest 补充）
- C) 统一为一个 task：所有 R12 补充工作合并为一个 task，在所有新增测试完成后统一执行
- D) 其他（请说明）

**A:** B — 合并到模块 task：将补充工作合并到对应模块的 task 中。

**Q2:** Task 执行顺序是否应严格按优先级（P0 → P1 → P2 → Integration → R12 补充 → R13 phpunit.xml），还是允许按模块关联性灵活调整？例如 Security 模块的 NullEntryPointTest（P1）和 SecurityAuthenticationFlowIntegrationTest（Integration）是否可以放在同一批次执行？
- A) 严格按优先级：P0 全部完成 → P1 全部完成 → P2 → Integration → R12 → R13
- B) 按模块关联性分批：同一模块的单元测试和集成测试放在一起，但 P0 模块优先
- C) 自由排序：只要依赖关系满足即可，不强制优先级顺序
- D) 其他（请说明）

**A:** B — 按模块关联性分批：同一模块的单元测试和集成测试放在一起，但 P0 模块优先。

**Q3:** R13（phpunit.xml 更新）是在所有测试文件创建完成后一次性更新，还是每个 task 完成时增量更新？
- A) 最后一次性更新：所有测试文件就绪后，统一修改 phpunit.xml
- B) 增量更新：每个 task 完成时，将该 task 新增的测试文件注册到 phpunit.xml
- C) 先创建空 suite 结构，再逐步填充：task 开始前先在 phpunit.xml 中创建所有空 suite，后续 task 逐步添加文件
- D) 其他（请说明）

**A:** C — 先创建空 suite 结构，再逐步填充。

**Q4:** Integration_Test 的 app 配置文件（`app.integration-security.php`、`app.integration-kernel.php`、`integration.routes.yml`）是否需要在集成测试 task 之前作为独立的基础设施 task 先行创建，还是在集成测试 task 内部一并创建？
- A) 独立基础设施 task：先创建所有配置文件和路由文件，再编写集成测试
- B) 内联创建：在每个集成测试 task 中同时创建所需的配置文件
- C) 其他（请说明）

**A:** A — 独立基础设施 task：先创建所有配置文件和路由文件，再编写集成测试。
