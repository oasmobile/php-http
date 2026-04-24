# PHP 8.5 Upgrade — Phase 5: Validation & Stabilization

> Proposal：全量测试、静态分析提升、CI 矩阵覆盖，确保项目在 PHP 8.4 和 8.5 下稳定运行。

## Status

`draft`

## Background

经过 Phase 0–4 的依赖升级、框架替换、安全组件重构和语言适配后，项目代码已在 PHP 8.5 下可编译运行。本 Phase 作为收尾阶段，通过全量测试、静态分析和 CI 配置确保升级的完整性和稳定性。

## Problem

- 前序 Phase 的变更覆盖面广，可能存在遗漏的兼容性问题
- 当前项目缺少静态分析工具（PHPStan / Psalm）的高级别检查
- CI 矩阵尚未覆盖 PHP 8.5 版本
- 需要一个系统性的验证阶段确认所有变更的正确性

## Goals

- 全量运行测试套件，确保所有测试在 PHP 8.5 下通过
- 引入或提升 PHPStan / Psalm 的分析级别，发现潜在类型问题
- 配置 CI 矩阵覆盖 PHP 8.5
- 修复验证过程中发现的所有问题
- 确认无 deprecation notice 输出
- 更新项目文档（`README.md`、`docs/state/architecture.md` 等）反映新的 PHP 版本要求和依赖版本

## Non-Goals

- 不引入新功能
- 不进行代码现代化重构（如采用 PHP 8.x 新语法）
- 不涉及性能优化

## Scope

- CI 配置文件（如 `.github/workflows/` 或等价 CI 配置）
- PHPStan / Psalm 配置文件
- `composer.json` — 开发依赖中添加静态分析工具
- `docs/state/architecture.md` — 更新架构文档
- `README.md` — 更新版本要求说明
- 验证过程中发现的任何需要修复的文件

## Risks

- PHP 8.5 GA 时间为 2025 年 11 月，若在此之前执行本 Phase，CI 矩阵中的 8.5 覆盖可能需要使用 RC 版本
- 静态分析提升级别后可能暴露大量历史问题，需评估修复范围

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`--testsuite all`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 5 完成后的测试预期

本 Phase 是 branch 级 DoD 的最终验证点。

**预期通过：**

- `--testsuite all` 全量通过
- PHPStan / Psalm 静态分析通过（目标级别在 spec design 阶段确定）
- CI 矩阵覆盖 PHP 8.4 + 8.5，全绿
- 无 deprecation notice

全部通过后，`feature/php85-upgrade` merge 回 develop。

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note

## Notes

- 依赖 Phase 0–4 全部完成
- 本 Phase 完成后，整个 PHP 8.5 升级工作结束，各 Phase 的 proposal 可标记为 `implemented`
- 建议在本 Phase 完成后，在 `docs/changes/` 中记录整个升级的变更摘要
