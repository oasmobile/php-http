# Bugfix Requirements Document

本文件为 hotfix/3.7.0 spec 的需求文档，定义 MicroKernel pre-boot security config 注入 API 的缺陷修复需求。

## Introduction

`MicroKernel` 缺少 boot 前注入 security config 的公开 API。路由已通过 `RoutingTrait` 的 `addRoute()`/`addRoutes()` + `$pendingRoutes` 机制解决了 ServiceProvider 在 `register()` 阶段编程式注入的需求，但 security config 没有对应机制。ServiceProvider 在 `register($kernel)` 阶段无法追加 firewalls、access_rules、policies、role_hierarchy，导致下游用户按文档只调用 `register()` 后，所有带 `allowed-roles` 的路由返回 403（因为没有 firewall listener，`tokenStorage` 中永远没有 token）。

本修复提供批量注入 API 和细粒度便捷 API，使 ServiceProvider 在 register 阶段可编程式注入 security config，`registerSecurity()` 在 boot 时统一合并后初始化。

### Non-scope

- 不修改 `SimpleSecurityProvider` 的内部认证/授权逻辑
- 不改变 `SecurityConfiguration` 的校验规则
- 不涉及 boot 后动态修改 security config 的能力
- 不涉及其他 config（cors、twig、routing）的类似改造

---

## Glossary

- **Kernel**: `MicroKernel` 实例，应用的核心引导器，负责 boot 阶段的服务注册与初始化
- **Security_Config**: 安全配置数据结构，包含 firewalls、access_rules、policies、role_hierarchy 四个顶层 key
- **Pending_Queue**: boot 前暂存的安全配置片段队列，由注入 API 写入，boot 时由 `registerSecurity()` 消费
- **Batch_Injection_API**: `addSecurityConfig(array $config)` 方法，接受与构造函数 `security` key 相同结构的完整或部分配置
- **Fine_Grained_API**: 细粒度便捷方法集合，包括 `addFirewall()`、`addAccessRule()`、`addPolicy()`、`addRoleHierarchy()`
- **Register_Phase**: ServiceProvider 调用 `register($kernel)` 的阶段，此时 Kernel 尚未 boot
- **Boot_Phase**: Kernel 执行 `boot()` 的阶段，此时所有 pending config 被合并并初始化
- **Constructor_Config**: 通过 `MicroKernel` 构造函数 `$httpConfig['security']` 传入的安全配置

---

## Bug Analysis

### Current Behavior (Defect)

1. WHEN a ServiceProvider calls `register($kernel)` and attempts to inject Security_Config THEN THE Kernel has no public API to accept this config, and the injected config is silently lost

2. WHEN `registerSecurity()` executes at Boot_Phase THEN THE Kernel only reads Security_Config from Constructor_Config, ignoring any config that ServiceProviders intended to inject

3. WHEN routes with `allowed-roles` are accessed after a ServiceProvider registered without injecting Security_Config THEN THE Kernel returns HTTP 403 because no firewall listener is registered and `tokenStorage` never contains a token

4. WHEN a ServiceProvider needs to conditionally inject Security_Config based on existing registered config THEN THE Kernel provides no read-only query method to inspect the current Security_Config state

### Expected Behavior (Correct)

1. WHEN a ServiceProvider calls Batch_Injection_API during Register_Phase THEN THE Kernel SHALL accept and store the config in Pending_Queue for later merge at Boot_Phase

2. WHEN a ServiceProvider calls Fine_Grained_API during Register_Phase THEN THE Kernel SHALL accept and store each config fragment in Pending_Queue

3. WHEN `registerSecurity()` executes at Boot_Phase THEN THE Kernel SHALL merge Constructor_Config with all Pending_Queue entries (in registration order) before initializing the security provider

4. WHEN a ServiceProvider calls any security injection API after Boot_Phase THEN THE Kernel SHALL throw a `LogicException`, consistent with `addRoute()` behavior

5. WHEN multiple ServiceProviders register firewalls with the same name THEN THE Kernel SHALL throw an exception indicating the conflict (no implicit overwrite or merge)

6. WHEN multiple ServiceProviders register access_rules THEN THE Kernel SHALL append them in registration order (Constructor_Config first, then subsequent Batch_Injection_API / Fine_Grained_API calls in order)

7. WHEN the same role appears in role_hierarchy from multiple sources THEN THE Kernel SHALL throw an exception (a single role must be defined by exactly one source)

8. WHEN a ServiceProvider calls `getSecurityConfig()` during Register_Phase THEN THE Kernel SHALL return a read-only view of the currently accumulated Security_Config (Constructor_Config + Pending_Queue entries merged so far, i.e. the complete view)

9. WHEN multiple ServiceProviders register policies with the same name THEN THE Kernel SHALL throw an exception indicating the conflict (no implicit overwrite or merge), consistent with firewalls behavior

10. WHEN Batch_Injection_API receives a config array containing top-level keys other than `firewalls`, `access_rules`, `policies`, `role_hierarchy` THEN THE Kernel SHALL throw an exception rejecting the config

11. WHEN a ServiceProvider calls `addRoleHierarchy(string $role, array $children)` during Register_Phase THEN THE Kernel SHALL store the role mapping in Pending_Queue; IF the same role has already been defined (by Constructor_Config, Batch_Injection_API, or a prior `addRoleHierarchy()` call) THEN THE Kernel SHALL throw an exception

### Unchanged Behavior (Regression Prevention)

1. WHEN Security_Config is provided only via Constructor_Config and no ServiceProvider injects additional config THEN THE Kernel SHALL CONTINUE TO initialize security exactly as before (no behavioral change for existing usage)

2. WHEN `SimpleSecurityProvider` receives the merged config at Boot_Phase THEN THE Kernel SHALL CONTINUE TO perform authentication and authorization using the same internal logic (no changes to `SimpleSecurityProvider` internals)

3. WHEN `SecurityConfiguration` validates the merged config THEN THE Kernel SHALL CONTINUE TO apply the same validation rules (no changes to configuration schema)

4. WHEN routes without `allowed-roles` are accessed THEN THE Kernel SHALL CONTINUE TO serve them without security checks regardless of whether Security_Config was injected via ServiceProvider or Constructor_Config

5. WHEN `addRoute()`/`addRoutes()` are called by ServiceProviders THEN THE Kernel SHALL CONTINUE TO function identically (routing injection is unaffected)

---

## Bug Condition

### Bug Condition Function

```pascal
FUNCTION isBugCondition(X)
  INPUT: X of type ServiceProviderRegistration
  OUTPUT: boolean

  // Returns true when a ServiceProvider attempts to inject security config
  // during register() phase — the kernel has no API to accept it
  RETURN X.attemptsSecurityConfigInjection = true
    AND X.phase = "register"
    AND kernel.hasSecurityInjectionAPI = false
END FUNCTION
```

### Fix Checking Property

```pascal
// Property: Fix Checking — ServiceProvider security config injection works
FOR ALL X WHERE isBugCondition(X) DO
  kernel' ← applyFix(kernel)
  kernel'.addSecurityConfig(X.securityConfig)
  kernel'.boot()
  mergedConfig ← kernel'.getEffectiveSecurityConfig()
  ASSERT X.securityConfig IS SUBSET OF mergedConfig
  ASSERT kernel'.securityProvider IS initialized
  ASSERT no_crash(kernel')
END FOR
```

### Preservation Checking Property

```pascal
// Property: Preservation Checking — Constructor-only usage unchanged
FOR ALL X WHERE NOT isBugCondition(X) DO
  // X represents constructor-only security config (no ServiceProvider injection)
  ASSERT F(X) = F'(X)
  // i.e., registerSecurity() produces identical results when no pending configs exist
END FOR
```

---

## Socratic Review

### 每条 requirement 是否都在描述外部可观察的行为？

是。所有 Expected Behavior 条款描述的是 ServiceProvider 调用 API 后的可观察结果（config 被接受、boot 时合并、冲突时抛异常），未涉及内部数据结构的实现方式。Bug Condition 中的伪代码用于形式化验证属性，不构成实现约束。

### 是否有遗漏的场景？

- **空 config 注入**：ServiceProvider 调用 `addSecurityConfig([])` 传入空数组时，应无副作用地接受（不抛异常、不影响已有 config）。当前 AC 未显式覆盖此边界，但语义上已被 Expected Behavior #1 涵盖（"accept and store"）。
- **注册顺序可观察性**：多个 ServiceProvider 注册 access_rules 时，最终顺序是否可通过 `getSecurityConfig()` 查询确认？Expected Behavior #8 已覆盖（返回当前累积状态）。
- **policies 冲突策略**：已通过 CR Q1 确认——同名 policy 抛异常，与 firewalls 一致。已补充 Expected Behavior #9。

### 各 requirement 之间是否存在矛盾或重叠？

无矛盾。Expected Behavior #1 和 #2 分别覆盖批量和细粒度 API，互补而非重叠。#5/#6/#7 分别定义了三种不同 config key 的冲突策略，互不矛盾。

### 是否有隐含的前置假设没有显式列出？

- 假设 `registerSecurity()` 在 `boot()` 中只执行一次（与 `registerRouting()` 一致）
- 假设 ServiceProvider 的 `register()` 调用顺序是确定性的（由调用方控制）
- 假设 `getSecurityConfig()` 的返回值是快照而非引用（修改返回值不影响 Pending_Queue）

### 与 goal.md 的 scope / non-goals 是否一致？

一致。goal.md 的 4 个 Clarification 决策（API 粒度、merge 策略、role_hierarchy 冲突、查询能力）均已体现在 Expected Behavior 条款中。Non-scope 与 goal.md 的 Non-Goals 一致。

### scope 边界是否清晰？

清晰。修复范围限于"提供 pre-boot 注入 API + boot 时合并"，不涉及 security provider 内部逻辑变更。唯一可能的模糊地带是 policies 的冲突策略（见上方分析），已纳入 CR。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Glossary` section，定义 8 个领域术语
- [结构] 补充 `## Socratic Review` section
- [语体] AC 中 Subject 从 "the system" 统一改为 Glossary 定义的术语（`THE Kernel`）
- [内容] Introduction 补充 Non-scope 小节，明确不涉及的内容
- [格式] 各 section 之间补充 `---` 分隔符
- [格式] AC 编号从 "section.number"（1.1, 2.1）改为各 section 内连续编号（1, 2, 3...）
- [内容] 一级标题下方补充一句话说明文件定位和所属 spec 目录
- [目的] Socratic Review 中识别出 policies 冲突策略未显式定义，纳入 CR

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Glossary 术语在 AC 中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 bug 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空
- [x] Bug Analysis section 存在且包含 Current/Expected/Unchanged 三类行为
- [x] 各 section 之间使用 `---` 分隔
- [x] AC 使用 `WHEN...THEN THE <Subject> SHALL...` 语体
- [x] Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续无跳号
- [x] Goal CR 决策已体现在 AC 中
- [x] Bug Condition 包含形式化验证属性
- [x] Socratic Review 覆盖充分

### Clarification Round

**状态**: 已完成

**Q1:** policies 的冲突策略是什么？goal.md 明确了 firewalls 同名抛异常、role_hierarchy 同角色抛异常、access_rules 按序追加，但 policies 未显式说明。当多个 ServiceProvider 注册同名 policy 时应如何处理？
- A) 同名 policy 抛异常（与 firewalls 一致，禁止冲突）
- B) 按注册顺序追加，同名 policy 后者覆盖前者（last-write-wins）
- C) 按注册顺序追加，同名 policy 保留前者忽略后者（first-write-wins）
- D) 其他（请说明）

**A:** A — 同名 policy 抛异常，与 firewalls 一致

**Q2:** `getSecurityConfig()` 返回的"当前累积状态"是否包含 Constructor_Config 与 Pending_Queue 的实时合并结果，还是仅返回 Pending_Queue 中已注册的片段（不含 Constructor_Config）？这影响 ServiceProvider 做条件注入时能看到的信息范围。
- A) 返回 Constructor_Config + Pending_Queue 的合并结果（完整视图）
- B) 仅返回 Pending_Queue 中的片段（不含 Constructor_Config）
- C) 分别提供两个方法：一个返回完整合并视图，一个仅返回 pending 片段
- D) 其他（请说明）

**A:** A — 返回 Constructor_Config + Pending_Queue 的完整合并视图

**Q3:** 当 `addSecurityConfig()` 传入的 config 包含未知的顶层 key（既不是 firewalls、access_rules、policies、role_hierarchy）时，应如何处理？
- A) 静默忽略未知 key（只处理已知的四个 key）
- B) 抛出异常，拒绝包含未知 key 的 config
- C) 原样存入 Pending_Queue，交由 `SecurityConfiguration` 在 boot 时校验
- D) 其他（请说明）

**A:** B — 抛出异常，拒绝包含未知 key 的 config

**Q4:** Fine_Grained_API 中 `setRoleHierarchy()` 的语义是"设置整个 role_hierarchy"还是"追加角色映射"？如果是"设置"，多次调用是否以最后一次为准？如果是"追加"，与 `addSecurityConfig()` 中的 role_hierarchy 合并时如何交互？
- A) `setRoleHierarchy()` 为整体设置语义，多次调用以最后一次为准；与 `addSecurityConfig()` 中的 role_hierarchy 冲突时抛异常
- B) 改为 `addRoleHierarchy(string $role, array $children)` 追加语义，与 `addSecurityConfig()` 统一走"同角色抛异常"规则
- C) 保留 `setRoleHierarchy()` 但限制只能调用一次，第二次调用抛异常
- D) 其他（请说明）

**A:** B — 改为 `addRoleHierarchy(string $role, array $children)` 追加语义，统一走"同角色抛异常"规则
