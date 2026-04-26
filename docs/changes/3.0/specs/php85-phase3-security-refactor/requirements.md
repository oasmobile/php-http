# Requirements Document

> PHP 8.5 Phase 3: Security Component Refactor — `.kiro/specs/php85-phase3-security-refactor/`

---

## Introduction

Phase 1 已完成 Silex → Symfony MicroKernel 替换和全部 Symfony 组件升级到 7.x，Phase 2 已完成 Twig 3.x 和 Guzzle 7.x 升级。Security 组件在 Phase 1 中仅做了最小可编译适配——移除了 Silex/Pimple 依赖，但 authenticator 系统仍为 stub 状态（`createToken()`、`authenticateToken()`、`getAuthenticationProvider()`、`getAuthenticationListener()` 等方法抛出 `LogicException`），无法实际工作。

本 spec 的目标是：将项目中所有自定义安全组件适配到 Symfony Security 7.x 的 authenticator 系统，使认证、授权、防火墙功能恢复正常工作，并大量补充 Property-Based Testing。

Symfony Security 7.x 的核心变化：旧系统的 `SimplePreAuthenticatorInterface` + `SimpleAuthenticationProvider` + `ListenerInterface` 三件套被统一的 `AuthenticatorInterface` 替代。一个 authenticator 同时承担凭证提取、认证、成功/失败处理的职责。

**关键决策**（来自 goal.md Clarification）：

- Q1=B: 新建 `AbstractPreAuthenticator` 抽象类实现 `AuthenticatorInterface`，用模板方法封装 `getCredentialsFromRequest()` + `authenticateAndGetUser()` 两步模式，废弃旧 `AbstractSimplePreAuthenticator`
- Q2=B: 完全重新设计 `AuthenticationPolicyInterface`，移除 `getAuthenticationProvider()` / `getAuthenticationListener()`，改为 `getAuthenticator()` + `getAuthenticatorConfig()`
- Q3=A: `SimpleSecurityProvider` 保持自管理模式，不引入 SecurityBundle
- Q4=B: PBT 范围包括配置层（access rule / firewall / role hierarchy）和认证流程

**不涉及的内容**：

- 新安全功能引入（OAuth、JWT 等）
- 安全策略逻辑变更（认证失败不阻断请求、授权由 AccessRule 拦截的模型保持不变）
- PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- Twig 或 Guzzle 相关工作（Phase 2 已完成）

**约束**：

- C-1: PBT 使用 Eris 1.x（Phase 1 已引入）
- C-2: 在 `feature/php85-upgrade` 分支上推进
- C-3: 预期通过 suite：`security`、`integration`
- C-4: 行为不变——认证（凭证提取 → 用户查找 → token 生成）、授权（access rule 匹配 → 角色检查）、防火墙规则（pattern 匹配 → policy 分发）的外部行为保持一致

---

## Glossary

- **Authenticator**: Symfony Security 7.x 中实现 `AuthenticatorInterface` 的类，统一承担凭证提取、认证、token 创建、成功/失败处理的职责
- **AuthenticatorInterface**: `Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface`，Symfony 7.x 的认证器统一接口
- **AbstractPreAuthenticator**: 新建的抽象类，实现 `AuthenticatorInterface`，用模板方法封装 pre-auth 模式，子类只需实现 `getCredentialsFromRequest()` + `authenticateAndGetUser()`
- **AbstractSimplePreAuthenticator**: 旧的 pre-authenticator 抽象类，基于已移除的 `SimplePreAuthenticatorInterface`，本 Phase 废弃
- **AbstractSimplePreAuthenticateUserProvider**: 实现 `SimplePreAuthenticateUserProviderInterface` 的抽象用户提供者，核心方法 `authenticateAndGetUser()` 由子类实现
- **AbstractSimplePreAuthenticationPolicy**: 认证策略抽象类，实现 `AuthenticationPolicyInterface`，本 Phase 原地重写以适配新接口
- **AuthenticationPolicyInterface**: 认证策略接口，定义 firewall 如何获取 authenticator 和相关配置
- **SimpleSecurityProvider**: 安全服务提供者，自行管理 firewall、access rule、authentication policy 的注册和配置解析
- **FirewallInterface**: 防火墙配置接口，定义 pattern、policies、user provider、stateless 等属性
- **SimpleFirewall**: `FirewallInterface` 的默认实现，从配置数组构造
- **AccessRuleInterface**: 访问规则接口，定义 pattern、required roles、required channel
- **SimpleAccessRule**: `AccessRuleInterface` 的默认实现，从配置数组构造
- **NullEntryPoint**: 实现 `AuthenticationEntryPointInterface`，在未认证时抛出 `AccessDeniedHttpException`
- **SimplePreAuthenticateUserProviderInterface**: 扩展 `UserProviderInterface` 的接口，增加 `authenticateAndGetUser()` 方法
- **Role_Hierarchy**: 角色继承层级，父角色自动拥有子角色的权限
- **Security_Suite**: `phpunit.xml` 中定义的 `security` 测试套件
- **Integration_Suite**: `phpunit.xml` 中定义的 `integration` 测试套件
- **PBT**: Property-Based Testing，使用 Eris 1.x 生成随机输入验证系统属性
- **Passport**: Symfony Security 7.x 中 `authenticate()` 方法返回的 `Passport` 对象，包含用户凭证和 badge 信息
- **MicroKernel**: 项目的核心 HTTP 内核类，替代原 Silex Application
- **SecurityConfiguration**: 安全配置定义类，校验并解析 `policies`、`firewalls`、`access_rules`、`role_hierarchy` 配置树
- **Security_Authentication_Flow**: 完整的认证授权链路——从请求进入 firewall、凭证提取、用户认证、token 存储到 access rule 授权的端到端流程
- **TestApiUserPreAuthenticator**: 测试用 pre-authenticator，继承 AbstractPreAuthenticator，从 request query 参数提取凭证
- **TestAuthenticationPolicy**: 测试用认证策略，实现 AuthenticationPolicyInterface，提供测试 authenticator 实例
- **TestApiUserProvider**: 测试用用户提供者，继承 AbstractSimplePreAuthenticateUserProvider，实现固定的凭证-用户映射
- **TestApiUser**: 测试用用户类，实现 `UserInterface`，支持角色和序列化
- **TestAccessRule**: 测试用访问规则，实现 AccessRuleInterface


---

## Requirements

### Requirement 1: AbstractPreAuthenticator — 新 Authenticator 抽象类

**User Story:** 作为库使用者，我希望有一个新的 pre-auth 抽象类封装 Symfony 7.x `AuthenticatorInterface` 的复杂性，以便子类只需实现 `getCredentialsFromRequest()` + `authenticateAndGetUser()` 两步即可完成认证。

#### Acceptance Criteria

1. THE AbstractPreAuthenticator SHALL implement AuthenticatorInterface
2. WHEN `supports()` is called with a Request, THE AbstractPreAuthenticator SHALL delegate to `getCredentialsFromRequest()` 并根据返回值是否为 null 判断是否支持该请求
3. WHEN `authenticate()` is called, THE AbstractPreAuthenticator SHALL 依次调用 `getCredentialsFromRequest()` 提取凭证，再调用 `authenticateAndGetUser()` 获取用户，最终返回包含该用户的 Passport 对象
4. WHEN `authenticateAndGetUser()` 抛出 `AuthenticationException`, THE AbstractPreAuthenticator SHALL 将异常传播给调用方
5. WHEN `createToken()` is called with a Passport and firewall name, THE AbstractPreAuthenticator SHALL 返回包含用户及其角色的认证 token
6. WHEN `onAuthenticationSuccess()` is called, THE AbstractPreAuthenticator SHALL return null（不中断请求处理，保持现有行为：认证成功后不产生额外响应）
7. WHEN `onAuthenticationFailure()` is called, THE AbstractPreAuthenticator SHALL return null（不中断请求处理，保持现有行为：认证失败不阻断请求）
8. THE AbstractPreAuthenticator SHALL 声明 `getCredentialsFromRequest()` 为 abstract 方法，接受 Request 并返回凭证（null 表示无凭证）
9. THE AbstractPreAuthenticator SHALL 声明 `authenticateAndGetUser()` 为 abstract 方法，接受凭证并返回已认证的用户

### Requirement 2: AbstractSimplePreAuthenticateUserProvider — 用户提供者适配

**User Story:** 作为库使用者，我希望 `AbstractSimplePreAuthenticateUserProvider` 与新 authenticator 系统协作，以便 `authenticateAndGetUser()` 模式继续可用。

#### Acceptance Criteria

1. THE AbstractSimplePreAuthenticateUserProvider SHALL 继续实现 `SimplePreAuthenticateUserProviderInterface`（扩展 `UserProviderInterface`）
2. THE AbstractSimplePreAuthenticateUserProvider SHALL 保留 `authenticateAndGetUser($credentials)` 作为核心抽象方法
3. WHEN `loadUserByIdentifier()` is called, THE AbstractSimplePreAuthenticateUserProvider SHALL 抛出 `LogicException`（保持现有行为，pre-auth 模式不使用 identifier 加载）
4. WHEN `refreshUser()` is called, THE AbstractSimplePreAuthenticateUserProvider SHALL 返回传入的用户对象（保持现有行为）
5. WHEN `supportsClass()` is called with a class name, THE AbstractSimplePreAuthenticateUserProvider SHALL 返回 true 当且仅当该类名等于或是其声明的受支持用户类的子类

### Requirement 3: AuthenticationPolicyInterface — 接口重新设计

**User Story:** 作为库使用者，我希望 `AuthenticationPolicyInterface` 适配 Symfony 7.x 的 authenticator 模型，以便移除对已删除 API 的依赖。

#### Acceptance Criteria

1. THE AuthenticationPolicyInterface SHALL 定义 `getAuthenticationType()` 方法，返回认证类型标识（保留）
2. THE AuthenticationPolicyInterface SHALL 定义 `getAuthenticator()` 方法，接受 MicroKernel、firewall 名称和选项，返回 AuthenticatorInterface 实例，替代旧的 `getAuthenticationProvider()` 和 `getAuthenticationListener()`
3. THE AuthenticationPolicyInterface SHALL 定义 `getAuthenticatorConfig()` 方法，返回 authenticator 的配置选项
4. THE AuthenticationPolicyInterface SHALL 定义 `getEntryPoint()` 方法，返回 AuthenticationEntryPointInterface 实例（保留）
5. THE AuthenticationPolicyInterface SHALL 移除 `getAuthenticationProvider()` 方法
6. THE AuthenticationPolicyInterface SHALL 移除 `getAuthenticationListener()` 方法
7. THE AuthenticationPolicyInterface SHALL 保留 `AUTH_TYPE_PRE_AUTH`、`AUTH_TYPE_FORM`、`AUTH_TYPE_HTTP` 等认证类型常量

### Requirement 4: AbstractSimplePreAuthenticationPolicy — 策略抽象类重写

**User Story:** 作为库使用者，我希望有一个适配新 `AuthenticationPolicyInterface` 的 pre-auth 策略抽象类，以便子类可以方便地提供 authenticator 实例。

#### Acceptance Criteria

1. THE AbstractSimplePreAuthenticationPolicy SHALL 实现新版 `AuthenticationPolicyInterface`
2. THE AbstractSimplePreAuthenticationPolicy SHALL 在 `getAuthenticationType()` 中返回 `AUTH_TYPE_PRE_AUTH`（保持现有行为）
3. THE AbstractSimplePreAuthenticationPolicy SHALL 在 `getEntryPoint()` 中默认返回 `NullEntryPoint` 实例（保持现有行为）
4. THE AbstractSimplePreAuthenticationPolicy SHALL 声明 `getAuthenticator()` 为 abstract 方法，由子类提供具体的 Authenticator 实例
5. THE AbstractSimplePreAuthenticationPolicy SHALL 在 `getAuthenticatorConfig()` 中默认返回空数组


### Requirement 5: SimpleSecurityProvider — Authenticator 系统集成

**User Story:** 作为库使用者，我希望 `SimpleSecurityProvider` 能够将配置正确传递给 Symfony Security 7.x 的 firewall 系统，以便 authenticator 实际生效。

#### Acceptance Criteria

1. THE SimpleSecurityProvider SHALL 保持现有配置 API 不变：`addFirewall()`、`addAccessRule()`、`addAuthenticationPolicy()`、`addRoleHierarchy()`
2. THE SimpleSecurityProvider SHALL 保持 `register()` 方法签名不变
3. WHEN `register()` is called, THE SimpleSecurityProvider SHALL 解析配置并为每个 firewall 创建对应的 authenticator（通过 `AuthenticationPolicyInterface::getAuthenticator()` 获取）
4. WHEN `register()` is called, THE SimpleSecurityProvider SHALL 注册 firewall listener 到 MicroKernel 的事件系统
5. WHEN `register()` is called, THE SimpleSecurityProvider SHALL 配置 access decision manager 以处理 access rule 的角色检查
6. THE SimpleSecurityProvider SHALL 保持自管理模式，不依赖 Symfony SecurityBundle 的 YAML/PHP 配置体系
7. THE SimpleSecurityProvider SHALL 保持 `getFirewalls()`、`getAccessRules()`、`getRoleHierarchy()`、`getPolicies()` 方法的返回格式不变
8. WHEN `getConfigDataProvider()` is called before `register()`, THE SimpleSecurityProvider SHALL 抛出 `LogicException`（保持现有行为）

### Requirement 6: 配置层组件 — 保持兼容

**User Story:** 作为库使用者，我希望 firewall、access rule、role hierarchy 的配置层接口和实现保持兼容，以便现有配置代码无需修改。

#### Acceptance Criteria

1. THE FirewallInterface SHALL 保持 `getPattern()`、`isStateless()`、`getPolicies()`、`getUserProvider()`、`getOtherSettings()` 方法签名不变
2. THE SimpleFirewall SHALL 继续从配置数组构造，保持 `SimpleFirewallConfiguration` 的校验规则不变
3. THE AccessRuleInterface SHALL 保持 `getPattern()`、`getRequiredRoles()`、`getRequiredChannel()` 方法签名不变
4. THE SimpleAccessRule SHALL 继续从配置数组构造，保持 `SimpleAccessRuleConfiguration` 的校验规则不变
5. THE NullEntryPoint SHALL 保持现有行为：`start()` 方法抛出 `AccessDeniedHttpException`
6. THE SecurityConfiguration SHALL 保持 `policies`、`firewalls`、`access_rules`、`role_hierarchy` 的配置树结构不变

### Requirement 7: 旧类废弃

**User Story:** 作为库使用者，我希望旧的 stub 类被明确标记为废弃，以便了解迁移路径。

#### Acceptance Criteria

1. THE AbstractSimplePreAuthenticator SHALL 被标记为 `@deprecated`，注释中指向 AbstractPreAuthenticator 作为替代
2. WHEN AbstractSimplePreAuthenticator 的 `createToken()`、`authenticateToken()`、`supportsToken()` 方法被调用, THE AbstractSimplePreAuthenticator SHALL 继续抛出 `LogicException`（保持 Phase 1 stub 行为，直到下游完成迁移）

### Requirement 8: 测试辅助类重写

**User Story:** 作为开发者，我希望 `ut/Helpers/Security/` 下的测试辅助类基于新 authenticator 系统工作，以便安全相关测试可以正常运行。

#### Acceptance Criteria

1. THE TestApiUserPreAuthenticator SHALL 继承 AbstractPreAuthenticator（新类），实现 `getCredentialsFromRequest()` 从 request query 参数 `sig` 提取凭证
2. WHEN `sig` 参数不存在, THE TestApiUserPreAuthenticator SHALL 从 `getCredentialsFromRequest()` 返回 null（表示不支持该请求，authenticator 跳过认证）
3. THE TestAuthenticationPolicy SHALL 实现新版 `AuthenticationPolicyInterface`，`getAuthenticator()` 返回基于 TestApiUserPreAuthenticator 和 TestApiUserProvider 的 authenticator 实例
4. THE TestApiUserProvider SHALL 保持 `authenticateAndGetUser()` 的凭证-用户映射逻辑不变（`abcd` → admin、`parent` → parent、`child` → child）
5. THE TestApiUser SHALL 保持 `UserInterface` 实现不变（`getRoles()`、`getUserIdentifier()`、`eraseCredentials()`、`jsonSerialize()`）
6. THE TestAccessRule SHALL 保持构造函数签名和行为不变


### Requirement 9: 安全功能行为不变 — 认证流程

**User Story:** 作为库使用者，我希望认证流程的外部行为在重写后保持一致，以便现有应用无需修改安全逻辑。

#### Acceptance Criteria

1. WHEN a request carries valid credentials, THE Security_Authentication_Flow SHALL 提取凭证、查找用户、生成认证 token，最终在 token storage 中存储已认证的 token
2. WHEN a request carries invalid credentials, THE Security_Authentication_Flow SHALL 不阻断请求（token 为 null 或未认证状态），由 AccessRule 决定是否拒绝
3. WHEN a request does not carry credentials, THE Security_Authentication_Flow SHALL 不阻断请求（authenticator 的 `supports()` 返回 false，跳过认证）
4. WHEN authentication succeeds and the user has required roles, THE AccessRule SHALL 允许访问
5. WHEN authentication fails or the user lacks required roles, THE AccessRule SHALL 返回 403 响应

### Requirement 10: 安全功能行为不变 — 防火墙与授权

**User Story:** 作为库使用者，我希望防火墙 pattern 匹配和 access rule 授权在重写后保持一致，以便现有安全配置继续生效。

#### Acceptance Criteria

1. WHEN a request URL matches a firewall pattern, THE SimpleSecurityProvider SHALL 将该请求路由到对应 firewall 的 authentication policy
2. WHEN a request URL does not match any firewall pattern, THE SimpleSecurityProvider SHALL 跳过认证处理
3. WHEN multiple access rules are configured, THE SimpleSecurityProvider SHALL 按注册顺序匹配，第一个匹配的 rule 生效
4. THE Role_Hierarchy SHALL 正确传递继承关系：如果 ROLE_ADMIN 继承 ROLE_USER，拥有 ROLE_ADMIN 的用户 SHALL 同时拥有 ROLE_USER 的权限
5. WHEN a firewall is configured as stateless, THE SimpleSecurityProvider SHALL 不创建 session 存储 token

### Requirement 11: PBT — Access Rule 组合验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 access rule 在各种 pattern × roles × channel 组合下的行为正确性。

#### Acceptance Criteria

1. FOR ALL valid pattern strings, THE SimpleAccessRule SHALL 正确存储并返回 pattern（round-trip property：构造 → `getPattern()` 返回原值）
2. FOR ALL valid roles arrays, THE SimpleAccessRule SHALL 正确存储并返回 roles（round-trip property：构造 → `getRequiredRoles()` 返回原值）
3. FOR ALL valid channel values (null, `http`, `https`), THE SimpleAccessRule SHALL 正确存储并返回 channel（round-trip property：构造 → `getRequiredChannel()` 返回原值）
4. FOR ALL SimpleAccessRule instances, `getPattern()` SHALL return a non-empty value（invariant property）
5. FOR ALL SimpleAccessRule instances, `getRequiredRoles()` SHALL return an array（invariant property）

### Requirement 12: PBT — Firewall Pattern 匹配验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 firewall 配置在各种 pattern 和 policy 组合下的正确性。

#### Acceptance Criteria

1. FOR ALL valid firewall configurations, THE SimpleFirewall SHALL 正确解析并返回 pattern（round-trip property：配置 → `getPattern()` 返回原值）
2. FOR ALL valid firewall configurations, THE SimpleFirewall SHALL 正确解析并返回 policies（round-trip property：配置 → `getPolicies()` 返回原值）
3. FOR ALL valid firewall configurations, THE SimpleFirewall SHALL 正确解析并返回 stateless 标志（round-trip property：配置 → `isStateless()` 返回原值）
4. FOR ALL SimpleFirewall instances, `parseFirewall()` 的输出 SHALL 包含 `pattern`、`users`、`stateless` 键（invariant property）
5. IF firewall configuration is missing required fields (pattern, policies, users), THEN THE SimpleFirewall SHALL 抛出配置校验异常（error condition property）

### Requirement 13: PBT — Role Hierarchy 传递性验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 role hierarchy 的继承链传递性。

#### Acceptance Criteria

1. FOR ALL role hierarchy configurations, THE SimpleSecurityProvider SHALL 正确合并 programmatic additions 和 config-based settings（idempotence property：重复添加同一 hierarchy 不改变最终结果的语义）
2. FOR ALL role hierarchy configurations with chain A → B → C, WHEN role A is resolved, THE resolved roles SHALL include B and C（metamorphic property：继承链越长，解析出的角色集合越大）
3. FOR ALL single-level role hierarchies (A → [B, C]), THE `getRoleHierarchy()` output SHALL contain all declared children for each parent（round-trip property）


### Requirement 14: PBT — Authenticator 凭证提取与认证流程验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 authenticator 的凭证提取和认证流程在各种输入下的正确性。

#### Acceptance Criteria

1. FOR ALL requests with non-null credentials, THE AbstractPreAuthenticator 的 `supports()` SHALL return true（metamorphic property：有凭证 → 支持）
2. FOR ALL requests without credentials, THE AbstractPreAuthenticator 的 `supports()` SHALL return false（metamorphic property：无凭证 → 不支持）
3. FOR ALL valid credentials that map to a user, THE AbstractPreAuthenticator 的 `authenticate()` SHALL return a Passport containing that user（round-trip property：凭证 → 用户 → Passport.user 等于原用户）
4. FOR ALL invalid credentials, THE AbstractPreAuthenticator 的 `authenticate()` SHALL throw `AuthenticationException`（error condition property）
5. FOR ALL successful authentications, THE `createToken()` output SHALL contain the authenticated user and the user's roles（invariant property：token.user == passport.user, token.roles == user.roles）

### Requirement 15: SecurityConfiguration 配置解析 PBT

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 `SecurityConfiguration` 在各种配置组合下的解析正确性。

#### Acceptance Criteria

1. FOR ALL valid security configurations containing policies, firewalls, access_rules, and role_hierarchy, THE SimpleSecurityProvider 的 `register()` SHALL 成功解析配置而不抛出异常（invariant property）
2. FOR ALL security configurations, THE SimpleSecurityProvider SHALL 保持 programmatic additions 和 config-based settings 的合并顺序：programmatic additions 追加在 config-based settings 之后（confluence property：合并顺序确定）
3. FOR ALL role_hierarchy entries with string values, THE SecurityConfiguration SHALL 自动将 string 转换为单元素数组（round-trip property：`"ROLE_A"` → `["ROLE_A"]`）

### Requirement 16: 现有安全测试适配

**User Story:** 作为开发者，我希望现有的安全相关测试（`ut/Security/`）适配新 authenticator 系统后继续通过。

#### Acceptance Criteria

1. THE Security_Suite 中的 `SecurityServiceProviderTest` SHALL 适配新的 `AuthenticationPolicyInterface`，使用 `getAuthenticator()` 替代 `getAuthenticationProvider()` / `getAuthenticationListener()`
2. THE Security_Suite 中的 `SecurityServiceProviderConfigurationTest` SHALL 继续验证 `SimpleSecurityProvider` 的配置解析行为
3. THE Security_Suite 中的 `NullEntryPointTest` SHALL 继续通过（`NullEntryPoint` 行为未变）
4. THE Integration_Suite 中的 `SecurityAuthenticationFlowIntegrationTest` SHALL 适配新 authenticator 系统，验证完整的认证授权链路

### Requirement 17: 测试套件通过

**User Story:** 作为开发者，我希望 Phase 3 完成后 `security` 和 `integration` 测试套件全部通过，以便确认安全组件重写成功。

#### Acceptance Criteria

1. WHEN `phpunit --testsuite security` is executed, THE test runner SHALL report all tests passing
2. WHEN `phpunit --testsuite integration` is executed, THE test runner SHALL report all tests passing
3. THE PBT tests for security components SHALL be registered in `phpunit.xml` 的 `pbt` 测试套件中
4. WHEN `phpunit --testsuite pbt` is executed, THE security-related PBT tests SHALL pass


---

## Socratic Review

**Q1: Requirements 是否完整覆盖了 goal.md 的所有目标？**
A: 是。goal.md 列出 8 个目标：(1) 适配 authenticator 系统 → R1, R5; (2) 重写 AbstractSimplePreAuthenticator → R1, R7; (3) 重写 AbstractSimplePreAuthenticateUserProvider → R2; (4) 重写 AuthenticationPolicyInterface → R3, R4; (5) 适配 SimpleSecurityProvider → R5; (6) 重写测试辅助类 → R8; (7) 确保行为不变 → R9, R10; (8) 补充 PBT → R11–R15。全部覆盖。

**Q2: Clarification 决策是否已体现在 Requirements 中？**
A: Q1=B（新建抽象类 + 模板方法）→ R1 明确了 AbstractPreAuthenticator 的设计；Q2=B（完全重新设计接口）→ R3 列出了新接口的方法；Q3=A（自管理模式）→ R5 AC6 明确不依赖 SecurityBundle；Q4=B（PBT 含认证流程）→ R11–R15 覆盖了配置层和认证流程。四个决策均已体现。

**Q3: PBT Requirements 是否遵循了 property 分类指南？**
A: R11（access rule round-trip + invariant）、R12（firewall round-trip + invariant + error condition）、R13（role hierarchy idempotence + metamorphic + round-trip）、R14（authenticator metamorphic + round-trip + error condition + invariant）、R15（configuration invariant + confluence + round-trip）。覆盖了 round-trip、invariant、metamorphic、idempotence、error condition、confluence 六种 property 类型，符合指南。

**Q4: 行为不变的 Requirements（R9, R10）是否与 PRP-005 的 Non-Goals 一致？**
A: 一致。R9 和 R10 明确了认证流程和防火墙/授权的外部行为保持不变，不引入新安全功能，不变更安全策略逻辑。与 PRP-005 Non-Goals 完全对齐。

**Q5: 配置层组件（R6）为什么单独列为 Requirement？**
A: 因为 `FirewallInterface`、`AccessRuleInterface`、`SimpleFirewall`、`SimpleAccessRule`、`NullEntryPoint` 在 Phase 1 中未修改且功能正常，但它们是 `SimpleSecurityProvider` 和 authenticator 系统的依赖。明确声明"保持兼容"可以防止在重写过程中意外破坏这些组件，也为 design 阶段提供了明确的不变量约束。

**Q6: R7（旧类废弃）是否必要？为什么不直接删除旧类？**
A: 旧类 `AbstractSimplePreAuthenticator` 可能被下游项目直接引用。标记 `@deprecated` 而非删除，给下游迁移时间。Phase 1 的 stub 行为（抛 `LogicException`）保留，确保未迁移的下游在调用时得到明确错误而非静默失败。注意 `AbstractSimplePreAuthenticationPolicy` 不属于废弃范畴——它在 R4 中被原地重写以适配新接口。

**Q7: R16（现有测试适配）的范围是否清晰？**
A: R16 列出了需要适配的 4 个测试类/文件。适配的核心是将旧 API 调用（`getAuthenticationProvider()`、`getAuthenticationListener()`）替换为新 API（`getAuthenticator()`），以及更新测试辅助类的使用方式。具体的适配细节将在 design 阶段确定。

**Q8: Requirements 之间是否存在矛盾或重叠？**
A: R1–R4 是组件级重写，R5 是集成级重写，R6 是兼容性约束，R7 是废弃策略，R8 是测试辅助类，R9–R10 是行为验证，R11–R15 是 PBT，R16–R17 是测试适配和通过标准。各 Requirement 关注层次不同，无矛盾。R9/R10 与 R11–R14 在安全行为验证上有部分重叠——R9/R10 是功能性行为约束，R11–R14 是通过 PBT 验证这些约束的具体 property，属于互补关系。

**Q9: 与 PRP-005 的 scope 是否一致？**
A: 完全一致。PRP-005 Scope 列出 `src/ServiceProviders/Security/`、`src/Configuration/SecurityConfiguration.php`、`src/Configuration/SimpleAccessRuleConfiguration.php`、`src/Configuration/SimpleFirewallConfiguration.php`、`ut/Security/`、`ut/Helpers/Security/`。Requirements 覆盖了所有这些目录中的组件。PBT 测试将放在 `ut/PBT/` 目录，与现有 PBT 测试一致。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [术语] Glossary 补充 6 个缺失术语：`SecurityConfiguration`、`Security_Authentication_Flow`、`TestApiUserPreAuthenticator`、`TestAuthenticationPolicy`、`TestApiUserProvider`、`TestApiUser`、`TestAccessRule`——这些术语在 AC 中作为 Subject 使用但未定义
- [内容] R1 AC6 `onAuthenticationSuccess()` 的括号注释错误地写了"认证失败不阻断请求"，修正为"认证成功后不产生额外响应"
- [内容] R8 AC2 原文要求 `sig` 不存在时抛出 `BadCredentialsException`，与 R1 AC2 的设计矛盾（`getCredentialsFromRequest()` 返回 null 表示不支持），修正为返回 null
- [语体] R1 AC1 移除 FQCN `Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface`，改用 Glossary 术语 `AuthenticatorInterface`
- [语体] R1 AC8-9 移除方法签名中的参数类型和返回类型声明，改为行为描述
- [语体] R3 AC1-4 移除完整方法签名（含参数类型），改为方法名 + 行为描述，具体签名留给 design 阶段
- [语体] R2 AC5 移除内部变量名 `$supportedUserClassname`，改为行为描述
- [内容] Glossary 中 `AbstractSimplePreAuthenticationPolicy` 的定义错误地标注为"本 Phase 废弃"，实际上 R4 是原地重写而非废弃，已修正
- [内容] Socratic Review Q6 错误地将 `AbstractSimplePreAuthenticationPolicy` 归入废弃类，已修正说明

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表中的术语在正文中使用）
- [x] 无 markdown 格式错误

**结构校验**
- [x] 一级标题 `# Requirements Document` 存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空
- [x] Requirements section 存在且包含 17 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Socratic Review 存在且覆盖充分

**术语表校验**
- [x] Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义（修正后）
- [x] 术语格式为 `- **Term**: 定义`

**Requirement 条款校验**
- [x] 每条 requirement 包含 User Story + Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 使用 THE/WHEN/IF/FOR ALL 语体
- [x] Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续，无跳号

**内容边界校验**
- [x] AC 聚焦外部可观察行为（修正后移除了方法签名中的类型声明）
- [○] 部分 AC 仍包含方法名（如 `getCredentialsFromRequest()`、`getAuthenticator()`）——对于库重构 spec，公共 API 方法名属于外部可观察契约，保留合理

**目的性审查**
- [x] Goal CR 回应：goal.md 中 4 个 Clarification 决策均已体现
- [x] Goal 清晰度：Introduction 清楚传达了 feature 目标
- [x] Non-goal / Scope 边界：明确且与 PRP-005 一致
- [x] 完成标准：R17 定义了测试通过标准，R9-R10 定义了行为不变约束
- [x] 可 design 性：组件边界清晰，接口变更明确，行为约束完整


### Clarification Round

**状态**: 已回答

**Q1:** `SimpleSecurityProvider.register()` 需要将 authenticator 注册到 Symfony Security 7.x 的 firewall 系统。Symfony 7.x 提供了多种集成方式：(a) 手动创建 `AuthenticatorManager` + `FirewallMap` + event listener 组装完整链路；(b) 使用 `AuthenticatorManager` 但复用 Symfony 的 `FirewallEventListener` 简化事件注册；(c) 仅使用 authenticator 的 `authenticate()` 方法，自行在 kernel event listener 中调用。R5 AC3-5 描述了需要做什么，但 design 需要知道集成深度。你倾向哪种方式？
- A) 手动组装完整链路（`AuthenticatorManager` + `FirewallMap` + 自定义 event listener），最大控制力但代码量最大
- B) 使用 `AuthenticatorManager` 管理 authenticator，复用 Symfony 的 `FirewallEventListener` 处理 request 事件，减少自定义代码
- C) 最小集成——在 kernel `request` event listener 中直接调用 authenticator 的 `supports()` + `authenticate()`，不引入 `AuthenticatorManager`
- D) 其他（请说明）

**A:** C — 最小集成。项目保持自管理模式（Q3=A），不引入 `AuthenticatorManager`。在 kernel request event listener 中直接调用 authenticator 的 `supports()` + `authenticate()` + `createToken()`，使用 MicroKernel 已预留的 `BEFORE_PRIORITY_FIREWALL = 8` 优先级位。

**Q2:** R1 AC5 要求 `createToken()` 返回包含用户及其角色的认证 token。Symfony 7.x 中 `AuthenticatorInterface::createToken()` 的默认实现返回 `PostAuthenticationToken`。是否直接使用 `PostAuthenticationToken`，还是需要自定义 token 类以携带额外信息（如原始凭证、认证时间戳等）？
- A) 直接使用 Symfony 的 `PostAuthenticationToken`，不自定义 token 类
- B) 创建自定义 token 类继承 `AbstractToken`，携带原始凭证信息（与旧系统行为更接近）
- C) 先用 `PostAuthenticationToken`，如果集成测试发现需要额外信息再扩展
- D) 其他（请说明）

**A:** C — 先用 `PostAuthenticationToken`，如果集成测试发现需要额外信息再扩展。

**Q3:** R5 AC4 要求注册 firewall listener 到事件系统。当前 `SimpleSecurityProvider` 的 `register()` 方法需要决定 listener 的优先级策略——firewall 认证 listener 应在路由解析之后、controller 执行之前运行。Symfony 的默认优先级是 `RequestEvent` priority 8。是否沿用 Symfony 默认优先级，还是需要自定义优先级以适配项目的 middleware 链？
- A) 沿用 Symfony 默认优先级（priority 8），与标准 Symfony 应用行为一致
- B) 自定义优先级，确保在项目现有 middleware/event listener 之后执行（需要分析现有 listener 优先级）
- C) 使优先级可配置，通过 `SecurityConfiguration` 或 `SimpleSecurityProvider` 的 API 暴露
- D) 其他（请说明）

**A:** B — 自定义优先级。MicroKernel 已定义 `BEFORE_PRIORITY_FIREWALL = 8`，需分析现有 listener 优先级链（routing=32, cors_preflight=20, firewall=8）确保 firewall listener 在正确位置执行。

**Q4:** R9 AC2 和 R1 AC7 都描述了认证失败时 `onAuthenticationFailure()` 返回 null（不阻断请求）。但 Symfony 7.x 的 `AuthenticatorManager` 在 `onAuthenticationFailure()` 返回 null 时的行为取决于 firewall 配置——lazy firewall 会静默跳过，非 lazy firewall 可能抛出异常。项目的 firewall 是否全部配置为 lazy 模式，还是需要在 `onAuthenticationFailure()` 中显式处理非 lazy 场景？
- A) 所有 firewall 均为 lazy 模式，`onAuthenticationFailure()` 返回 null 即可
- B) 需要支持非 lazy firewall，`onAuthenticationFailure()` 应根据 firewall 配置决定是返回 null 还是返回错误响应
- C) 不使用 Symfony 的 lazy firewall 机制，自行在 event listener 中控制认证失败行为
- D) 其他（请说明）

**A:** C — 不使用 Symfony 的 lazy firewall 机制。与 Q1=C 一致，不引入 `AuthenticatorManager` 就没有 lazy firewall 概念。在自定义 event listener 中自行控制认证失败行为：`supports()` 返回 false → 跳过；`authenticate()` 抛异常 → catch 后不设 token，请求继续，由 access rule 决定是否拒绝。
