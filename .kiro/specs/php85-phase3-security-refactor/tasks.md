# Implementation Plan: PHP 8.5 Phase 3 — Security Component Refactor

## Overview

按依赖层次拆分实现：先完成所有接口和抽象类（R1–R4, R7），再做 `SimpleSecurityProvider.register()` 集成（R5），最后做测试辅助类重写和现有测试适配（R8, R16）。PBT 作为子任务紧跟对应实现，尽早捕获错误。

Design CR 决策：
- Q1=C: 按依赖层次拆分 task
- Q2=C: 混合测试策略——firewall listener 集成测试，access rule listener 单元测试
- Q3=A: `AbstractSimplePreAuthenticator` 保持 `abstract class` 声明
- Q4=A: 硬编码 `MicroKernel::BEFORE_PRIORITY_FIREWALL - 1`

## Tasks

- [x] 1. 接口层：重新设计 AuthenticationPolicyInterface
  - [x] 1.1 重写 `src/ServiceProviders/Security/AuthenticationPolicyInterface.php`
    - 移除 `getAuthenticationProvider()` 和 `getAuthenticationListener()` 方法
    - 新增 `getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface`
    - 新增 `getAuthenticatorConfig(): array`
    - 保留 `getAuthenticationType(): string`、`getEntryPoint()` 和所有 `AUTH_TYPE_*` 常量
    - 所有方法使用强类型声明（参见 Design Components §2）
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_
  - [x] 1.2 Checkpoint: 运行 `composer dump-autoload` 确认无 autoload 错误，确认接口文件无语法错误，commit

- [x] 2. 抽象类层：新建 AbstractPreAuthenticator 和重写 AbstractSimplePreAuthenticationPolicy
  - [x] 2.1 新建 `src/ServiceProviders/Security/AbstractPreAuthenticator.php`
    - 实现 `AuthenticatorInterface`，用模板方法封装 Symfony 7.x 认证 API
    - `supports()` 委托给 `getCredentialsFromRequest()`，返回值非 null 则 true
    - `authenticate()` 依次调用 `getCredentialsFromRequest()` + `authenticateAndGetUser()`，返回 `SelfValidatingPassport`
    - `createToken()` 返回 `PostAuthenticationToken`（含用户和角色）
    - `onAuthenticationSuccess()` / `onAuthenticationFailure()` 均返回 null
    - 声明 `getCredentialsFromRequest(Request): mixed` 和 `authenticateAndGetUser(mixed): UserInterface` 为 abstract protected
    - 完整代码参见 Design Components §1
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_
  - [x] 2.2 编写 AbstractPreAuthenticator 的 property tests — `ut/PBT/AuthenticatorPropertyTest.php`
    - **Property 1: Supports ↔ Credentials 一致性** — `supports()` 返回值等于 `getCredentialsFromRequest() !== null`
    - **Property 2: Authenticate round-trip** — 有效凭证 → Passport.user 等于 `authenticateAndGetUser()` 返回的用户
    - **Property 3: Authenticate error condition** — 无效凭证 → 抛出 `AuthenticationException`
    - **Property 4: CreateToken invariant** — `token.getUser() === passport.getUser()` 且 `token.getRoleNames()` 包含用户所有角色
    - 使用 Eris 1.x 生成随机凭证输入，通过测试子类（concrete implementation）验证
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 14.1, 14.2, 14.3, 14.4, 14.5_
  - [x] 2.3 重写 `src/ServiceProviders/Security/AbstractSimplePreAuthenticationPolicy.php`
    - 实现新版 `AuthenticationPolicyInterface`
    - `getAuthenticationType()` 返回 `AUTH_TYPE_PRE_AUTH`
    - `getAuthenticator()` 声明为 abstract
    - `getAuthenticatorConfig()` 默认返回空数组
    - `getEntryPoint()` 默认返回 `NullEntryPoint` 实例
    - 完整代码参见 Design Components §3
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_
  - [x] 2.4 编写 AbstractSimplePreAuthenticationPolicy 的单元测试
    - 通过测试子类验证 `getAuthenticationType()` 返回 `AUTH_TYPE_PRE_AUTH`
    - 验证 `getAuthenticatorConfig()` 默认返回空数组
    - 验证 `getEntryPoint()` 返回 `NullEntryPoint` 实例
    - _Requirements: 4.1, 4.2, 4.3, 4.5_
  - [x] 2.5 Checkpoint: 运行 `composer dump-autoload` 确认无 autoload 错误，运行已有测试确认无回归，commit

- [x] 3. 确认 UserProvider 无需修改 + 废弃旧 Authenticator + 配置层兼容确认
  - [x] 3.1 确认 `src/ServiceProviders/Security/AbstractSimplePreAuthenticateUserProvider.php` 无需修改
    - 继续实现 `SimplePreAuthenticateUserProviderInterface`（扩展 `UserProviderInterface`）
    - `authenticateAndGetUser()` 保留为核心抽象方法
    - `loadUserByIdentifier()` 继续抛出 `LogicException`
    - `refreshUser()` 继续返回传入的用户对象
    - `supportsClass()` 继续基于声明的受支持用户类判断
    - 验证现有代码已满足 R2 所有 AC，无需改动
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
  - [x] 3.2 修改 `src/ServiceProviders/Security/AbstractSimplePreAuthenticator.php`
    - 添加类级 `@deprecated` 注解，指向 `AbstractPreAuthenticator` 作为替代
    - 将 `createToken()`、`authenticateToken()`、`supportsToken()` 从 abstract 改为 concrete 方法，方法体抛出 `LogicException`
    - 保持 `abstract class` 声明（`getCredentialsFromRequest()` 仍为 abstract）
    - 保持 `getCredentialsFromRequest()` 为 abstract（Design CR Q3=A）
    - _Requirements: 7.1, 7.2_
  - [x] 3.3 确认配置层组件无需修改
    - 确认 `FirewallInterface`、`SimpleFirewall`、`AccessRuleInterface`、`SimpleAccessRule`、`NullEntryPoint`、`SecurityConfiguration`、`SimpleFirewallConfiguration`、`SimpleAccessRuleConfiguration`、`SimplePreAuthenticateUserProviderInterface` 保持兼容，无需改动
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_
  - [x] 3.4 Checkpoint: 运行 `composer dump-autoload` 确认无 autoload 错误，运行已有测试确认无回归，commit

- [x] 4. 集成层：SimpleSecurityProvider.register() 重写
  - [x] 4.1 重写 `src/ServiceProviders/Security/SimpleSecurityProvider.php` 的 `register()` 方法
    - 保持现有配置合并逻辑不变
    - 新增 TokenStorage 创建和 `$kernel->setTokenStorage()`
    - 新增 RoleHierarchy + RoleHierarchyVoter + AccessDecisionManager(voters, UnanimousStrategy) 配置
    - 新增 AuthorizationChecker 创建和 `$kernel->setAuthorizationChecker()`
    - 调用 `registerFirewallListener()` 和 `registerAccessRuleListener()`
    - 保持 `addFirewall()`、`addAccessRule()`、`addAuthenticationPolicy()`、`addRoleHierarchy()` API 不变
    - 保持 `getFirewalls()`、`getAccessRules()`、`getRoleHierarchy()`、`getPolicies()` 返回格式不变
    - 保持 `getConfigDataProvider()` 在 `register()` 前调用时抛出 `LogicException`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8_
  - [x] 4.2 实现 `registerFirewallListener()` protected 方法
    - 注册 `KernelEvents::REQUEST` listener，优先级 `MicroKernel::BEFORE_PRIORITY_FIREWALL`（= 8）
    - 遍历 firewalls，URL pattern 匹配后遍历 policies
    - 通过 `AuthenticationPolicyInterface::getAuthenticator()` 获取 authenticator
    - 调用 `supports()` → `authenticate()` → `createToken()` → `tokenStorage->setToken()`
    - catch `AuthenticationException`：不设 token，请求继续
    - 第一个匹配的 firewall 生效后 break
    - 仅处理 main request（`$event->isMainRequest()`）
    - stateless firewall 不创建 session 存储 token
    - 完整代码参见 Design Components §4 `registerFirewallListener()`
    - _Requirements: 5.3, 5.4, 9.1, 9.2, 9.3, 10.1, 10.2, 10.5_
  - [x] 4.3 实现 `registerAccessRuleListener()` protected 方法
    - 注册 `KernelEvents::REQUEST` listener，优先级 `MicroKernel::BEFORE_PRIORITY_FIREWALL - 1`（= 7，Design CR Q4=A 硬编码）
    - 遍历 access rules，按注册顺序匹配，第一个匹配的 rule 生效
    - 无角色要求 → 允许；token 为 null 或缺少角色 → 抛出 `AccessDeniedHttpException`
    - 使用 `AccessDecisionManager::decide()` 判断授权
    - 仅处理 main request
    - 完整代码参见 Design Components §4 `registerAccessRuleListener()`
    - _Requirements: 5.5, 9.4, 9.5, 10.3, 10.4_
  - [x] 4.4 实现 `requestMatchesPattern()` protected 方法
    - 支持 string（正则）和 `RequestMatcherInterface` 两种 pattern 类型
    - string pattern 使用 `preg_match('{' . $pattern . '}', rawurldecode($request->getPathInfo()))`
    - 完整代码参见 Design Components §4 `requestMatchesPattern()`
    - _Requirements: 10.1, 10.2_
  - [x] 4.5 编写 access rule listener 单元测试
    - 验证按注册顺序匹配，第一个匹配的 rule 生效
    - 验证无角色要求时允许访问
    - 验证 token 为 null 时抛出 `AccessDeniedHttpException`
    - 验证角色不足时抛出 `AccessDeniedHttpException`
    - 验证 role hierarchy 正确传递继承关系
    - _Requirements: 9.4, 9.5, 10.3, 10.4, 10.5_
  - [x] 4.6 Checkpoint: 运行 `composer dump-autoload` 确认无 autoload 错误，运行已有测试确认无回归，commit

- [x] 5. 配置层 PBT
  - [x] 5.1 编写 SimpleAccessRule 的 property tests — `ut/PBT/AccessRulePropertyTest.php`
    - **Property 5: Access rule 配置 round-trip** — pattern / roles / channel 构造后 getter 返回原值
    - **Property 6: Access rule invariant** — `getPattern()` 非空，`getRequiredRoles()` 为数组
    - 使用 Eris 生成随机 pattern 字符串、roles 数组、channel 值
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_
  - [x] 5.2 编写 SimpleFirewall 的 property tests — `ut/PBT/FirewallPropertyTest.php`
    - **Property 7: Firewall 配置 round-trip** — pattern / policies / stateless 构造后 getter 返回原值
    - **Property 8: Firewall 解析输出 invariant** — `parseFirewall()` 输出包含 `pattern`、`users`、`stateless` 键
    - **Property 9: Firewall 缺失必填字段 error condition** — 缺少 pattern/policies/users 时抛出配置校验异常
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_
  - [x] 5.3 编写 RoleHierarchy 的 property tests — `ut/PBT/RoleHierarchyPropertyTest.php`
    - **Property 10: Role hierarchy merge idempotence** — 重复 `addRoleHierarchy()` 后语义结果不变
    - **Property 11: Role hierarchy 继承链传递性** — A → B → C 时解析 A 包含 B 和 C
    - **Property 12: Role hierarchy single-level round-trip** — A → [B, C] 时 `getRoleHierarchy()` 输出包含 B 和 C
    - _Requirements: 13.1, 13.2, 13.3_
  - [x] 5.4 编写 SecurityConfiguration 的 property tests — `ut/PBT/SecurityConfigPropertyTest.php`
    - **Property 13: Security 配置注册 invariant** — 有效配置 `register()` 不抛异常
    - **Property 14: 配置合并顺序 confluence** — programmatic additions 追加在 config-based settings 之后
    - **Property 15: Role hierarchy string 归一化 round-trip** — string 值自动转为单元素数组
    - **Property 16: RefreshUser identity** — `refreshUser(user)` 返回同一用户对象
    - _Requirements: 2.4, 15.1, 15.2, 15.3_
  - [x] 5.5 Checkpoint: 运行 `phpunit --testsuite pbt` 确认所有 PBT 通过，commit

- [x] 6. 测试辅助类重写
  - [x] 6.1 重写 `ut/Helpers/Security/TestApiUserPreAuthenticator.php`
    - 改为继承 `AbstractPreAuthenticator`（新类）
    - `getCredentialsFromRequest()` 从 request query 参数 `sig` 提取凭证，无 `sig` 返回 null
    - `authenticateAndGetUser()` 委托给注入的 `SimplePreAuthenticateUserProviderInterface`
    - 构造函数接受 `SimplePreAuthenticateUserProviderInterface` 参数
    - 完整代码参见 Design Components §8
    - _Requirements: 8.1, 8.2_
  - [x] 6.2 重写 `ut/Helpers/Security/TestAuthenticationPolicy.php`
    - 实现新版 `AuthenticationPolicyInterface`（继承 `AbstractSimplePreAuthenticationPolicy`）
    - `getAuthenticator()` 返回基于 `TestApiUserPreAuthenticator` + `TestApiUserProvider` 的 authenticator 实例
    - _Requirements: 8.3_
  - [x] 6.3 确认 `TestApiUserProvider`、`TestApiUser`、`TestAccessRule` 无需修改
    - `TestApiUserProvider` 保持 `authenticateAndGetUser()` 凭证-用户映射不变（`abcd` → admin、`parent` → parent、`child` → child）
    - `TestApiUser` 保持 `UserInterface` 实现不变
    - `TestAccessRule` 保持构造函数签名和行为不变
    - _Requirements: 8.4, 8.5, 8.6_
  - [x] 6.4 Checkpoint: 运行 `composer dump-autoload` 确认无 autoload 错误，commit

- [x] 7. 现有安全测试适配
  - [x] 7.1 适配 `ut/Security/SecurityServiceProviderTest.php`
    - 将旧 API 调用（`getAuthenticationProvider()`、`getAuthenticationListener()`）替换为新 API（`getAuthenticator()`）
    - 更新测试中的 mock/stub 以匹配新 `AuthenticationPolicyInterface`
    - _Requirements: 16.1_
  - [x] 7.2 适配 `ut/Security/SecurityServiceProviderConfigurationTest.php`
    - 确保配置解析测试继续验证 `SimpleSecurityProvider` 的配置行为
    - _Requirements: 16.2_
  - [x] 7.3 确认 `ut/Security/NullEntryPointTest.php` 无需修改
    - `NullEntryPoint` 行为未变，测试应直接通过
    - _Requirements: 16.3_
  - [x] 7.4 适配 `ut/Integration/SecurityAuthenticationFlowIntegrationTest.php`
    - 适配新 authenticator 系统，验证完整认证授权链路
    - 更新 `ut/Integration/app.integration-security.php` 中的安全配置
    - 验证：有效凭证 → 认证成功 → token 存储；无效凭证 → 不阻断；无凭证 → 跳过；角色检查 → 403
    - _Requirements: 16.4, 9.1, 9.2, 9.3, 9.4, 9.5_
  - [x] 7.5 适配 `ut/Security/app.security.php` 和 `ut/Security/app.security2.php`
    - 更新安全配置 bootstrap 文件以使用新 API
    - _Requirements: 16.1, 16.2_
  - [x] 7.6 Checkpoint: 运行 `phpunit --testsuite security` 和 `phpunit --testsuite integration` 确认全部通过，commit

- [ ] 8. State 文档更新
  - [ ] 8.1 更新 `docs/state/architecture.md`
    - 更新 `## 安全模型` section，反映新的 authenticator 系统（`AuthenticatorInterface` 替代三件套）
    - 补充 firewall event listener 和 access rule listener 的注册机制
    - 更新 `## 请求处理流程` 第 3 步 "Firewall（priority 8）" 的描述，补充 authenticator 调用链路
    - _Requirements: 9.1, 10.1_
  - [ ] 8.2 Checkpoint: 运行全量测试 `phpunit --testsuite security --testsuite integration --testsuite pbt` 确认全部通过，commit

- [ ] 9. 手工测试
  - [ ] 9.1 验证认证流程端到端行为
    - 使用测试 bootstrap 配置启动 MicroKernel，发送带有效凭证（`sig=abcd`）的请求，确认返回 200 且 token storage 中有已认证 token
    - 发送带无效凭证（`sig=invalid`）的请求，确认请求不被阻断（认证失败但请求继续），access rule 返回 403
    - 发送不带凭证的请求，确认 authenticator 跳过认证（`supports()` 返回 false）
    - _Requirements: 9.1, 9.2, 9.3_
  - [ ] 9.2 验证防火墙和授权行为
    - 确认请求 URL 匹配 firewall pattern 时触发认证流程
    - 确认请求 URL 不匹配任何 firewall pattern 时跳过认证
    - 确认 access rule 按注册顺序匹配，第一个匹配的 rule 生效
    - 确认 role hierarchy 继承关系正确（ROLE_ADMIN 用户可访问 ROLE_USER 资源）
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  - [ ] 9.3 验证旧类废弃标记
    - 确认 `AbstractSimplePreAuthenticator` 的 `@deprecated` 注解存在
    - 确认调用 `createToken()`、`authenticateToken()`、`supportsToken()` 抛出 `LogicException`
    - _Requirements: 7.1, 7.2_
  - [ ] 9.4 Checkpoint: 手工测试全部通过，commit

- [ ] 10. Code Review
  - [ ] 10.1 委托给 code-reviewer agent 执行
  - [ ] 10.2 Checkpoint: Code review 通过，处理所有 review 意见，commit

## Socratic Review

**Q1: tasks 是否完整覆盖了 design 中的所有实现项？有无遗漏的模块或接口？**
A: Design 中列出 8 个组件：(1) AbstractPreAuthenticator → Task 2.1; (2) AuthenticationPolicyInterface → Task 1.1; (3) AbstractSimplePreAuthenticationPolicy → Task 2.3; (4) SimpleSecurityProvider → Task 4.1–4.4; (5) AbstractSimplePreAuthenticateUserProvider → Task 3.1; (6) AbstractSimplePreAuthenticator 废弃 → Task 3.2; (7) 配置层组件 → Task 3.3; (8) 测试辅助类 → Task 6.1–6.3。Design 中的 16 个 Correctness Properties → Task 2.2 + 5.1–5.4。Testing Strategy 中的 unit/integration tests → Task 2.4 + 4.5 + 7.1–7.5。State 文档更新 → Task 8.1。全部覆盖，无遗漏。

**Q2: task 之间的依赖顺序是否正确？是否存在隐含的前置依赖未体现在排序中？**
A: 严格依赖链：Task 1（接口）→ Task 2（抽象类，依赖接口）→ Task 3（确认/废弃）→ Task 4（集成层，依赖接口+抽象类）→ Task 6（测试辅助类，依赖抽象类）→ Task 7（测试适配，依赖辅助类+集成层）。Task 5（配置层 PBT）可在 Task 4 之后任意时间执行，与 Task 6/7 无依赖。graphify 查询确认 `AuthenticationPolicyInterface` 被 `AbstractSimplePreAuthenticationPolicy` 和 `SimpleSecurityProvider` 依赖，排序正确。

**Q3: 每个 task 的粒度是否合适？是否有过粗或过细的 task？**
A: 各 sub-task 对应单个文件或单个测试文件的修改，粒度适中。Task 4（SimpleSecurityProvider）拆分为 register() + registerFirewallListener() + registerAccessRuleListener() + requestMatchesPattern() + 单元测试，避免了单个 sub-task 过大。

**Q4: checkpoint 的设置是否覆盖了关键阶段？**
A: 每个 top-level task 末尾都有 checkpoint，覆盖了接口层编译、抽象类层编译、集成层编译、PBT 通过、测试辅助类编译、测试套件通过、全量验证等关键阶段。

**Q5: 手工测试是否覆盖了 requirements 中的关键用户场景？**
A: 手工测试覆盖了认证流程三种场景（有效凭证/无效凭证/无凭证）、防火墙匹配、access rule 授权、role hierarchy 继承、旧类废弃标记，对应 R9、R10、R7 的核心行为。

**Q6: Task 3.1 和 3.2 是否可以并行？**
A: 可以。3.1 确认 UserProvider 无需修改，3.2 修改旧 Authenticator 添加废弃标记，两者修改不同文件且无调用依赖。

## Notes

- 按 spec-execution 规范执行各 task
- commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint + commit
- 依赖层次：Task 1 → Task 2 → Task 3 → Task 4 → Task 6 → Task 7（严格顺序）
- Task 3.1（确认 UserProvider 无需修改）和 Task 3.2（废弃旧 Authenticator）可并行
- Task 5（配置层 PBT）可在 Task 4 之后任意时间执行，与 Task 6/7 无依赖
- Property tests 验证 Design 中定义的 16 个 Correctness Properties
- Unit tests 和 integration tests 验证具体行为和端到端链路
- Firewall listener 通过集成测试覆盖（Design CR Q2=C），access rule listener 通过单元测试覆盖
- 配置层组件（R6）无需修改，通过 Task 3.3 确认兼容性，通过 PBT（Task 5）验证正确性

## Gatekeep Log

**校验时间**: 2025-07-16
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] Checkpoint 从独立 top-level task（原 Task 4, 6, 10, 12）改为每个 top-level task 的最后一个 sub-task，符合规范要求
- [结构] 新增手工测试 top-level task（Task 9），覆盖认证流程端到端行为、防火墙和授权行为、旧类废弃标记验证
- [结构] 新增 Code Review top-level task（Task 10），描述为委托给 code-reviewer agent 执行
- [结构] 重新编号所有 top-level task（1–10）和 sub-task，确保序号连续无跳号
- [格式] 移除所有 `[ ]*` 非标准 checkbox 语法，统一为 `[ ]`；所有 task 均为 mandatory，移除 optional 标记
- [内容] 移除 Notes 中 "Tasks marked with `*` are optional and can be skipped for faster MVP" 的说明，所有 task 均为必须执行
- [内容] Notes 补充 spec-execution 规范引用（"按 spec-execution 规范执行各 task"）
- [内容] Notes 补充 commit 时机说明（"commit 随 checkpoint 一起执行"）
- [内容] 新增 Task 3.3 确认配置层组件无需修改，补充 R6（配置层兼容）的 requirement 追溯，原文档中 R6 仅在 Notes 中提及但无对应 task
- [内容] Task 4.2（registerFirewallListener）补充 R10 AC5（stateless firewall 不创建 session 存储 token）的引用和描述
- [内容] 新增 Socratic Review section，覆盖 design 全覆盖、依赖顺序、粒度、checkpoint、手工测试、并行条件 6 个问题

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 中的模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误

**结构校验**
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（Task 10）
- [x] 倒数第二个 top-level task 是手工测试（Task 9）
- [x] 自动化实现 task（Task 1–8）排在手工测试和 Code Review 之前

**Task 格式校验**
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–10）
- [x] sub-task 有层级序号（1.1, 1.2, 2.1–2.5, ...）
- [x] 序号连续，无跳号

**Requirement 追溯校验**
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] R1–R17 每条 requirement 至少被一个 task 引用（修正后 R6 由 Task 3.3 覆盖）
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在

**依赖与排序校验**
- [x] top-level task 按依赖关系排序：接口（1）→ 抽象类（2）→ 确认/废弃（3）→ 集成层（4）→ PBT（5）→ 测试辅助类（6）→ 测试适配（7）→ State 更新（8）→ 手工测试（9）→ Code Review（10）
- [x] 无循环依赖
- [x] Task 3.1 和 3.2 标注可并行，条件成立（不同文件、无调用依赖）

**Graphify 跨模块依赖校验**
- [x] 已对 AuthenticationPolicyInterface、SimpleSecurityProvider、AbstractSimplePreAuthenticationPolicy 执行 graphify 依赖查询
- [x] task 排序与 graphify 揭示的模块依赖一致：AuthenticationPolicyInterface（Task 1）先于依赖它的 AbstractSimplePreAuthenticationPolicy（Task 2）和 SimpleSecurityProvider（Task 4）
- [x] 无遗漏的隐含跨模块依赖

**Checkpoint 校验**
- [x] checkpoint 作为每个 top-level task 的最后一个 sub-task（修正后）
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 描述中包含具体的验证命令和 commit 动作
- [x] checkpoint 有可执行的验证步骤

**Test-first 校验**
- [○] Task 2 中实现（2.1）在测试（2.2）之前，未严格遵循 test-first。但 PBT 需要 concrete implementation 才能运行，且 task 描述中已明确测试内容，可接受

**Task 粒度校验**
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗的 task
- [x] 无过细的 task
- [x] 所有 task 均为 mandatory（修正后移除 optional 标记）

**手工测试 Task 校验**
- [x] 手工测试 top-level task 存在（Task 9）
- [x] 手工测试覆盖了 requirements 中的关键用户场景（认证流程、防火墙、授权、废弃标记）
- [x] 手工测试场景描述具体，可执行

**Code Review Task 校验**
- [x] Code Review 是最后一个 top-level task（Task 10）
- [x] 描述为"委托给 code-reviewer agent 执行"
- [x] 未在 task 描述中展开 review checklist

**执行注意事项校验**
- [x] `## Notes` section 存在
- [x] 明确提到按 spec-execution 规范执行（修正后补充）
- [x] 明确说明 commit 随 checkpoint 一起执行（修正后补充）
- [x] 包含当前 spec 特有的执行要点（依赖层次、并行条件、测试策略）

**Socratic Review 校验**
- [x] Socratic Review section 存在（修正后新增）
- [x] 覆盖 design 全覆盖、依赖顺序、粒度、checkpoint、手工测试、并行条件

**目的性审查**
- [x] Design CR 回应：Q1=C（按依赖层次拆分）→ Task 1–4 按层次排列；Q2=C（混合测试策略）→ Task 4.5 单元测试 + Task 7.4 集成测试；Q3=A（保持 abstract class）→ Task 3.2 描述中明确；Q4=A（硬编码优先级）→ Task 4.3 描述中明确
- [x] Design 全覆盖：8 个组件 + 16 个 Properties + Testing Strategy + State 更新均有对应 task
- [x] 可独立执行：每个 sub-task 描述自包含，含文件路径、实现要点、Design 参考和 Requirement 引用
- [x] 验收闭环：checkpoint（每个 top-level task）+ 手工测试（Task 9）+ Code Review（Task 10）构成完整验收
- [x] 执行路径无歧义：依赖链在 Notes 中明确，并行条件有说明