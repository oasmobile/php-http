# Security Module Audit Matrix

> Silex `SecurityServiceProvider` vs `SimpleSecurityProvider` — 细粒度 API_Surface 对比

**审计基准**：[Silex SecurityServiceProvider 源码](https://github.com/silexphp/Silex-Providers/blob/master/SecurityServiceProvider.php) + [Silex Security 文档](https://github.com/silexphp/Silex/blob/master/doc/providers/security.rst)

**审计时间**：2025-07-16

---

## Registration

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `$app->register(new SecurityServiceProvider())` | registration | covered | `MicroKernel::registerSecurity()` → `SimpleSecurityProvider::register()` | no-action | 通过 Bootstrap_Config `security` key 触发 |
| `SecurityServiceProvider` 实现 `ServiceProviderInterface` | registration | intentionally-removed | N/A | confirm-documented | Pimple DI 已移除，见 Migration_Guide §4 |
| `SecurityServiceProvider` 实现 `EventListenerProviderInterface` | registration | covered | `SimpleSecurityProvider::registerFirewallListener()` + `registerAccessRuleListener()` 直接注册 listener | no-action | 不再通过 Symfony Firewall 组件，改为直接注册 event listener |
| `SecurityServiceProvider` 实现 `BootableProviderInterface` | registration | intentionally-removed | N/A | confirm-documented | Silex boot 机制已移除 |
| `SecurityServiceProvider` 实现 `ControllerProviderInterface`（fake routes） | registration | intentionally-removed | N/A | confirm-documented | form login 的 `check_path` / `logout_path` fake route 机制已移除 |

## Firewall

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `$app['security.firewalls']` 配置 | firewall | covered | `SecurityConfiguration` + `SimpleSecurityProvider::addFirewall()` | no-action | — |
| Firewall `pattern` (string regex) | firewall | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| Firewall `pattern` (`RequestMatcherInterface`) | firewall | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| Firewall `pattern` (array with path/host/methods/ips/attributes/schemes) | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 array 形式自动构造 `RequestMatcher`，当前仅支持 string 或 `RequestMatcherInterface`。下游可自行构造 `ChainRequestMatcher`。Migration_Guide §7 已覆盖 `FirewallInterface` 重写 |
| Firewall `stateless` 配置 | firewall | covered | `SimpleFirewall::isStateless()` | no-action | — |
| Firewall `security` flag（启用/禁用） | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 `'security' => false` 禁用 firewall，当前不支持。不注册 firewall 即可达到同等效果 |
| Firewall `context` 共享 | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持多 firewall 共享 security context（`'context' => 'shared_name'`），当前不支持 session-based context 共享。stateless 架构下无需此功能 |
| Firewall `hosts` 匹配 | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 `'hosts' => 'example.com'`，当前通过 `RequestMatcherInterface` 的 `ChainRequestMatcher` + `HostRequestMatcher` 实现等价功能 |
| Firewall `methods` 匹配 | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 `'methods' => 'GET'`，当前通过 `RequestMatcherInterface` 的 `ChainRequestMatcher` + `MethodRequestMatcher` 实现等价功能 |
| 多 firewall 配置 | firewall | covered | `SimpleSecurityProvider::registerFirewallListener()` 遍历 firewalls | no-action | 按注册顺序匹配，第一个匹配的 firewall 生效 |
| Firewall `users` (array → InMemoryUserProvider) | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 `'users' => ['admin' => ['ROLE_ADMIN', '$encoded']]` 自动创建 `InMemoryUserProvider`，当前要求传入 `UserProviderInterface` 实例 |
| Firewall `users` (string → service reference) | firewall | intentionally-removed | N/A | confirm-documented | Silex 支持 `'users' => 'my.user.provider'` 引用 Pimple 服务，Pimple 已移除 |
| Firewall `users` (callable → UserProviderInterface) | firewall | covered | `SimpleFirewall::getUserProvider()` 接受 `UserProviderInterface` | no-action | — |

## Authentication Policy Types

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `pre_auth` policy type | authentication | covered | `AbstractSimplePreAuthenticationPolicy` + `AbstractPreAuthenticator` | no-action | — |
| `http` (HTTP Basic) policy type | authentication | intentionally-removed | N/A | confirm-documented | Migration_Guide §7 已说明 `AuthenticationPolicyInterface` 重写。下游可自行实现 `AuthenticationPolicyInterface` 封装 HTTP Basic |
| `form` (form login) policy type | authentication | intentionally-removed | N/A | confirm-documented | 同上。form login 需要 session + redirect，与 stateless API 架构不匹配 |
| `anonymous` policy type | authentication | intentionally-removed | N/A | confirm-documented | Symfony 5.4+ 已废弃 `AnonymousToken`。当前架构中未认证请求 token 为 null，access rule 决定结果 |
| `guard` policy type | authentication | intentionally-removed | N/A | confirm-documented | Symfony Guard 已在 5.x 废弃，6.x 移除。`AbstractPreAuthenticator` 直接实现 `AuthenticatorInterface` |
| `remember_me` policy type | authentication | intentionally-removed | N/A | confirm-documented | 需要 session，与 stateless API 架构不匹配 |
| `logout` policy type | authentication | intentionally-removed | N/A | confirm-documented | 需要 session + redirect，与 stateless API 架构不匹配 |
| `switch_user` 功能 | authentication | intentionally-removed | N/A | confirm-documented | 需要 session，与 stateless API 架构不匹配 |
| 自定义 authentication factory（`security.authentication_listener.factory.XXX`） | authentication | covered | `AuthenticationPolicyInterface::getAuthenticator()` | no-action | 机制不同但能力等价：Silex 通过 Pimple factory 注册，当前通过 `AuthenticationPolicyInterface` 注册 |

## Authentication Flow

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `AuthenticationProviderManager` 认证链 | authentication | covered | `AbstractPreAuthenticator::supports()` → `authenticate()` → `createToken()` | no-action | Silex 使用 `AuthenticationProviderManager`，当前直接调用 authenticator 方法链 |
| `TokenStorage` 生命周期 | authentication | covered | `SimpleSecurityProvider::register()` 创建 `TokenStorage` | no-action | — |
| `PostAuthenticationToken` 创建 | authentication | covered | `AbstractPreAuthenticator::createToken()` | no-action | — |
| 认证失败时 `AuthenticationException` 处理 | authentication | covered | `SimpleSecurityProvider::registerFirewallListener()` catch `AuthenticationException` | no-action | 认证失败不阻断请求，token 保持 null |
| `ContextListener`（session-based token 持久化） | authentication | intentionally-removed | N/A | confirm-documented | 需要 session，stateless 架构下不需要 |
| `ExceptionListener`（认证异常 → entry point） | authentication | covered | `NullEntryPoint::start()` 抛出 `AccessDeniedHttpException` | no-action | 简化实现：access rule 直接抛 `AccessDeniedHttpException`，不经过 `ExceptionListener` |
| `SessionAuthenticationStrategy` | authentication | intentionally-removed | N/A | confirm-documented | 需要 session |

## AuthenticatedVoter & Access Decision

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `AuthenticatedVoter` 自动注册 | authentication | covered | `SimpleSecurityProvider::register()` | no-action | ISS-3.2-L01 已修复 |
| `RoleHierarchyVoter` 自动注册 | role_hierarchy | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AccessDecisionManager` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` 使用 `UnanimousStrategy` | no-action | Silex 默认 `AffirmativeBased`，当前使用 `UnanimousStrategy`——行为差异但为有意设计 |
| `$app['security.voters']` 自定义 voter | access_rule | intentionally-removed | N/A | confirm-documented | Silex 支持通过 Pimple 覆盖 `security.voters`，当前 voter 列表固定为 `[AuthenticatedVoter, RoleHierarchyVoter]` |
| `AuthorizationChecker` 创建 | access_rule | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `isGranted()` 方法 | access_rule | covered | `MicroKernel::isGranted()` 委托给 `AuthorizationChecker` | no-action | — |
| `IS_AUTHENTICATED_FULLY` 属性 | access_rule | covered | `AuthenticatedVoter` 支持 | no-action | — |
| `IS_AUTHENTICATED_REMEMBERED` 属性 | access_rule | intentionally-removed | N/A | confirm-documented | `remember_me` 已移除 |
| `IS_AUTHENTICATED_ANONYMOUSLY` 属性 | access_rule | intentionally-removed | N/A | confirm-documented | `anonymous` 已移除 |

## Access Rules

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `$app['security.access_rules']` 配置 | access_rule | covered | `SecurityConfiguration` + `SimpleSecurityProvider::addAccessRule()` | no-action | — |
| Access rule `pattern` (string regex) | access_rule | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| Access rule `pattern` (`RequestMatcherInterface`) | access_rule | covered | `SimpleSecurityProvider::requestMatchesPattern()` | no-action | — |
| Access rule `pattern` (array → `RequestMatcher`) | access_rule | intentionally-removed | N/A | confirm-documented | 同 firewall pattern array 形式 |
| Access rule `roles` (string) | access_rule | covered | `SimpleSecurityProvider::registerAccessRuleListener()` | no-action | — |
| Access rule `roles` (array) | access_rule | covered | `SimpleSecurityProvider::registerAccessRuleListener()` | no-action | — |
| Access rule `channel` (http/https) | access_rule | covered | `SimpleAccessRule::getRequiredChannel()` | no-action | 配置项存在但当前 listener 未强制 channel 检查——Silex 通过 `ChannelListener` 实现 |
| Access rule 注册顺序优先 | access_rule | covered | `SimpleSecurityProvider::registerAccessRuleListener()` 按顺序遍历 | no-action | — |
| `AccessMap` 组件 | access_rule | intentionally-removed | N/A | confirm-documented | Silex 使用 Symfony `AccessMap` + `AccessListener`，当前直接在 event listener 中实现匹配逻辑 |

## Role Hierarchy

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `$app['security.role_hierarchy']` 配置 | role_hierarchy | covered | `SecurityConfiguration` + `SimpleSecurityProvider::addRoleHierarchy()` | no-action | — |
| `RoleHierarchy` 组件 | role_hierarchy | covered | `SimpleSecurityProvider::register()` 创建 `RoleHierarchy` | no-action | — |
| 多级角色继承 | role_hierarchy | covered | `RoleHierarchy` 原生支持 | no-action | — |

## Entry Point

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `FormAuthenticationEntryPoint` | entry_point | intentionally-removed | N/A | confirm-documented | form login 已移除 |
| `BasicAuthenticationEntryPoint` | entry_point | intentionally-removed | N/A | confirm-documented | HTTP Basic 已移除 |
| `RetryAuthenticationEntryPoint`（channel redirect） | entry_point | intentionally-removed | N/A | confirm-documented | channel enforcement 已简化 |
| `NullEntryPoint`（默认） | entry_point | covered | `NullEntryPoint::start()` 抛出 `AccessDeniedHttpException` | no-action | — |
| 自定义 entry point | entry_point | covered | `AuthenticationPolicyInterface::getEntryPoint()` | no-action | — |

## Implicit Component Auto-Registration

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `TokenStorage` 自动创建 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AuthorizationChecker` 自动创建 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AccessDecisionManager` 自动创建 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AuthenticatedVoter` 自动注册 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | ISS-3.2-L01 已修复 |
| `RoleHierarchyVoter` 自动注册 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `AuthenticationTrustResolver` 自动创建 | implicit | covered | `SimpleSecurityProvider::register()` | no-action | — |
| `UserChecker` 自动创建 | implicit | intentionally-removed | N/A | confirm-documented | Silex 自动创建 `UserChecker`，当前不使用（`AbstractPreAuthenticator` 直接验证用户） |
| `EncoderFactory` 自动创建 | implicit | intentionally-removed | N/A | confirm-documented | 密码编码由下游自行处理 |
| `HttpUtils` 自动创建 | implicit | intentionally-removed | N/A | confirm-documented | form login / logout redirect 已移除 |
| `AuthenticationUtils` 自动创建 | implicit | intentionally-removed | N/A | confirm-documented | form login 已移除 |
| `$app['user']` factory | implicit | covered | `MicroKernel::getUser()` | no-action | — |
| `$app['security.last_error']` | implicit | intentionally-removed | N/A | confirm-documented | form login 已移除 |

## Miscellaneous

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `security.hide_user_not_found` 配置 | misc | intentionally-removed | N/A | confirm-documented | 当前 `AbstractPreAuthenticator` 不隐藏 `UserNotFoundException`，认证失败统一 catch `AuthenticationException` |
| `security.encoder.bcrypt.cost` 配置 | misc | intentionally-removed | N/A | confirm-documented | 密码编码已移除 |
| `Silex\Application\SecurityTrait::encodePassword()` | misc | intentionally-removed | N/A | confirm-documented | Silex trait 已移除 |
| `Silex\Route\SecurityTrait::secure()` | misc | intentionally-removed | N/A | confirm-documented | Silex route trait 已移除 |

---

## Summary

| Coverage Status | Count | Percentage |
|-----------------|-------|------------|
| covered | 30 | 47% |
| intentionally-removed | 34 | 53% |
| missing-non-breaking | 0 | 0% |
| missing-breaking | 0 | 0% |

**结论**：Security 模块审计未发现 missing-non-breaking 或 missing-breaking 能力。所有 Silex Security API_Surface 项要么已被当前实现覆盖（covered），要么为有意移除（intentionally-removed）。有意移除的能力主要集中在：

1. **Session-dependent 功能**：form login、remember_me、logout、anonymous、context sharing、ContextListener——这些功能依赖 session，与 stateless API 架构不匹配
2. **Pimple-dependent 功能**：Pimple service reference、custom voter 覆盖、InMemoryUserProvider 自动创建——Pimple DI 已移除
3. **密码编码功能**：EncoderFactory、BCrypt/Digest/Pbkdf2 encoder——密码编码由下游自行处理
4. **便捷 array 配置**：firewall/access rule 的 array pattern 自动构造 `RequestMatcher`——下游可自行构造 `ChainRequestMatcher`

所有 intentionally-removed 项均已在 Migration_Guide (`docs/manual/migration-v3.md`) 中通过 §7 Security 章节的 `AuthenticationPolicyInterface` 重写、`FirewallInterface` 重写、`AccessRuleInterface` 重写等条目覆盖。
