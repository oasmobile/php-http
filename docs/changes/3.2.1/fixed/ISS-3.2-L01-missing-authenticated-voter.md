# ISS-3.2-L01 Missing AuthenticatedVoter in AccessDecisionManager

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `closed` |
| Found In | `v3.2.0` |
| Fixed In | `v3.2.1` |
| Related Test | |

---

## Description

`SimpleSecurityProvider::register()` 构建 `AccessDecisionManager` 时只注册了 `RoleHierarchyVoter`，缺少 `AuthenticatedVoter`。导致 `MicroKernel::isGranted('IS_AUTHENTICATED_FULLY')` 在用户已通过 firewall 认证后仍返回 `false`。

---

## Steps to Reproduce

1. 创建 `MicroKernel` 实例并注册 security（含 firewall + policy）
2. 启动 kernel，发送带有效认证凭据的请求
3. Firewall 认证成功，`getToken()` 返回 `PostAuthenticationToken`，`getUser()` 返回正确用户
4. 调用 `$kernel->isGranted('IS_AUTHENTICATED_FULLY')`

---

## Expected Behavior

`isGranted('IS_AUTHENTICATED_FULLY')` 返回 `true`。

---

## Actual Behavior

`isGranted('IS_AUTHENTICATED_FULLY')` 返回 `false`。

---

## Analysis

`RoleHierarchyVoter` 只能判断角色属性（如 `ROLE_ADMIN`），无法处理 Symfony Security 的特殊认证属性（`IS_AUTHENTICATED_FULLY`、`IS_AUTHENTICATED_REMEMBERED` 等）。需要在 `AccessDecisionManager` 的 voters 中额外注册 `AuthenticatedVoter`。

相关代码位于 `src/ServiceProviders/Security/SimpleSecurityProvider.php` 的 `register()` 方法：

```php
$roleHierarchyVoter = new RoleHierarchyVoter($roleHierarchy);
$accessDecisionManager = new AccessDecisionManager([$roleHierarchyVoter], new UnanimousStrategy());
```

修复方案：加入 `AuthenticatedVoter`（构造参数为 `$tokenStorage`）：

```php
$authenticatedVoter = new AuthenticatedVoter($tokenStorage);
$accessDecisionManager = new AccessDecisionManager(
    [$authenticatedVoter, $roleHierarchyVoter],
    new UnanimousStrategy()
);
```

影响范围：所有依赖 `isGranted('IS_AUTHENTICATED_FULLY')` 判断用户认证状态的下游代码。

---

## History

- `2026-05-05T12:00Z` `v3.2.0` [修复] hotfix/3.2.1 分支修复，添加 `AuthenticatedVoter` 到 `AccessDecisionManager`
- `2026-05-05T12:00Z` `v3.2.0` [发现] 用户报告 `isGranted('IS_AUTHENTICATED_FULLY')` 在认证成功后仍返回 `false`
