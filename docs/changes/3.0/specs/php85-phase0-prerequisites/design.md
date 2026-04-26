# Design Document

> PHP 8.5 升级 Phase 0：前置依赖与测试框架升级 — `.kiro/specs/php85-phase0-prerequisites/`

---

## Introduction

本文档描述 PHP 8.5 升级 Phase 0 的技术方案：升级 `composer.json` 中的 PHP 版本约束、内部依赖版本和 PHPUnit 版本，适配 `phpunit.xml` 和 `ut/bootstrap.php`，将所有测试文件从 PHPUnit 5.x API 迁移到 PHPUnit 13.x API，确保 Framework_Independent_Suite 在 PHP 8.5 下通过。

Requirements CR 决策已纳入本方案：
- CR Q1 = B：Framework_Independent_Suite 中发现间接框架依赖的测试，在本 Phase 修复使其真正独立
- CR Q2 = B：`all` suite 改为 `<directory>` 方式，只要覆盖的测试集合不变
- CR Q3 = B：bootstrap 加载后 Framework_Independent_Suite 所需的类能被正常加载

---

## 1. Composer_JSON 变更（R1–R3）

### 1.1 PHP 版本约束（R1）

```json
// before
"php": ">=7.0.0"

// after
"php": ">=8.5"
```

### 1.2 内部依赖升级（R2）

```json
// before
"oasis/logging": "^1.1",
"oasis/utils": "^1.6"

// after — 大版本号提升，具体版本由 composer update 解析
"oasis/logging": "^3.0",
"oasis/utils": "^3.0"
```

> 注：`^3.0` 为示意，实际大版本号在执行 `composer update` 时确认。Requirements 只要求使用 `^` 语义化约束指向 PHP 8.5 兼容版本。

### 1.3 PHPUnit 升级（R3）

```json
// before (require-dev)
"phpunit/phpunit": "^5.2"

// after
"phpunit/phpunit": "^13.0"
```

执行 `composer update` 后验证 `composer validate` 通过、`vendor/bin/phpunit --version` 输出 PHPUnit 13.x。

---

## 2. PHPUnit_Config 适配（R4）

### 2.1 XML Schema 更新

```xml
<!-- before -->
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/5.7/phpunit.xsd"
    bootstrap="ut/bootstrap.php"
>

<!-- after -->
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="ut/bootstrap.php"
>
```

PHPUnit 13.x 的 schema 文件随 composer 包分发，使用本地路径 `vendor/phpunit/phpunit/phpunit.xsd`。

### 2.2 Suite 结构调整

**保留所有现有 suite 定义**（`all`、`exceptions`、`cors`、`security`、`twig`、`aws`、`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）。

**`all` suite 改为 `<directory>` 方式**（CR Q2 = B）：

```xml
<!-- before: 逐文件列出 38 个 <file> -->
<testsuite name="all">
    <file>ut/SilexKernelTest.php</file>
    ...
</testsuite>

<!-- after: 使用 <directory> -->
<testsuite name="all">
    <directory>ut</directory>
</testsuite>
```

其余 suite 保持 `<file>` 方式不变，因为它们按模块精确选择文件。

### 2.3 PHPUnit 13.x 移除的 XML 元素

PHPUnit 5.x → 13.x 期间，以下 XML 配置元素已被移除或变更：

| 元素 | 状态 | 处理方式 |
|------|------|---------|
| `<filter>` / `<whitelist>` | 移除（PHPUnit 9.3+） | 当前 `phpunit.xml` 未使用，无需处理 |
| `<logging>` 子元素 | 部分移除 | 当前未使用，无需处理 |
| `beStrictAboutTestsThatDoNotTestAnything` 等属性 | 重命名 | 当前未使用，无需处理 |

当前 `phpunit.xml` 结构简洁（仅 `<testsuites>` + `bootstrap`），除 schema 引用和 `all` suite 外无需其他变更。

---

## 3. Bootstrap_File 适配（R5）

### 3.1 当前状态分析

```php
// ut/bootstrap.php 当前内容
error_reporting(E_ALL);
(new LocalFileHandler('/tmp'))->install();
```

autoloader 加载行已被注释掉。`LocalFileHandler` 来自 `oasis/logging`，升级到 PHP 8.5 兼容版本后应可正常工作。

### 3.2 适配方案

1. **恢复 autoloader 加载**：取消注释 `require __DIR__ . "/../vendor/autoload.php"`，确保 Framework_Independent_Suite 所需的类能被正常加载（CR Q3 = B）
2. **验证 `LocalFileHandler` 兼容性**：`oasis/logging` 升级后，`LocalFileHandler::install()` 应在 PHP 8.5 下正常工作。如有 API 变更，按新版本 API 适配
3. **移除已注释的旧代码**：清理注释掉的 `Debug::enable()` 等不再需要的代码

适配后的 bootstrap.php：

```php
<?php
use Oasis\Mlib\Logging\LocalFileHandler;

require __DIR__ . "/../vendor/autoload.php";

error_reporting(E_ALL);
(new LocalFileHandler('/tmp'))->install();
```

---

## 4. Test_Adaptation — PHPUnit API 迁移（R6–R10）

### 4.1 Return_Type_Declaration（R6）

**影响范围**：代码扫描发现以下文件的 fixture 方法缺少 `: void` 返回类型：

| 方法 | 受影响文件数 |
|------|------------|
| `setUp()` | 21 处（20 个文件，`SilexKernelWebTest.php` 含 2 个类） |
| `tearDown()` | 1 处（`SilexKernelTest.php`） |
| `setUpBeforeClass()` | 1 处（`ElbTrustedProxyTest.php`） |
| `tearDownAfterClass()` | 1 处（`ElbTrustedProxyTest.php`） |

**迁移规则**：

```php
// before
protected function setUp()
protected function tearDown()
public static function setUpBeforeClass()
public static function tearDownAfterClass()

// after
protected function setUp(): void
protected function tearDown(): void
public static function setUpBeforeClass(): void
public static function tearDownAfterClass(): void
```

### 4.2 SetExpectedException_Migration（R7）

**影响范围**：代码扫描发现 22 处 `setExpectedException()` 调用，分布在 12 个文件中。

**迁移规则**：

```php
// Pattern A: 仅异常类
// before
$this->setExpectedException(SomeException::class);
// after
$this->expectException(SomeException::class);

// Pattern B: 异常类 + 消息
// before
$this->setExpectedException(SomeException::class, 'message');
// after
$this->expectException(SomeException::class);
$this->expectExceptionMessage('message');
```

**受影响文件清单**：

| 文件 | 调用数 | Pattern |
|------|--------|---------|
| `ut/SilexKernelTest.php` | 5 | A |
| `ut/Views/RouteBasedResponseRendererResolverTest.php` | 1 | B |
| `ut/Twig/TwigServiceProviderTest.php` | 1 | A（字符串类名） |
| `ut/Routing/GroupUrlMatcherTest.php` | 2 | A |
| `ut/Routing/CacheableRouterProviderTest.php` | 1 | A |
| `ut/Routing/GroupUrlGeneratorTest.php` | 2 | A |
| `ut/Integration/SilexKernelCrossCommunityIntegrationTest.php` | 1 | A |
| `ut/Configuration/ConfigurationValidationTraitTest.php` | 1 | A |
| `ut/Configuration/HttpConfigurationTest.php` | 1 | A |
| `ut/Configuration/SimpleAccessRuleConfigurationTest.php` | 3 | A |
| `ut/Configuration/SimpleFirewallConfigurationTest.php` | 3 | A |
| `ut/Configuration/CrossOriginResourceSharingConfigurationTest.php` | 1 | A |

### 4.3 Data_Provider_Attribute（R8）

**影响范围**：代码扫描发现 1 处 `@dataProvider` 注解，位于 `ut/Configuration/HttpConfigurationTest.php`。

**迁移规则**：

```php
// before
/**
 * @dataProvider variableNodeProvider
 */
public function testVariableNodeAcceptsArbitraryValue($nodeName, $value)

public function variableNodeProvider()

// after
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('variableNodeProvider')]
public function testVariableNodeAcceptsArbitraryValue($nodeName, $value)

public static function variableNodeProvider(): array
```

注意：data provider 方法必须声明为 `public static`，返回类型声明为 `array`。

### 4.4 Mock_API 兼容（R9）

**影响范围**：代码扫描发现大量 `getMockBuilder()` 调用，分布在以下文件中：

| 文件 | 调用数 | Mock 目标 |
|------|--------|----------|
| `ut/SilexKernelTest.php` | 8 | `ServiceProviderInterface`、`AuthorizationCheckerInterface`、`TokenInterface`、`TokenStorageInterface`、`UserInterface` |
| `ut/Routing/GroupUrlGeneratorTest.php` | 8 | `UrlGeneratorInterface` |
| `ut/Routing/GroupUrlMatcherTest.php` | 6 | `UrlMatcherInterface` |
| `ut/Routing/CacheableRouterUrlMatcherWrapperTest.php` | 10 | `UrlMatcherInterface` |
| `ut/Routing/CacheableRouterTest.php` | 2 | `SilexKernel`、`LoaderInterface` |
| `ut/Views/DefaultHtmlRendererTest.php` | 2 | `\Twig_Environment` |

**兼容性分析**：

PHPUnit 13.x 中 `getMockBuilder()` 仍然可用（hard-deprecated 但未移除）。当前代码使用的 mock 链式调用模式（`getMockBuilder()->getMock()`、`getMockBuilder()->disableOriginalConstructor()->getMock()`）在 PHPUnit 13.x 下仍可工作。

**迁移策略**：

- **接口 mock**：`getMockBuilder(Interface::class)->getMock()` 替换为 `createMock(Interface::class)` 或 `createStub(Interface::class)`。需要验证调用次数的用 `createMock()`，仅需返回值的用 `createStub()`
- **具体类 mock**（需 `disableOriginalConstructor`）：`getMockBuilder(Class::class)->disableOriginalConstructor()->getMock()` 替换为 `createMock(Class::class)`（`createMock()` 默认禁用构造函数）
- **`\Twig_Environment` mock**：此类属于 Twig 1.x 的遗留类名。由于 Twig 升级不在本 Phase scope 内，暂保留对 `\Twig_Environment` 的 mock。如果 PHP 8.5 下该类不存在导致 mock 失败，则该测试归入 Framework_Dependent 范畴，在后续 Phase 处理

**`$this->any()` matcher 处理**：PHPUnit 13.x 中 `any()` 已 hard-deprecated。当前代码未使用 `$this->any()`（使用的是 `$this->once()`、`$this->never()` 等具体 matcher，以及无 `expects()` 的 `method()->willReturn()` 链），无需处理。

### 4.5 其他 API 变更（R10）

#### 4.5.1 `assertContains()` 用于字符串

PHPUnit 9.x 起，`assertContains()` 不再支持字符串 haystack，必须使用 `assertStringContainsString()`。

**影响范围**：代码扫描发现大量 `assertContains()` 用于字符串比较的调用：

| 文件 | 调用数 | 用途 |
|------|--------|------|
| `ut/SilexKernelTest.php` | 8 | 检查数组内容（`getTrustedProxies()`、`getCacheDirectories()` 等返回数组）— **无需迁移** |
| `ut/Twig/TwigServiceProviderTest.php` | 9 | 检查 HTML 内容（字符串）— 需迁移 |
| `ut/Views/DefaultHtmlRendererTest.php` | 6 | 检查 Response content（字符串）— 需迁移 |
| `ut/Cors/CrossOriginResourceSharingAdvancedTest.php` | 1 | 检查 header 值（字符串）— 需迁移 |
| `ut/Cors/CrossOriginResourceSharingTest.php` | 5+1 | 检查 header 值（字符串）— 需迁移；`assertNotContains` 1 处 |
| `ut/Integration/SecurityAuthenticationFlowIntegrationTest.php` | 4 | 检查 JSON 数组内容 — **无需迁移** |
| `ut/Integration/SilexKernelCrossCommunityIntegrationTest.php` | 2 | 检查 JSON 数组内容 — **无需迁移** |
| `ut/Integration/BootstrapConfigurationIntegrationTest.php` | 1+1 | 1 处检查数组、1 处 `assertInternalType` |
| `ut/Misc/ChainedParameterBagDataProviderTest.php` | 2+1 | 2 处检查数组、1 处 `assertInternalType` |

**迁移规则**：

```php
// 字符串 haystack
// before
$this->assertContains('needle', $stringHaystack);
// after
$this->assertStringContainsString('needle', $stringHaystack);

// before
$this->assertNotContains('needle', $stringHaystack);
// after
$this->assertStringNotContainsString('needle', $stringHaystack);

// 数组 haystack — 无需迁移，assertContains() 仍支持数组
```

#### 4.5.2 `assertInternalType()`

PHPUnit 9.x 起移除 `assertInternalType()`，替换为具体类型断言。

**影响范围**：3 处调用。

```php
// before
$this->assertInternalType('array', $result);
// after
$this->assertIsArray($result);
```

| 文件 | 调用数 |
|------|--------|
| `ut/ErrorHandlers/JsonErrorHandlerTest.php` | 1 |
| `ut/Integration/BootstrapConfigurationIntegrationTest.php` | 1 |
| `ut/Misc/ChainedParameterBagDataProviderTest.php` | 1 |

#### 4.5.3 基类变更

所有测试类已使用 `PHPUnit\Framework\TestCase`（非旧版 `PHPUnit_Framework_TestCase`），无需迁移基类名。

Framework_Dependent 测试使用 `Silex\WebTestCase`，不在本 Phase 迁移范围内。

#### 4.5.4 `use` Import 补充

使用 `#[DataProvider]` Attribute 的文件需要添加：

```php
use PHPUnit\Framework\Attributes\DataProvider;
```

仅影响 `ut/Configuration/HttpConfigurationTest.php`。

---

## 5. 间接框架依赖修复（CR Q1 = B）

代码扫描发现以下 Framework_Independent_Suite 中的测试存在间接框架依赖：

### 5.1 `ut/HttpExceptionTest.php`（exceptions suite）

**问题**：继承 `Silex\WebTestCase`，构造 `SilexKernel` 实例，使用 `createClient()` 发送 HTTP 请求。完全依赖 Silex 框架运行时。

**修复方案**：将 `HttpExceptionTest` 从 `exceptions` suite 移出，归入 Framework_Dependent 范畴。同时在 `exceptions` suite 中保留一个不依赖框架的替代测试，直接测试 `UniquenessViolationHttpException` 的行为（该异常类本身不依赖框架）。

具体做法：
- `exceptions` suite 改为引用 `ut/Misc/UniquenessViolationHttpExceptionTest.php`（已存在，纯单元测试）
- `HttpExceptionTest` 保留在 `all` suite 中（通过 `<directory>ut</directory>` 自动包含），但不再出现在 `exceptions` suite
- `HttpExceptionTest` 本身的 PHPUnit API 适配（`setUp(): void` 等）仍需执行，以确保 `all` suite 在框架问题修复后能正常运行

### 5.2 `ut/Cookie/SimpleCookieProviderTest.php`（cookie suite）

**问题**：`testBootRegistersAfterMiddlewareThatWritesCookiesToResponse()` 构造 `SilexKernel` 实例并调用 `$kernel->handle()`，依赖 Silex 框架运行时。`testBootThrowsLogicExceptionForNonSilexKernel()` 构造 `Silex\Application` 实例。

**修复方案**：
- `testBootThrowsLogicExceptionForNonSilexKernel`：将 `new Application()` 替换为 PHPUnit mock（`$this->createMock(Application::class)` 或使用匿名类实现 `Silex\Application` 接口），避免直接依赖 Silex 类的加载
- `testBootRegistersAfterMiddlewareThatWritesCookiesToResponse`：此测试需要完整的 Silex 请求处理链路，无法在不依赖框架的情况下测试。标记为框架依赖测试，从 `cookie` suite 移出或在本 Phase 中跳过（`#[RequiresPhpExtension]` 或条件跳过）
- 如果 `Silex\Application` 类在 PHP 8.5 下仍可加载（仅实例化不触发不兼容代码），则保持现状。实际兼容性在执行阶段验证

### 5.3 `ut/Middlewares/AbstractMiddlewareTest.php`（middlewares suite）

**问题**：引用 `Silex\Application::LATE_EVENT` 和 `Silex\Application::EARLY_EVENT` 常量。

**修复方案**：
- 这些是常量引用，不涉及 Silex 运行时行为。如果 `Silex\Application` 类在 PHP 8.5 下可加载（常量定义不触发不兼容代码），则无需修改
- 如果加载失败，将常量值硬编码到测试中（`LATE_EVENT = -512`，`EARLY_EVENT = 512`），移除对 `Silex\Application` 的 `use` 引用

### 5.4 处理原则

以上分析基于静态代码扫描。实际兼容性需在 PHP 8.5 环境下执行验证。处理原则：
1. 优先尝试运行，确认是否真的失败
2. 如果失败，按上述方案修复
3. 修复后确保 suite 覆盖的测试语义不变

---

## 6. Framework_Independent_Suite 验证策略（R11）

### 6.1 验证命令

逐个执行以下命令，确认全部通过：

```bash
vendor/bin/phpunit --testsuite configuration
vendor/bin/phpunit --testsuite error-handlers
vendor/bin/phpunit --testsuite views
vendor/bin/phpunit --testsuite misc
vendor/bin/phpunit --testsuite exceptions
vendor/bin/phpunit --testsuite cookie
vendor/bin/phpunit --testsuite middlewares
```

### 6.2 失败处理流程

1. 如果失败原因是 PHPUnit API 未适配 → 修复适配问题（R6–R10 范畴）
2. 如果失败原因是间接框架依赖 → 按第 5 节方案修复（CR Q1 = B）
3. 如果失败原因是 `oasis/logging` 或 `oasis/utils` API 变更 → 适配新 API（R2 范畴）
4. 如果失败原因是 PHP 8.5 语言层面 breaking change → 记录到 R12，留给 Phase 4

---

## 7. Framework_Dependent_Suite 预期失败记录（R12）

### 7.1 预期失败的 suite

| Suite | 失败原因 |
|-------|---------|
| `cors` | `CrossOriginResourceSharingTest` 和 `CrossOriginResourceSharingAdvancedTest` 继承 `Silex\WebTestCase`，依赖 Silex Application 启动 |
| `security` | `SecurityServiceProviderTest` 继承 `Silex\WebTestCase`；`SecurityServiceProviderConfigurationTest` 继承 `SecurityServiceProviderTest` |
| `twig` | `TwigServiceProviderTest` 继承 `Silex\WebTestCase`，依赖 Twig 1.x |
| `aws` | `ElbTrustedProxyTest` 继承 `Silex\WebTestCase` |
| `routing` | 部分测试依赖 Symfony Routing 4.x（`CacheableRouterTest` mock `SilexKernel`） |
| `integration` | 所有集成测试依赖 Silex 完整启动链路 |

### 7.2 `all` suite 中的预期失败

`all` suite 使用 `<directory>ut</directory>` 后会包含所有测试文件。以下测试预期失败：
- `SilexKernelTest.php` — 部分测试构造 `SilexKernel` 实例
- `SilexKernelWebTest.php` — 继承 `Silex\WebTestCase`
- `FallbackViewHandlerTest.php` — 继承 `Silex\WebTestCase`
- `HttpExceptionTest.php` — 继承 `Silex\WebTestCase`
- 所有 `cors`、`security`、`twig`、`aws`、`integration` 目录下的测试

### 7.3 意外失败记录

如果 Framework_Independent_Suite 中出现非预期失败，在 task 执行阶段记录到 CHANGELOG 或 spec notes 中，包含：
- 失败的测试类和方法
- 失败原因分析
- 归属的后续 Phase

---

## 8. 受影响文件完整清单

### 8.1 修改的文件

| 文件 | 变更内容 |
|------|---------|
| `composer.json` | PHP 版本约束、`oasis/logging`、`oasis/utils`、`phpunit/phpunit` 版本 |
| `phpunit.xml` | XML schema、`all` suite 改 `<directory>`、`exceptions` suite 调整 |
| `ut/bootstrap.php` | 恢复 autoloader、清理注释 |
| `ut/Configuration/HttpConfigurationTest.php` | `setUp(): void`、`setExpectedException` → `expectException`、`@dataProvider` → `#[DataProvider]`、data provider 方法改 `public static` |
| `ut/Configuration/SecurityConfigurationTest.php` | `setUp(): void` |
| `ut/Configuration/CacheableRouterConfigurationTest.php` | `setUp(): void` |
| `ut/Configuration/TwigConfigurationTest.php` | `setUp(): void` |
| `ut/Configuration/SimpleAccessRuleConfigurationTest.php` | `setUp(): void`、`setExpectedException` → `expectException`（3 处） |
| `ut/Configuration/SimpleFirewallConfigurationTest.php` | `setUp(): void`、`setExpectedException` → `expectException`（3 处） |
| `ut/Configuration/CrossOriginResourceSharingConfigurationTest.php` | `setUp(): void`、`setExpectedException` → `expectException` |
| `ut/Configuration/ConfigurationValidationTraitTest.php` | `setUp(): void`、`setExpectedException` → `expectException` |
| `ut/ErrorHandlers/JsonErrorHandlerTest.php` | `setUp(): void`、`assertInternalType` → `assertIsArray` |
| `ut/ErrorHandlers/ExceptionWrapperTest.php` | `setUp(): void` |
| `ut/ErrorHandlers/WrappedExceptionInfoTest.php` | 无 fixture 方法，无需修改（已确认无 PHPUnit 5.x API 使用） |
| `ut/Views/DefaultHtmlRendererTest.php` | `assertContains` → `assertStringContainsString`（6 处）、`getMockBuilder` → `createMock`（2 处） |
| `ut/Views/RouteBasedResponseRendererResolverTest.php` | `setExpectedException` → `expectException` + `expectExceptionMessage` |
| `ut/Routing/GroupUrlMatcherTest.php` | `setExpectedException` → `expectException`（2 处）、`getMockBuilder` → `createMock` |
| `ut/Routing/GroupUrlGeneratorTest.php` | `setExpectedException` → `expectException`（2 处）、`getMockBuilder` → `createMock`/`createStub` |
| `ut/Routing/CacheableRouterProviderTest.php` | `setExpectedException` → `expectException` |
| `ut/Routing/CacheableRouterTest.php` | `getMockBuilder` → `createMock` |
| `ut/Routing/CacheableRouterUrlMatcherWrapperTest.php` | `getMockBuilder` → `createMock`/`createStub` |
| `ut/Routing/InheritableRouteCollectionTest.php` | 无需修改（已确认无 PHPUnit 5.x API 使用） |
| `ut/Routing/InheritableYamlFileLoaderTest.php` | 无需修改 |
| `ut/Cookie/ResponseCookieContainerTest.php` | 无需修改 |
| `ut/Cookie/SimpleCookieProviderTest.php` | 间接框架依赖修复（见第 5.2 节） |
| `ut/Middlewares/AbstractMiddlewareTest.php` | 间接框架依赖修复（见第 5.3 节，视实际兼容性） |
| `ut/Misc/ChainedParameterBagDataProviderTest.php` | `assertInternalType` → `assertIsArray`、`assertContains` 用于数组无需迁移 |
| `ut/Misc/ExtendedArgumentValueResolverTest.php` | 无需修改 |
| `ut/Misc/ExtendedExceptionListnerWrapperTest.php` | 无需修改 |
| `ut/Misc/UniquenessViolationHttpExceptionTest.php` | 无需修改 |
| `ut/Security/NullEntryPointTest.php` | 无需修改 |

### 8.2 Framework_Dependent 测试文件（仅做 PHPUnit API 适配，不修复框架依赖）

这些文件需要 PHPUnit API 适配（`setUp(): void` 等），以确保框架问题修复后能正常运行：

| 文件 | 适配内容 |
|------|---------|
| `ut/SilexKernelTest.php` | `setUp(): void`、`tearDown(): void`、`setExpectedException` → `expectException`（5 处）、`getMockBuilder` → `createMock`（8 处）、`assertContains` 用于数组无需迁移 |
| `ut/SilexKernelWebTest.php` | `setUp(): void`（2 处，2 个类） |
| `ut/FallbackViewHandlerTest.php` | `setUp(): void` |
| `ut/HttpExceptionTest.php` | 无 fixture 方法需修改 |
| `ut/Cors/CrossOriginResourceSharingTest.php` | `setUp(): void`、`assertContains` → `assertStringContainsString`（字符串 haystack）、`assertNotContains` → `assertStringNotContainsString` |
| `ut/Cors/CrossOriginResourceSharingAdvancedTest.php` | `setUp(): void`、`assertContains` → `assertStringContainsString` |
| `ut/Security/SecurityServiceProviderTest.php` | `setUp(): void` |
| `ut/Twig/TwigServiceProviderTest.php` | `setUp(): void`、`setExpectedException` → `expectException`、`assertContains` → `assertStringContainsString`（9 处） |
| `ut/AwsTests/ElbTrustedProxyTest.php` | `setUpBeforeClass(): void`、`tearDownAfterClass(): void` |
| `ut/Integration/BootstrapConfigurationIntegrationTest.php` | `setUp(): void`、`assertInternalType` → `assertIsArray`、`assertContains` 用于数组/字符串需逐一判断 |
| `ut/Integration/SecurityAuthenticationFlowIntegrationTest.php` | `setUp(): void`、`assertContains` 用于数组无需迁移 |
| `ut/Integration/SilexKernelCrossCommunityIntegrationTest.php` | `setUp(): void`、`setExpectedException` → `expectException`、`assertContains` 用于数组无需迁移 |

---

## Impact Analysis

### 受影响的 State 文档

- `docs/state/architecture.md`：不涉及修改。本 spec 不改变系统架构，仅升级测试基础设施和依赖版本。

### 现有 model / service / CLI 行为变化

- 不涉及。约束 C-7 明确规定不修改现有业务逻辑。

### 数据模型变更

- 不涉及。

### 外部系统交互变化

- 不涉及。

### 配置项变更

| 配置项 | 变更 |
|--------|------|
| `composer.json` `php` | `>=7.0.0` → `>=8.5` |
| `composer.json` `oasis/logging` | `^1.1` → `^3.0`（示意，实际版本由 composer 解析） |
| `composer.json` `oasis/utils` | `^1.6` → `^3.0`（示意） |
| `composer.json` `phpunit/phpunit` | `^5.2` → `^13.0` |
| `phpunit.xml` schema | `http://schema.phpunit.de/5.7/phpunit.xsd` → `vendor/phpunit/phpunit/phpunit.xsd` |
| `phpunit.xml` `all` suite | `<file>` 列表 → `<directory>ut</directory>` |
| `phpunit.xml` `exceptions` suite | 移除 `HttpExceptionTest`，改为引用纯单元测试 |

### 风险点

1. **`oasis/logging` 和 `oasis/utils` 的实际兼容版本号**：design 中使用 `^3.0` 为示意，实际版本需在执行 `composer update` 时确认。如果这些包的 PHP 8.5 兼容版本不是 3.x，需调整约束
2. **`Silex\Application` 类在 PHP 8.5 下的可加载性**：影响 `SimpleCookieProviderTest` 和 `AbstractMiddlewareTest` 的修复策略。需在执行阶段验证
3. **`\Twig_Environment` 类在 PHP 8.5 下的可加载性**：影响 `DefaultHtmlRendererTest` 的 mock 是否能正常工作
4. **`composer.lock` 更新**：`composer update` 会更新 `composer.lock`，需确保所有依赖的传递性依赖也兼容 PHP 8.5

---

## Alternatives Considered

### PHPUnit API 迁移策略

| 方案 | 描述 | 落选理由 |
|------|------|---------|
| A) 使用 polyfill 库 | 引入 `phpunit/phpunit-bridge` 或类似 polyfill 提供向后兼容 API | 增加额外依赖，且 PHPUnit 5 → 13 跨度太大，polyfill 无法覆盖所有 breaking change |
| **B) 直接迁移**（采用） | 逐文件扫描并替换所有 PHPUnit 5.x API 为 13.x 等价 API | 工作量大但一步到位，不引入额外依赖 |

### `getMockBuilder()` 处理策略

| 方案 | 描述 | 落选理由 |
|------|------|---------|
| A) 保留 `getMockBuilder()` | PHPUnit 13.x 中 `getMockBuilder()` 仍可用（hard-deprecated），暂不迁移 | 会产生大量 deprecation warning，影响测试输出可读性，且后续版本会移除 |
| **B) 迁移到 `createMock()`/`createStub()`**（采用） | 接口 mock 统一使用 `createMock()`/`createStub()`，具体类 mock 使用 `createMock()` | 消除 deprecation warning，符合 PHPUnit 13.x 推荐实践 |

### 间接框架依赖处理策略

| 方案 | 描述 | 落选理由 |
|------|------|---------|
| A) 从 suite 中移除 | 将有间接依赖的测试从 Framework_Independent_Suite 移出 | 降低了 suite 的覆盖范围 |
| **B) 修复依赖**（采用，CR Q1 = B） | 修复间接依赖使测试真正独立于框架 | 保持 suite 覆盖完整性 |
| C) 记录为预期失败 | 保留在 suite 中但标记为预期失败 | 不符合 R11 的 AC（要求 SHALL pass） |

---

## Socratic Review

**Q: design 是否完整覆盖了 requirements 中的每条需求？**
A: R1–R3 在第 1 节（Composer_JSON 变更），R4 在第 2 节（PHPUnit_Config 适配），R5 在第 3 节（Bootstrap_File 适配），R6–R10 在第 4 节（Test_Adaptation），R11 在第 6 节（验证策略），R12 在第 7 节（预期失败记录）。所有 12 条 requirement 均有对应技术方案。

**Q: PHPUnit 5 → 13 的 API breaking change 是否有遗漏？**
A: 代码扫描覆盖了以下维度：fixture 方法返回类型（R6）、`setExpectedException`（R7）、`@dataProvider`（R8）、mock API（R9）、`assertContains` 字符串用法、`assertInternalType`、基类名称（R10）。PHPUnit 13.x 的其他新特性（sealed test doubles、新数组断言、`withParameterSetsInOrder`）是新增功能，不影响现有代码的兼容性。`any()` matcher 的 hard-deprecation 经扫描确认当前代码未使用。

**Q: `oasis/logging` 和 `oasis/utils` 的目标版本号为什么用 `^3.0` 示意而不给出确切值？**
A: Requirements 约束 C-4 明确规定"内部包版本约束使用 `^` 语义化约束，具体版本由 composer 解析"。Design 阶段无法确定这些内部包的确切 PHP 8.5 兼容版本号（可能是 2.x、3.x 或其他），需要在执行阶段通过 `composer update` 确认。

**Q: `all` suite 改为 `<directory>ut</directory>` 后，是否会包含非测试文件？**
A: PHPUnit 默认只加载匹配 `*Test.php` 后缀的文件。`ut/` 目录下的非测试文件（`bootstrap.php`、`app.php`、`routes.yml`、Helper 类等）不会被当作测试执行。但需确认 PHPUnit 13.x 的默认文件后缀配置，如有需要可在 `<directory>` 元素中添加 `suffix` 属性。

**Q: 间接框架依赖的修复方案是否可能引入新的问题？**
A: `HttpExceptionTest` 的处理是将其从 `exceptions` suite 移出，不修改测试代码本身，风险最低。`SimpleCookieProviderTest` 和 `AbstractMiddlewareTest` 的修复取决于 `Silex\Application` 在 PHP 8.5 下的实际可加载性，需在执行阶段验证后决定具体方案。Design 提供了多种备选路径。

**Q: 是否存在未经确认的重大技术选型？**
A: 三个 requirements CR 决策（Q1=B 修复间接依赖、Q2=B `all` suite 改 `<directory>`、Q3=B bootstrap 确保类可加载）均已在 design 中体现。Mock API 迁移策略（`getMockBuilder` → `createMock`/`createStub`）和 `assertContains` 迁移策略是 PHPUnit 官方推荐的标准做法，无需额外确认。

---

## Requirement Coverage Matrix

| Requirement | AC | Design Section | 覆盖方式 |
|-------------|-----|---------------|---------|
| R1 | AC 1–2 | §1.1 | `composer.json` PHP 约束改为 `>=8.5`，`composer validate` 验证 |
| R2 | AC 1–3 | §1.2 | `oasis/logging` 和 `oasis/utils` 使用 `^` 约束升级 |
| R3 | AC 1–3 | §1.3 | `phpunit/phpunit` 改为 `^13.0`，`composer update` + `--version` 验证 |
| R4 | AC 1 | §2.1 | XML schema 更新为 PHPUnit 13.x 本地路径 |
| R4 | AC 2 | §2.2 | 保留所有 14 个 suite 定义 |
| R4 | AC 3 | §2.3 | 已分析移除/变更的 XML 元素，当前配置无需额外处理 |
| R4 | AC 4 | §2.1 | `bootstrap="ut/bootstrap.php"` 保留 |
| R5 | AC 1–3 | §3 | 恢复 autoloader、验证 `LocalFileHandler` 兼容性、清理注释 |
| R6 | AC 1–4 | §4.1 | 所有 fixture 方法添加 `: void` 返回类型 |
| R7 | AC 1–4 | §4.2 | 22 处 `setExpectedException` 迁移到 `expectException` + `expectExceptionMessage` |
| R8 | AC 1–4 | §4.3 | 1 处 `@dataProvider` 迁移到 `#[DataProvider]`，方法改 `public static` |
| R9 | AC 1–3 | §4.4 | `getMockBuilder` 迁移到 `createMock`/`createStub`，mock 语义保持不变 |
| R10 | AC 1 | §4.5.1–4.5.2 | `assertContains`（字符串）→ `assertStringContainsString`、`assertInternalType` → `assertIsArray` |
| R10 | AC 2 | §4.5.3 | 基类已使用 `PHPUnit\Framework\TestCase`，无需迁移 |
| R10 | AC 3 | §4.5.4 | `use PHPUnit\Framework\Attributes\DataProvider` 补充 |
| R10 | AC 4 | §6.1 | `--testsuite configuration` 验证无 fatal error |
| R11 | AC 1–7 | §6 | 7 个 Framework_Independent_Suite 逐一验证通过 |
| R12 | AC 1 | §7.1 | 6 个 Framework_Dependent_Suite 预期失败原因记录 |
| R12 | AC 2 | §5 + §7.3 | 间接框架依赖识别并修复（CR Q1=B），意外失败记录机制 |


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [内容] §4.2 SetExpectedException_Migration 影响范围：文首声称"25 处调用，分布在 14 个文件中"，但受影响文件清单实际列出 12 个文件共 22 处调用。已修正为"22 处...12 个文件中"，同步修正 Requirement Coverage Matrix 中 R7 的描述

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号 R1–R12、CR Q1–Q3、Glossary 术语在正文中使用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确（`# Design Document`）
- [x] 技术方案主体存在（§1–§7），承接 requirements 中的 R1–R12
- [x] 接口签名 / 迁移规则有明确定义（before/after 代码模式、逐文件变更清单）
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应的实现描述（Requirement Coverage Matrix 确认）
- [x] 无遗漏的 requirement
- [x] design 中的方案不超出 requirements 的范围
- [x] Impact Analysis 覆盖：state 文档、model/service/CLI 行为、数据模型、外部系统交互、配置项变更、风险点
- [x] 技术选型有明确理由（Alternatives Considered 覆盖 3 个关键决策）
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致（不改变架构，仅升级测试基础设施）
- [x] Socratic Review 覆盖充分（requirements 覆盖、API 遗漏、版本号理由、directory suite 副作用、间接依赖风险、未确认选型）
- [x] Requirements CR 决策（Q1=B, Q2=B, Q3=B）在 design 中得到体现
- [x] 技术选型明确，无"待定"或含糊选型
- [x] 迁移规则可执行，能让 task 执行者直接编码
- [x] 可 task 化：文件级变更清单 + 迁移规则 + 验证命令，足以拆分为独立 task
- [○] `graphify-out/GRAPH_REPORT.md` 未在 Impact Analysis 中引用——本 spec 不改变系统架构，仅升级测试基础设施和依赖版本，graph report 对本次影响分析无实质帮助，可接受

### Clarification Round

**状态**: ✅ 已回答

**Q1:** Design §4（Test_Adaptation）涉及约 30 个测试文件的 PHPUnit API 迁移，§5 涉及 3 个间接框架依赖修复。在拆分 task 时，PHPUnit API 迁移部分应按什么维度组织？
- A) 按 API 变更类型拆分（一个 task 做所有文件的 `setUp(): void`，另一个做所有 `setExpectedException` 迁移，以此类推）——每个 task 聚焦单一变更类型，review 时容易确认完整性
- B) 按文件/模块拆分（一个 task 做 `ut/Configuration/` 下所有文件的全部适配，另一个做 `ut/Routing/` 等）——每个 task 完成后该模块即可独立验证
- C) 按 Framework_Independent vs Framework_Dependent 拆分（先做 independent 的全部适配 + 验证，再做 dependent 的适配）——与 R11 验证策略对齐
- D) 其他（请说明）

**A:** B — 按文件/模块拆分

**Q2:** Design §5 的间接框架依赖修复方案中，§5.2（SimpleCookieProviderTest）和 §5.3（AbstractMiddlewareTest）的最终修复策略取决于 `Silex\Application` 在 PHP 8.5 下的实际可加载性，需在执行阶段验证。这个验证应在什么时机执行？
- A) 作为独立的前置 task：先在 PHP 8.5 环境下验证 Silex 类的可加载性，根据结果确定后续 task 的具体修复方案
- B) 合并到 composer update task 中：执行 `composer update` 后立即验证，结果记录在 task 产出中，后续 task 根据记录选择修复路径
- C) 合并到间接依赖修复 task 中：在修复 task 内先尝试运行，失败再按备选方案修复
- D) 其他（请说明）

**A:** B — 合并到 composer update task 中

**Q3:** Design §8 列出了 Framework_Dependent 测试文件也需要做 PHPUnit API 适配（`setUp(): void` 等），以确保框架问题修复后能正常运行。这些文件的适配应在本 spec 的 task 中完成，还是留给后续 Phase？
- A) 在本 spec 中完成所有文件的 PHPUnit API 适配（包括 Framework_Dependent），但验证只针对 Framework_Independent_Suite——一次性完成迁移，避免后续 Phase 还要处理 PHPUnit API 问题
- B) 本 spec 只适配 Framework_Independent 文件，Framework_Dependent 文件的适配留给各自的后续 Phase——每个 Phase 只改自己 scope 内的文件
- C) 本 spec 适配所有文件，但 Framework_Dependent 文件作为低优先级 task 排在最后——如果时间不够可以跳过
- D) 其他（请说明）

**A:** B — 本 spec 只适配 Framework_Independent 文件，Framework_Dependent 留给后续 Phase
