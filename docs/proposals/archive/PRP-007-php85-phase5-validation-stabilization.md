# PHP 8.5 Upgrade — Phase 5: Validation & Stabilization

> Proposal：全量测试、静态分析提升，确保项目在 PHP 8.5 下稳定运行。

## Status

`released`

## Background

经过 Phase 0–4 的依赖升级、框架替换、安全组件重构和语言适配后，项目代码已在 PHP 8.5 下可编译运行。本 Phase 作为收尾阶段，通过内部依赖升级、全量测试、静态分析和文档全面 review 确保升级的完整性和稳定性。

## Problem

- 前序 Phase 的变更覆盖面广，可能存在遗漏的兼容性问题
- 当前项目缺少静态分析工具（PHPStan）的高级别检查
- `oasis/utils` 和 `oasis/logging` 仍为 `^2.0`，需要升级到 `^3.0`
- 需要一个系统性的验证阶段确认所有变更的正确性
- `PROJECT.md`、`docs/state/`、`docs/manual/` 等文档在 Phase 0–4 完成后可能存在过时内容

## Goals

- 将 `oasis/utils` 从 `^2.0` 升级到 `^3.0`，排查并适配所有 API 变更
- 将 `oasis/logging` 从 `^2.0` 升级到 `^3.0`，排查并适配所有 API 变更
- 全量运行测试套件，确保所有测试在 PHP 8.5 下通过
- 引入 PHPStan 静态分析，发现潜在类型问题
- 修复验证过程中发现的所有问题
- 确认无 deprecation notice 输出
- 全面更新项目文档（`PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/`）反映 Phase 0–5 完成后的系统现状

## Non-Goals

- 不引入新功能
- 不进行代码现代化重构（已在 Phase 4 完成）
- 不涉及性能优化
- 不配置 CI 矩阵

## Scope

- `composer.json` — `oasis/utils`、`oasis/logging` 版本约束更新 + 开发依赖中添加 PHPStan
- PHPStan 配置文件
- `PROJECT.md` — 全面更新技术栈描述
- `README.md` — 更新版本要求说明
- `docs/state/` — 全面 review 并更新架构文档
- `docs/manual/` — 全面 review 并更新使用文档
- 验证过程中发现的任何需要修复的文件

## Risks

- `oasis/utils` ^3.0 可能引入 breaking changes，项目中使用面广（`ArrayDataProvider`、`DataProviderInterface` 等贯穿配置层）
- 静态分析提升级别后可能暴露大量历史问题，需评估修复范围

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`phpunit`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 5 完成后的测试预期

本 Phase 是 branch 级 DoD 的最终验证点。

**预期通过：**

- `phpunit` 全量通过
- PHPStan 静态分析通过
- 无 deprecation notice

全部通过后，`feature/php85-upgrade` merge 回 develop。

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note

## Notes

- 依赖 Phase 0–4 全部完成
- 本 Phase 完成后，整个 PHP 8.5 升级工作结束，各 Phase 的 proposal 可标记为 `implemented`
- 建议在本 Phase 完成后，在 `docs/changes/` 中记录整个升级的变更摘要
