# MicroKernel Pre-boot Security Config Injection API — Bugfix Design

本文件为 hotfix/3.7.0 spec 的技术设计文档，定义 MicroKernel pre-boot security config 注入 API 的修复方案。

---

## Overview

`MicroKernel` 缺少 boot 前注入 security config 的公开 API。ServiceProvider 在 `register($kernel)` 阶段无法追加 firewalls、access_rules、policies、role_hierarchy，导致所有带 `allowed-roles` 的路由返回 403。

修复策略：新增 `SecurityTrait`（与 `RoutingTrait` 同级），提供批量注入 API `addSecurityConfig()` 和细粒度便捷 API（`addFirewall()`、`addAccessRule()`、`addPolicy()`、`addRoleHierarchy()`），以及只读查询 `getSecurityConfig()`。boot 时 `registerSecurity()` 合并 Constructor_Config 与 Pending_Queue 后统一初始化 `SimpleSecurityProvider`。

---

## Glossary

- **Bug_Condition (C)**: ServiceProvider 在 register 阶段尝试注入 security config，但 Kernel 无 API 接受
- **Property (P)**: 注入 API 存在且正确工作——config 被暂存、boot 时合并、冲突时抛异常
- **Preservation**: Constructor-only 用法（无 ServiceProvider 注入）的行为完全不变
- **SecurityTrait**: 新增 trait，位于 `src/Kernel/SecurityTrait.php`，承载所有 security 注入 API
- **Pending_Queue**: `$pendingSecurityConfigs` 属性，暂存 boot 前注入的 security config 片段
- **Constructor_Config**: 通过 `MicroKernel` 构造函数 `$httpConfig['security']` 传入的安全配置
- **Register_Phase**: ServiceProvider 调用 `register($kernel)` 的阶段，Kernel 尚未 boot
- **Boot_Phase**: Kernel 执行 `boot()` 的阶段，所有 pending config 被合并并初始化

---

## Bug Details

### Bug Condition

ServiceProvider 在 register 阶段需要注入 security config（firewalls、access_rules、policies、role_hierarchy），但 `MicroKernel` 没有对应的公开 API。`registerSecurity()` 仅从 `httpDataProvider` 读取 Constructor_Config，忽略 ServiceProvider 的注入意图。

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type ServiceProviderRegistration
  OUTPUT: boolean

  RETURN input.attemptsSecurityConfigInjection = true
         AND input.phase = "register"
         AND kernel.hasPublicSecurityInjectionAPI() = false
END FUNCTION
```

### Examples

- ServiceProvider 调用 `$kernel->addSecurityConfig(['firewalls' => [...]])` → 方法不存在，Fatal Error
- ServiceProvider 调用 `$kernel->addFirewall('api', [...])` → 方法不存在，Fatal Error
- ServiceProvider 只调用 `register($kernel)` 不注入 security config → 路由带 `allowed-roles` 时返回 403（因为没有 firewall listener）
- ServiceProvider 在 boot 后尝试注入 → 应抛 LogicException（与 `addRoute()` 一致）

---

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- 仅通过 Constructor_Config 提供 security config 时，`registerSecurity()` 行为完全不变
- `SimpleSecurityProvider` 内部认证/授权逻辑不变
- `SecurityConfiguration` 的校验规则不变
- 无 `allowed-roles` 的路由不受影响
- `addRoute()`/`addRoutes()` 路由注入功能不受影响

**Scope:**
所有不涉及 security config 注入 API 的输入路径完全不受此修复影响。包括：
- 仅使用 Constructor_Config 的现有用法
- 路由注入 API
- CORS、Twig、Cookie 等其他 service provider 注册

---

## Hypothesized Root Cause

基于 bug 分析，根因是架构缺失：

1. **API 缺失**: `MicroKernel` 没有 `addSecurityConfig()`、`addFirewall()` 等公开方法，ServiceProvider 无法在 register 阶段注入 security config

2. **Pending 机制缺失**: 路由有 `$pendingRoutes` + `RoutingTrait`，但 security 没有对应的 `$pendingSecurityConfigs` + `SecurityTrait`

3. **registerSecurity() 只读 Constructor_Config**: 当前实现直接从 `httpDataProvider->getOptional('security')` 读取，不考虑任何外部注入

4. **无查询能力**: ServiceProvider 无法在 register 阶段检查当前已注册的 security config，无法做条件注入

---

## Correctness Properties

Property 1: Bug Condition — Security Config Injection API Works

_For any_ ServiceProvider registration where the provider attempts to inject security config during Register_Phase, the fixed Kernel SHALL accept the config via `addSecurityConfig()` or fine-grained APIs, store it in Pending_Queue, and at Boot_Phase merge it with Constructor_Config before initializing `SimpleSecurityProvider`.

**Validates: Requirements Expected Behavior 1, 2, 3**

Property 2: Preservation — Constructor-Only Usage Unchanged

_For any_ usage where security config is provided only via Constructor_Config and no ServiceProvider injects additional config, the fixed Kernel SHALL produce exactly the same `registerSecurity()` behavior as the original code, preserving all existing authentication and authorization functionality.

**Validates: Requirements Unchanged Behavior 1, 2, 3, 4, 5**

---

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `src/Kernel/SecurityTrait.php` (新建)

**Pattern**: 与 `RoutingTrait` 同构

**Specific Changes**:

1. **新建 SecurityTrait**: 包含所有 security 注入 API 和 `registerSecurity()` 的新实现
   - `addSecurityConfig(array $config, bool $allowOverwrite = false): void` — 批量注入，校验顶层 key，fail-fast 冲突检测
   - `addFirewall(string $name, array $config, bool $allowOverwrite = false): void` — 细粒度注入单个 firewall
   - `addAccessRule(array $rule): void` — 细粒度注入单条 access rule（无冲突概念，始终追加）
   - `addPolicy(string $name, mixed $config, bool $allowOverwrite = false): void` — 细粒度注入单个 policy
   - `addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false): void` — 细粒度注入单个角色层级
   - `getSecurityConfig(): array` — 只读查询，返回 Constructor_Config + Pending_Queue 合并视图；boot 后调用抛 LogicException
   - `registerSecurity(): void` — 重写，合并后初始化

2. **MicroKernel 新增属性**:
   - `protected array $pendingSecurityConfigs = []` — pending queue

3. **MicroKernel use SecurityTrait**: 替换 `ServicesTrait` 中的 `registerSecurity()` 实现

4. **ServicesTrait 移除 registerSecurity()**: 该方法迁移到 `SecurityTrait`

5. **冲突检测逻辑（fail-fast）**:
   - `addSecurityConfig()`: 校验顶层 key 只允许 `firewalls`、`access_rules`、`policies`、`role_hierarchy`，未知 key 抛异常
   - 注入时立即检测冲突（对比当前累积状态）：同名 firewall → 抛异常（除非 `$allowOverwrite = true`）
   - 同名 policy → 抛异常（除非 `$allowOverwrite = true`）
   - 同角色 role_hierarchy → 抛异常（除非 `$allowOverwrite = true`）
   - access_rules → 按注册顺序追加（无冲突概念）
   - `$allowOverwrite` 默认 `false`（security 默认不允许覆盖）

6. **Boot 后调用保护**: 所有注入 API 和 `getSecurityConfig()` 检查 `$this->booted`，为 true 时抛 `LogicException`

7. **RoutingTrait 对齐改造**: `addRoute()` / `addRoutes()` 增加 `bool $allowOverwrite = true` 参数，改为注入时 fail-fast 冲突检测。routing 默认 `$allowOverwrite = true`（保持向后兼容，现有调用方不受影响），security 默认 `$allowOverwrite = false`

### SecurityTrait 伪代码

```php
trait SecurityTrait
{
    protected array $pendingSecurityConfigs = [];

    public function addSecurityConfig(array $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }
        $allowedKeys = ['firewalls', 'access_rules', 'policies', 'role_hierarchy'];
        $unknownKeys = array_diff(array_keys($config), $allowedKeys);
        if ($unknownKeys) {
            throw new \InvalidArgumentException('Unknown security config keys: ' . implode(', ', $unknownKeys));
        }
        // Fail-fast: check conflicts immediately against current accumulated state
        $this->validateSecurityConfigConflicts($config, $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => $config, 'allowOverwrite' => $allowOverwrite];
    }

    public function addFirewall(string $name, array $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }
        $this->validateSecurityConfigConflicts(['firewalls' => [$name => $config]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['firewalls' => [$name => $config]], 'allowOverwrite' => $allowOverwrite];
    }

    public function addAccessRule(array $rule): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }
        $this->pendingSecurityConfigs[] = ['config' => ['access_rules' => [$rule]], 'allowOverwrite' => false];
    }

    public function addPolicy(string $name, mixed $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }
        $this->validateSecurityConfigConflicts(['policies' => [$name => $config]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['policies' => [$name => $config]], 'allowOverwrite' => $allowOverwrite];
    }

    public function addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }
        $this->validateSecurityConfigConflicts(['role_hierarchy' => [$role => $children]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['role_hierarchy' => [$role => $children]], 'allowOverwrite' => $allowOverwrite];
    }

    public function getSecurityConfig(): array
    {
        if ($this->booted) {
            throw new \LogicException('Cannot query security config after the kernel has been booted.');
        }
        $constructorConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);
        $base = is_array($constructorConfig) ? $constructorConfig : [];
        return $this->mergeSecurityConfigs($base, $this->pendingSecurityConfigs);
    }

    protected function registerSecurity(): void
    {
        $constructorConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);
        $base = is_array($constructorConfig) ? $constructorConfig : [];
        $mergedConfig = $this->mergeSecurityConfigs($base, $this->pendingSecurityConfigs);

        if (empty($mergedConfig)) {
            return;
        }

        $securityProvider = new SimpleSecurityProvider();
        $securityProvider->register($this, $mergedConfig);
    }

    private function validateSecurityConfigConflicts(array $fragment, bool $allowOverwrite): void
    {
        if ($allowOverwrite) {
            return; // overwrite mode skips conflict detection
        }
        // Build current accumulated state for conflict checking
        $currentConfig = $this->getSecurityConfigInternal();

        if (isset($fragment['firewalls'])) {
            foreach ($fragment['firewalls'] as $name => $fw) {
                if (array_key_exists($name, $currentConfig['firewalls'])) {
                    throw new \LogicException("Duplicate firewall: '$name'");
                }
            }
        }
        if (isset($fragment['policies'])) {
            foreach ($fragment['policies'] as $name => $policy) {
                if (array_key_exists($name, $currentConfig['policies'])) {
                    throw new \LogicException("Duplicate policy: '$name'");
                }
            }
        }
        if (isset($fragment['role_hierarchy'])) {
            foreach ($fragment['role_hierarchy'] as $role => $children) {
                if (array_key_exists($role, $currentConfig['role_hierarchy'])) {
                    throw new \LogicException("Duplicate role in role_hierarchy: '$role'");
                }
            }
        }
    }

    private function getSecurityConfigInternal(): array
    {
        $constructorConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);
        $base = is_array($constructorConfig) ? $constructorConfig : [];
        return $this->mergeSecurityConfigs($base, $this->pendingSecurityConfigs);
    }

    private function mergeSecurityConfigs(array $base, array $pendingQueue): array
    {
        $merged = [
            'firewalls'      => $base['firewalls'] ?? [],
            'access_rules'   => $base['access_rules'] ?? [],
            'policies'       => $base['policies'] ?? [],
            'role_hierarchy'  => $base['role_hierarchy'] ?? [],
        ];

        foreach ($pendingQueue as $entry) {
            $fragment = $entry['config'];
            $overwrite = $entry['allowOverwrite'];

            // firewalls: same-name throws exception unless allowOverwrite
            if (isset($fragment['firewalls'])) {
                foreach ($fragment['firewalls'] as $name => $fw) {
                    if (!$overwrite && array_key_exists($name, $merged['firewalls'])) {
                        throw new \LogicException("Duplicate firewall: '$name'");
                    }
                    $merged['firewalls'][$name] = $fw;
                }
            }
            // policies: same-name throws exception unless allowOverwrite
            if (isset($fragment['policies'])) {
                foreach ($fragment['policies'] as $name => $policy) {
                    if (!$overwrite && array_key_exists($name, $merged['policies'])) {
                        throw new \LogicException("Duplicate policy: '$name'");
                    }
                    $merged['policies'][$name] = $policy;
                }
            }
            // role_hierarchy: same-role throws exception unless allowOverwrite
            if (isset($fragment['role_hierarchy'])) {
                foreach ($fragment['role_hierarchy'] as $role => $children) {
                    if (!$overwrite && array_key_exists($role, $merged['role_hierarchy'])) {
                        throw new \LogicException("Duplicate role in role_hierarchy: '$role'");
                    }
                    $merged['role_hierarchy'][$role] = $children;
                }
            }
            // access_rules: always append in order
            if (isset($fragment['access_rules'])) {
                foreach ($fragment['access_rules'] as $rule) {
                    $merged['access_rules'][] = $rule;
                }
            }
        }

        // Remove empty keys to match original behavior (no security if all empty)
        $merged = array_filter($merged, fn($v) => !empty($v));
        return $merged;
    }
}
```

---

## Impact Analysis

### 受影响的源文件

| 文件 | 变更类型 | 说明 |
|------|----------|------|
| `src/Kernel/SecurityTrait.php` | 新建 | 承载所有 security 注入 API 和 `registerSecurity()` 新实现 |
| `src/Kernel/ServicesTrait.php` | 修改 | 移除 `registerSecurity()` 方法（迁移到 SecurityTrait） |
| `src/MicroKernel.php` | 修改 | 新增 `use SecurityTrait`，新增 `$pendingSecurityConfigs` 属性 |
| `src/Kernel/RoutingTrait.php` | 修改 | `addRoute()` / `addRoutes()` 增加 `bool $allowOverwrite = true` 参数，改为 fail-fast 冲突检测 |

### 受影响的 state 文档

- `docs/state/architecture.md`：
  - "核心类" section 需补充 `SecurityTrait` 描述
  - "模块结构" section 的 `Kernel/` 目录树需新增 `SecurityTrait.php`
  - "boot 前支持编程式注入" 描述需补充 security 注入 API

### 现有行为变化

- `ServicesTrait` 不再包含 `registerSecurity()` 方法——该方法迁移到 `SecurityTrait`，对外行为不变
- `MicroKernel` 新增 6 个公开方法（`addSecurityConfig`、`addFirewall`、`addAccessRule`、`addPolicy`、`addRoleHierarchy`、`getSecurityConfig`）——纯新增，不影响现有调用方
- `registerSecurity()` 内部逻辑变更：从直接读取 Constructor_Config 改为合并 Constructor_Config + Pending_Queue。当 Pending_Queue 为空时，行为与原实现等价

### 数据模型变更

不涉及。security config 的数据结构（firewalls、access_rules、policies、role_hierarchy）不变，仅新增了注入路径。

### 外部系统交互

不涉及。修复仅影响 Kernel 内部的 config 注入机制，不改变与外部系统的交互方式。

### 配置项变更

不涉及。Bootstrap config 的 `security` key 结构不变，不新增、删除或修改任何配置项的默认值。

---

## Alternatives Considered

### A) 直接在 ServicesTrait 中扩展 registerSecurity()

在 `ServicesTrait` 中直接添加注入 API 和 pending 机制，不新建 trait。

**落选理由**：`ServicesTrait` 当前承载 Cookie、CORS、Twig、Security 四个 service 的注册逻辑。Security 注入 API 的复杂度（冲突检测、合并逻辑）远超其他三个 service，放在同一 trait 中会导致职责膨胀。`RoutingTrait` 已建立了"独立 trait 承载复杂注入逻辑"的先例，SecurityTrait 应遵循同一模式。

### B) 通过 EventDispatcher 事件机制注入

ServiceProvider 在 register 阶段发布 security config 事件，`registerSecurity()` 在 boot 时收集事件。

**落选理由**：引入不必要的间接层。路由注入使用直接方法调用 + pending queue 模式，security 应保持一致。事件机制还会使冲突检测时机延后（只能在 boot 时检测），降低开发体验（错误反馈不及时）。

---

## Socratic Review

### design 是否完整覆盖了 requirements 中的每条需求？

是。bugfix.md 的 11 条 Expected Behavior 和 5 条 Unchanged Behavior 均在 design 中有对应的技术方案：
- Expected Behavior 1-2 → `addSecurityConfig()` 和细粒度 API 的 pending 存储
- Expected Behavior 3 → `registerSecurity()` 的合并逻辑
- Expected Behavior 4 → `$this->booted` 检查
- Expected Behavior 5/7/9 → `mergeSecurityConfigs()` 中的冲突检测
- Expected Behavior 6 → access_rules 追加逻辑
- Expected Behavior 8 → `getSecurityConfig()` 返回合并视图
- Expected Behavior 10 → `$allowedKeys` 校验
- Expected Behavior 11 → `addRoleHierarchy()` 方法
- Unchanged Behaviors → Preservation Requirements section + 测试策略

### 技术选型是否合理？

合理。选择 trait + pending queue 模式是因为 `RoutingTrait` 已建立了完全相同的先例，保持架构一致性。无需引入新的设计模式或外部依赖。

### 接口签名和数据模型是否足够清晰？

是。每个公开方法的参数类型、返回类型、异常类型均已在伪代码中明确定义。`mergeSecurityConfigs()` 的合并逻辑通过完整伪代码展示，无歧义。

### 模块间的依赖关系是否会引入循环依赖或过度耦合？

不会。`SecurityTrait` 仅依赖 `$this->httpDataProvider`（已有属性）、`$this->booted`（已有属性）和 `SimpleSecurityProvider`（已有依赖）。不引入新的模块间依赖。

### 是否有过度设计的部分？

无。所有 API 均直接对应 requirements 中的需求，无预留扩展点或不必要的抽象层。

### Impact Analysis 是否充分？

充分。影响范围限于 3 个文件（1 新建 + 2 修改），不涉及数据模型变更、外部系统交互或配置项变更。state 文档需同步更新。

---

## Testing Strategy

### Validation Approach

测试策略分两阶段：先在未修复代码上验证 bug 存在（exploration），再验证修复后行为正确（fix checking）并确保现有行为不变（preservation checking）。

### Exploratory Bug Condition Checking

**Goal**: 在未修复代码上 surface counterexamples，确认 bug 存在。确认或否定根因分析。

**Test Plan**: 编写测试尝试调用 `addSecurityConfig()` 等方法，在未修复代码上观察 Fatal Error（方法不存在）。

**Test Cases**:
1. **addSecurityConfig 不存在**: 调用 `$kernel->addSecurityConfig([...])` → Fatal Error（will fail on unfixed code）
2. **addFirewall 不存在**: 调用 `$kernel->addFirewall('api', [...])` → Fatal Error（will fail on unfixed code）
3. **registerSecurity 忽略外部注入**: 即使能绕过 API 缺失，boot 后 security provider 未初始化（will fail on unfixed code）
4. **getSecurityConfig 不存在**: 调用 `$kernel->getSecurityConfig()` → Fatal Error（will fail on unfixed code）

**Expected Counterexamples**:
- 所有注入 API 调用均产生 Fatal Error（Call to undefined method）
- 即使 Constructor_Config 为空，ServiceProvider 无法补充 security config

### Fix Checking

**Goal**: 验证对所有满足 bug condition 的输入，修复后的函数产生期望行为。

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  kernel' := createFixedKernel()
  kernel'.addSecurityConfig(input.securityConfig)
  kernel'.boot()
  mergedConfig := kernel'.getSecurityConfig()
  ASSERT input.securityConfig IS SUBSET OF mergedConfig
  ASSERT kernel'.tokenStorage IS NOT NULL
  ASSERT no_crash(kernel')
END FOR
```

**Test Cases**:
1. **Batch injection**: `addSecurityConfig()` 接受完整 config，boot 后 security provider 正确初始化
2. **Fine-grained injection**: `addFirewall()` + `addAccessRule()` + `addPolicy()` + `addRoleHierarchy()` 各自工作
3. **Conflict detection — firewalls**: 同名 firewall 抛异常
4. **Conflict detection — policies**: 同名 policy 抛异常
5. **Conflict detection — role_hierarchy**: 同角色抛异常
6. **access_rules ordering**: 按注册顺序追加
7. **Post-boot throws**: boot 后调用任何注入 API 抛 LogicException
8. **Unknown keys rejected**: `addSecurityConfig(['unknown_key' => [...]])` 抛异常
9. **getSecurityConfig() read-only query**: 返回 Constructor_Config + Pending_Queue 合并视图

### Preservation Checking

**Goal**: 验证对所有不满足 bug condition 的输入，修复后的函数与原函数产生相同结果。

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT registerSecurity_original(input) = registerSecurity_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing 推荐用于 preservation checking，因为：
- 自动生成大量 Constructor_Config 变体，验证无 pending config 时行为不变
- 捕获手动测试可能遗漏的边界情况
- 提供强保证：所有非 bug 输入的行为不变

**Test Plan**: 先在未修复代码上观察 Constructor-only 用法的行为，再编写 PBT 验证修复后行为一致。

**Test Cases**:
1. **Constructor-only preservation**: 仅通过构造函数传入 security config，boot 后行为与修复前完全一致
2. **Empty security config preservation**: 不传 security config 时，`registerSecurity()` 仍然 early return
3. **Route injection unaffected**: `addRoute()`/`addRoutes()` 功能不受影响
4. **Other services unaffected**: CORS、Twig、Cookie 注册不受影响

### Unit Tests

- 测试每个注入 API 的正常路径（accept and store）
- 测试 boot 后调用每个 API 抛 LogicException
- 测试冲突检测（同名 firewall、同名 policy、同角色 role_hierarchy）
- 测试未知 key 拒绝
- 测试 `getSecurityConfig()` 返回正确的合并视图
- 测试空 config 注入无副作用

### Property-Based Tests

- 生成随机 security config 片段，验证 `mergeSecurityConfigs()` 的合并逻辑正确性
- 生成随机 Constructor_Config，验证无 pending config 时 `registerSecurity()` 行为不变
- 生成随机注册顺序，验证 access_rules 按序追加

### Integration Tests

- 完整 ServiceProvider 注册流程：register 阶段注入 security config → boot → 验证带 `allowed-roles` 的路由不再返回 403
- 多个 ServiceProvider 按序注册，验证合并结果正确
- 与路由注入 API 共存，验证两者互不干扰

---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 补充 `## Impact Analysis` section，覆盖受影响文件、state 文档、行为变化、数据模型、外部系统、配置项六个维度
- [结构] 补充 `## Alternatives Considered` section，列出两个备选方案及落选理由
- [结构] 补充 `## Socratic Review` section，覆盖 6 个审查维度
- [格式] 各 section 之间补充 `---` 分隔符
- [格式] 一级标题下方补充一句话说明文件定位和所属 spec 目录

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] 技术方案主体存在，承接了 requirements 中的需求
- [x] 接口签名 / 数据模型有明确定义（伪代码完整）
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应的实现描述
- [x] 无遗漏的 requirement
- [x] design 中的方案不超出 requirements 的范围
- [x] Impact Analysis 覆盖所有相关维度
- [x] 技术选型有明确理由（与 RoutingTrait 同构）
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 模块间依赖关系清晰，无循环依赖
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致
- [x] Requirements CR 决策已在 design 中体现
- [x] Socratic Review 覆盖充分

### Clarification Round

**状态**: 已完成

**Q1:** SecurityTrait 的实现顺序偏好——是先完成 trait 本体（含所有 API + mergeSecurityConfigs），再修改 MicroKernel 和 ServicesTrait 的引用关系；还是先做最小可用版本（仅 addSecurityConfig + registerSecurity 合并），再逐步添加细粒度 API？
- A) 先完成 SecurityTrait 全部 API，再一次性修改 MicroKernel / ServicesTrait 引用
- B) 先做最小可用版本（addSecurityConfig + registerSecurity 合并 + boot 后保护），验证通过后再添加细粒度 API 和 getSecurityConfig
- C) 按功能切片：每个 API 方法作为独立 task（addSecurityConfig → addFirewall → addAccessRule → ...），每个 task 包含对应的单元测试
- D) 其他（请说明）

**A:** B — 先做最小可用版本（addSecurityConfig + registerSecurity 合并 + boot 后保护），验证通过后再添加细粒度 API 和 getSecurityConfig

**Q2:** 冲突检测的时机——当前 design 中 `addSecurityConfig()` 仅做顶层 key 校验，冲突检测（同名 firewall/policy/role）延迟到 `mergeSecurityConfigs()` 执行时（即 boot 阶段或 `getSecurityConfig()` 调用时）。是否需要在注入时（`addSecurityConfig` / `addFirewall` 等调用时）就立即检测冲突？这会影响 task 的拆分方式和测试策略。
- A) 保持当前设计——冲突在 merge 时检测（boot 阶段），注入时仅做 key 校验。简单且与 RoutingTrait 一致（路由冲突也是 boot 时才暴露）
- B) 注入时立即检测——每次 `addFirewall()` 调用时就检查已有 pending queue + Constructor_Config 中是否有同名 firewall，有则立即抛异常。开发体验更好（fail-fast），但实现更复杂
- C) 细粒度 API（addFirewall 等）注入时立即检测，批量 API（addSecurityConfig）延迟到 merge 时检测
- D) 其他（请说明）

**A:** B — 注入时立即检测（fail-fast）。扩展决策：所有 add 函数增加 `bool $allowOverwrite = false`（security）/ `bool $allowOverwrite = true`（routing）参数，控制同名冲突时是覆盖还是抛异常。RoutingTrait 的 `addRoute()` / `addRoutes()` 也需同步改造为 fail-fast + overwrite 参数模式，两者 API 对齐。

**Q3:** 测试策略中 Property-Based Tests 的范围——design 中提到 PBT 用于 preservation checking 和 mergeSecurityConfigs 合并逻辑。考虑到项目已有 `SecurityConfigPropertyTest.php`（graphify 中可见），新的 PBT 应该扩展现有测试类还是新建独立测试类？
- A) 扩展现有 `SecurityConfigPropertyTest.php`，在其中新增 test method 覆盖 SecurityTrait 的合并逻辑
- B) 新建独立测试类（如 `SecurityTraitPropertyTest.php`），与现有 PBT 分离
- C) 两者结合——preservation checking 放在现有类中（因为验证的是相同行为），SecurityTrait 特有逻辑（冲突检测、注入 API）放在新类中
- D) 其他（请说明）

**A:** D — 重组 security 相关 PBT 的文件结构（re-org），使 security PBT 测试有更清晰的目录组织

**Q4:** `getSecurityConfig()` 在 boot 后的行为——design 中未明确 `getSecurityConfig()` 在 boot 后是否仍可调用。boot 后 Pending_Queue 已被消费，此时调用 `getSecurityConfig()` 应返回什么？
- A) boot 后仍可调用，返回最终合并结果（与 boot 时传给 SimpleSecurityProvider 的 config 相同）。这对调试和运行时查询有用
- B) boot 后调用抛 LogicException，与注入 API 保持一致（所有 security config 相关方法 boot 后均不可用）
- C) boot 后可调用但返回空数组（Pending_Queue 已清空，Constructor_Config 不再重复读取）
- D) 其他（请说明）

**A:** B — boot 后调用 `getSecurityConfig()` 抛 LogicException，与注入 API 保持一致
