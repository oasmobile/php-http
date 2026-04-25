# Spec Goal: PHP 8.5 Upgrade — Phase 4: PHP Language Adaptation

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-006-php85-phase4-language-adaptation.md`

## 背景摘要

项目从 PHP 7.0 升级到 8.5，跨越 5 个大版本。Phase 0 已完成依赖升级和 PHPUnit 13.x 适配，Phase 1 已完成 Silex → Symfony MicroKernel 替换和 Symfony 组件升级到 7.x，Phase 2 已完成 Twig 3.x 和 Guzzle 7.x 升级，Phase 3 已完成 Security 组件的 authenticator 系统重写。

前序 Phase 聚焦于框架和依赖层面的兼容性，PHP 语言本身在 7.0 → 8.5 之间引入的 breaking changes 尚未系统性排查和修复。这些语言层面的变化分散在多个大版本中，包括但不限于：

- **隐式 nullable 参数移除**（PHP 8.2 弃用 → 8.4 正式移除）：`function foo(Type $param = null)` 必须改为 `?Type $param = null`。当前代码中已发现 `src/Exceptions/UniquenessViolationHttpException.php` 存在 `\Exception $previous = null` 的隐式 nullable 模式，`src/` 和 `ut/` 中可能还有更多
- **动态属性弃用**（PHP 8.2 起）：未声明的属性赋值会产生 deprecation notice。Phase 1 已移除 Silex/Pimple（动态属性的主要来源），但项目自身代码中可能仍有残留
- **字符串/数字比较行为变更**（PHP 8.0 起）：`0 == "foo"` 从 `true` 变为 `false`，影响松散比较逻辑
- **内部函数参数类型检查严格化**（PHP 8.0 起）：传入错误类型从 warning 变为 TypeError
- **其他累积的弃用和移除**：`each()`、`create_function()`、`Serializable` 接口弃用、`${var}` 字符串插值弃用等

此外，`composer.json` 中的 PHP 版本约束已在 Phase 0 更新为 `>=8.5`，本 Phase 无需再改。但 `composer.json` 的 `description` 字段仍引用 "Silex"（`"An extension to Silex, for HTTP related routing, middleware, and so on."`），Phase 1 已将框架替换为 Symfony MicroKernel，该描述已过时。

### 当前测试状态

Phase 3 完成后，`security`、`integration`、`pbt` 等 suite 已通过。但由于 PHP 语言层面 breaking changes 尚未修复，全量测试（`--testsuite all`）可能存在因 deprecation notice 或隐式行为变更导致的零星失败。

## 目标

- 排查并修复 `src/` 和 `ut/` 中所有隐式 nullable 参数（`Type $param = null` → `?Type $param = null`）
- 排查并修复动态属性使用（添加属性声明或使用 `#[AllowDynamicProperties]`）
- 排查并修复松散比较中可能受字符串/数字比较行为变更影响的逻辑
- 排查并修复内部函数调用中的隐式类型转换
- 修复其他 PHP 7.x → 8.5 的已知 breaking changes（`each()`、`create_function()`、`Serializable` 接口弃用、`${var}` 字符串插值弃用等）
- **主动采用 PHP 8.x 新语法进行代码现代化**，包括但不限于：
  - Constructor property promotion
  - `match` 表达式替代适用的 `switch`
  - `readonly` 属性（适用于不可变字段）
  - Union types / intersection types（替代 `@param` 注释中的类型声明）
  - Named arguments（在提升可读性的场景使用）
  - `enum`（替代常量组，如适用）
  - Nullsafe operator `?->`（替代 `if ($x !== null) $x->method()` 模式）
  - `str_contains()` / `str_starts_with()` / `str_ends_with()`（替代 `strpos()` 惯用法）
  - `array_is_list()`（如适用）
  - First-class callable syntax `foo(...)` （替代 `Closure::fromCallable()`）
- 更新 `composer.json` 的 `description` 字段，移除对 Silex 的引用，反映当前框架（Symfony MicroKernel）
- 确保代码在 PHP 8.5 下无 deprecation notice
- 确保 `--testsuite all` 全量通过
- 更新 `docs/state/architecture.md`，反映本 Phase 的变更（如有结构性变化）

## 不做的事情（Non-Goals）

- 不涉及依赖包的升级（已在前序 Phase 完成）
- 不引入静态分析工具（PHPStan / Psalm）——留给 Phase 5
- 不配置 CI 矩阵——留给 Phase 5
- 不涉及新功能引入
- 不变更公共 API 的外部行为（现代化是语法层面的，不改变功能语义）

## Clarification 记录

### Q1: 隐式 nullable 参数的排查范围

`src/` 和 `ut/` 中都可能存在隐式 nullable 参数。排查范围是什么？

- 选项: A) 仅修复 `src/` 中的隐式 nullable 参数，`ut/` 中的留给后续 / B) `src/` 和 `ut/` 全部修复 / C) 补充说明
- 回答: B — `src/` 和 `ut/` 全部修复

### Q2: 松散比较排查策略

PHP 8.0 起字符串/数字比较行为变更（`0 == "foo"` 从 `true` 变为 `false`）。排查策略是什么？

- 选项: A) 仅排查 `src/` 中的松散比较（`==`、`!=`），`ut/` 中的测试代码不排查 / B) `src/` 和 `ut/` 全部排查 / C) 仅排查涉及可能为数字 0 或空字符串的松散比较，不做全面替换 / D) 补充说明
- 回答: B — `src/` 和 `ut/` 全部排查

### Q3: Phase 3 遗留项的处理方式

Phase 3 的手工测试（Task 9）和 Code Review（Task 10）未完成。处理方式是什么？

- 选项: A) 在本 Phase 开始前先完成 Phase 3 遗留项，作为本 Phase 的前置 task / B) 将 Phase 3 遗留项合并到本 Phase 的手工测试和 Code Review 中，一并执行 / C) 跳过 Phase 3 遗留项，仅在本 Phase 中做本 Phase 范围的手工测试和 Code Review / D) 补充说明
- 回答: D — Phase 3 没有遗留，Phase 3 的任务会在本 spec 执行前完成。本 Phase 不需要处理 Phase 3 遗留项

### Q4: `composer.json` description 更新

`composer.json` 的 `description` 仍引用 Silex。是否在本 Phase 更新？

- 选项: A) 在本 Phase 更新 description，移除 Silex 引用 / B) 留给 Phase 5 或单独处理 / C) 补充说明
- 回答: A — 在本 Phase 更新 description，移除 Silex 引用

## 约束与决策

- PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- 依赖 Phase 0–3 完成（测试框架可用、框架已替换、Twig/Guzzle 已升级、Security 已重写）
- `composer.json` 中 PHP 版本约束已在 Phase 0 更新为 `>=8.5`，本 Phase 无需再改
- 在修复兼容性问题的同时，主动采用 PHP 8.x 新语法进行代码现代化（与 PRP-006 原始 Non-Goals 不同，用户明确要求扩展 scope）
- 隐式 nullable 参数排查范围为 `src/` 和 `ut/` 全部修复（Q1=B）
- 松散比较排查范围为 `src/` 和 `ut/` 全部排查（Q2=B）
- Phase 3 的任务会在本 spec 执行前完成，本 Phase 不处理 Phase 3 遗留项（Q3=D）
- `composer.json` 的 `description` 在本 Phase 更新，移除 Silex 引用（Q4=A）
- spec 级 DoD：tasks 全部完成 + PRP-006 中定义的预期通过 suite 实际通过
- 预期通过的 suite：`--testsuite all` 全量通过，无 deprecation notice
- 预期可能残留的问题：静态分析发现的类型问题（等 Phase 5 处理）、CI 矩阵尚未配置（等 Phase 5）

## Socratic Review

1. **goal 是否完整覆盖了 PRP-006 的 Goals？**
   - PRP-006 列出 7 个目标，全部覆盖（详见各目标项）。此外，用户明确要求扩展 scope：在兼容性修复之外，主动采用 PHP 8.x 新语法进行代码现代化。这超出了 PRP-006 原始 Non-Goals 的范围，但用户指令优先于 proposal。目标中已列出具体的现代化项（constructor promotion、match、readonly、union types、named arguments、enum、nullsafe operator、str_contains 等）。

2. **Non-Goals 是否与 PRP-006 一致？**
   - 不完全一致。PRP-006 的 Non-Goals 排除了代码现代化，但用户明确要求纳入。goal 中已移除"不主动采用新特性"的 Non-Goal，改为目标项。其余 Non-Goals（不升级依赖、不引入静态分析、不配置 CI）与 PRP-006 一致。

3. **`composer.json` PHP 版本约束是否需要在本 Phase 处理？**
   - 不需要。Phase 0 已将 `php` 约束从 `>=7.0.0` 改为 `>=8.5`。PRP-006 的 Goals 中提到此项可能是基于 draft 时的假设（各 Phase 独立时的预期），实际执行中已在 Phase 0 完成。

4. **背景摘要是否准确反映了代码现状？**
   - 是。通过 grep 搜索确认了 `src/Exceptions/UniquenessViolationHttpException.php` 中存在隐式 nullable 参数（`\Exception $previous = null`）。`composer.json` 确认 PHP 版本已为 `>=8.5`，description 仍引用 Silex。

5. **Clarification 决策是否已完整体现在约束中？**
   - Q1（排查范围 `src/` + `ut/`）→ 约束中明确；Q2（松散比较全面排查）→ 约束中明确；Q3（Phase 3 无遗留）→ 约束中明确本 Phase 不处理；Q4（更新 description）→ 目标中已列出。四个决策均已体现。

6. **Clarification 问题是否覆盖了关键决策点？**
   - Q1（排查范围）决定工作量和 scope 边界；Q2（松散比较策略）决定排查深度；Q3（Phase 3 遗留）澄清了前置条件；Q4（description 更新）决定是否包含非语言层面的修复。四个问题覆盖了主要决策点。

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ✅ 通过

### 修正项
无

### 合规检查
- [x] 标题格式符合 `# Spec Goal: <标题>` 规范
- [x] `## 来源` 包含分支名和需求文档路径，且路径正确
- [x] `## 背景摘要` 内容充实（2-4 段），准确反映 SSOT 和代码现状
- [x] 背景中的事实声明已验证：`composer.json` PHP 约束为 `>=8.5`、description 仍引用 Silex、`UniquenessViolationHttpException.php` 存在隐式 nullable 参数
- [x] `## 目标` 以 bullet list 呈现，完整覆盖 PRP-006 的 7 个 Goals
- [x] 目标中扩展的代码现代化 scope 有明确的用户决策依据，与 PRP-006 原始 Non-Goals 的偏差已在约束和 Socratic Review 中说明
- [x] `## 不做的事情（Non-Goals）` 以 bullet list 呈现，边界清晰
- [x] `## Clarification 记录` 包含至少 3 个问题（实际 4 个），每个问题有至少 3 个选项，最后一个选项为开放式补充说明
- [x] Clarification 聚焦 scope 边界和意图澄清，未涉及技术选型或实现细节（符合 spec-goal steering 的聚焦方向）
- [x] `## 约束与决策` 完整体现了 4 个 Clarification 决策（Q1=B, Q2=B, Q3=D, Q4=A）
- [x] 约束中包含 spec 级 DoD 和预期通过的 suite
- [x] `## Socratic Review` 为额外自检 section，内容准确，不影响结构合规性
