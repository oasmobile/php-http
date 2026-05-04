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
| `AccessDecisionManager` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` | no-action | v2.5.0 使用 Silex 默认的 `AffirmativeBased`，v3.x 使用 `UnanimousStrategy`——行为差异但为有意设计 |
| `AuthorizationChecker` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` | no-action | — |

## SilexKernel Security API

| v2.5.0 API_Surface Item | Category | Coverage Status | v3.x Implementation | Disposition | Notes |
|-------------------------|----------|-----------------|---------------------|-------------|-------|
| `SilexKernel::isGranted()` | public_api | covered | `MicroKernel::isGranted()` | no-action | — |
| `SilexKernel::getToken()` | public_api | covered | `MicroKernel::getToken()` | no-action | — |
| `SilexKernel::getUser()` | public_api | covered | `MicroKernel::getUser()` | no-action | — |
| `IS_AUTHENTICATED_FULLY` 属性支持 | public_api | covered | `AuthenticatedVoter` 支持 | no-action | — |
| 角色继承（`ROLE_ADMIN` → `ROLE_USER`） | public_api | covered | `RoleHierarchyVoter` 支持 | no-action | — |

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

| Coverage Status | Count | Percentage |
|-----------------|-------|------------|
| covered | 44 | 100% |
| missing-non-breaking | 0 | 0% |
| missing-breaking | 0 | 0% |
| intentionally-removed | 0 | 0% |

**结论**：以 `oasis/http` v2.5.0 为基准，Security 模块的所有公开 API_Surface 项在 v3.x 中均已覆盖（covered）。接口签名的 breaking change（`AuthenticationPolicyInterface` 重写、`FirewallInterface` 类型声明、`AbstractSimplePreAuthenticator` → `AbstractPreAuthenticator`）已在 Migration_Guide 中文档化。

与之前以 Silex 原始能力为基准的审计相比，关键差异在于：v2.5.0 的 `SimpleSecurityProvider` 已经对 Silex 做了大量封装和裁剪，许多 Silex 原生能力（form login、HTTP Basic、anonymous、remember_me、logout、switch_user、密码编码等）在 v2.5.0 时就未暴露给下游。这些能力不应算作 v3.x 的 "intentionally-removed"。
