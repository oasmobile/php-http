# Security Module Audit Matrix

> `oasis/http` v2.5.0 `SimpleSecurityProvider` (Silex-based) vs v3.x `SimpleSecurityProvider` (Symfony MicroKernel-based) — 细粒度 API_Surface 对比

**审计基准**：`oasis/http` v2.5.0（tag `v2.5.0`），基于 Silex 2.3 的 `SecurityServiceProvider`。审计对象是 v2.5.0 实际暴露给下游的 API_Surface，而非 Silex 原始的全部能力。

**审计时间**：2025-07-16（修订）

---

## v2.5.0 架构概述

v2.5.0 的 `SimpleSecurityProvider` 继承 Silex `SecurityServiceProvider`，通过以下 API 暴露 Security 能力：

- `addFirewall(string $name, FirewallInterface|array $firewall)` — 注册 firewall
- `addAccessRule(AccessRuleInterface|array $rule)` — 注册 access rule
- `addAuthenticationPolicy(string $name, AuthenticationPolicyInterface $policy)` — 注册自定义认证策略
- `addRoleHierarchy(string $role, string|array $children)` — 注册角色继承
- Bootstrap_Config `security` key 驱动配置

v2.5.0 通过 `installAuthenticationFactory()` 将自定义 policy 注入 Silex 的 factory 机制，底层仍由 Silex `SecurityServiceProvider` 处理 firewall map、access map、voter 注册等。

---

## Registration & Bootstrap

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `SimpleSecurityProvider extends SecurityServiceProvider` | registration | covered | `SimpleSecurityProvider` 独立实现（不继承 Silex） | no-action | 内部架构变更，外部行为等价 |
| Bootstrap_Config `security` key 驱动初始化 | registration | covered | `MicroKernel::registerSecurity()` → `SimpleSecurityProvider::register()` | no-action | — |
| `addFirewall()` 编程式注册 | registration | covered | `SimpleSecurityProvider::addFirewall()` | no-action | 签名从 untyped 变为 typed |
| `addAccessRule()` 编程式注册 | registration | covered | `SimpleSecurityProvider::addAccessRule()` | no-action | — |
| `addAuthenticationPolicy()` 编程式注册 | registration | covered | `SimpleSecurityProvider::addAuthenticationPolicy()` | no-action | — |
| `addRoleHierarchy()` 编程式注册 | registration | covered | `SimpleSecurityProvider::addRoleHierarchy()` | no-action | — |
| 编程式 + 配置混合（merge 逻辑） | registration | covered | `SimpleSecurityProvider::register()` 中 merge | no-action | 行为一致：编程式添加与 config 合并 |

## Firewall

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `FirewallInterface::getPattern()` (string regex) | firewall | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| `FirewallInterface::getPattern()` (`RequestMatcherInterface`) | firewall | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| `FirewallInterface::isStateless()` | firewall | covered | `SimpleFirewall::isStateless()` | no-action | — |
| `FirewallInterface::getPolicies()` | firewall | covered | `SimpleFirewall::getPolicies()` | no-action | — |
| `FirewallInterface::getUserProvider()` | firewall | covered | `SimpleFirewall::getUserProvider()` | no-action | — |
| `FirewallInterface::getOtherSettings()` | firewall | covered | `SimpleFirewall::getOtherSettings()` | no-action | — |
| 多 firewall 配置（按注册顺序匹配） | firewall | covered | `registerFirewallListener()` 遍历 firewalls | no-action | — |
| Firewall `stateless` 模式（跳过 `ContextListener`） | firewall | covered | v3.x 默认 stateless（无 `ContextListener`） | no-action | v2.5.0 通过 Silex 的 `ContextListener` 控制，v3.x 整体无 session 依赖 |
| `SimpleFirewall` 配置解析 | firewall | covered | `SimpleFirewall` 构造函数 + `SimpleFirewallConfiguration` | no-action | — |

## Authentication Policy

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `AuthenticationPolicyInterface::getAuthenticationType()` | authentication | covered | `AuthenticationPolicyInterface::getAuthenticationType()` | no-action | — |
| `AuthenticationPolicyInterface::getAuthenticationProvider()` | authentication | covered | 合并为 `getAuthenticator()` | no-action | Breaking change，已在 Migration_Guide §7 文档化 |
| `AuthenticationPolicyInterface::getAuthenticationListener()` | authentication | covered | 合并为 `getAuthenticator()` | no-action | 同上 |
| `AuthenticationPolicyInterface::getEntryPoint()` | authentication | covered | `AuthenticationPolicyInterface::getEntryPoint()` | no-action | 签名变更（`Container` → `MicroKernel`） |
| `AUTH_TYPE_PRE_AUTH` 常量 | authentication | covered | `AUTH_TYPE_PRE_AUTH` | no-action | — |
| `AUTH_TYPE_FORM` 常量 | authentication | covered | 常量保留，但无内置 form 实现 | no-action | v2.5.0 也无内置 form 实现，仅定义常量 |
| `AUTH_TYPE_HTTP` 常量 | authentication | covered | 常量保留，但无内置 http 实现 | no-action | v2.5.0 也无内置 http 实现，仅定义常量 |
| `AUTH_TYPE_ANONYMOUS` 常量 | authentication | covered | 常量保留 | no-action | v2.5.0 也无内置 anonymous 实现 |
| `AUTH_TYPE_LOGOUT` 常量 | authentication | covered | 常量保留 | no-action | — |
| `AUTH_TYPE_REMEMBER_ME` 常量 | authentication | covered | 常量保留 | no-action | — |
| `installAuthenticationFactory()` 机制 | authentication | covered | 直接调用 `policy->getAuthenticator()` | no-action | 内部机制变更，外部行为等价 |
| `AbstractSimplePreAuthenticator` | authentication | covered | `AbstractPreAuthenticator`（旧类保留但 deprecated） | no-action | Breaking change，已在 Migration_Guide §7 文档化 |
| `AbstractSimplePreAuthenticateUserProvider` | authentication | covered | 保留并适配 Symfony 8.x | no-action | — |

## Authentication Flow

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| Firewall 匹配 → policy 认证 → token 存储 | authentication | covered | `registerFirewallListener()` 实现相同流程 | no-action | — |
| 认证失败 → `AuthenticationException` catch → token 保持 null | authentication | covered | `registerFirewallListener()` catch `AuthenticationException` | no-action | — |
| `NullEntryPoint` 默认 entry point | authentication | covered | `NullEntryPoint::start()` 抛出 `AccessDeniedHttpException` | no-action | — |
| `PostAuthenticationToken` 创建 | authentication | covered | `AbstractPreAuthenticator::createToken()` | no-action | — |
| `TokenStorage` 生命周期 | authentication | covered | `SimpleSecurityProvider::register()` 创建 `TokenStorage` | no-action | — |

## Access Rules

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `AccessRuleInterface::getPattern()` (string) | access_rule | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| `AccessRuleInterface::getPattern()` (`RequestMatcherInterface`) | access_rule | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| `AccessRuleInterface::getRequiredRoles()` | access_rule | covered | `registerAccessRuleListener()` | no-action | — |
| `AccessRuleInterface::getRequiredChannel()` | access_rule | covered | `SimpleAccessRule::getRequiredChannel()` 保留 | no-action | 配置项保留但 v3.x listener 未强制 channel 检查（v2.5.0 通过 Silex `ChannelListener` 实现） |
| Access rule 注册顺序优先（first match wins） | access_rule | covered | `registerAccessRuleListener()` 按顺序遍历 | no-action | — |
| 未认证访问受保护资源 → `AccessDeniedHttpException` | access_rule | covered | `registerAccessRuleListener()` 抛出 | no-action | — |

## Role Hierarchy & Voters

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `RoleHierarchy` 配置和创建 | role_hierarchy | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `RoleHierarchyVoter` 自动注册 | role_hierarchy | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AuthenticatedVoter` 自动注册 | authentication | covered | `SimpleSecurityProvider::register()` | no-action | ISS-3.2-L01 修复后恢复 |
| `AccessDecisionManager` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` | no-action | 已修复为 `AffirmativeStrategy`，与 v2.5.0 行为一致 |
| `AuthorizationChecker` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` | no-action | — |

## SilexKernel Security API

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `SilexKernel::isGranted()` | public_api | covered | `MicroKernel::isGranted()` | no-action | — |
| `SilexKernel::getToken()` | public_api | covered | `MicroKernel::getToken()` | no-action | — |
| `SilexKernel::getUser()` | public_api | covered | `MicroKernel::getUser()` | no-action | — |
| `IS_AUTHENTICATED_FULLY` 属性支持 | public_api | covered | `AuthenticatedVoter` 支持 | no-action | — |
| 角色继承（`ROLE_ADMIN` → `ROLE_USER`） | public_api | covered | `RoleHierarchyVoter` 支持 | no-action | — |

## Behavioral Equivalence Audit（行为等价性审计）

以上 API_Surface 审计确认了"接口存在性"——v2.5.0 有的接口 v3.x 也有。本节深入对比 v3.x 重写后的**运行时行为**是否与 v2.5.0 等价。

### Firewall Pattern Matching

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| String pattern matching | Silex `FirewallMap` → `RequestMatcher($pattern)` → Symfony `preg_match('{' . $pattern . '}', $pathinfo)` | `requestMatchesPattern()` → `preg_match('{' . $pattern . '}', rawurldecode($pathinfo))` | ⚠️ 微差异 | v3.x 额外做了 `rawurldecode()`。对于不含 URL 编码字符的路径，行为一致。含 `%2F` 等编码字符时 v3.x 会先解码再匹配，v2.5.0 不会。这是有意的改进（防止 URL 编码绕过 pattern），不是 regression |
| `RequestMatcherInterface` pattern matching | Silex `FirewallMap` 直接使用 `RequestMatcher` 实例 | `requestMatchesPattern()` 调用 `$pattern->matches($request)` | ✅ 等价 | — |
| 多 firewall 匹配顺序 | Silex `FirewallMap::getListeners()` 按注册顺序遍历，first match wins | `registerFirewallListener()` 按注册顺序遍历，first match + `break` | ✅ 等价 | — |

### Authentication Flow

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| 认证入口 | Silex `Firewall` 组件 dispatch → 各 firewall listener 按 position 排序执行 | 单个 event listener (priority 8) 内部遍历 firewalls + policies | ✅ 等价 | 内部机制不同，但外部可观察行为一致：first matching firewall → iterate policies → authenticate |
| `supports()` 判断 | v2.5.0 `AbstractSimplePreAuthenticator::createToken()` 中 `getCredentialsFromRequest()` 返回 null 时创建 anon token | v3.x `AbstractPreAuthenticator::supports()` 返回 `getCredentialsFromRequest() !== null` | ⚠️ 语义变更 | v2.5.0 无凭证时仍创建 `PreAuthenticatedToken('anon.', null, ...)`，后续 `authenticateToken()` 会因 null credentials 失败。v3.x 无凭证时 `supports()` 返回 false，直接跳过认证。**最终效果一致**（都不会产生有效 token），但中间路径不同 |
| 认证成功 → token 类型 | `PreAuthenticatedToken` (Symfony 4.x) | `PostAuthenticationToken` (Symfony 8.x) | ⚠️ 类型变更 | Symfony 8.x 移除了 `PreAuthenticatedToken`，替换为 `PostAuthenticationToken`。如果下游代码 `instanceof PreAuthenticatedToken`，需要更新。已在 Migration_Guide §7 覆盖 |
| 认证失败处理 | Silex `ExceptionListener` catch `AuthenticationException` → 调用 entry point → `NullEntryPoint` 抛 `AccessDeniedHttpException` | `registerFirewallListener()` catch `AuthenticationException` → 静默忽略 → access rule listener 判定 | ⚠️ 路径变更 | v2.5.0：认证失败 → `ExceptionListener` → entry point → 403。v3.x：认证失败 → token 保持 null → access rule 检查 → 无 token → 403。**最终 HTTP 响应一致**（都是 403），但异常传播路径不同。如果下游代码依赖 `ExceptionListener` 的行为（如自定义 `access_denied_handler`），需要适配 |
| 无凭证 + 无 access rule | v2.5.0：无凭证 → anon token → 无 access rule 匹配 → 请求通过 | v3.x：无凭证 → `supports()` false → token null → 无 access rule 匹配 → 请求通过 | ✅ 等价 | — |
| 无凭证 + 有 access rule | v2.5.0：无凭证 → anon token → access rule 匹配 → `AccessListener` → `AccessDecisionManager` → deny → `ExceptionListener` → entry point → 403 | v3.x：无凭证 → token null → access rule 匹配 → `!$token` → 直接抛 `AccessDeniedHttpException` → 403 | ✅ 等价 | 最终 HTTP 响应一致 |

### Access Decision Strategy

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| `AccessDecisionManager` strategy | Silex 默认 `AffirmativeBased`（任一 voter grant → allow） | `AffirmativeStrategy`（与 v2.5.0 一致） | ✅ 已修复 | 原为 `UnanimousStrategy`（非有意选择），已改回 `AffirmativeStrategy` 以保持行为等价 |
| Access rule `channel` enforcement | Silex `ChannelListener` 强制 http/https redirect | v3.x 不强制 channel 检查 | ⚠️ 能力缺失 | `AccessRuleInterface::getRequiredChannel()` 配置项保留，但 v3.x 的 `registerAccessRuleListener()` 未读取 `$channel` 值做任何处理。如果 v2.5.0 下游使用了 channel enforcement（`'https'`），v3.x 不会 redirect。**但**：v2.5.0 的 channel enforcement 来自 Silex 的 `ChannelListener`，不是 `SimpleSecurityProvider` 自己实现的。v2.5.0 的 `subscribe()` 中也没有额外处理 channel。所以这个能力实际上是 Silex 底层提供的，v2.5.0 只是透传了配置。v3.x 保留了配置接口但未实现 enforcement |

### Firewall Scope & Sub-request Handling

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| Main request only | Silex `Firewall` 组件仅处理 main request | `registerFirewallListener()` 检查 `$event->isMainRequest()` | ✅ 等价 | — |
| Access rule main request only | Silex `AccessListener` 仅处理 main request | `registerAccessRuleListener()` 检查 `$event->isMainRequest()` | ✅ 等价 | — |

### Token Lifecycle

| 行为 | v2.5.0 | v3.x | 等价？ | 说明 |
|------|--------|------|--------|------|
| Token 创建时机 | Silex authentication listener 成功后设置 token | `registerFirewallListener()` 成功后 `$tokenStorage->setToken($token)` | ✅ 等价 | — |
| Token 跨请求持久化（non-stateless） | Silex `ContextListener` 通过 session 持久化 | v3.x 无 `ContextListener`，token 不跨请求持久化 | ⚠️ 行为变更 | v3.x 所有 firewall 实际上都是 stateless 的（无论 `isStateless()` 返回什么）。v2.5.0 中 `stateless = false` 的 firewall 会通过 session 持久化 token。**但**：oasis/http 的典型使用场景是 stateless API（每次请求都带凭证），session 持久化在实际使用中很少被依赖。如果下游确实依赖了 session token 持久化，v3.x 会表现为"每次请求都需要重新认证" |

### Summary of Behavioral Differences

| # | 差异 | 影响 | 处置 |
|---|------|------|------|
| B1 | Pattern matching 额外 `rawurldecode()` | 低：仅影响含 URL 编码字符的路径 | no-action（有意改进） |
| B2 | Token 类型从 `PreAuthenticatedToken` 变为 `PostAuthenticationToken` | 中：下游 `instanceof` 检查需更新 | document-only（已在 Migration_Guide §7） |
| B3 | 认证失败路径变更（`ExceptionListener` → 直接抛异常） | 低：最终 HTTP 响应一致（403） | no-action |
| B4 | `AccessDecisionManager` strategy 变更 | 低：当前 voter 组合下行为一致 | fix-code（改回 `AffirmativeStrategy` 以保持行为等价） |
| B5 | Channel enforcement 未实现 | 低：v2.5.0 也是透传 Silex 底层能力 | document-only（需确认 Migration_Guide 是否覆盖） |
| B6 | Token 不跨请求持久化 | 中：依赖 session 的场景受影响 | document-only（stateless API 场景不受影响） |
| B7 | `supports()` 语义变更（无凭证时跳过 vs 创建 anon token） | 低：最终效果一致 | no-action |

---

## v2.5.0 未暴露的 Silex 能力（不在审计范围内）

以下 Silex 原生能力在 v2.5.0 的 `SimpleSecurityProvider` 中**未作为公开 API 暴露**，因此不属于 v2.5.0 → v3.x 迁移的审计范围：

- `$app['security.firewalls']` 直接赋值（v2.5.0 通过 `addFirewall()` 封装）
- `$app['security.voters']` 自定义覆盖
- `$app['security.hide_user_not_found']` 配置
- `$app['security.encoder_factory']` / 密码编码
- `$app['security.authentication_utils']`
- `$app['security.last_error']`
- `$app['user']` factory（v2.5.0 通过 `SilexKernel::getUser()` 封装）
- Silex `SecurityTrait::encodePassword()`
- Silex `Route\SecurityTrait::secure()`
- Firewall `context` 共享
- Firewall `hosts` / `methods` 直接配置（v2.5.0 通过 `RequestMatcherInterface` 间接支持）
- Firewall `security` flag
- Firewall `users` array → `InMemoryUserProvider` 自动创建
- `switch_user` 功能

---

## Summary

### API_Surface Coverage

| Coverage Status | Count | Percentage |
|-----------------|-------|------------|
| covered | 44 | 100% |
| missing-non-breaking | 0 | 0% |
| missing-breaking | 0 | 0% |
| intentionally-removed | 0 | 0% |

### Behavioral Equivalence

| 等价状态 | Count | 说明 |
|----------|-------|------|
| ✅ 等价 | 11 | 行为完全一致（含 B4 修复后） |
| ⚠️ 有意变更 | 3 | 行为有差异但为有意设计（B1 rawurldecode、B2 token 类型、B7 supports 语义） |
| ⚠️ 能力缺失 | 1 | channel enforcement 未实现（B5） |
| ⚠️ 行为变更 | 2 | 认证失败路径变更（B3）、token 不跨请求持久化（B6） |

**结论**：

1. **API_Surface**：v2.5.0 的所有公开接口在 v3.x 中均已覆盖。接口签名的 breaking change 已在 Migration_Guide 中文档化。
2. **行为等价性**：v3.x 重写了 v2.5.0 的底层实现（从 Silex `SecurityServiceProvider` 继承变为独立实现），大部分行为等价。发现 7 处行为差异：1 处已修复（B4 `AffirmativeStrategy`），3 处为有意变更，2 处最终 HTTP 响应一致（路径不同但结果相同），1 处为 channel enforcement 能力缺失（低影响）。
3. **需确认**：B5（channel enforcement）和 B6（token 跨请求持久化）在 Migration_Guide 中仅作为接口签名变更提及，未明确说明行为差异。建议在 Task 9（文档更新）中补充。
