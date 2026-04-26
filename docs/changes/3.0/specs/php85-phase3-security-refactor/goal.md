# Spec Goal: PHP 8.5 Upgrade — Phase 3: Security Component Refactor

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-005-php85-phase3-security-refactor.md`

## 背景摘要

Phase 1 已完成 Silex → Symfony MicroKernel 替换和全部 Symfony 组件升级到 7.x，Phase 2 已完成 Twig 3.x 适配和 Guzzle 7.x 升级。Security 组件在 Phase 1 中仅做了最小可编译适配——移除了 Silex/Pimple 依赖，但 authenticator 系统仍为 stub 状态，无法实际工作。

当前 Security 组件的现状：

- `SimpleSecurityProvider`：已重写为独立类（不再继承 Silex SecurityServiceProvider），保留了配置 API（`addFirewall`、`addAccessRule`、`addAuthenticationPolicy`、`addRoleHierarchy`）和 `register()` 方法，但 **实际的 firewall/authenticator 系统未接入 Symfony Security 7.x**
- `AbstractSimplePreAuthenticator`：原基于 Symfony 4.x 的 `SimplePreAuthenticatorInterface`（已在 Symfony 6.0 移除），Phase 1 将 `createToken()`、`authenticateToken()`、`supportsToken()` 改为 abstract stub，仅保留 `getCredentialsFromRequest()` 抽象方法
- `AbstractSimplePreAuthenticateUserProvider`：实现 `SimplePreAuthenticateUserProviderInterface`（扩展 `UserProviderInterface`），提供 `loadUserByIdentifier()`（抛 LogicException）、`refreshUser()`、`supportsClass()` 的默认实现，核心方法 `authenticateAndGetUser()` 由子类实现
- `AbstractSimplePreAuthenticationPolicy`：实现 `AuthenticationPolicyInterface`，`getAuthenticationProvider()` 和 `getAuthenticationListener()` 为 abstract stub（原依赖 `SimpleAuthenticationProvider` 和 `SimplePreAuthenticationListener`，均在 Symfony 6.0 移除）
- `AuthenticationPolicyInterface`：定义了 `getAuthenticationType()`、`getAuthenticationProvider()`、`getAuthenticationListener()`、`getEntryPoint()` 四个方法，其中 `getAuthenticationProvider()` 返回类型引用了已移除的 `AuthenticationProviderInterface`
- `FirewallInterface`、`AccessRuleInterface`、`SimpleFirewall`、`SimpleAccessRule`：配置层接口和实现，Phase 1 未修改，功能正常
- `NullEntryPoint`：实现 `AuthenticationEntryPointInterface`，功能正常
- 测试辅助类（`ut/Helpers/Security/`）：`TestApiUserPreAuthenticator`、`TestAuthenticationPolicy`、`TestApiUserProvider`、`TestApiUser`、`TestAccessRule` 均为 stub 状态，`createToken()`、`authenticateToken()`、`getAuthenticationProvider()`、`getAuthenticationListener()` 等方法抛出 LogicException

Symfony Security 7.x 的 authenticator 系统与旧系统的核心差异：
- 旧系统：`SimplePreAuthenticatorInterface` + `SimpleAuthenticationProvider` + `ListenerInterface`（三件套）
- 新系统：统一的 `AuthenticatorInterface`（`Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface`），一个接口覆盖凭证提取、认证、成功/失败处理

## 目标

- 将项目中所有自定义安全组件适配到 Symfony Security 7.x 的 authenticator 系统
- 重写 `AbstractSimplePreAuthenticator` 及其子类，使用新的 `AuthenticatorInterface` 替代已移除的 `SimplePreAuthenticatorInterface` 三件套
- 重写 `AbstractSimplePreAuthenticateUserProvider`，确保与新 authenticator 系统协作（`authenticateAndGetUser()` 模式保留，但集成方式适配新 API）
- 重写 `AbstractSimplePreAuthenticationPolicy` 和 `AuthenticationPolicyInterface`，移除对已删除的 `AuthenticationProviderInterface` 和 `ListenerInterface` 的依赖，适配新的 authenticator 注册机制
- 适配 `SimpleSecurityProvider` 的 firewall 和 access rule 注册机制，使其能够将配置正确传递给 Symfony Security 7.x 的 firewall 系统
- 重写测试辅助类（`ut/Helpers/Security/`），使其基于新 authenticator 系统工作
- 确保安全功能行为不变：认证（凭证提取 → 用户查找 → token 生成）、授权（access rule 匹配 → 角色检查）、防火墙规则（pattern 匹配 → policy 分发）
- 大量补充 Property-Based Testing（Eris 1.x，Phase 1 已引入），为 access rule 组合、firewall 匹配、认证策略等建立 property 验证

## 不做的事情（Non-Goals）

- 不引入新的安全功能（如 OAuth、JWT 等）
- 不变更现有的安全策略逻辑（认证失败不阻断请求、授权由 AccessRule 负责拦截的模型保持不变）
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 不涉及 Twig 或 Guzzle 相关工作（Phase 2 已完成）

## Clarification 记录

### Q1: Authenticator 系统适配策略

Symfony 7.x 的 `AuthenticatorInterface` 要求实现 `supports()`、`authenticate()`、`createToken()`、`onAuthenticationSuccess()`、`onAuthenticationFailure()` 等方法。当前项目的 pre-auth 模式（从 request 提取凭证 → 查找用户 → 生成 token）需要映射到新 API。适配策略是什么？

- 选项: A) 原地改造 `AbstractSimplePreAuthenticator`，直接实现 `AuthenticatorInterface` / B) 创建新的抽象类实现 `AuthenticatorInterface`，用模板方法封装复杂性，保留 `getCredentialsFromRequest()` + `authenticateAndGetUser()` 两步模式，废弃旧类 / C) 补充说明
- 回答: B — 创建新的抽象类（如 `AbstractPreAuthenticator`）实现 `AuthenticatorInterface`，内部将 `supports()`、`authenticate()`、`createToken()` 等方法拆解为 `getCredentialsFromRequest()` + `authenticateAndGetUser()` 两步模板方法，子类只需实现这两个方法。旧的 `AbstractSimplePreAuthenticator` 废弃

### Q2: `AuthenticationPolicyInterface` 重新设计

当前 `AuthenticationPolicyInterface` 定义了 `getAuthenticationProvider()`、`getAuthenticationListener()`、`getEntryPoint()` 三个方法，前两个依赖已移除的 Symfony API。新系统中 authenticator 同时承担了 provider 和 listener 的职责。如何重新设计这个接口？

- 选项: A) 最小改动，合并为 `getAuthenticator()` + 保留 `getEntryPoint()` / B) 完全重新设计接口，移除 provider/listener 概念，改为 `getAuthenticator()` + `getAuthenticatorConfig()`（返回 authenticator 的配置选项） / C) 补充说明
- 回答: B — 完全重新设计 `AuthenticationPolicyInterface`，移除 `getAuthenticationProvider()` 和 `getAuthenticationListener()`，改为 `getAuthenticator()`（返回 `AuthenticatorInterface`）+ `getAuthenticatorConfig()`（返回配置选项数组）。保留 `getAuthenticationType()` 和 `getEntryPoint()`

### Q3: `SimpleSecurityProvider` 与 Symfony Security Bundle 的集成深度

当前 `SimpleSecurityProvider` 自行管理 firewall 和 access rule 的注册。Symfony Security 7.x 提供了完整的 SecurityBundle 配置体系。集成深度是什么？

- 选项: A) 保持自管理模式，手动创建 authenticator、注册 firewall listener、配置 access decision manager / B) 部分集成 SecurityBundle 配置机制 / C) 补充说明
- 回答: A — 保持当前的自管理模式，`SimpleSecurityProvider` 继续自行解析配置，手动创建 authenticator、注册 firewall listener、配置 access decision manager，不依赖 SecurityBundle 的 YAML/PHP 配置

### Q4: Property-Based Testing 范围

PRP-005 提到"大量补充 PBT"。Security 组件中哪些部分适合 PBT？

- 选项: A) 仅覆盖配置层（access rule / firewall / role hierarchy）的组合验证 / B) 在 A 的基础上，还包括 authenticator 的凭证提取和认证流程的 property 验证 / C) 补充说明
- 回答: B — 除 access rule 匹配（pattern × roles × channel 组合）、firewall pattern 匹配、role hierarchy 解析（继承链传递性）外，还包括 authenticator 的凭证提取和认证流程的 property 验证

## 约束与决策

- PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- 依赖 Phase 1（Symfony 组件已升级到 7.x）和 Phase 2（Twig + Guzzle 已完成）
- Eris 1.x 已在 Phase 1 引入，本 Phase 是 PBT 的主要产出阶段
- Authenticator 适配采用新建抽象类 + 模板方法模式（Q1=B）：创建 `AbstractPreAuthenticator implements AuthenticatorInterface`，封装 Symfony 新 API 复杂性，子类只需实现 `getCredentialsFromRequest()` + `authenticateAndGetUser()`。旧 `AbstractSimplePreAuthenticator` 废弃
- `AuthenticationPolicyInterface` 完全重新设计（Q2=B）：移除 `getAuthenticationProvider()` / `getAuthenticationListener()`，改为 `getAuthenticator()` + `getAuthenticatorConfig()`，保留 `getAuthenticationType()` 和 `getEntryPoint()`
- `SimpleSecurityProvider` 保持自管理模式（Q3=A）：不引入 SecurityBundle，继续自行解析配置、手动创建 authenticator、注册 firewall listener、配置 access decision manager
- PBT 范围包括配置层和认证流程（Q4=B）：access rule 组合、firewall 匹配、role hierarchy 传递性，以及 authenticator 凭证提取和认证流程的 property 验证
- spec 级 DoD：tasks 全部完成 + PRP-005 中定义的预期通过 suite 实际通过
- 预期通过的 suite（在 Phase 2 基础上新增）：`security`（authenticator 系统重写完成）、`integration`（Security + Twig + 框架完整链路恢复）
- 预期仍失败的测试：PHP 语言层面 deprecation 导致的零星失败（等 Phase 4）——如隐式 nullable 参数、动态属性等触发的 warning/error

## Socratic Review

1. **goal 是否完整覆盖了 PRP-005 的 Goals？**
   - PRP-005 列出 7 个目标：适配 authenticator 系统、重写 AbstractSimplePreAuthenticator、重写 AbstractSimplePreAuthenticateUserProvider、适配 SimpleSecurityProvider、重写接口定义、确保行为不变、补充 PBT。goal 中均已覆盖，且补充了测试辅助类的重写（PRP-005 Scope 中提到 `ut/Helpers/Security/`）。

2. **Non-Goals 是否与 PRP-005 一致？**
   - 一致。PRP-005 明确排除了新安全功能引入、安全策略逻辑变更、PHP 语言层面修复。goal 中已体现，并额外排除了 Phase 2 范围（Twig/Guzzle）。

3. **背景摘要是否准确反映了代码现状？**
   - 是。通过读取 `src/ServiceProviders/Security/` 全部 11 个文件、`ut/Helpers/Security/` 全部 5 个测试辅助类、`src/Configuration/SecurityConfiguration.php` 确认了各组件的实际状态。Phase 1 的 stub 标记（LogicException + "Phase 3 (PRP-005)" 注释）在代码中清晰可见。

4. **Clarification 决策是否已完整体现在约束中？**
   - Q1（新建抽象类 + 模板方法）→ 约束中明确了 `AbstractPreAuthenticator` 的设计和旧类废弃策略；Q2（完全重新设计接口）→ 约束中列出了新接口的方法签名；Q3（自管理模式）→ 约束中明确不引入 SecurityBundle；Q4（PBT 含认证流程）→ 约束中列出了完整的 PBT 范围。四个决策均已体现。

5. **约束与决策是否遗漏了关键信息？**
   - 已包含分支策略、Phase 依赖、四个 Clarification 决策、DoD 定义、预期测试结果。无遗漏。
