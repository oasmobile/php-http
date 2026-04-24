# PHP 8.5 Phase 0 — Framework_Dependent_Suite 预期失败记录

> Phase 0（PRP-002）执行阶段产出，记录 6 个 Framework_Dependent_Suite 在 PHP 8.5 + PHPUnit 13.x 下的失败情况。

---

## 概述

Phase 0 完成后，7 个 Framework_Independent_Suite 全部通过（Task 6 已验证）。以下 6 个 Framework_Dependent_Suite 均预期失败，无意外失败。

失败模式分为两类：

1. **Fatal Error（类加载阶段崩溃）**：`Silex\WebTestCase::setUp()` 缺少 `: void` 返回类型声明，与 PHPUnit 13.x 的 `TestCase::setUp(): void` 签名不兼容，导致 PHP fatal error。所有继承 `Silex\WebTestCase` 的测试类在加载时即崩溃，整个 suite 无法执行。
2. **PHPUnit 5.x API 未迁移**：`setExpectedException()` 在 PHPUnit 13.x 中已移除，调用时抛出 `Error: Call to undefined method`。

此外，所有 suite 运行时均产生 GuzzleHttp\Promise 函数的 implicit nullable parameter deprecation warning（来自 `guzzlehttp/promises` 包），以及 Symfony Config 组件的 implicit nullable parameter deprecation warning。这些 deprecation 属于第三方依赖的 PHP 8.5 兼容性问题，将在后续 Phase 中随依赖升级解决。

---

## 逐 Suite 失败详情

### cors

| 项目 | 内容 |
|------|------|
| 失败类型 | Fatal Error |
| Exit Code | 255 |
| 失败测试 | `CrossOriginResourceSharingTest`、`CrossOriginResourceSharingAdvancedTest` |
| 根因 | 两个测试类均继承 `Silex\WebTestCase`，加载 `WebTestCase` 时触发 `Declaration of Silex\WebTestCase::setUp() must be compatible with PHPUnit\Framework\TestCase::setUp(): void` |
| 触发文件 | `vendor/silex/silex/src/Silex/WebTestCase.php:38` |
| 归属 Phase | Phase 1（PRP-003，框架替换） |

### security

| 项目 | 内容 |
|------|------|
| 失败类型 | Fatal Error |
| Exit Code | 255 |
| 失败测试 | `SecurityServiceProviderTest`、`SecurityServiceProviderConfigurationTest` |
| 根因 | `SecurityServiceProviderTest` 继承 `Silex\WebTestCase`，`SecurityServiceProviderConfigurationTest` 继承 `SecurityServiceProviderTest`，加载时触发同一 fatal error |
| 触发文件 | `vendor/silex/silex/src/Silex/WebTestCase.php:38` |
| 附注 | `NullEntryPointTest` 本身不依赖 Silex（直接继承 `TestCase`），但因 suite 中 `SecurityServiceProviderTest.php` 先加载并 fatal error，导致整个 suite 中止，`NullEntryPointTest` 未被执行。`NullEntryPointTest` 已在 Framework_Independent 的单独验证中确认通过 |
| 归属 Phase | Phase 1（PRP-003）+ Phase 3（PRP-005，Security 组件重构） |

### twig

| 项目 | 内容 |
|------|------|
| 失败类型 | Fatal Error |
| Exit Code | 255 |
| 失败测试 | `TwigServiceProviderTest`、`TwigServiceProviderConfigurationTest` |
| 根因 | `TwigServiceProviderTest` 继承 `Silex\WebTestCase`，加载时触发同一 fatal error |
| 触发文件 | `vendor/silex/silex/src/Silex/WebTestCase.php:38` |
| 归属 Phase | Phase 1（PRP-003）+ Phase 2（PRP-004，Twig 升级） |

### aws

| 项目 | 内容 |
|------|------|
| 失败类型 | Fatal Error |
| Exit Code | 255 |
| 失败测试 | `ElbTrustedProxyTest` |
| 根因 | 继承 `Silex\WebTestCase`，加载时触发同一 fatal error |
| 触发文件 | `vendor/silex/silex/src/Silex/WebTestCase.php:38` |
| 归属 Phase | Phase 1（PRP-003） |

### routing

| 项目 | 内容 |
|------|------|
| 失败类型 | PHPUnit API Error（非 fatal，suite 可执行） |
| Exit Code | 2 |
| 测试结果 | Tests: 46, Assertions: 103, Errors: 5, Deprecations: 41, PHPUnit Notices: 8 |
| 通过的测试 | 大部分测试通过，包括 `CacheableRouterTest`（8 tests）、`CacheableRouterUrlMatcherWrapperTest`（10 tests）、`InheritableRouteCollectionTest`、`InheritableYamlFileLoaderTest`、`CacheableRouterProviderTest`（部分） |

**失败的 5 个测试方法：**

| 测试类 | 方法 | 失败原因 |
|--------|------|---------|
| `GroupUrlMatcherTest` | `testMatchThrowsExceptionWhenAllMatchersFail` | `setExpectedException()` 未迁移 |
| `GroupUrlMatcherTest` | `testMatchThrowsExceptionWhenNoMatchers` | 同上 |
| `GroupUrlGeneratorTest` | `testGenerateThrowsExceptionWhenAllGeneratorsFail` | 同上 |
| `GroupUrlGeneratorTest` | `testGenerateThrowsExceptionWhenNoGenerators` | 同上 |
| `CacheableRouterProviderTest` | `testGetConfigDataProviderThrowsLogicExceptionBeforeRegister` | 同上 |

**Deprecation 来源：**

- `getMockBuilder()` hard-deprecated（PHPUnit 13.x）— 来自 `CacheableRouterTest`、`CacheableRouterUrlMatcherWrapperTest`、`GroupUrlMatcherTest`、`GroupUrlGeneratorTest`
- Symfony Config 组件 implicit nullable parameter — 来自 `CacheableRouterProviderTest`

**附注：** routing suite 的失败全部是 PHPUnit 5.x API 未迁移（`setExpectedException`、`getMockBuilder`），不涉及 Silex 框架运行时依赖。`CacheableRouterTest` mock 了 `SilexKernel` 但未加载 `WebTestCase`，因此不触发 fatal error。这些测试的 PHPUnit API 适配留给后续 Phase（CR Q3=B 决策：本 spec 只适配 Framework_Independent 文件）。

| 归属 Phase | Phase 1（PRP-003，`setExpectedException` 迁移 + `getMockBuilder` 迁移随框架替换一并完成） |

### integration

| 项目 | 内容 |
|------|------|
| 失败类型 | Fatal Error |
| Exit Code | 255 |
| 失败测试 | `BootstrapConfigurationIntegrationTest`、`SecurityAuthenticationFlowIntegrationTest`、`SilexKernelCrossCommunityIntegrationTest` |
| 根因 | `BootstrapConfigurationIntegrationTest::setUp()` 缺少 `: void` 返回类型声明，直接触发 `Declaration of ... must be compatible with PHPUnit\Framework\TestCase::setUp(): void` fatal error。后续测试文件未被加载 |
| 触发文件 | `ut/Integration/BootstrapConfigurationIntegrationTest.php:34` |
| 附注 | 与其他 suite 不同，此处 fatal error 来自测试文件自身（非 `Silex\WebTestCase`），因为 integration 测试的 `setUp()` 未做 PHPUnit API 适配（CR Q3=B 决策） |
| 归属 Phase | Phase 1（PRP-003，集成测试随框架替换一并适配） |

---

## 汇总

### 失败统计

| Suite | 失败类型 | 测试文件数 | 可执行 | 通过 | 失败/错误 |
|-------|---------|-----------|--------|------|----------|
| cors | Fatal Error | 2 | 否 | 0 | 2（全部） |
| security | Fatal Error | 3 | 否 | 0 | 3（全部） |
| twig | Fatal Error | 2 | 否 | 0 | 2（全部） |
| aws | Fatal Error | 1 | 否 | 0 | 1（全部） |
| routing | API Error | 7 | 是 | 41 | 5 |
| integration | Fatal Error | 3 | 否 | 0 | 3（全部） |

### 失败根因分类

| 根因 | 影响 suite | 影响测试数 | 解决 Phase |
|------|-----------|-----------|-----------|
| `Silex\WebTestCase::setUp()` 签名不兼容 PHPUnit 13.x | cors, security, twig, aws | 8 个测试类 | Phase 1（PRP-003） |
| 测试文件自身 `setUp()` 缺少 `: void`（CR Q3=B 未适配） | integration | 3 个测试类 | Phase 1（PRP-003） |
| `setExpectedException()` 未迁移（CR Q3=B 未适配） | routing | 5 个测试方法 | Phase 1（PRP-003） |
| `getMockBuilder()` hard-deprecated（CR Q3=B 未适配） | routing | 多个测试方法（deprecation warning，非 error） | Phase 1（PRP-003） |

### 意外失败

无。所有失败均在 Design §7 预期范围内。

### 后续 Phase 修复指引

- **Phase 1（PRP-003）**：替换 Silex 框架后，`WebTestCase` 依赖自然消除。同时需对所有 Framework_Dependent 测试文件执行 PHPUnit API 适配（`setUp(): void`、`setExpectedException` → `expectException`、`getMockBuilder` → `createMock`、`assertContains` → `assertStringContainsString` 等）。Design §8.2 已列出完整的文件清单和适配内容。
- **Phase 2（PRP-004）**：twig suite 需额外处理 Twig 1.x → 3.x 的 API 变更。
- **Phase 3（PRP-005）**：security suite 需额外处理 Symfony Security 组件的 authenticator 系统重构。
