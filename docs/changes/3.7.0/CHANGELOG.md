# Changelog v3.7.0

修复 MicroKernel 缺少 boot 前注入 security config 的公开 API（ISS-3.6.4-L01），新增 `SecurityTrait` 提供编程式安全配置注入能力，`RoutingTrait` 对齐新增 `$allowOverwrite` 冲突检测参数。

---

## Fixed

- **ISS-3.6.4-L01**：MicroKernel 缺少 pre-boot security config 注入 API，ServiceProvider 在 `register()` 阶段无法追加 firewalls / access_rules / policies / role_hierarchy，导致带 `allowed-roles` 的路由返回 403

---

## Added

- `SecurityTrait`（`src/Kernel/SecurityTrait.php`）：提供 boot 前编程式安全配置注入
  - `addSecurityConfig(array $config, bool $allowOverwrite = false)` — 批量注入
  - `addFirewall(string $name, array $config, bool $allowOverwrite = false)` — 注入单个 firewall
  - `addAccessRule(array $rule)` — 注入单条 access rule（始终追加）
  - `addPolicy(string $name, mixed $config, bool $allowOverwrite = false)` — 注入单个 policy
  - `addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false)` — 注入单个角色层级
  - `getSecurityConfig(): array` — 只读查询当前累积配置
- `RoutingTrait` 新增 `$allowOverwrite` 参数：
  - `addRoute(string $name, Route $route, bool $allowOverwrite = true)` — 默认 `true` 向后兼容
  - `addRoutes(RouteCollection $routes, bool $allowOverwrite = true)` — 默认 `true` 向后兼容
- 冲突检测（fail-fast）：注入时立即检测同名 firewall / policy / role_hierarchy / route 冲突
- PBT 测试重组：`tests/PBT/Security/` 目录结构化
- 集成测试：`SecurityInjectionIntegrationTest`、`SecurityRoutingCoexistenceTest`

---

## Changed

- `registerSecurity()` 从 `ServicesTrait` 迁移到 `SecurityTrait`，合并 Constructor_Config + Pending_Queue 后初始化
- `MicroKernel` 新增 `use SecurityTrait` 和 `$pendingSecurityConfigs` 属性

---

## Migration Impact

**向后兼容**：仅通过构造函数传入 security config 的现有用法无需任何修改。`RoutingTrait` 的 `$allowOverwrite` 默认 `true`，现有路由注入代码无需修改。

**新能力**：ServiceProvider 可在 `register()` 阶段通过注入 API 追加安全配置，无需在构造 kernel 前手动获取 config。
