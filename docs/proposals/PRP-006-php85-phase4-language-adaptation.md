# PHP 8.5 Upgrade — Phase 4: PHP Language Adaptation

> Proposal：修复从 PHP 7.0 到 8.4/8.5 的语言层面 breaking changes，确保代码在目标 PHP 版本下无 deprecation 和错误。

## Status

`implemented`

## Background

项目从 PHP 7.0 升级到 8.5，跨越 5 个大版本。PHP 语言本身在这些版本中引入了大量 breaking changes，包括隐式 nullable 参数移除、动态属性弃用、字符串/数字比较行为变更、内部函数类型检查严格化等。这些变化可能导致运行时错误或行为不一致。

## Problem

- **隐式 nullable 参数**：PHP 8.4 正式移除 `function foo(Type $param = null)` 模式，需全面改为 `?Type $param = null`
- **动态属性弃用**：PHP 8.2 起弃用，项目代码中可能存在依赖动态属性的模式
- **字符串/数字比较行为变更**：PHP 8.0 起 `0 == "foo"` 从 `true` 变为 `false`，影响松散比较逻辑
- **内部函数参数类型检查严格化**：PHP 8.0 起传入错误类型从 warning 变为 TypeError
- **其他累积的弃用和移除**：`each()`、`create_function()`、`Serializable` 接口弃用、`${var}` 字符串插值弃用等

## Goals

- 排查并修复所有隐式 nullable 参数（`Type $param = null` → `?Type $param = null`）
- 排查并修复动态属性使用（添加属性声明或使用 `#[AllowDynamicProperties]`）
- 排查并修复松散比较中可能受字符串/数字比较行为变更影响的逻辑
- 排查并修复内部函数调用中的隐式类型转换
- 修复其他 PHP 7.x → 8.5 的已知 breaking changes
- 将 `composer.json` 中的 PHP 版本要求从 `>=7.0.0` 更新为 `>=8.5`
- 确保代码在 PHP 8.5 下无 deprecation notice

## Non-Goals

- 不主动采用 PHP 8.x 新特性（如 enum、readonly、match 等）进行代码现代化——仅修复兼容性问题
- 不涉及依赖包的升级（已在前序 Phase 完成）

## Scope

- `src/` 目录下所有 PHP 源文件
- `ut/` 目录下所有测试文件
- `composer.json` 中的 PHP 版本约束

## Risks

- 跨 5 个大版本的隐式行为变更多且分散，可能遗漏
- 字符串/数字比较行为变更的影响难以通过静态分析完全发现，需依赖测试覆盖
- 部分 breaking changes 仅在特定运行路径下触发，需要充分的测试场景

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`phpunit`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 4 完成后的测试预期

PHP 语言层面 breaking changes 全部修复，`composer.json` 中 PHP 约束收紧到 `>=8.5`。

**预期通过：**

- `phpunit` 全量通过——所有框架依赖已在 Phase 1–3 解决，语言层面兼容性在本 Phase 修复
- 无 deprecation notice

**预期可能残留的问题：**

- 静态分析发现的类型问题（等 Phase 5 处理）
- CI 矩阵尚未配置（等 Phase 5）

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note（语言层面 Breaking Changes 章节）

## Notes

- 依赖 Phase 0 和 Phase 1 完成（测试框架可用、框架已替换）；可与 Phase 2、3 并行推进——语言层面修复不依赖 Twig/Guzzle 升级或 Security 重写
- 建议配合 PHPStan / Psalm 静态分析工具辅助排查
