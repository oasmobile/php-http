# Spec Goal: MicroKernel Pre-boot Security Config Injection API

## 来源

- 分支: `hotfix/3.7.0`
- 需求文档: `issues/ISS-3.6.4-L01-missing-pre-boot-security-config-api.md`

## 背景摘要

`MicroKernel` 的 bootstrap config 驱动初始化模型中，路由已通过 `RoutingTrait` 的 `addRoute()` / `addRoutes()` 提供了 boot 前编程式注入能力——ServiceProvider 在 `register()` 阶段调用这些方法，boot 时由 `registerRouting()` 统一消费 `$pendingRoutes`。

Security config 缺少对应机制。当前只能通过构造函数 `$httpConfig['security']` 一次性传入，`registerSecurity()` 在 boot 时直接从 `httpDataProvider` 读取。ServiceProvider 在 `register($kernel)` 阶段无法追加 firewalls、access_rules、policies、role_hierarchy。

这导致下游集成（如 CoreSDK）必须在构造 kernel 前手动获取 security config 再传入构造函数，破坏了 ServiceProvider "注册即生效"的语义。实际表现为：用户按文档只调用 `register()` 后，所有带 `allowed-roles` 的路由返回 403。

## 目标

- 提供批量注入 API `addSecurityConfig(array $config)`，接受与构造函数 `security` key 相同结构的完整/部分 config
- 提供细粒度便捷 API：`addFirewall()`、`addAccessRule()`、`addPolicy()`、`setRoleHierarchy()` 等
- 提供只读查询方法（如 `getSecurityConfig()`），允许 ServiceProvider 在 register 阶段检查当前已注册的 security config
- `registerSecurity()` 在 boot 时合并构造函数 config 与所有 pending configs 后统一初始化
- boot 后调用注入 API 抛出 `LogicException`，与 `addRoute()` 行为一致

## 不做的事情（Non-Goals）

- 不修改 `SimpleSecurityProvider` 的内部认证/授权逻辑
- 不改变 security config 的 Configuration 校验规则（`SecurityConfiguration`）
- 不涉及 boot 后动态修改 security config 的能力
- 不涉及其他 config（cors、twig、routing）的类似改造（如有需要另开 issue）

## Clarification 记录

### Q1: API 粒度

security config 结构复杂（firewalls、access_rules、role_hierarchy、policies 嵌套），注入 API 应该是什么粒度？

- 选项: A) 单一方法 `addSecurityConfig(array $config)` / B) 细粒度方法 / C) 两者都提供 / D) 补充说明
- 回答: C — 两者都提供。`addSecurityConfig` 作为批量入口，细粒度方法作为便捷 API

### Q2: merge 策略冲突处理

构造函数 config 与 `addSecurityConfig()` 追加的 config 存在同名 firewall 或同 pattern access rule 时如何处理？

- 选项: A) last-write-wins / B) 追加不覆盖 / C) 同名 firewall 抛异常，access_rules 按顺序追加 / D) 补充说明
- 回答: C — 同名 firewall 抛异常（禁止冲突），access_rules 按注册顺序追加

### Q3: role_hierarchy 合并策略

多个 ServiceProvider 各自声明 role_hierarchy 时如何合并？

- 选项: A) 同角色子角色列表 array_merge 去重 / B) 同角色出现多次抛异常 / C) 只允许构造函数定义 / D) 补充说明
- 回答: B — 同一角色出现多次时抛异常，要求由单一来源定义

### Q4: boot 后调用行为与查询能力

是否需要提供查询方法让 ServiceProvider 检查当前已注册的 security config？

- 选项: A) 不需要 / B) 提供只读查询方法 / C) 补充说明
- 回答: B — 提供只读查询方法，允许 ServiceProvider 做条件注入

## 约束与决策

- API 设计同时提供批量入口和细粒度便捷方法
- 同名 firewall 冲突时抛异常，不做隐式覆盖或合并
- access_rules 按注册顺序追加（构造函数的在前，后续 `addSecurityConfig` / `addAccessRule` 的在后）
- role_hierarchy 同一角色不允许多次定义，冲突时抛异常
- boot 后调用注入 API 抛 `LogicException`，与 `addRoute()` 保持一致
- 提供只读查询方法，支持条件注入场景
