# Requirements Document

> PHP 8.5 升级前测试基线补全 — `.kiro/specs/php85-test-baseline/`

---

## Introduction

`oasis/http` 计划从 PHP >=7.0 升级到 PHP >=8.5。后续 Phase 1–4 将引入 Silex → Symfony 框架替换、Twig/Guzzle 大版本升级、Security 组件重写等大量 breaking change。当前测试覆盖存在明显缺口，多个核心模块完全无测试。

本 spec 的目标是：在框架替换前，为所有缺少测试的模块补充单元测试和集成测试，建立完整的 Behavior_Baseline。补全的测试将作为行为 SSOT，确保后续迁移不引入功能退化。测试补全必须在框架替换（Phase 1 / PRP-003）之前完成——替换后旧 API 不再可用，届时无法再针对旧行为编写测试。

基于知识图谱分析（`graphify-out/GRAPH_REPORT.md`），Error_Handlers 模块中的 `WrappedExceptionInfo`（God_Node，13 edges）、Configuration 模块中的 `processConfiguration()`（Cross_Community_Bridge，betweenness 0.078）等关键节点完全无测试覆盖。

**不涉及的内容**：

- 依赖升级（PHPUnit、Symfony 组件等升级在 PRP-002 中处理）
- Silex 框架替换
- PHP 语言层面 breaking changes 修复
- 现有业务逻辑修改——仅补充测试

**约束**：

- C-1: 使用当前 PHPUnit 5.x，不升级测试框架（升级在 PRP-002）
- C-2: 不修改现有业务逻辑——仅补充测试
- C-3: 测试记录当前系统的实际行为（Behavior_Baseline），不是期望行为
- C-4: 所有测试在当前 PHP 版本 + PHPUnit 5.x 下通过
- C-5: Test_Suite 按模块目录一一对应，每个 `src/` 子目录对应一个 Test_Suite
- C-6: Integration_Test 集中放在 `ut/Integration/` 目录，按链路命名
- C-7: 对已有测试类，系统性分析被测代码的所有分支，全面补充所有未覆盖场景

---

## Glossary

- **Behavior_Baseline**: 在代码变更前，通过测试记录系统当前的实际行为。后续迁移中，这些测试作为行为 SSOT，确保功能不退化
- **God_Node**: 知识图谱中边数最多的核心节点，变更影响面最大，测试优先级最高
- **Cross_Community_Bridge**: 知识图谱中连接不同社区的桥梁节点（betweenness 高），跨模块交互的关键路径
- **Test_Suite**: phpunit.xml 中定义的测试分组，按模块目录一一对应
- **Test_Double**: 用于替代真实依赖的测试替身（mock、stub、concrete subclass 等）
- **Integration_Test**: 跨模块链路测试，验证模块间交互的正确性
- **Error_Handlers**: `src/ErrorHandlers/` 模块，包含 `WrappedExceptionInfo`、`ExceptionWrapper`、`JsonErrorHandler`
- **Configuration**: `src/Configuration/` 模块，包含 8 个配置类和 `ConfigurationValidationTrait`
- **Views**: `src/Views/` 模块，包含视图处理器和响应渲染器
- **Routing**: `src/ServiceProviders/Routing/` 模块，包含可缓存路由、URL 匹配器和生成器
- **Cookie**: `src/ServiceProviders/Cookie/` 模块，包含 Response Cookie 管理
- **Middlewares**: `src/Middlewares/` 模块，包含 Before/After 中间件抽象
- **Security**: `src/ServiceProviders/Security/` 模块，包含 Firewall、Policy、AccessRule
- **Bootstrap_Configuration**: SilexKernel 构造时通过配置数组驱动各 ServiceProvider 注册的初始化链路
- **Security_Authentication_Flow**: Policy → Firewall → AccessRule 的完整认证授权链路

---

## Requirements

### Requirement 1: P0 — Error_Handlers 单元测试

**User Story:** 作为迁移开发者，我希望 Error_Handlers 模块具备完整的测试覆盖，以便在框架替换后验证异常处理行为未退化。

#### Acceptance Criteria

1. THE Error_Handlers Test_Suite SHALL include tests for `WrappedExceptionInfo` covering:
   - construction with normal HTTP status code, and auto-conversion of status code 0 to 500
   - `toArray()` in normal mode returning `code`, `exception` (with `type`/`message`/`file`/`line`), and `extra`
   - `toArray()` in rich mode (`$rich = true`) additionally including `trace`
   - `jsonSerialize()` returning the same result as `toArray()`
   - `getAttribute()` / `setAttribute()`: returning set value, or null when unset
   - `getAttributes()` returning the full attributes array
   - `getCode()` / `setCode()`: code modification
   - `getOriginalCode()`: always returning the original construction-time value even after code modification
   - `getShortExceptionType()`: returning the short class name of the exception
   - `serializeException()` with nested previous exception chain: recursive serialization of `previous`
   - `serializeException()` omitting `code` field when exception code is 0, including it when non-zero

2. THE Error_Handlers Test_Suite SHALL include tests for `ExceptionWrapper` covering:
   - `__invoke()` basic wrapping: returning a `WrappedExceptionInfo` instance
   - WHEN invoked with `ExistenceViolationException` THEN code SHALL be set to 404 and `key` attribute SHALL be set
   - WHEN invoked with `DataValidationException` THEN code SHALL be set to 400 and `key` attribute SHALL be set
   - WHEN invoked with a plain `\Exception` THEN code SHALL remain the original httpStatusCode

3. THE Error_Handlers Test_Suite SHALL include tests for `JsonErrorHandler` covering:
   - `__invoke()` returning an array containing `code`, `type`, `message`, `file`, `line`
   - `type` being the full class name of the exception
   - correct pass-through of different code values

### Requirement 2: P0 — Configuration 单元测试

**User Story:** 作为迁移开发者，我希望所有 Configuration 类具备测试覆盖，以便在 Symfony Config 组件升级后验证配置校验行为未退化。

#### Acceptance Criteria

1. THE Configuration Test_Suite SHALL include tests for `HttpConfiguration` covering:
   - default values: `cache_dir` defaults to null, `behind_elb` defaults to false, `trust_cloudfront_ips` defaults to false
   - all variable nodes (`routing`, `twig`, `security`, `cors`, `view_handlers`, `error_handlers`, `injected_args`, `middlewares`, `providers`, `trusted_proxies`, `trusted_header_set`) accepting arbitrary values
   - WHEN an unknown key is provided THEN `InvalidConfigurationException` SHALL be thrown

2. THE Configuration Test_Suite SHALL include tests for `SecurityConfiguration` covering:
   - `policies`, `firewalls`, `access_rules` as array prototypes
   - `role_hierarchy` beforeNormalization: string values auto-converted to single-element arrays
   - empty configuration passing validation

3. THE Configuration Test_Suite SHALL include tests for `CrossOriginResourceSharingConfiguration` covering:
   - `pattern` as required field; WHEN missing THEN an exception SHALL be thrown
   - `origins` beforeNormalization: string values auto-converted to single-element arrays
   - `max_age` defaulting to 86400
   - `credentials_allowed` defaulting to false
   - `headers` and `headers_exposed` as optional variable nodes

4. THE Configuration Test_Suite SHALL include tests for `TwigConfiguration` covering:
   - `template_dir` as optional scalar
   - `cache_dir` defaulting to null
   - `asset_base` defaulting to empty string
   - `globals` defaulting to empty array

5. THE Configuration Test_Suite SHALL include tests for `CacheableRouterConfiguration` covering:
   - `path` as optional scalar
   - `cache_dir` defaulting to null
   - `namespaces` beforeNormalization: string values auto-converted to single-element arrays

6. THE Configuration Test_Suite SHALL include tests for `SimpleAccessRuleConfiguration` covering:
   - `pattern` and `roles` as required fields
   - `roles` beforeNormalization: string values auto-converted to single-element arrays
   - `channel` as enum (null / `http` / `https`), defaulting to null

7. THE Configuration Test_Suite SHALL include tests for `SimpleFirewallConfiguration` covering:
   - `pattern`, `policies`, `users` as required fields
   - `stateless` defaulting to false
   - `misc` defaulting to empty array

8. THE Configuration Test_Suite SHALL include tests for `ConfigurationValidationTrait` covering:
   - `processConfiguration()` returning an `ArrayDataProvider` instance
   - WHEN valid configuration is provided THEN processing SHALL succeed
   - WHEN invalid configuration is provided THEN a Symfony Config exception SHALL be thrown

### Requirement 3: P1 — Views 单元测试

**User Story:** 作为迁移开发者，我希望 Views 模块具备测试覆盖，以便在视图处理链变更后验证渲染行为未退化。

#### Acceptance Criteria

1. THE Views Test_Suite SHALL include tests for `AbstractSmartViewHandler` (via concrete Test_Double) covering:
   - `shouldHandle()` returning true when Accept header contains a compatible type
   - `shouldHandle()` returning true when Accept is `*/*`
   - `shouldHandle()` returning true when Accept is empty (defaults to `*/*`)
   - `shouldHandle()` returning false when Accept does not contain a compatible type
   - `shouldHandle()` supporting wildcard matching (e.g. `application/*`)

2. THE Views Test_Suite SHALL include tests for `JsonViewHandler` covering:
   - `__invoke()` returning `JsonResponse` when Accept is JSON-compatible
   - `__invoke()` returning null when Accept is not compatible
   - `wrapResult()` wrapping scalar/null values as `["result" => $value]`
   - `wrapResult()` returning array values directly
   - `getCompatibleTypes()` returning `application/json` and `text/json`

3. THE Views Test_Suite SHALL include tests for `DefaultHtmlRenderer` covering:
   - `renderOnSuccess()` handling `__toString` objects, booleans, scalars, arrays, and unsupported types
   - `renderOnException()` falling back to JSON serialization when Twig is unavailable
   - `renderOnException()` using template rendering when Twig is available
   - `renderOnException()` falling back when Twig template does not exist

4. THE Views Test_Suite SHALL include tests for `JsonApiRenderer` covering:
   - `renderOnSuccess()` returning `JsonResponse` directly for array input
   - `renderOnSuccess()` wrapping non-array input as `["result" => $value]`
   - `renderOnException()` returning `JsonResponse` with status code from exception code

5. THE Views Test_Suite SHALL include tests for `PrefilightResponse` covering:
   - construction resulting in status code 204 and `X-Status-Code` header present
   - `addAllowedMethod()` / `getAllowedMethods()` correctly managing the method list
   - `freeze()` / `isFrozen()` state management

6. THE Views Test_Suite SHALL include tests for `RouteBasedResponseRendererResolver` covering:
   - `resolveRequest()` returning `DefaultHtmlRenderer` for `html`/`page` format
   - `resolveRequest()` returning `JsonApiRenderer` for `api`/`json` format
   - WHEN format is unknown THEN `resolveRequest()` SHALL throw `InvalidConfigurationException`
   - format resolution priority: `format` attribute first, fallback to `_format`, default `html`

### Requirement 4: P1 — Routing 单元测试

**User Story:** 作为迁移开发者，我希望 Routing 模块具备测试覆盖，以便在路由系统变更后验证路由解析行为未退化。

#### Acceptance Criteria

1. THE Routing Test_Suite SHALL include tests for `GroupUrlMatcher` covering:
   - `match()` returning immediately when the first matcher succeeds
   - WHEN the first matcher throws `ResourceNotFoundException` THEN `match()` SHALL try the next matcher
   - WHEN all matchers fail THEN `match()` SHALL throw `ResourceNotFoundException`
   - `matchRequest()` delegating to `match()`
   - `setContext()` / `getContext()` correctly managing context

2. THE Routing Test_Suite SHALL include tests for `GroupUrlGenerator` covering:
   - `generate()` returning immediately when the first generator succeeds
   - WHEN the first generator throws `RouteNotFoundException` THEN `generate()` SHALL try the next generator
   - WHEN all generators fail THEN `generate()` SHALL throw `RouteNotFoundException`
   - `generate()` passing context to sub-generators
   - `setContext()` / `getContext()` correctly managing context

3. THE Routing Test_Suite SHALL include tests for `CacheableRouterUrlMatcherWrapper` covering:
   - `match()` delegating to the inner matcher and returning its result
   - WHEN `_controller` contains `::` and the class does not exist THEN `match()` SHALL attempt namespace prefix
   - WHEN the class already exists THEN `match()` SHALL not modify `_controller`
   - `setContext()` / `getContext()` delegating to the inner matcher

4. THE Routing Test_Suite SHALL include tests for `InheritableRouteCollection` covering:
   - construction copying all routes from the wrapped `RouteCollection`
   - `addDefaults()` adding default values for routes without specified defaults
   - `addDefaults()` not overwriting existing default values

5. THE Routing Test_Suite SHALL include tests for `InheritableYamlFileLoader` covering:
   - `import()` returning an `InheritableRouteCollection` instance

6. THE Routing Test_Suite SHALL include tests for `CacheableRouter` covering:
   - `getRouteCollection()` replacing `%param%` placeholders in route defaults
   - WHEN a parameter does not exist THEN the original placeholder SHALL be preserved
   - `getRouteCollection()` escaping `%%` as `%`
   - `getRouteCollection()` replacing only once (via `isParamReplaced` flag)

7. THE Routing Test_Suite SHALL include tests for `CacheableRouterProvider` covering:
   - `register()` correctly registering `request_matcher`, `url_generator`, `router` and related services
   - WHEN `getConfigDataProvider()` is called before `register()` THEN a `LogicException` SHALL be thrown

### Requirement 5: P1 — Cookie 单元测试

**User Story:** 作为迁移开发者，我希望 Cookie 模块具备测试覆盖，以便在 Cookie 处理变更后验证行为未退化。

#### Acceptance Criteria

1. THE Cookie Test_Suite SHALL include tests for `ResponseCookieContainer` covering:
   - `addCookie()` making the cookie retrievable via `getCookies()`
   - multiple `addCookie()` calls accumulating all cookies
   - initial state of `getCookies()` returning an empty array

2. THE Cookie Test_Suite SHALL include tests for `SimpleCookieProvider` covering:
   - WHEN `boot()` receives a non-`SilexKernel` instance THEN a `LogicException` SHALL be thrown
   - `boot()` registering an after middleware that writes cookies to response headers

### Requirement 6: P1 — Middlewares 单元测试

**User Story:** 作为迁移开发者，我希望 Middlewares 抽象层具备测试覆盖，以便在中间件机制变更后验证行为未退化。

#### Acceptance Criteria

1. THE Middlewares Test_Suite SHALL include tests for `AbstractMiddleware` (via concrete Test_Double) covering:
   - `onlyForMasterRequest()` defaulting to true
   - `getAfterPriority()` defaulting to `Application::LATE_EVENT`
   - `getBeforePriority()` defaulting to `Application::EARLY_EVENT`

### Requirement 7: P1 — NullEntryPoint 单元测试

**User Story:** 作为迁移开发者，我希望 Security 模块的 `NullEntryPoint` 具备测试覆盖，以便在安全组件变更后验证入口点行为未退化。

#### Acceptance Criteria

1. THE Security Test_Suite SHALL include tests for `NullEntryPoint` covering:
   - WHEN `start()` is called with an `AuthenticationException` THEN an `AccessDeniedHttpException` SHALL be thrown with the exception's message
   - WHEN `start()` is called without an exception THEN an `AccessDeniedHttpException` SHALL be thrown with message `'Access Denied'`

### Requirement 8: P2 — 独立模块单元测试

**User Story:** 作为迁移开发者，我希望独立模块具备测试覆盖，以便在依赖升级后验证这些工具类行为未退化。

#### Acceptance Criteria

1. THE Misc Test_Suite SHALL include tests for `ExtendedArgumentValueResolver` covering:
   - WHEN a non-object argument is passed to the constructor THEN an `InvalidArgumentException` SHALL be thrown
   - `supports()` returning true when argument type exactly matches a registered class
   - `supports()` returning true when argument type is a parent class/interface of a registered object (`instanceof` match)
   - `supports()` returning false when argument type is a non-existent class
   - `supports()` returning false when argument type has no match
   - `resolve()` yielding the corresponding object on exact match
   - `resolve()` yielding the corresponding object on `instanceof` match

2. THE Misc Test_Suite SHALL include tests for `ExtendedExceptionListnerWrapper` covering:
   - WHEN response is null and event has no response THEN `ensureResponse()` SHALL not invoke parent
   - WHEN response is non-null THEN `ensureResponse()` SHALL invoke parent

3. THE Misc Test_Suite SHALL include tests for `ChainedParameterBagDataProvider` covering:
   - WHEN a non-`ParameterBag`/`HeaderBag` object is passed to the constructor THEN an `InvalidArgumentException` SHALL be thrown
   - `getValue()` searching bags in order, with the first bag containing the key taking priority
   - `getValue()` using `get()` for `ParameterBag`
   - `getValue()` using `get($key, null, false)` for `HeaderBag`: single value returns string, multiple values return array, zero values return null
   - WHEN no bag contains the key THEN `getValue()` SHALL return null

4. THE Misc Test_Suite SHALL include tests for `UniquenessViolationHttpException` covering:
   - `getStatusCode()` returning 400 after construction
   - `getMessage()` returning the provided message
   - `getPrevious()` returning the provided previous exception

### Requirement 9: Integration_Test — Bootstrap_Configuration 链路

**User Story:** 作为迁移开发者，我希望 Bootstrap_Configuration → ServiceProvider → Kernel 链路具备 Integration_Test，以便在框架替换后验证配置驱动的初始化流程未退化。

#### Acceptance Criteria

1. WHEN `routing` is configured THEN THE SilexKernel SHALL correctly register `CacheableRouterProvider` and routes SHALL be matchable.
2. WHEN `security` is configured THEN THE SilexKernel SHALL correctly register `SimpleSecurityProvider` and the firewall SHALL be active.
3. WHEN `cors` is configured THEN THE SilexKernel SHALL correctly register `CrossOriginResourceSharingProvider` and CORS headers SHALL be present.
4. WHEN `twig` is configured THEN THE SilexKernel SHALL correctly register `SimpleTwigServiceProvider` and templates SHALL be renderable.
5. WHEN `middlewares` is configured THEN before/after middlewares SHALL execute as expected.

### Requirement 10: Integration_Test — Security_Authentication_Flow

**User Story:** 作为迁移开发者，我希望完整的 Security_Authentication_Flow 具备 Integration_Test，以便在 Security 组件重写后验证认证授权行为未退化。

#### Acceptance Criteria

1. WHEN authentication succeeds and authorization passes THEN THE Security_Authentication_Flow SHALL allow normal access through the Policy → Firewall → AccessRule chain.
2. WHEN authentication fails THEN the token SHALL be null and the request SHALL not be blocked (AccessRule decides).
3. WHEN AccessRule authorization fails THEN a 403 response SHALL be returned.
4. THE Security_Authentication_Flow SHALL correctly apply Role Hierarchy inheritance.

### Requirement 11: Integration_Test — SilexKernel 跨社区集成

**User Story:** 作为迁移开发者，我希望 SilexKernel 与 Cookie、Middlewares、Configuration 交互具备 Integration_Test，以便在核心类变更后验证跨模块交互未退化。

#### Acceptance Criteria

1. WHEN a controller sets a cookie via `SimpleCookieProvider` THEN THE response SHALL contain the corresponding cookie header.
2. THE SilexKernel SHALL execute before/after middlewares according to priority and master-request-only rules.
3. WHEN invalid configuration is provided THEN THE SilexKernel SHALL throw a validation exception; WHEN valid configuration is provided THEN it SHALL be correctly dispatched to the respective providers.

### Requirement 12: 现有测试场景补充

**User Story:** 作为迁移开发者，我希望对已有测试类全面补充未覆盖的分支场景，以便建立更完整的 Behavior_Baseline。

#### Acceptance Criteria

1. THE existing SilexKernel tests SHALL be supplemented to cover:
   - `__set()` handling of `view_handlers`, `error_handlers`, `middlewares`, `providers` and other magic properties
   - `handle()` processing of ELB / CloudFront trusted proxies
   - `boot()` middleware registration logic
   - `getParameter()` / `getToken()` / `getUser()` / `getTwig()` in various states
   - `isGranted()` authorization checks
   - `getCacheDirectories()` return value

2. THE existing CORS tests SHALL be supplemented to cover:
   - matching priority under multi-strategy configuration
   - header behavior when `credentials_allowed` is true
   - effect of `headers_exposed` configuration
   - CORS header handling for non-preflight requests

3. THE existing Security tests SHALL be supplemented to cover:
   - authentication failure paths (invalid token, expired token)
   - AccessRule boundary conditions (pattern mismatch, multi-role matching)
   - Role Hierarchy multi-level inheritance

4. THE existing Twig tests SHALL be supplemented to cover:
   - `globals` configuration variants
   - `asset_base` configuration effect
   - behavior when `cache_dir` is absent

5. THE existing AWS tests SHALL be supplemented to cover:
   - trusted proxy setup when `behind_elb` is true
   - behavior when `trust_cloudfront_ips` is true
   - behavior when both are enabled simultaneously

### Requirement 13: phpunit.xml Test_Suite 注册

**User Story:** 作为开发者，我希望所有新增测试正确注册到 `phpunit.xml`，以便通过 `phpunit` 命令运行全部测试。

#### Acceptance Criteria

1. THE phpunit.xml SHALL define the following new Test_Suites (one per module directory):
   - `error-handlers` — Error_Handlers tests
   - `configuration` — Configuration tests
   - `views` — Views tests
   - `routing` — Routing tests
   - `cookie` — Cookie tests
   - `middlewares` — Middlewares tests
   - `misc` — P2 standalone module tests (`ExtendedArgumentValueResolver`, `ExtendedExceptionListnerWrapper`, `ChainedParameterBagDataProvider`, `UniquenessViolationHttpException`)
   - `integration` — Integration_Tests
2. THE `all` Test_Suite SHALL include all newly added test files.
3. THE existing Test_Suite structure SHALL remain unchanged.

---

## Socratic Review

**Q: 为什么 P0 优先级给了 Error_Handlers 和 Configuration 而不是 SilexKernel？**
A: SilexKernel 已有测试（`SilexKernelTest`、`SilexKernelWebTest`），只需补充场景。Error_Handlers 中的 `WrappedExceptionInfo` 是 God_Node（13 edges）完全无测试，Configuration 中的 `processConfiguration()` 是 Cross_Community_Bridge（betweenness 0.078）完全无测试，风险更高。

**Q: Integration_Test 是否会与现有 WebTest 重复？**
A: 现有 `SilexKernelWebTest` 侧重端到端请求处理，Integration_Test 侧重图谱识别的跨模块链路（Bootstrap_Configuration → Provider → Kernel、Security_Authentication_Flow），关注点不同。部分场景可能有重叠，但 Integration_Test 更聚焦于模块间交互的正确性。

**Q: `InheritableYamlFileLoader` 的 `import()` 方法依赖文件系统，如何测试？**
A: 可以使用真实的 YAML 路由文件（类似现有测试中的 `routes.yml`），验证 `import()` 返回 `InheritableRouteCollection` 实例。这是 Behavior_Baseline 测试，不需要 mock 文件系统。

**Q: `DefaultHtmlRenderer` 依赖 SilexKernel 和 Twig，测试复杂度如何控制？**
A: 使用 mock SilexKernel（控制 `getTwig()` 返回值和 `debug` 属性），分别测试有 Twig / 无 Twig / Twig 模板不存在三种场景。

**Q: Requirement 12 的场景补充范围是否过大？**
A: goal.md 中用户明确选择了"全面补充所有未覆盖分支"（Q3 回答 B）。每个 AC 列出了具体的补充方向，实现时需要先分析被测代码的分支再确定具体 test case。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: Requirement 1–8（单元测试）与 Requirement 9–11（Integration_Test）关注层次不同，不存在矛盾。Requirement 12（场景补充）与 Requirement 9–11 在 Security 和 SilexKernel 方面可能有部分重叠——Requirement 12 补充的是已有测试类的分支覆盖，Requirement 9–11 是新建的跨模块链路测试，测试粒度和关注点不同。Requirement 13 是基础设施需求，与其他 Requirement 无冲突。

**Q: 与 proposal（PRP-001）的 scope 是否一致？**
A: 完全一致。PRP-001 定义的 Goals（P0/P1/P2 单元测试 + 集成测试 + 现有测试补充）和 Non-Goals（不涉及依赖升级、框架替换、业务逻辑修改）均已体现在 Requirements 中。Scope 限定在 `ut/` 目录和 `phpunit.xml`，与 PRP-001 一致。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] 一级标题从 `# Requirements: PHP 8.5 升级前测试基线补全` 修正为 `# Requirements Document`，feature 名称和 spec 路径移至副标题
- [结构] `## Background` + `## Constraints` 合并重组为 `## Introduction`，包含范围说明、不涉及内容（Non-scope）和约束条件
- [结构] 新增 `## Glossary` section，定义 AC 中使用的领域术语（Behavior_Baseline、God_Node、Cross_Community_Bridge、Test_Suite、Test_Double、Integration_Test 及各模块名称）
- [结构] `#### Acceptance Criteria` 标题层级从隐含的列表头修正为显式的四级标题
- [语体] 所有 AC 从描述性列表格式（`- AC N.M: ...`）修正为 `THE <Subject> SHALL ...` / `WHEN ... THEN ... SHALL ...` 规范语体
- [语体] AC 编号从 `AC N.M` 格式修正为每个 Requirement 内连续编号（1, 2, 3...）
- [语体] User Story 从 `我需要...以便...` 统一修正为 `我希望...以便...`
- [内容] Socratic Review 补充了两个新问题：Requirement 间矛盾/重叠检查、与 proposal scope 一致性检查
- [格式] 正文中的术语统一使用 Glossary 中定义的大写下划线形式（如 Error_Handlers、Behavior_Baseline、Integration_Test）

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Glossary 术语在 AC 中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Requirements Document`
- [x] Introduction 存在，描述了 feature 范围和不涉及的内容
- [x] Glossary 存在且非空，术语在 AC 中被实际使用
- [x] Requirements section 存在且包含 13 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] AC 使用 `THE <Subject> SHALL ...` 语体
- [x] AC 编号连续，无跳号
- [x] User Story 使用中文 `作为...我希望...以便...` 格式
- [x] Socratic Review 覆盖充分（优先级、重复性、测试策略、矛盾检查、scope 一致性）
- [x] Goal CR 决策（Q1:C / Q2:B / Q3:B）已体现在 Requirements 中
- [x] 完成标准充分：所有 AC 满足即 feature 完成
- [x] 可 design 性：信息足够开始技术方案设计
- [○] 内容边界：AC 中包含具体类名和方法名，但本 spec 的 Subject 是"为这些类补充测试"，被测对象名称是领域概念而非实现细节，已在 Glossary 中定义

### Clarification Round

**状态**: 已回答

**Q1:** Requirement 12（现有测试场景补充）中列出了补充方向，但具体的 test case 需要在实现时分析源代码分支后确定。Design 阶段是否需要先完成源代码分支分析，将具体 test case 列入 design，还是在 task 执行阶段边分析边编写？
- A) Design 阶段完成分支分析，在 design 中列出每个已有测试类需要补充的具体 test case
- B) Design 阶段只规划测试文件结构和 helper 依赖，具体 test case 在 task 执行阶段由实现者分析源代码后确定
- C) 混合方式：P0 相关的补充（如 SilexKernel 与 ErrorHandlers/Configuration 的交互）在 design 阶段分析，其余在 task 阶段确定
- D) 其他（请说明）

**A:** B — Design 阶段只规划测试文件结构和 helper 依赖，具体 test case 在 task 执行阶段由实现者分析源代码后确定。

**Q2:** Integration_Test（Requirement 9–11）需要启动完整的 SilexKernel 实例。现有 `SilexKernelWebTest` 已有类似的 bootstrap 机制（`app.php` 配置文件 + `WebTestCase`）。Integration_Test 是复用现有的 bootstrap 模式，还是建立独立的 fixture 体系？
- A) 复用现有模式：Integration_Test 使用类似 `app.*.php` 的配置文件 + `WebTestCase` 基类
- B) 独立 fixture：Integration_Test 在测试方法内直接构造 SilexKernel，不依赖外部配置文件
- C) 按链路选择：Bootstrap_Configuration 链路用独立构造（更精确控制配置），Security_Authentication_Flow 用 WebTestCase（需要完整 HTTP 请求）
- D) 其他（请说明）

**A:** C — 按链路选择：Bootstrap_Configuration 链路用独立构造（更精确控制配置），Security_Authentication_Flow 用 WebTestCase（需要完整 HTTP 请求）。

**Q3:** Requirement 1 的 AC 2 中提到 `ExceptionWrapper` 处理 `ExistenceViolationException` 和 `DataValidationException`。这两个异常类不在 `oasis/http` 仓库中（来自外部依赖）。测试中如何处理这些外部异常类？
- A) 直接引用外部异常类（假设 composer 依赖已安装）
- B) 在测试 helper 中创建 stub 异常类，避免对外部包的硬依赖
- C) 使用条件跳过：如果外部异常类不存在则 skip 相关 test case
- D) 其他（请说明）

**A:** A — 直接引用外部异常类（假设 composer 依赖已安装）。
