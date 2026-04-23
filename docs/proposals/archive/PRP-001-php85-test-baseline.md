# PHP 8.5 Upgrade — Test Baseline: Coverage Completion

> Proposal：在 PHP 8.5 升级前，全面补足现有功能的测试覆盖，为后续 breaking change 迁移建立行为基线。

## Status

`released`

## Background

项目计划从 PHP `>=7.0.0` 升级到 PHP `>=8.5`。后续 Phase 1–4 将引入框架替换、依赖大版本升级、Security 组件重写、语言层面适配等大量 breaking change。这些变更的正确性验证完全依赖测试套件——现有测试必须足够全面，才能在迁移后作为行为 SSOT，确保功能不退化。

当前测试覆盖不足，多个核心模块缺少测试，无法为后续迁移提供可靠的行为基线。测试补全必须在框架替换（Phase 1）之前完成——替换后旧 API 不再可用，届时无法再针对旧行为编写测试。

### 依据：知识图谱分析

基于 `graphify-out/GRAPH_REPORT.md` 的图谱分析结果，项目包含 566 个节点、647 条边、71 个社区。以下图谱发现直接影响测试补全的优先级和范围：

**God Nodes（核心抽象，测试优先级最高）**

| 节点 | 边数 | 测试现状 |
|------|------|----------|
| `SilexKernel` | 19 | 有测试，但需补充场景 |
| `TestController` | 16 | 测试辅助类，不需独立测试 |
| `SilexKernelWebTest` | 14 | 已有，需补充场景 |
| `CrossOriginResourceSharingTest` | 13 | 已有 |
| `WrappedExceptionInfo` | 13 | **无测试** |
| `SimpleSecurityProvider` | 12 | 有测试，但需补充场景 |
| `CrossOriginResourceSharingStrategy` | 10 | 有间接测试 |
| `SecurityServiceProviderTest` | 9 | 已有 |

**关键社区（无测试覆盖）**

图谱识别出以下社区完全缺少测试：

- Community 6 "Error Handling & Renderers" — `DefaultHtmlRenderer`、`ExceptionWrapper`、`JsonApiRenderer`、`WrappedExceptionInfo`
- Community 5 "CORS Provider & Preflight" — `PrefilightResponse` 无独立测试
- Community 9 "Cookie & Middleware Interface" — `ResponseCookieContainer`、`SimpleCookieProvider`、`after()`、`before()`
- Community 16 "Smart View & JSON Handler" — `AbstractSmartViewHandler`、`JsonViewHandler`
- Community 26 "Argument Value Resolver" — `ExtendedArgumentValueResolver`
- Community 35 "Chained Parameter Bag" — `ChainedParameterBagDataProvider`
- Community 44 "Exception Listener Wrapper" — `ExtendedExceptionListnerWrapper`
- Community 45–51 "Configuration 系列" — 7 个配置类各自独立社区，全部无测试
- Community 52 "Uniqueness Violation Exception" — `UniquenessViolationHttpException`
- Community 53 "JSON Error Handler" — `JsonErrorHandler`
- Community 54 "Null Entry Point" — `NullEntryPoint`
- Community 55 "YAML File Loader" — `InheritableYamlFileLoader`
- Community 56 "Route-Based Renderer Resolver" — `RouteBasedResponseRendererResolver`

**Hyperedges（跨模块交互链路，集成测试重点）**

- **Bootstrap Configuration System** — `config_routing`、`config_security`、`config_cors`、`config_twig`、`config_middlewares`、`config_providers`、`concept_view_handler`、`concept_error_handler`、`concept_injected_args`、`concept_trusted_proxies`
- **Security Authentication Flow** — `security_policies`、`security_firewalls`、`security_access_rules`、`security_role_hierarchy`、`custom_policy_flow`、`concept_pre_authenticator`、`concept_user_provider`、`concept_request_sender`

**Cross-community Bridges（跨社区桥梁节点）**

- `SilexKernel`（betweenness 0.160）连接 Kernel Core、Cookie & Middleware、Kernel Web Tests、Configuration & Bootstrap 四个社区——其集成测试覆盖尤为关键
- `processConfiguration()`（betweenness 0.078）连接 Configuration & Bootstrap 与 Security Provider Core——配置到 provider 的链路需要集成测试

## Problem

现有测试覆盖存在明显缺口——以下模块缺少或测试不足：

- `src/Configuration/` — 8 个配置类（含 `ConfigurationValidationTrait`），无独立测试
- `src/ErrorHandlers/` — `ExceptionWrapper`、`JsonErrorHandler`、`WrappedExceptionInfo`（god node，13 edges），无测试
- `src/Exceptions/` — `UniquenessViolationHttpException`，无测试
- `src/Middlewares/` — `AbstractMiddleware`、`MiddlewareInterface`，无测试
- `src/ExtendedArgumentValueResolver.php` — 无测试
- `src/ExtendedExceptionListnerWrapper.php` — 无测试
- `src/ChainedParameterBagDataProvider.php` — 无测试
- `src/Views/` — 9 个视图类，仅 `FallbackViewHandlerTest` 覆盖了 1 个；`PrefilightResponse` 无独立测试
- `src/ServiceProviders/Cookie/` — 无测试
- `src/ServiceProviders/Routing/` — 无独立测试（含 `InheritableYamlFileLoader`、`InheritableRouteCollection`、`GroupUrlGenerator`、`GroupUrlMatcher`、`CacheableRouterUrlMatcherWrapper` 等）
- `src/ServiceProviders/Security/NullEntryPoint.php` — 无测试

如果不在迁移前补足测试，后续 Phase 的 breaking change 可能引入无法被发现的行为退化。

## Goals

在当前代码（迁移前）基础上，为所有缺少测试的模块补充全面的单元测试和集成测试，建立完整的行为基线。

### 单元测试覆盖目标

按图谱社区结构组织，优先级由 god node 边数和 cross-community bridge betweenness 决定：

**P0 — God Nodes & High-Betweenness Bridges**

- `src/ErrorHandlers/WrappedExceptionInfo.php` — god node（13 edges），`toArray()` 输出（normal/rich mode）、`jsonSerialize()`、`getAttribute()`、嵌套 previous exception 链
- `src/ErrorHandlers/ExceptionWrapper.php` — `__invoke()` 包装行为、`furtherProcessException` 钩子
- `src/ErrorHandlers/JsonErrorHandler.php` — JSON 错误响应生成
- `src/Configuration/` — 所有 8 个配置类（含 `ConfigurationValidationTrait`）的验证逻辑、默认值、边界条件；`processConfiguration()` 是 cross-community bridge（betweenness 0.078）

**P1 — 无测试社区**

- `src/Views/` — `AbstractSmartViewHandler`、`JsonViewHandler`、`DefaultHtmlRenderer`、`JsonApiRenderer`、`PrefilightResponse`、`RouteBasedResponseRendererResolver`
- `src/ServiceProviders/Routing/` — `CacheableRouter`、`CacheableRouterProvider`、`CacheableRouterUrlMatcherWrapper`、`GroupUrlGenerator`、`GroupUrlMatcher`、`InheritableRouteCollection`、`InheritableYamlFileLoader`
- `src/ServiceProviders/Cookie/` — `ResponseCookieContainer`、`SimpleCookieProvider`
- `src/Middlewares/` — `AbstractMiddleware`（通过 concrete test double 测试）
- `src/ServiceProviders/Security/NullEntryPoint.php`

**P2 — 独立节点**

- `src/ExtendedArgumentValueResolver.php` — `supports()` 和 `resolve()` 的各种输入场景
- `src/ExtendedExceptionListnerWrapper.php` — `ensureResponse()` 的 null/non-null 分支
- `src/ChainedParameterBagDataProvider.php` — 链式优先级、`HeaderBag` 单值/多值/零值行为
- `src/Exceptions/UniquenessViolationHttpException.php` — 构造与 HTTP status code

### 集成测试覆盖目标

基于图谱 hyperedges 和 cross-community bridges：

- **Bootstrap Configuration → ServiceProvider → Kernel 链路** — 覆盖 hyperedge "Bootstrap Configuration System" 中的 `config_routing`、`config_security`、`config_cors`、`config_twig`、`config_middlewares` 到实际 provider 注册和 kernel 行为的完整链路
- **Security Authentication Flow** — 覆盖 hyperedge 中的 `security_policies` → `security_firewalls` → `security_access_rules` 完整认证授权链路
- **SilexKernel 跨社区集成** — 作为 betweenness 最高的 bridge（0.160），补充 SilexKernel 与 Cookie、Middleware、Configuration 的交互场景

### 现有测试场景补充

- `SilexKernel` 相关测试 — 边界条件、异常路径、配置变体
- `Cors` 测试 — 配置变体
- `Security` 测试 — 认证失败路径、access rule 边界条件
- `Twig` 测试 — 配置变体
- `Aws` 测试 — 边界条件

### 测试补全原则

- 测试记录的是当前系统的实际行为，不是期望行为——迁移后这些测试就是行为 SSOT
- 覆盖正常路径、异常路径、边界条件
- 集成测试覆盖模块间的交互，重点关注图谱识别的 hyperedges 和 cross-community bridges
- 测试应在当前 PHP 版本 + 当前 PHPUnit 下全部通过

## Non-Goals

- 不涉及依赖升级——依赖和测试框架升级在 PRP-002 中处理
- 不涉及 Silex 框架替换
- 不涉及 Symfony 组件升级
- 不涉及 PHP 语言层面 breaking changes 修复
- 不修改现有业务逻辑——仅补充测试

## Scope

- `ut/` — 新增测试文件
- `phpunit.xml` — 新增测试文件注册
- `ut/Helpers/` — 测试辅助类（如需要）

## Risks

- 测试补全工作量可能较大，但这是后续所有 Phase 正确性的基础，不可跳过
- 部分模块与 Silex 框架深度耦合（`SilexKernel` betweenness 0.160），集成测试需要启动完整的 Application 实例
- 图谱中 23 个孤立节点（`Symfony Components`、`Pimple DI Container` 等）表明框架层面的集成点文档不足，测试编写时可能需要额外探索

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note
- `docs/proposals/PRP-002-php85-phase0-prerequisites.md` — 依赖升级 proposal（从同一 Phase 0 拆出）
- `graphify-out/GRAPH_REPORT.md` — 知识图谱分析报告（god nodes、社区结构、hyperedges、cross-community bridges）

## Notes

- 本 PRP 从原 PRP-001（Phase 0: Prerequisites）中拆出，原 PRP-001 同时包含依赖升级和测试补全，拆分后职责更清晰
- 测试补全必须在框架替换（Phase 1 / PRP-003）之前完成
- 补全的测试在后续 Phase 中可能需要适配新 API，但测试的断言（期望行为）应保持不变，这正是行为基线的意义
- 本 PRP 的测试使用当前 PHPUnit 5.x 编写；PRP-002 完成后，这些测试将被适配到 PHPUnit 13.x
