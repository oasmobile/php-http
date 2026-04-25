# Project

`oasis/http` — 基于 Symfony MicroKernel 的 HTTP 框架，提供路由、安全、CORS、模板、中间件等 Web 应用基础能力。

---

## 技术栈

| 层 | 技术 |
|----|------|
| 语言 | PHP ≥ 8.5 |
| 框架 | Symfony MicroKernel（Symfony 7.x 组件） |
| 模板 | Twig 3.x |
| HTTP | Symfony HttpFoundation 7.x |
| 路由 | Symfony Routing 7.x（YAML 配置 + 缓存） |
| 安全 | Symfony Security 7.x |
| HTTP 客户端 | Guzzle 7.x |
| 测试 | PHPUnit 13.x |
| 静态分析 | PHPStan（level 8） |
| PBT | Eris 1.x |
| 内部依赖 | oasis/utils ^3.0、oasis/logging ^3.0 |
| 包管理 | Composer |

---

## 命名空间

- 源码：`Oasis\Mlib\Http\` → `src/`
- 测试：`Oasis\Mlib\Http\Test\` → `ut/`

---

## 核心入口

- `src/MicroKernel.php` — 核心类，继承 Symfony `HttpKernel`，通过 bootstrap config 数组初始化

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 运行全量测试
./vendor/bin/phpunit

# 运行指定 suite
./vendor/bin/phpunit --testsuite cors
./vendor/bin/phpunit --testsuite security
./vendor/bin/phpunit --testsuite twig
./vendor/bin/phpunit --testsuite aws
./vendor/bin/phpunit --testsuite exceptions

# 运行 PBT 测试
./vendor/bin/phpunit --testsuite pbt

# 静态分析
./vendor/bin/phpstan analyse

# 对重复失败的 suite，用 --log-junit 输出日志以缩小定位
./vendor/bin/phpunit --testsuite <suite> --log-junit build/junit-<suite>.xml
```

---

## 测试 Suite

| Suite | 内容 |
|-------|------|
| exceptions | `UniquenessViolationHttpExceptionTest`, `HttpExceptionTest` |
| cors | `CrossOriginResourceSharingTest`, `CrossOriginResourceSharingAdvancedTest` |
| security | `SecurityServiceProviderTest`, `SecurityServiceProviderConfigurationTest`, `NullEntryPointTest`, `AccessRuleListenerTest`, `AbstractSimplePreAuthenticationPolicyTest` |
| twig | `TwigServiceProviderTest`, `TwigServiceProviderConfigurationTest` |
| aws | `ElbTrustedProxyTest` |
| error-handlers | `WrappedExceptionInfoTest`, `ExceptionWrapperTest`, `JsonErrorHandlerTest` |
| configuration | `HttpConfigurationTest`, `SecurityConfigurationTest`, `CrossOriginResourceSharingConfigurationTest`, `TwigConfigurationTest`, `CacheableRouterConfigurationTest`, `SimpleAccessRuleConfigurationTest`, `SimpleFirewallConfigurationTest`, `ConfigurationValidationTraitTest` |
| views | `AbstractSmartViewHandlerTest`, `JsonViewHandlerTest`, `DefaultHtmlRendererTest`, `JsonApiRendererTest`, `PrefilightResponseTest`, `RouteBasedResponseRendererResolverTest` |
| routing | `GroupUrlMatcherTest`, `GroupUrlGeneratorTest`, `CacheableRouterUrlMatcherWrapperTest`, `InheritableRouteCollectionTest`, `InheritableYamlFileLoaderTest`, `CacheableRouterTest`, `CacheableRouterProviderTest` |
| cookie | `ResponseCookieContainerTest`, `SimpleCookieProviderTest` |
| middlewares | `AbstractMiddlewareTest` |
| misc | `ExtendedArgumentValueResolverTest`, `ExtendedExceptionListnerWrapperTest`, `ChainedParameterBagDataProviderTest` |
| integration | `BootstrapConfigurationIntegrationTest`, `SecurityAuthenticationFlowIntegrationTest`, `SilexKernelCrossCommunityIntegrationTest` |
| SilexKernelTest | `SilexKernelTest` |
| SilexKernelWebTest | `SilexKernelWebTest` |
| FallbackViewHandlerTest | `FallbackViewHandlerTest` |
| pbt | `ut/PBT/` 目录下所有 PBT 测试 |

---

## 版本号位置

- `composer.json` → `version` 字段（当前未显式声明，由 Packagist / tag 管理）

---

## 敏感文件

- 无 `.env` 文件
- 测试中的密码为硬编码示例值，非真实凭据
