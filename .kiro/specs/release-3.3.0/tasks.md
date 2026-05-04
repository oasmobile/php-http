# Implementation Plan: Release 3.3.0

## Overview

对 Silex → Symfony MicroKernel 迁移涉及的 7 个模块 + MicroKernel 聚合层进行系统性行为审计与场景测试加固。按风险排序逐模块推进（Security → Routing → Middleware → CORS → Error Handling → Twig → Cookie → MicroKernel 聚合层），每个模块合并审计 + 修复 + 回归测试 + 场景测试为一个 task（Design CR Q1=A）。Security task 中一并实现 `ScenarioTestCase` 基类（Design CR Q2=B）。Audit_Matrix 先产出到 `.kiro/specs/release-3.3.0/`，全部完成后移动到 `docs/changes/3.3/`（Design CR Q3=B）。高风险模块（Security、Routing）场景测试允许与现有测试重复以确保覆盖，低风险模块（Twig、Cookie）优先引用现有测试（Design CR Q4=B+C）。

## Tasks

- [x] 1. Security 模块：行为审计 + 场景测试 + 修复
  - [x] 1.1 实现 `ScenarioTestCase` 基类（`tests/Helpers/ScenarioTestCase.php`）
    - 继承 `PHPUnit\Framework\TestCase`
    - 封装 `buildKernel(array $config, bool $isDebug = false): MicroKernel`
    - 封装 `handleRequest(MicroKernel $kernel, string $method, string $uri, array $parameters = [], array $server = []): Response`
    - 封装 `assertJsonResponse(Response $response, int $expectedStatus): array`
    - 封装 `assertStatusCode(Response $response, int $expectedStatus): void`
    - 提供 `createRoutingConfig(string $routesFile): array`
    - 提供 `createTempCacheDir(): string`
    - `tearDown()` 中自动 shutdown kernel 并清理缓存
    - _Requirements: R2（基础设施）_
  - [x] 1.2 编写 Security 场景测试（`tests/Security/SecurityScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testCompleteAuthenticationFlow`：firewall with pre_auth policy → boot → 发请求 → 验证 `getToken()` 返回 `PostAuthenticationToken`，`getUser()` 返回认证用户
    - `testAuthenticationFailure`：invalid credentials → token 为 null，access rule 决定结果
    - `testIsGrantedWithVariousAttributes`：`IS_AUTHENTICATED_FULLY`、`ROLE_ADMIN`、角色继承
    - `testMultipleFirewallConfiguration`：两个 firewall 不同 URL pattern，各自仅匹配自己的 pattern
    - `testMultipleAccessRuleOrdering`：多条 access rule，按注册顺序匹配，第一条匹配生效
    - `testUnauthenticatedAccessToProtectedResource`：`AccessDeniedHttpException` → 403
    - `testStatelessFirewallBehavior`：无 session 交互
    - _Requirements: R2-AC1, R2-AC2, R2-AC3, R2-AC4, R2-AC5, R2-AC6, R2-AC7_
  - [x] 1.3 执行 Security 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准，而非 Silex 原始能力
    - 枚举 v2.5.0 `SimpleSecurityProvider` 暴露的 API_Surface（`addFirewall`、`addAccessRule`、`addAuthenticationPolicy`、`addRoleHierarchy`、`FirewallInterface`、`AccessRuleInterface`、`AuthenticationPolicyInterface`、`AbstractSimplePreAuthenticator`、`SilexKernel::isGranted/getToken/getUser`）
    - **接口存在性审计**：逐项对比 v3.x 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（pattern matching 逻辑、认证流程、token 类型、异常传播路径、AccessDecisionManager strategy、session/stateless 行为等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/security-audit-matrix.md`
    - _Requirements: R1-AC1, R1-AC2, R1-AC6_
  - [x] 1.4 修复 Security 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Security/SecurityFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking 能力 → 文档化到 `docs/manual/migration-v3.md`
    - 如审计发现 intentionally-removed 能力 → 确认 Migration_Guide 已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R1-AC3, R1-AC4, R1-AC5_
  - [x] 1.5 Checkpoint: 运行 `php vendor/bin/phpunit --testsuite security`（或全量测试），确认 Security 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(security): behavior audit + scenario tests for release 3.3.0`


- [x] 2. Routing 模块：行为审计 + 场景测试 + 修复
  - [x] 2.1 编写 Routing 场景测试（`tests/Routing/RoutingScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testYamlRouteLoadingAndMatching`：`routing.path` → boot → 发请求 → 正确 controller 被调用
    - `testProgrammaticRouteInjection`：`addRoute()` before boot → 注入路由可匹配
    - `testMixedRoutingPriority`：YAML + `addRoute()` 路径重叠 → 编程式路由优先
    - `testRouteParameterReplacement`：`%param%` placeholder → 解析后使用替换值
    - `testBootAfterRouteFreeze`：boot → `addRoute()` → `LogicException`
    - `testBootAfterRouteCollectionFreeze`：boot → `getRouter()->getRouteCollection()->add()` → `LogicException`
    - `testRouteCacheBehavior`：`cache_dir` → cached matcher 创建 → 复用
    - `testUndefinedRoute`：请求未定义路径 → 404
    - _Requirements: R4-AC1, R4-AC2, R4-AC3, R4-AC4, R4-AC5, R4-AC6, R4-AC7, R4-AC8_
  - [x] 2.2 执行 Routing 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 Routing 相关代码暴露的 API_Surface（`SilexKernel` 中的路由注册、YAML route loading、route parameter replacement、route caching、URL matching、URL generation）
    - **接口存在性审计**：逐项对比 v3.x `CacheableRouterProvider` 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（route matching 逻辑、cache 机制、parameter replacement 时机、route collection 操作语义等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/routing-audit-matrix.md`
    - _Requirements: R3-AC1, R3-AC2, R3-AC6_
  - [x] 2.3 修复 Routing 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Routing/RoutingFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking 能力 → 文档化到 `docs/manual/migration-v3.md`
    - 如审计发现 intentionally-removed 能力 → 确认 Migration_Guide 已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R3-AC3, R3-AC4, R3-AC5_
  - [x] 2.4 Checkpoint: 运行全量测试，确认 Routing 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(routing): behavior audit + scenario tests for release 3.3.0`

- [x] 3. Middleware 模块：行为审计 + 场景测试 + 修复
  - [x] 3.1 编写 Middleware 场景测试（`tests/Middlewares/MiddlewareScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testBeforeMiddlewareExecution`：before middleware 在 controller 前执行
    - `testAfterMiddlewareExecution`：after middleware 在 controller 后执行，可修改 response
    - `testMiddlewarePriorityOrdering`：多个 before middleware 不同 priority → 按 priority 降序执行
    - `testBeforeMiddlewareShortCircuit`：before middleware 返回 Response → controller 不执行
    - `testMasterRequestOnlyFiltering`：`onlyForMasterRequest() = true` → main request 执行，sub-request 不执行
    - `testMiddlewareExceptionBehavior`：before middleware 抛异常 → Error_Handler_Chain 被调用
    - _Requirements: R6-AC1, R6-AC2, R6-AC3, R6-AC4, R6-AC5, R6-AC6_
  - [x] 3.2 执行 Middleware 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 Middleware 相关代码暴露的 API_Surface（`SilexKernel::before/after` 覆写、`MiddlewareInterface`、`AbstractMiddleware`、priority ordering、master-request-only filtering、short-circuit behavior）
    - **接口存在性审计**：逐项对比 v3.x Middleware_Chain 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（event listener 注册方式、priority 映射、short-circuit 机制、after middleware 的 response 修改时机等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/middleware-audit-matrix.md`
    - _Requirements: R5-AC1, R5-AC2, R5-AC6_
  - [x] 3.3 修复 Middleware 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Middlewares/MiddlewareFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R5-AC3, R5-AC4, R5-AC5_
  - [x] 3.4 Checkpoint: 运行全量测试，确认 Middleware 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(middleware): behavior audit + scenario tests for release 3.3.0`

- [x] 4. CORS 模块：行为审计 + 场景测试 + 修复
  - [x] 4.1 编写 CORS 场景测试（`tests/Cors/CorsScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testPreflightRequestHandling`：OPTIONS + `Access-Control-Request-Method` → preflight response with `Access-Control-Allow-*` headers
    - `testNormalCorsRequest`：cross-origin GET → `Access-Control-Allow-Origin` header
    - `testMultipleCorsStrategyMatching`：两个 strategy 不同 URL pattern → 各自仅匹配自己的 pattern
    - `testCorsWithCredentials`：`credentials = true` → `Access-Control-Allow-Credentials: true`
    - `testCorsAndSecurityInteraction`：CORS + Security → preflight 不触发认证
    - `testNonMatchingOrigin`：不在 allowed list 的 origin → 无 `Access-Control-Allow-Origin` header
    - _Requirements: R8-AC1, R8-AC2, R8-AC3, R8-AC4, R8-AC5, R8-AC6_
  - [x] 4.2 执行 CORS 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 CORS 相关代码暴露的 API_Surface（preflight detection、`Access-Control-*` header processing、strategy matching、origin validation、credentials support、max-age、Security interaction）
    - **接口存在性审计**：逐项对比 v3.x `CrossOriginResourceSharingProvider` 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（event listener priority、preflight response 生成逻辑、origin 匹配算法、与 Security firewall 的交互顺序等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/cors-audit-matrix.md`
    - _Requirements: R7-AC1, R7-AC2, R7-AC6_
  - [x] 4.3 修复 CORS 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Cors/CorsFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R7-AC3, R7-AC4, R7-AC5_
  - [x] 4.4 Checkpoint: 运行全量测试，确认 CORS 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(cors): behavior audit + scenario tests for release 3.3.0`


- [x] 5. Error Handling 模块：行为审计 + 场景测试 + 修复
  - [x] 5.1 编写 Error Handling 场景测试（`tests/ErrorHandlers/ErrorHandlerScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testCustomErrorHandler`：`error_handlers` 配置 → handler 接收异常 → 返回自定义 Response
    - `testErrorHandlerChainOrdering`：多个 error handler → 按注册顺序调用，第一个返回 Response 的短路
    - `testErrorHandlerPassthrough`：handler 返回 null → 异常传递到下一个 handler 或默认处理
    - `testHttpExceptionStatusCodePreservation`：抛 `HttpException(403)` → response status = 403
    - `testFallbackViewHandlerErrorRendering`：无自定义 error handler → `FallbackViewHandler` 产出 response
    - _Requirements: R10-AC1, R10-AC2, R10-AC3, R10-AC4, R10-AC5_
  - [x] 5.2 执行 Error Handling 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 Error Handling 相关代码暴露的 API_Surface（`SilexKernel::error()` 覆写、error handler chain、exception-to-response conversion、`ExceptionListenerWrapper`、chain short-circuit/passthrough、HTTP exception status code preservation、`FallbackViewHandler`）
    - **接口存在性审计**：逐项对比 v3.x Error_Handler_Chain 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（exception listener 注册方式、handler 调用顺序、null 返回值处理、HttpException status code 传递路径等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/error-handling-audit-matrix.md`
    - _Requirements: R9-AC1, R9-AC2, R9-AC6_
  - [x] 5.3 修复 Error Handling 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/ErrorHandlers/ErrorHandlerFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R9-AC3, R9-AC4, R9-AC5_
  - [x] 5.4 Checkpoint: 运行全量测试，确认 Error Handling 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(error-handling): behavior audit + scenario tests for release 3.3.0`

- [ ] 6. Twig 模块：行为审计 + 场景测试 + 修复
  - [ ] 6.1 编写 Twig 场景测试（`tests/Twig/TwigScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testTwigTemplateRendering`：twig config + template path → boot → controller 渲染模板 → response body 包含渲染内容
    - `testTwigAbsence`：无 `twig` key → `getTwig()` 返回 null
    - `testTwigStrictVariablesMode`：`strict_variables = true` → 引用未定义变量抛异常
    - `testTwigAutoReloadBehavior`：`auto_reload` 配置 → Twig environment 反映配置值
    - 低风险模块：优先引用现有测试（`@see` 注释），仅补充现有测试未覆盖的场景视角
    - _Requirements: R12-AC1, R12-AC2, R12-AC3, R12-AC4_
  - [ ] 6.2 执行 Twig 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 Twig 相关代码暴露的 API_Surface（`SimpleTwigServiceProvider` 注册、`TwigEnvironment` initialization、template path、strict variables、auto-reload、`getTwig()` access、Twig absence handling）
    - **接口存在性审计**：逐项对比 v3.x `SimpleTwigServiceProvider` 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（Twig environment 配置项传递、global 变量注册、template loader 配置等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/twig-audit-matrix.md`
    - _Requirements: R11-AC1, R11-AC2, R11-AC6_
  - [ ] 6.3 修复 Twig 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Twig/TwigFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R11-AC3, R11-AC4, R11-AC5_
  - [ ] 6.4 Checkpoint: 运行全量测试，确认 Twig 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(twig): behavior audit + scenario tests for release 3.3.0`

- [ ] 7. Cookie 模块：行为审计 + 场景测试 + 修复
  - [ ] 7.1 编写 Cookie 场景测试（`tests/Cookie/CookieScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testCookieWriting`：controller 添加 cookie → response `Set-Cookie` header 包含 cookie
    - `testResponseCookieContainerInjection`：`SimpleCookieProvider` 配置 → `ResponseCookieContainer` 可作为 controller 参数
    - `testMultipleCookies`：controller 添加多个 cookie → 所有 cookie 出现在 response headers
    - 低风险模块：优先引用现有测试（`@see` 注释），仅补充现有测试未覆盖的场景视角
    - _Requirements: R14-AC1, R14-AC2, R14-AC3_
  - [ ] 7.2 执行 Cookie 模块行为审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现作为审计基准
    - 枚举 v2.5.0 Cookie 相关代码暴露的 API_Surface（`SimpleCookieProvider` 注册、`ResponseCookieContainer` injection、cookie writing on `KernelEvents::RESPONSE`、cookie container lifecycle）
    - **接口存在性审计**：逐项对比 v3.x `SimpleCookieProvider` 实现，分类为 covered / missing-non-breaking / missing-breaking
    - **行为等价性审计**：对比 v2.5.0 和 v3.x 的运行时行为差异（event listener 注册方式、cookie 写入时机、container 生命周期管理等）
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/cookie-audit-matrix.md`
    - _Requirements: R13-AC1, R13-AC2, R13-AC6_
  - [ ] 7.3 修复 Cookie 模块缺失能力（如有）+ 回归测试
    - 如审计发现 missing-non-breaking 能力 → 修复代码恢复到 Silex 等价行为
    - 如有修复 → 编写 `tests/Cookie/CookieFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R13-AC3, R13-AC4, R13-AC5_
  - [ ] 7.4 Checkpoint: 运行全量测试，确认 Cookie 场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(cookie): behavior audit + scenario tests for release 3.3.0`

- [ ] 8. MicroKernel 聚合层：汇总审计 + 场景测试
  - [ ] 8.1 编写 MicroKernel 聚合层场景测试（`tests/Integration/MicroKernelAggregationScenarioTest.php`）
    - 继承 `ScenarioTestCase`
    - `testFullPipelineTraversal`：routing + security + CORS + middleware + view handler + error handler → 完整 pipeline 产出预期 response
    - `testMinimalConfiguration`：仅 `routing` config → 基本 request-response 正常
    - `testNoOptionalModules`：无 security/cors/twig → kernel 正常运行
    - `testAddControllerInjectedArg`：注册自定义对象 → controller 可获取该对象
    - `testAddExtraParameters`：添加 extra parameters → `getParameter()` 返回添加的值
    - `testSlowRequestDetection`：controller 超过慢请求阈值 → 慢请求日志行为
    - _Requirements: R16-AC1, R16-AC2, R16-AC3, R16-AC4, R16-AC5, R16-AC6_
  - [ ] 8.2 执行 MicroKernel 聚合层汇总审计
    - 基于 `oasis/http` v2.5.0（tag `v2.5.0`）的 `SilexKernel` 作为审计基准
    - 枚举 v2.5.0 `SilexKernel` public API methods，逐项对比 v3.x `MicroKernel` 对应方法的行为等价性
    - 验证请求处理 pipeline 顺序：ELB/CloudFront trusted proxy → Routing (32) → CORS preflight (20) → Firewall (8) → Access rule (7) → user before middleware → controller → View Handler chain → after middleware → Response
    - 验证 Bootstrap_Config key 完整性：所有 documented keys 均被处理且行为匹配文档
    - **行为等价性审计**：对比 v2.5.0 `SilexKernel` 和 v3.x `MicroKernel` 的 `run()`、`handle()`、`boot()` 等核心方法的运行时行为差异
    - 检查跨模块交互问题：Security + CORS interaction、Security + Middleware ordering、Error Handler + View Handler precedence
    - 如发现模块审计遗漏的 gap → 按处置策略分类处置
    - 产出 Audit_Matrix 到 `.kiro/specs/release-3.3.0/microkernel-aggregation-audit-matrix.md`
    - _Requirements: R15-AC1, R15-AC2, R15-AC3, R15-AC4, R15-AC5, R15-AC6_
  - [ ] 8.3 修复聚合层缺失能力（如有）+ 回归测试
    - 如汇总审计发现 missing-non-breaking 能力 → 修复代码
    - 如有修复 → 编写 `tests/Integration/MicroKernelAggregationFixRegressionTest.php` 专项回归测试
    - 如审计发现 missing-breaking / intentionally-removed → 文档化或确认已标注
    - 如审计未发现需修复的能力 → 跳过本 sub-task
    - _Requirements: R15-AC5_
  - [ ] 8.4 Checkpoint: 运行全量测试，确认聚合层场景测试全部通过、回归测试全部通过（如有）、现有测试无回归。Commit message: `test(microkernel): aggregation audit + scenario tests for release 3.3.0`


- [ ] 9. 文档更新 + Audit_Matrix 归档
  - [ ] 9.1 更新 Migration_Guide（`docs/manual/migration-v3.md`）
    - 如审计发现未文档化的 breaking change → 补充 severity marker + before/after 代码示例 + 迁移说明
    - 已确认需补充的条目：
      - 🔴 `FirewallInterface::isStateless()` 移除 + `SimpleFirewall` 不再接受 `stateless` 配置项（v3.x 为 stateless-only 架构）
      - 🔴 `AccessRuleInterface::getRequiredChannel()` 的 channel enforcement 行为变更（v3.x 实现为 301 redirect，v2.5.0 由 Silex `ChannelListener` 实现）
    - 对照全部 Audit_Matrix 结果做完整性 review
    - 遵循 writing conventions：中文行文 + 英文术语 + backtick 包裹代码引用 + 表格格式
    - _Requirements: R17-AC1, R17-AC3, R17-AC4_
  - [ ] 9.2 更新架构文档（`docs/state/architecture.md`）
    - 如审计发现架构文档描述不准确之处 → 修正
    - 如无不准确之处 → 跳过本 sub-task
    - _Requirements: R17-AC2_
  - [ ] 9.3 移动 Audit_Matrix 文件到 `docs/changes/3.3/`
    - 将 `.kiro/specs/release-3.3.0/*-audit-matrix.md` 移动到 `docs/changes/3.3/audit/`
    - 验证移动后文件完整
    - _Requirements: Design CR Q3=B_
  - [ ] 9.4 Checkpoint: 运行全量测试确认文档变更未影响测试结果，验证 `docs/changes/3.3/audit/` 下包含所有 Audit_Matrix 文件。Commit message: `docs: documentation update + audit matrix archival for release 3.3.0`

- [ ] 10. 手工测试
  - [ ] 10.1 Increment alpha tag：查询已有 alpha tag（`git tag -l 'v3.3.0-alpha*'`），取最大序号 +1，打新 tag（如 `git tag v3.3.0-alpha1`）
  - [ ] 10.2 全量测试验证
    - 执行 `php vendor/bin/phpunit`，确认所有测试通过（含新增的场景测试和回归测试）
    - 记录最终测试统计（tests / assertions / failures / errors）
    - _Requirements: 全量回归验证_
  - [ ] 10.3 静态分析验证
    - 执行 `php vendor/bin/phpstan analyse`，确认 PHPStan level 8 零错误
    - _Requirements: 代码质量验证_
  - [ ] 10.4 场景测试覆盖完整性确认
    - 确认 8 个 `*ScenarioTest.php` 文件均存在且包含预期的测试方法
    - 确认 `ScenarioTestCase` 基类存在且被所有场景测试继承
    - 确认 Audit_Matrix 文件已归档到 `docs/changes/3.3/audit/`
    - _Requirements: R1-R16 覆盖验证_
  - [ ] 10.5 Checkpoint: alpha tag 已打，全量测试通过，静态分析通过，场景测试覆盖完整。Commit message: `release: v3.3.0 manual testing passed`

- [ ] 11. Code Review
  - 委托给 code-reviewer sub-agent 执行。Review 范围为 `release/3.3.0` 分支上 Task 1–10 的所有变更。

## Issues

（stabilize 阶段新发现的 issue 记录于此，初始为空）

无

## Notes

- 执行时须遵循 `spec-execution.md` 规范
- Commit 随各 top-level task 的 checkpoint 一起执行，以 top-level task 为 commit 粒度
- 本 plan 不包含 release spec 自身（`.kiro/specs/release-3.3.0/`）的归档，该操作由 gitflow-finisher 在 finish 阶段执行
- **审计基准与方法**：审计基准为 `oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 的整体实现——v2.5.0 继承了 Silex，下游用户实际享受的是两者叠加的能力。审计包含两个维度：(1) 接口存在性——v2.5.0 + Silex 暴露的 API 在 v3.x 是否存在；(2) 行为等价性——v3.x 重写的实现是否产出与 v2.5.0 + Silex 一样的运行时行为
- **Test-First 编排**：每个模块 task 中，场景测试编写（RED）排在审计和修复之前。场景测试先写出来作为行为基准，审计发现的修复需要通过场景测试验证（GREEN）
- **条件性 sub-task**：修复类 sub-task（1.4, 2.3, 3.3, 4.3, 5.3, 6.3, 7.3, 8.3）的执行取决于审计结果。如审计未发现 missing-non-breaking 能力，该 sub-task 标记为"无需修复"并跳过
- **Audit_Matrix 产出位置**：执行过程中产出到 `.kiro/specs/release-3.3.0/`，Task 9.3 统一移动到 `docs/changes/3.3/audit/`（Design CR Q3=B）
- **重叠处理策略**：高风险模块（Security、Routing）场景测试允许与现有测试重复以确保覆盖；低风险模块（Twig、Cookie）优先引用现有测试，仅补充未覆盖的场景视角（Design CR Q4=B+C）
- **ScenarioTestCase 基类**：在 Task 1.1 中实现，后续所有模块 task 复用（Design CR Q2=B）
- Design CR Q1=A：每个模块一个 task（审计 + 场景测试 + 修复 + 回归测试合并），共 8 个模块 task + 1 个文档 task
- Design CR Q2=B：ScenarioTestCase 基类在第一个模块 task（Security）中一并实现
- Design CR Q3=B：Audit_Matrix 先在 spec 目录下产出，全部完成后移动到 `docs/changes/3.3/`
- Design CR Q4=B+C：高风险模块允许重复，低风险模块优先引用现有测试
- Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 为顺序依赖（每个模块 task 依赖前一个模块的 checkpoint 通过）
- 辅助资源（`scenario.routes.yml`、测试用 controller/middleware/authenticator）在对应模块 task 中按需创建

## Socratic Review

**Q: tasks 是否完整覆盖了 requirements 中的所有 17 条 Requirement？**
A: 覆盖完整。R1（Security 审计）→ Task 1.3/1.4；R2（Security 测试）→ Task 1.2；R3（Routing 审计）→ Task 2.2/2.3；R4（Routing 测试）→ Task 2.1；R5（Middleware 审计）→ Task 3.2/3.3；R6（Middleware 测试）→ Task 3.1；R7（CORS 审计）→ Task 4.2/4.3；R8（CORS 测试）→ Task 4.1；R9（Error Handling 审计）→ Task 5.2/5.3；R10（Error Handling 测试）→ Task 5.1；R11（Twig 审计）→ Task 6.2/6.3；R12（Twig 测试）→ Task 6.1；R13（Cookie 审计）→ Task 7.2/7.3；R14（Cookie 测试）→ Task 7.1；R15（MicroKernel 聚合层审计）→ Task 8.2/8.3；R16（MicroKernel 聚合层测试）→ Task 8.1；R17（文档更新）→ Task 9.1/9.2。

**Q: Test-First（RED → GREEN）编排是否正确？**
A: 正确。每个模块 task 中，场景测试编写（sub-task X.1 或 X.2）排在审计（sub-task X.2 或 X.3）和修复（sub-task X.3 或 X.4）之前。场景测试先写出来建立行为基准（RED），审计发现的修复使测试通过（GREEN）。

**Q: 每个 top-level task 的最后一个 sub-task 是否为 checkpoint？**
A: 是。Task 1.5、2.4、3.4、4.4、5.4、6.4、7.4、8.4、9.4、10.5 均为 checkpoint，包含验证步骤和 commit message。Task 11（Code Review）委托给 code-reviewer sub-agent，无需 checkpoint。

**Q: Design CR 的四项决策是否都在 tasks 中体现？**
A: 均已体现。Q1=A（每个模块一个 task）→ 8 个模块 task + 1 个文档 task 结构；Q2=B（ScenarioTestCase 在 Security task 中实现）→ Task 1.1；Q3=B（Audit_Matrix 先在 spec 目录产出）→ 各模块 task 产出到 `.kiro/specs/release-3.3.0/`，Task 9.3 移动；Q4=B+C（重叠处理策略）→ Task 6.1/7.1 中标注"低风险模块优先引用现有测试"。

**Q: 手工测试类 top-level task 的第一个 sub-task 是否为 "Increment alpha tag"？**
A: 是。Task 10.1 为 "Increment alpha tag"，遵循 spec-planning 中 release top-level task 额外规则。

**Q: 是否存在 optional task？**
A: 不存在。所有 task 和 sub-task 均为 mandatory，使用 `- [ ]` 语法，无 `*` 后缀。条件性 sub-task（修复类）通过"如审计未发现需修复的能力 → 跳过本 sub-task"的描述处理，而非标记为 optional。

**Q: top-level task 结构是否符合 spec-planning 规则？**
A: 符合。Task 1-9 为实现 task（含 test-first 编排），Task 10 为手工测试 task，Task 11 为 Code Review task。符合 "1~N 实现 task → N+1 手工测试 task → 最后 Code Review task" 的结构。

**Q: 场景测试辅助资源（routes.yml、test controller 等）在哪里创建？**
A: 在对应模块 task 的场景测试编写 sub-task 中按需创建。不单独拆为 task，因为辅助资源是场景测试的一部分。


## Gatekeep Log

**校验时间**: 2025-07-16
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Issues` section——release spec 必须包含 stabilize 阶段 issue 记录区（初始为空），参照 release-2.5.0 tasks.md 的结构
- [内容] Task 8.3（聚合层修复）补充 missing-breaking / intentionally-removed 处置路径——原文仅描述了 missing-non-breaking → 修复，缺少另外两种处置路径，与其他模块修复 sub-task（1.4, 2.3, 3.3, 4.3, 5.3, 6.3, 7.3）的描述不一致

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1-R17 编号、Design CR Q1-Q4 引用、模块名称均与 requirements.md / design.md 一致）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] `## Issues` section 存在（release spec 必须，初始为空）
- [x] Release spec 手工测试类 top-level task（Task 10）的第一个 sub-task（10.1）为 "Increment alpha tag"
- [x] 最后一个 top-level task（Task 11）为 Code Review
- [x] 自动化实现 task（Task 1-9）排在手工测试（Task 10）和 Code Review（Task 11）之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1-11），连续无跳号
- [x] sub-task 有层级序号（1.1-1.5, 2.1-2.4, ...），连续无跳号
- [x] 实现类 sub-task 引用了具体的 requirements 条款（`_Requirements: RX-ACY_` 格式）
- [x] requirements.md 中的 17 条 requirement 均被至少一个 task 引用，无遗漏
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在，无悬空引用
- [x] top-level task 按依赖关系排序（Task 1→2→...→11 顺序依赖）
- [x] 无循环依赖
- [x] 已对核心模块执行 graphify 依赖查询（MicroKernel、SimpleSecurityProvider、CacheableRouterProvider、CrossOriginResourceSharingProvider）
- [x] task 排序与 graphify 揭示的模块依赖一致——MicroKernel 为 god node（community 1），各模块 provider 分属独立 community，聚合层 task（Task 8）排在所有模块 task 之后，符合依赖方向
- [x] 无遗漏的隐含跨模块依赖
- [x] checkpoint 不作为独立 top-level task，而是每个 top-level task 的最后一个 sub-task
- [x] 每个 top-level task 的最后一个 sub-task 为 checkpoint（1.5, 2.4, 3.4, 4.4, 5.4, 6.4, 7.4, 8.4, 9.4, 10.5）
- [x] checkpoint 包含具体验证命令和 commit message
- [x] Test-first 编排正确：每个模块 task 中场景测试编写排在审计和修复之前
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task（Task 10）存在
- [x] 手工测试覆盖关键场景（全量测试、静态分析、场景测试覆盖完整性）
- [x] 手工测试场景描述具体可执行
- [x] Code Review（Task 11）为最后一个 top-level task
- [x] Code Review 描述为"委托给 code-reviewer sub-agent 执行"
- [x] Code Review task 未展开 review checklist
- [x] `## Notes` section 存在
- [x] Notes 明确提到执行时须遵循 `spec-execution.md`
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（test-first、条件性 sub-task、Audit_Matrix 产出位置、重叠处理策略、ScenarioTestCase 基类、CR 决策引用、顺序依赖、辅助资源）
- [x] `## Socratic Review` section 存在且覆盖充分（8 个 Q&A）
- [x] Design CR Q1=A 体现：8 个模块 task + 1 个文档 task
- [x] Design CR Q2=B 体现：ScenarioTestCase 在 Task 1.1 中实现
- [x] Design CR Q3=B 体现：Audit_Matrix 先在 spec 目录产出，Task 9.3 移动到 `docs/changes/3.3/`
- [x] Design CR Q4=B+C 体现：Task 6.1/7.1 标注低风险模块优先引用现有测试
- [x] Design 全覆盖：所有模块、接口、实现项均有对应 task
- [x] 可独立执行：每个 sub-task 描述自包含，配合 Ref 指向的 requirement 和 design section 可完成实现
- [x] 验收闭环完整：checkpoint（每个 task）+ 手工测试（Task 10）+ Code Review（Task 11）
- [x] 执行路径无歧义：Task 1→2→...→11 顺序依赖，Notes 中明确说明
