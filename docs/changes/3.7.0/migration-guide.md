# Migration Guide — v3.7.0

本文件为 v3.7.0 的迁移指南，说明 SecurityTrait 新增 API 和 RoutingTrait 接口变更。

---

## Overview

v3.7.0 为 `MicroKernel` 新增 pre-boot security config 注入 API（`SecurityTrait`），使 ServiceProvider 在 `register()` 阶段可编程式注入 firewalls、access_rules、policies、role_hierarchy。同时 `RoutingTrait` 的 `addRoute()` / `addRoutes()` 新增 `$allowOverwrite` 参数以支持 fail-fast 冲突检测。

**向后兼容性**：本版本所有变更均向后兼容，现有代码无需修改即可正常工作。

---

## 新增 API — SecurityTrait

以下方法新增于 `MicroKernel`，由 `src/Kernel/SecurityTrait.php` 提供：

### `addSecurityConfig(array $config, bool $allowOverwrite = false): void`

批量注入 security config。接受与构造函数 `$httpConfig['security']` 相同结构的配置片段。

```php
$kernel->addSecurityConfig([
    'firewalls' => [
        'api' => [
            'pattern' => '^/api',
            'stateless' => true,
        ],
    ],
    'access_rules' => [
        ['path' => '^/api/admin', 'allowed-roles' => ['ROLE_ADMIN']],
    ],
]);
```

- 仅允许顶层 key：`firewalls`、`access_rules`、`policies`、`role_hierarchy`，未知 key 抛 `InvalidArgumentException`
- 同名 firewall/policy/role 默认抛 `LogicException`（除非 `$allowOverwrite = true`）
- boot 后调用抛 `LogicException`

### `addFirewall(string $name, array $config, bool $allowOverwrite = false): void`

注入单个 firewall 配置。

```php
$kernel->addFirewall('api', [
    'pattern' => '^/api',
    'stateless' => true,
]);
```

### `addAccessRule(array $rule): void`

注入单条 access rule。始终按注册顺序追加，无冲突概念。

```php
$kernel->addAccessRule([
    'path' => '^/api/admin',
    'allowed-roles' => ['ROLE_ADMIN'],
]);
```

### `addPolicy(string $name, mixed $config, bool $allowOverwrite = false): void`

注入单个 policy 配置。

```php
$kernel->addPolicy('is_owner', ['class' => OwnerPolicy::class]);
```

### `addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false): void`

注入单个角色层级映射。

```php
$kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER', 'ROLE_MODERATOR']);
```

### `getSecurityConfig(): array`

只读查询，返回 Constructor_Config + Pending_Queue 的合并视图。用于 ServiceProvider 做条件注入。

```php
$current = $kernel->getSecurityConfig();
if (!isset($current['firewalls']['api'])) {
    $kernel->addFirewall('api', [...]);
}
```

- boot 后调用抛 `LogicException`

---

## RoutingTrait 接口变更

### `addRoute(string $name, Route $route, bool $allowOverwrite = true): void`

新增第三个参数 `$allowOverwrite`，默认 `true`（向后兼容）。

### `addRoutes(RouteCollection $routes, bool $allowOverwrite = true): void`

新增第三个参数 `$allowOverwrite`，默认 `true`（向后兼容）。

**行为说明**：

| `$allowOverwrite` | 遇到同名路由时 |
|---|---|
| `true`（默认） | 静默覆盖，与 v3.6.x 行为一致 |
| `false` | 抛 `LogicException`，fail-fast 冲突检测 |

现有调用方不传第三个参数时行为完全不变。

---

## 从 Workaround 迁移到新 API

### 旧方式（workaround）

v3.6.x 中 ServiceProvider 无法在 `register()` 阶段注入 security config，需要在构造 Kernel 前手动组装完整配置：

```php
// 旧方式：构造函数前手动获取并合并 config
$baseConfig = loadSecurityConfig();
$extraConfig = MyServiceProvider::getSecurityConfig();
$mergedConfig = array_merge_recursive($baseConfig, $extraConfig);

$kernel = new MicroKernel($app, [
    'security' => $mergedConfig,
    // ...
]);
```

### 新方式（推荐）

v3.7.0 起，ServiceProvider 在 `register()` 中直接调用注入 API：

```php
class MyServiceProvider
{
    public function register(MicroKernel $kernel): void
    {
        // 直接注入，无需在构造函数前手动合并
        $kernel->addFirewall('api', [
            'pattern' => '^/api',
            'stateless' => true,
        ]);

        $kernel->addAccessRule([
            'path' => '^/api/admin',
            'allowed-roles' => ['ROLE_ADMIN'],
        ]);

        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
    }
}
```

### 迁移步骤

1. 将构造函数前手动获取 security config 的逻辑移入 ServiceProvider 的 `register()` 方法
2. 使用 `addSecurityConfig()` 批量注入，或使用细粒度 API（`addFirewall()`、`addAccessRule()` 等）逐项注入
3. 如需条件注入，使用 `getSecurityConfig()` 查询当前已注册的配置
4. 移除构造函数中手动合并 security config 的代码（Constructor_Config 仍可保留基础配置）

---

## 冲突检测行为

### Security API（默认 `$allowOverwrite = false`）

Security 注入 API 默认不允许覆盖，遇到同名条目立即抛异常（fail-fast）：

| 配置类型 | 冲突条件 | 默认行为 |
|---|---|---|
| `firewalls` | 同名 firewall | 抛 `LogicException` |
| `policies` | 同名 policy | 抛 `LogicException` |
| `role_hierarchy` | 同角色 | 抛 `LogicException` |
| `access_rules` | 无冲突概念 | 始终按序追加 |

使用 `$allowOverwrite = true` 可覆盖已有条目（last-write-wins）：

```php
// 覆盖已有的 'api' firewall
$kernel->addFirewall('api', $newConfig, allowOverwrite: true);
```

**幂等性**：同名 + 完全相同配置内容的重复注入不视为冲突，静默接受。

### Routing API（默认 `$allowOverwrite = true`）

Routing 注入 API 默认允许覆盖（保持向后兼容）：

```php
// 默认行为：同名路由静默覆盖
$kernel->addRoute('home', $route);
$kernel->addRoute('home', $newRoute); // 覆盖，不报错

// 严格模式：同名路由抛异常
$kernel->addRoute('home', $route, allowOverwrite: false);
$kernel->addRoute('home', $newRoute, allowOverwrite: false); // LogicException
```

### 使用场景

- **多 ServiceProvider 协作**：各 provider 注入独立的 firewall/policy，使用默认 `$allowOverwrite = false` 确保无意外覆盖
- **可覆盖的默认配置**：基础 provider 注入默认配置，业务 provider 使用 `$allowOverwrite = true` 按需覆盖
- **路由严格模式**：多团队协作时使用 `addRoute($name, $route, allowOverwrite: false)` 防止路由名冲突

---

## Boot 后调用限制

所有 security 注入 API 和 `getSecurityConfig()` 在 Kernel boot 后调用均抛 `LogicException`：

```
Cannot add security config after the kernel has been booted.
Cannot query security config after the kernel has been booted.
```

这与 `addRoute()` / `addRoutes()` 的 boot 后保护行为一致。所有注入操作必须在 `register()` 阶段完成。
