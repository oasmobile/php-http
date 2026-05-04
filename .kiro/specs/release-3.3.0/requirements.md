# Requirements Document

> Silex Migration Behavior Audit & Scenario Test Hardening — `.kiro/specs/release-3.3.0/`

---

## Introduction

oasis/http v3.0 完成了 Silex → Symfony MicroKernel 的框架替换，后续版本逐步完成 Security 重写（v3.0 Phase 3）、Symfony 8.x 适配（v3.1）、编程式路由注入 API（v3.2）、`AuthenticatedVoter` 修复（v3.2.1）等工作。当前版本 v3.2.1，测试覆盖率 89%（650 tests / 21850 assertions）。

v3.0 ~ v3.2 期间暴露的三个 issue（ISS-3.0-L01、ISS-3.0-L02、ISS-3.2-L01）揭示了一个结构性盲区：迁移时做了形式上的替换，但没有验证行为等价性。Silex 自动做的事在 Symfony 中需要手动组装，手动组装时容易遗漏组件。现有测试从实现出发验证实现，缺少从用户场景出发验证行为的测试，无法捕获"能力丢失"类问题。

本次 release 的核心目标是系统性消除这一盲区：对迁移涉及的每个模块，基于 Silex 官方文档和 GitHub 源码存档，逐项对比 API surface 和运行时行为；按风险排序逐模块推进审计和场景测试补充；最后做一轮 MicroKernel 聚合层汇总审计。

**审计范围与风险排序**：Security → Routing → Middleware → CORS → Error Handling → Twig → Cookie

**处置策略**：
- 非 breaking 的缺失能力 → 直接补回代码
- 需要 breaking change 的缺失能力 → 仅文档化到 `docs/manual/migration-v3.md`
- 有意移除的能力 → 确认已在 migration guide 中标注

**不涉及的内容**：
- 不做功能新增——仅恢复到 Silex 时代的等价行为
- 不重构现有实现——除非审计发现的问题必须通过重构修复
- 不追求 100% 行覆盖率——场景测试的目标是行为完整性
- 不涉及下游项目的适配工作
- 不处理需要 breaking change 才能修复的缺失能力（仅文档化）

**约束**：
- C-1: 审计基准为 Silex 官方文档和 GitHub 源码存档，不依赖本仓库 Git 历史中的迁移前快照
- C-2: 按风险排序逐模块推进，每个模块审计+测试完成后 checkpoint commit
- C-3: 测试视角为场景级集成测试（boot → 配置 → 请求 → 验证响应），不是从实现出发验证实现
- C-4: MicroKernel 上暴露的方法归入对应模块一起审计，各模块完成后再做聚合层汇总审计
- C-5: 全部工作在 `release/3.3.0` 分支上线性推进

---

## Glossary

- **MicroKernel**: 核心入口类（`Oasis\Mlib\Http\MicroKernel`），继承 Symfony `HttpKernel`，实现 `AuthorizationCheckerInterface`，通过 Bootstrap_Config 数组驱动初始化
- **Bootstrap_Config**: `MicroKernel` 构造函数接受的关联数组，包含 `routing`、`security`、`cors`、`twig`、`middlewares`、`providers`、`view_handlers`、`error_handlers`、`injected_args` 等顶层 key
- **API_Surface**: 模块对外暴露的公开方法、可配置项、事件、隐含行为的总和
- **Behavior_Audit**: 基于 Silex 官方文档和源码，逐项对比 Silex 时代 API_Surface 与当前 MicroKernel 实现的覆盖情况
- **Scenario_Test**: 从用户场景出发的行为测试，流程为：构造 MicroKernel → 配置 → boot → 发请求 → 验证响应
- **Audit_Matrix**: 审计对比矩阵，记录每个模块的 Silex API_Surface 项、MicroKernel 覆盖状态、处置决策
- **SimpleSecurityProvider**: Security 模块的核心 provider，自管理认证和授权，不依赖 Symfony SecurityBundle
- **CacheableRouterProvider**: Routing 模块的核心 provider，支持 YAML 路由加载、路由缓存、编程式路由注入
- **CrossOriginResourceSharingProvider**: CORS 模块的 EventSubscriber，处理 preflight 和响应头
- **SimpleCookieProvider**: Cookie 模块的 EventSubscriber，在 response 阶段写入 cookie
- **SimpleTwigServiceProvider**: Twig 模块的 provider，集成 Twig 3.x 模板引擎
- **Middleware_Chain**: before / after middleware 的执行链，按 priority 排序
- **Error_Handler_Chain**: 异常处理链，通过 `KernelEvents::EXCEPTION` 事件触发
- **View_Handler_Chain**: View Handler 链，处理控制器返回的非 Response 值，通过 `KernelEvents::VIEW` 事件触发，按注册顺序尝试各 handler
- **FallbackViewHandler**: 默认的 View Handler / Error 渲染器，在无自定义 handler 处理时提供兜底响应
- **Migration_Guide**: 面向下游消费者的迁移文档（`docs/manual/migration-v3.md`）


---

## Requirements

### Requirement 1: Security 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex Security Provider 与当前 SimpleSecurityProvider 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Security Provider API_Surface, including: firewall registration, authentication policy types (pre_auth / http / form / anonymous), access rule matching, role hierarchy, `AuthenticatedVoter` capabilities, token storage, entry point behavior, and implicit component auto-registration.
2. THE Behavior_Audit SHALL compare each Silex Security API_Surface item against the current SimpleSecurityProvider implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Security module, recording each API_Surface item, coverage status, and disposition.

### Requirement 2: Security 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Security 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for the complete authentication flow: configure firewall with pre_auth policy → boot MicroKernel → send request with valid credentials → verify `getToken()` returns `PostAuthenticationToken` and `getUser()` returns the authenticated user.
2. THE Scenario_Test suite SHALL include a test for authentication failure: configure firewall → boot MicroKernel → send request with invalid credentials → verify the request is not blocked at firewall level (token remains null) and access rule enforcement determines the outcome.
3. THE Scenario_Test suite SHALL include tests for `isGranted()` with various attributes: `IS_AUTHENTICATED_FULLY`, role-based attributes (`ROLE_ADMIN`), and role hierarchy inheritance.
4. THE Scenario_Test suite SHALL include a test for multiple firewall configuration: configure two firewalls with different URL patterns and policies → verify each firewall applies only to its matched pattern.
5. THE Scenario_Test suite SHALL include a test for multiple access rule configuration: configure access rules with different URL patterns and required roles → verify rules are matched in registration order and the first match takes effect.
6. THE Scenario_Test suite SHALL include a test for unauthenticated access to a protected resource: verify `AccessDeniedHttpException` is thrown.
7. THE Scenario_Test suite SHALL include a test for stateless firewall behavior: verify no session interaction occurs during authentication.

### Requirement 3: Routing 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex 路由机制与当前 CacheableRouterProvider 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Routing API_Surface, including: YAML route loading, route parameter replacement (`%param%`), route caching, URL matching (including redirectable matching), URL generation, route collection manipulation, namespace prefix handling, and inheritable route loading.
2. THE Behavior_Audit SHALL compare each Silex Routing API_Surface item against the current CacheableRouterProvider implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Routing module, recording each API_Surface item, coverage status, and disposition.

### Requirement 4: Routing 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Routing 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for YAML route loading and matching: configure `routing.path` → boot MicroKernel → send request to a defined route → verify correct controller is invoked and response is returned.
2. THE Scenario_Test suite SHALL include a test for programmatic route injection: call `addRoute()` before boot → boot MicroKernel → send request to the injected route → verify correct response.
3. THE Scenario_Test suite SHALL include a test for mixed routing (YAML + programmatic): verify programmatic routes take priority over YAML routes when paths overlap.
4. THE Scenario_Test suite SHALL include a test for route parameter replacement: define a route with `%param%` placeholder → configure the parameter value → verify the resolved route uses the replaced value.
5. THE Scenario_Test suite SHALL include a test for boot-after route freeze: boot MicroKernel → attempt `addRoute()` → verify `LogicException` is thrown.
6. THE Scenario_Test suite SHALL include a test for boot-after RouteCollection freeze: boot MicroKernel → attempt `getRouter()->getRouteCollection()->add()` → verify `LogicException` is thrown.
7. THE Scenario_Test suite SHALL include a test for route cache behavior: configure `cache_dir` → boot and handle request → verify cached matcher is created → boot again → verify cached matcher is reused.
8. THE Scenario_Test suite SHALL include a test for undefined route: send request to an undefined path → verify 404 response.


### Requirement 5: Middleware 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex middleware 机制与当前 Middleware_Chain 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Middleware API_Surface, including: `before()` / `after()` callback registration, priority ordering, master-request-only filtering, short-circuit behavior (before middleware returning Response), `Application::EARLY_EVENT` / `LATE_EVENT` constants, and `MiddlewareInterface` / `AbstractMiddleware` contracts.
2. THE Behavior_Audit SHALL compare each Silex Middleware API_Surface item against the current Middleware_Chain implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Middleware module, recording each API_Surface item, coverage status, and disposition.

### Requirement 6: Middleware 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Middleware 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for before middleware execution: register a before middleware → boot MicroKernel → send request → verify the middleware executes before the controller.
2. THE Scenario_Test suite SHALL include a test for after middleware execution: register an after middleware → boot MicroKernel → send request → verify the middleware executes after the controller and can modify the response.
3. THE Scenario_Test suite SHALL include a test for middleware priority ordering: register multiple before middlewares with different priorities → verify execution order is strictly descending by priority (higher priority executes first).
4. THE Scenario_Test suite SHALL include a test for before middleware short-circuit: register a before middleware that returns a Response → verify the controller does not execute and the middleware's Response is returned.
5. THE Scenario_Test suite SHALL include a test for master-request-only filtering: register a middleware with `onlyForMasterRequest() = true` → verify the middleware executes for main requests and does not execute for sub-requests.
6. THE Scenario_Test suite SHALL include a test for middleware exception behavior: register a before middleware that throws an exception → verify the Error_Handler_Chain is invoked.

### Requirement 7: CORS 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex CORS 处理与当前 CrossOriginResourceSharingProvider 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex CORS API_Surface, including: preflight detection and response, `Access-Control-*` header processing, strategy matching by URL pattern, origin validation, credentials support, max-age configuration, `MethodNotAllowed` exception handling for OPTIONS requests, and interaction with Security firewall.
2. THE Behavior_Audit SHALL compare each Silex CORS API_Surface item against the current CrossOriginResourceSharingProvider implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the CORS module, recording each API_Surface item, coverage status, and disposition.

### Requirement 8: CORS 模块场景测试

**User Story:** 作为迁移维护者，我希望为 CORS 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for preflight request handling: configure CORS strategy → boot MicroKernel → send OPTIONS request with `Access-Control-Request-Method` header → verify preflight response with correct `Access-Control-Allow-*` headers.
2. THE Scenario_Test suite SHALL include a test for normal CORS request: send a cross-origin GET request → verify response includes `Access-Control-Allow-Origin` header matching the strategy.
3. THE Scenario_Test suite SHALL include a test for multiple CORS strategy matching: configure two strategies with different URL patterns → verify each strategy applies only to its matched pattern.
4. THE Scenario_Test suite SHALL include a test for CORS with credentials: configure a strategy with `credentials = true` → verify `Access-Control-Allow-Credentials: true` header is present in the response.
5. THE Scenario_Test suite SHALL include a test for CORS and Security interaction: configure both CORS and Security → send a preflight request to a secured endpoint → verify the preflight response is returned without triggering authentication.
6. THE Scenario_Test suite SHALL include a test for non-matching origin: send a cross-origin request with an origin not in the allowed list → verify no `Access-Control-Allow-Origin` header is added.


### Requirement 9: Error Handling 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex 异常处理链与当前 Error_Handler_Chain 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Error Handling API_Surface, including: `error()` callback registration, error handler priority, exception-to-response conversion, `ExceptionListenerWrapper` behavior, error handler chain short-circuit (handler returns Response), error handler chain passthrough (handler returns null), HTTP exception status code preservation, and `FallbackViewHandler` error rendering.
2. THE Behavior_Audit SHALL compare each Silex Error Handling API_Surface item against the current Error_Handler_Chain implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Error Handling module, recording each API_Surface item, coverage status, and disposition.

### Requirement 10: Error Handling 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Error Handling 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for custom error handler: register an error handler via Bootstrap_Config → boot MicroKernel → send request that triggers an exception → verify the error handler receives the exception and its Response is returned.
2. THE Scenario_Test suite SHALL include a test for error handler chain ordering: register multiple error handlers → trigger an exception → verify handlers are invoked in registration order and the first handler returning a Response short-circuits the chain.
3. THE Scenario_Test suite SHALL include a test for error handler passthrough: register an error handler that returns null → verify the exception propagates to the next handler or to the default Symfony exception handling.
4. THE Scenario_Test suite SHALL include a test for HTTP exception status code preservation: throw an `HttpException` with status 403 → verify the response status code is 403.
5. THE Scenario_Test suite SHALL include a test for `FallbackViewHandler` error rendering: configure no custom error handler → trigger an exception → verify `FallbackViewHandler` produces a response.

### Requirement 11: Twig 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex Twig 集成与当前 SimpleTwigServiceProvider 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Twig API_Surface, including: Twig environment initialization, template path configuration, strict variables mode, auto-reload behavior, `getTwig()` access, Twig absence handling (no twig config), and Twig 3.x class name migration.
2. THE Behavior_Audit SHALL compare each Silex Twig API_Surface item against the current SimpleTwigServiceProvider implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Twig module, recording each API_Surface item, coverage status, and disposition.

### Requirement 12: Twig 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Twig 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for Twig template rendering: configure Twig with template path → boot MicroKernel → send request to a controller that renders a template → verify the response body contains the rendered template content.
2. THE Scenario_Test suite SHALL include a test for Twig absence: configure MicroKernel without `twig` key → boot → call `getTwig()` → verify null is returned.
3. THE Scenario_Test suite SHALL include a test for Twig strict variables mode: configure `twig.strict_variables = true` → render a template referencing an undefined variable → verify an exception is thrown.
4. THE Scenario_Test suite SHALL include a test for Twig auto-reload behavior: configure `twig.auto_reload` → verify the Twig environment reflects the configured auto-reload setting.

### Requirement 13: Cookie 模块行为审计

**User Story:** 作为迁移维护者，我希望系统性对比 Silex Cookie 处理与当前 SimpleCookieProvider 的 API_Surface，以便发现并处置所有行为差异。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the Silex Cookie API_Surface, including: `ResponseCookieContainer` injection into controllers, cookie writing to response headers on `KernelEvents::RESPONSE`, and cookie container lifecycle per request.
2. THE Behavior_Audit SHALL compare each Silex Cookie API_Surface item against the current SimpleCookieProvider implementation and classify the result as: covered, missing-non-breaking, missing-breaking, or intentionally-removed.
3. IF a missing-non-breaking capability is found THEN THE implementation SHALL restore the capability to Silex-era equivalence.
4. IF a missing-breaking capability is found THEN THE Migration_Guide SHALL document the gap with severity and workaround.
5. IF an intentionally-removed capability is found THEN THE Behavior_Audit SHALL confirm the Migration_Guide already documents the removal.
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the Cookie module, recording each API_Surface item, coverage status, and disposition.

### Requirement 14: Cookie 模块场景测试

**User Story:** 作为迁移维护者，我希望为 Cookie 模块补充从用户场景出发的行为测试，以便建立行为基准并防止回归。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for cookie writing: controller adds a cookie to `ResponseCookieContainer` → verify the response `Set-Cookie` header contains the cookie.
2. THE Scenario_Test suite SHALL include a test for `ResponseCookieContainer` controller injection: configure SimpleCookieProvider → boot MicroKernel → verify `ResponseCookieContainer` is available as a controller injected argument.
3. THE Scenario_Test suite SHALL include a test for multiple cookies: controller adds multiple cookies → verify all cookies appear in the response headers.


### Requirement 15: MicroKernel 聚合层汇总审计

**User Story:** 作为迁移维护者，我希望在各模块审计完成后，对 MicroKernel 聚合层做一轮汇总审计，以便发现模块审计遗漏的跨模块或聚合层问题。

#### Acceptance Criteria

1. THE Behavior_Audit SHALL enumerate the MicroKernel public API methods (`run()`, `handle()`, `isGranted()`, `getToken()`, `getUser()`, `getTwig()`, `getParameter()`, `addExtraParameters()`, `addControllerInjectedArg()`, `addMiddleware()`, `addRoute()`, `addRoutes()`, `getCacheDirectories()`) and verify each method's behavior matches the Silex-era `SilexKernel` equivalent (or is documented as changed in Migration_Guide).
2. THE Behavior_Audit SHALL verify the request processing pipeline order: ELB/CloudFront trusted proxy → Routing (priority 32) → CORS preflight (priority 20) → Firewall (priority 8) → Access rule (priority 7) → user before middleware → controller → View Handler chain → after middleware → Response.
3. THE Behavior_Audit SHALL verify Bootstrap_Config key completeness: all documented keys (`routing`, `security`, `cors`, `twig`, `middlewares`, `providers`, `view_handlers`, `error_handlers`, `injected_args`, `trusted_proxies`, `trusted_header_set`, `behind_elb`, `trust_cloudfront_ips`, `cache_dir`) are processed and their behavior matches documentation.
4. THE Behavior_Audit SHALL check for cross-module interaction issues not covered by individual module audits, including: Security + CORS interaction (preflight bypasses firewall), Security + Middleware ordering, Error Handler + View Handler precedence.
5. IF the aggregation audit discovers gaps not found in individual module audits THEN THE gaps SHALL be classified and disposed following the same strategy (non-breaking → fix, breaking → document, intentionally-removed → confirm documented).
6. THE Behavior_Audit SHALL produce an Audit_Matrix for the MicroKernel aggregation layer.

### Requirement 16: MicroKernel 聚合层场景测试

**User Story:** 作为迁移维护者，我希望为 MicroKernel 聚合层补充跨模块的场景测试，以便验证各模块协同工作的行为正确性。

#### Acceptance Criteria

1. THE Scenario_Test suite SHALL include a test for full pipeline traversal: configure routing + security + CORS + middleware + view handler + error handler → boot MicroKernel → send a normal request → verify the request traverses the complete pipeline and produces the expected response.
2. THE Scenario_Test suite SHALL include a test for MicroKernel boot with minimal configuration: construct MicroKernel with only `routing` config → boot → send request → verify basic request-response cycle works.
3. THE Scenario_Test suite SHALL include a test for MicroKernel boot with no optional modules: construct MicroKernel without `security`, `cors`, `twig` → boot → send request → verify the kernel operates correctly with only routing.
4. THE Scenario_Test suite SHALL include a test for `addControllerInjectedArg()`: register a custom object → boot → verify the object is available as a controller argument.
5. THE Scenario_Test suite SHALL include a test for `addExtraParameters()`: add extra parameters → verify `getParameter()` returns the added values.
6. THE Scenario_Test suite SHALL include a test for slow request detection: configure a controller that exceeds the slow request threshold → verify slow request logging behavior.

### Requirement 17: 文档更新

**User Story:** 作为迁移维护者，我希望审计发现的文档遗漏或不准确之处被修正，以便 Migration_Guide 和架构文档准确反映系统行为。

#### Acceptance Criteria

1. IF the Behavior_Audit discovers undocumented breaking changes THEN THE Migration_Guide (`docs/manual/migration-v3.md`) SHALL be updated to include the missing breaking change with severity marker, before/after code examples, and migration instructions.
2. IF the Behavior_Audit discovers inaccuracies in the architecture document (`docs/state/architecture.md`) THEN THE architecture document SHALL be corrected.
3. WHEN all module audits and the aggregation audit are complete THEN THE Migration_Guide SHALL be reviewed for completeness against the full set of Audit_Matrix results.
4. THE documentation updates SHALL follow the project writing conventions: Chinese prose with English technical terms, backtick-wrapped code references, table format for structured comparisons.


---

## Socratic Review

**Q: 为什么按 Security → Routing → Middleware → CORS → Error Handling → Twig → Cookie 的顺序审计？**
A: 按风险排序。Security 已暴露 ISS-3.2-L01（`AuthenticatedVoter` 遗漏），Silex Security Provider 内部自动注册大量组件，手动重组最易遗漏。Routing 已暴露 ISS-3.0-L01/L02（编程式路由 API 丢失、boot 后路由修改静默失效）。Middleware 和 CORS 风险中等（事件优先级和执行顺序可能存在隐含差异）。Error Handling 风险中等（Silex 和 Symfony HttpKernel 的异常处理链机制不同）。Twig 和 Cookie 风险低（集成方式相对简单）。

**Q: 审计基准为什么选择 Silex 官方文档和 GitHub 源码存档，而不是本仓库 Git 历史？**
A: Goal CR Q1 的决策。Silex 已 abandoned，本仓库 Git 历史中的迁移前快照可能不完整或不代表 Silex 的完整能力。官方文档和 GitHub 源码存档是最权威的基准。

**Q: 场景测试与现有测试是否重复？**
A: 视角不同。现有测试从实现出发验证实现（如 `SecurityServiceProviderTest` 验证 provider 注册逻辑），场景测试从用户场景出发验证行为（如"配置 firewall → boot → 发请求 → 验证认证结果"）。两者互补，场景测试能捕获"能力丢失"类问题，这正是现有测试体系的盲区。

**Q: Audit_Matrix 作为过程产物，长期维护成本如何？**
A: Audit_Matrix 记录在 spec design 中，作为审计过程的证据。长期价值由场景测试承载——测试即行为基准，Audit_Matrix 本身不需要持续维护。

**Q: 为什么 MicroKernel 聚合层需要单独审计？**
A: Goal CR Q4 的决策（A+C）。各模块审计时覆盖 MicroKernel 上对应的方法（如 Security 审计覆盖 `isGranted()`、`getToken()`、`getUser()`），但跨模块交互问题（如 Security + CORS 的 preflight 绕过 firewall）和 Bootstrap_Config 整体完整性需要聚合层视角才能发现。

**Q: 处置策略中"非 breaking 补回来"的边界是什么？**
A: Goal CR Q3 的决策。"非 breaking"指补回的能力不改变现有公开 API 的签名或行为，下游消费者无需修改代码即可受益。如果恢复某个能力需要改变现有 API 签名或行为语义，则归类为"breaking"，仅文档化。

**Q: Requirements 中审计类 requirement（奇数编号）和测试类 requirement（偶数编号）的关系？**
A: 审计类 requirement 定义"发现什么"，测试类 requirement 定义"验证什么"。审计是一次性的过程产物，测试是持久的行为基准。两者按模块配对，审计发现驱动测试设计，但测试的 AC 独立于审计结果——即使审计未发现差异，场景测试仍需补充以建立行为基准。

**Q: 与 `docs/notes/coverage-improvement.md` 的关系？**
A: 互补但侧重不同。coverage-improvement 关注行覆盖率数字（89% → 95%），本 spec 关注行为完整性。场景测试的副产品会提升覆盖率（特别是 `MicroKernel` 的 75 行未覆盖缺口），但这不是本 spec 的目标。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: 不存在矛盾。R1-R14 按模块配对（审计+测试），模块之间互不重叠。R15-R16 是聚合层，关注跨模块交互，与单模块 requirement 互补。R17 是文档更新，依赖所有审计 requirement 的结果。审计类 requirement 的 AC 结构一致（enumerate → compare → classify → dispose），测试类 requirement 的 AC 各自针对模块特性设计。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [术语] Glossary 补充 `View_Handler_Chain` 和 `FallbackViewHandler` 定义——R9-AC1、R10-AC5、R15-AC2/AC4 中引用了这两个概念但 Glossary 中未定义

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表中的术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空，术语与 AC 交叉引用一致
- [x] Requirements section 存在且包含 17 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] 所有 AC 使用 `THE <Subject> SHALL ...` / `IF ... THEN ...` / `WHEN ... THEN ...` 语体
- [x] 所有 User Story 使用中文行文
- [x] AC 编号连续无跳号
- [x] AC 聚焦外部可观察行为，未混入不当实现细节
- [x] Socratic Review 覆盖充分（9 个 Q&A，涵盖排序理由、基准来源、测试重叠、维护成本、聚合审计、处置边界、requirement 关系、coverage-improvement 关系、矛盾/重叠检查）
- [x] Goal CR 决策已体现在 requirements 中（Q1→C-1, Q2→C-2, Q3→处置策略, Q4→C-4/R15-R16）
- [x] Goal 清晰度达标
- [x] Non-goal / Scope 边界清晰
- [x] 完成标准充分（17 条 requirement 覆盖 7 模块 + 聚合层 + 文档）
- [x] 可 design 性达标

### Clarification Round

**状态**: 已回答

**Q1:** 场景测试的组织方式——每个模块的 Scenario_Test 应如何组织到测试目录中？

各模块的场景测试可能有不同的组织策略，这会影响 design 阶段的测试架构设计。

- A) 每个模块一个独立的测试类（如 `SecurityScenarioTest`、`RoutingScenarioTest`），放在现有 `ut/` 目录下的对应子目录中
- B) 所有场景测试集中在一个新的顶层目录（如 `ut/Scenarios/`），按模块分子目录
- C) 融入现有测试目录结构，在各模块已有的测试目录下新增场景测试文件
- D) 其他（请说明）

**A:** A — 每个模块一个独立测试类，放在现有 `ut/` 目录下的对应子目录中

**Q2:** Audit_Matrix 的记录粒度和格式——design 阶段需要确定 Audit_Matrix 的具体结构，但粒度选择会影响审计工作量和产出价值。

- A) 粗粒度：每个模块一张表，行为 Silex 的功能类别（如"firewall registration"、"role hierarchy"），列为覆盖状态和处置决策
- B) 细粒度：每个模块一张表，行为 Silex 的每个具体 API 方法/配置项/事件，列为覆盖状态、当前对应实现、处置决策
- C) 混合粒度：高风险模块（Security、Routing）用细粒度，中低风险模块用粗粒度
- D) 其他（请说明）

**A:** B — 细粒度：每个模块一张表，行为 Silex 的每个具体 API 方法/配置项/事件，列为覆盖状态、当前对应实现、处置决策

**Q3:** 场景测试中 MicroKernel 的构造方式——场景测试需要完整 boot MicroKernel，构造成本较高。design 阶段需要决定是否提取测试辅助工具。

- A) 每个测试直接构造 MicroKernel（通过 Bootstrap_Config 数组），不提取公共辅助工具，保持测试的独立性和可读性
- B) 提取一个 `TestKernelFactory` 或 trait，提供常用配置模板（如 `withSecurity()`、`withRouting()`），减少重复代码
- C) 提取一个轻量级 base test case 类，封装 boot + handle + assert 的通用流程，各模块测试继承
- D) 其他（请说明）

**A:** C — 提取一个轻量级 base test case 类，封装 boot + handle + assert 的通用流程，各模块测试继承

**Q4:** 审计发现的 missing-non-breaking 能力的修复验证策略——R1/R3/R5/R7/R9/R11/R13 的 AC3 都要求"restore the capability"，但修复后的验证方式会影响 design。

- A) 修复的能力由对应模块的 Scenario_Test 覆盖即可（即 R2/R4/R6/R8/R10/R12/R14 的 AC 自然覆盖修复）
- B) 修复的能力需要额外的专项回归测试（独立于场景测试），确保修复本身不引入新问题
- C) 修复的能力优先由场景测试覆盖，如果场景测试无法充分验证（如涉及内部状态），再补充单元测试
- D) 其他（请说明）

**A:** B — 修复的能力需要额外的专项回归测试（独立于场景测试），确保修复本身不引入新问题
