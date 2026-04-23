# Changelog — v2.5.0

> Release date: 2026-04-24

## Summary

Release 2.5.0 为 `oasis/http` 项目在 PHP 8.5 升级前建立了完整的测试行为基线。在框架替换（Phase 1）之前，为所有缺少测试的模块补充了单元测试和集成测试，确保后续 breaking change 迁移有可靠的行为 SSOT。本次发布不包含功能变更，仅包含测试补全。

## Features

### PHP 8.5 升级前测试基线补全（PRP-001）

#### Added

- ErrorHandlers 模块单元测试：`WrappedExceptionInfoTest`、`ExceptionWrapperTest`、`JsonErrorHandlerTest`
- Configuration 模块 8 个单元测试：`HttpConfigurationTest`、`SecurityConfigurationTest`、`CrossOriginResourceSharingConfigurationTest`、`TwigConfigurationTest`、`CacheableRouterConfigurationTest`、`SimpleAccessRuleConfigurationTest`、`SimpleFirewallConfigurationTest`、`ConfigurationValidationTraitTest`
- Views 模块 6 个单元测试：`AbstractSmartViewHandlerTest`、`JsonViewHandlerTest`、`DefaultHtmlRendererTest`、`JsonApiRendererTest`、`PrefilightResponseTest`、`RouteBasedResponseRendererResolverTest`
- Routing 模块 7 个单元测试：`GroupUrlMatcherTest`、`GroupUrlGeneratorTest`、`CacheableRouterUrlMatcherWrapperTest`、`InheritableRouteCollectionTest`、`InheritableYamlFileLoaderTest`、`CacheableRouterTest`、`CacheableRouterProviderTest`
- Cookie 模块 2 个单元测试：`ResponseCookieContainerTest`、`SimpleCookieProviderTest`
- Middlewares 模块单元测试：`AbstractMiddlewareTest`
- Security 模块单元测试：`NullEntryPointTest`
- Misc 模块 4 个单元测试：`ExtendedArgumentValueResolverTest`、`ExtendedExceptionListnerWrapperTest`、`ChainedParameterBagDataProviderTest`、`UniquenessViolationHttpExceptionTest`
- 集成测试 3 个：`BootstrapConfigurationIntegrationTest`、`SecurityAuthenticationFlowIntegrationTest`、`SilexKernelCrossCommunityIntegrationTest`
- 集成测试基础设施：`integration.routes.yml`、`app.integration-security.php`、`app.integration-kernel.php`、`IntegrationController.php`
- 测试辅助类：`ConcreteSmartViewHandler`、`TestMiddleware`
- `RouteCacheCleaner` trait，统一路由缓存清理逻辑
- phpunit.xml 新增 8 个 test suite（`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）

#### Changed

- 补充 SilexKernel、CORS、Security、Twig、AWS 现有测试的未覆盖分支场景

#### Test Coverage

- 测试总数：333 tests, 597 assertions（全部通过）
- 覆盖了图谱分析识别的所有无测试社区和 god node
