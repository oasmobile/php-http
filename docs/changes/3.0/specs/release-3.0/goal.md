# Spec Goal: Release 3.0

## 来源

- 分支: `release/3.0`
- 来源分支: `develop`

## 背景摘要

`oasis/http` 自 v2.5.0（测试基线补全）发布以来，在 `feature/php85-upgrade` 分支上完成了 PHP 8.5 全面升级（PRP-002 ~ PRP-007，共 6 个 Phase），并在独立分支上完成了迁移指南与检查脚本（PRP-008）。所有 feature 已合并回 develop，对应的 7 个 proposal 均处于 `implemented` 状态。

本次 release 是一个 **major version bump**（2.5.0 → 3.0），因为包含大量 breaking changes：

- PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`
- 核心框架从 Silex 2.x 替换为 Symfony MicroKernel（Symfony 7.x）
- DI 容器从 Pimple 迁移到 Symfony DependencyInjection
- Security 组件 authenticator 系统完全重写（适配 Symfony Security 7.x）
- 多个公共 API 接口重新设计（`AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface` 等）
- 多个依赖大版本升级（Twig 3.x、Guzzle 7.x、oasis/utils ^3.0、oasis/logging ^3.0、PHPUnit 13.x）
- 引入 PHPStan level 8 静态分析和 Eris PBT 框架

当前 develop 分支上的代码状态：

- `phpunit` 全量通过（560 tests, 21182 assertions）
- PHPStan level 8 通过，零错误
- 零 deprecation notice
- `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 已在 Phase 5 全面更新
- Migration Guide（`docs/manual/migration-v3.md`）和 Check Script（`bin/oasis-http-migrate-v3-check`）已就绪

### unreleased 变更清单

| 变更文档 | 对应 Proposal | 内容 |
|----------|--------------|------|
| `docs/changes/unreleased/php85-upgrade.md` | PRP-002 ~ PRP-007 | PHP 8.5 全面升级：框架替换、依赖升级、Security 重构、语言适配、验证稳定化 |
| `docs/changes/unreleased/php85-migration-guide.md` | PRP-008 | Migration Guide 文档 + Check Script |

### unreleased specs 清单

| Spec | 对应 Phase |
|------|-----------|
| `docs/changes/unreleased/specs/php85-phase0-prerequisites/` | Phase 0（PRP-002） |
| `docs/changes/unreleased/specs/php85-phase1-framework-replacement/` | Phase 1（PRP-003） |
| `docs/changes/unreleased/specs/php85-phase2-twig-guzzle-upgrade/` | Phase 2（PRP-004） |
| `docs/changes/unreleased/specs/php85-phase3-security-refactor/` | Phase 3（PRP-005） |
| `docs/changes/unreleased/specs/php85-phase4-language-adaptation/` | Phase 4（PRP-006） |
| `docs/changes/unreleased/specs/php85-phase5-validation-stabilization/` | Phase 5（PRP-007） |

### 活跃 spec

| Spec | 对应 Proposal | 状态 |
|------|--------------|------|
| `.kiro/specs/php85-migration-guide/` | PRP-008 | tasks 全部完成（所有 checkbox `[x]`） |

## 目标

- 验证 develop 分支上所有 unreleased 变更在 release 分支上的完整性和正确性
- 运行全量测试套件（`phpunit`）和静态分析（`phpstan analyse`），确认零失败、零错误
- 对 Migration Guide（`docs/manual/migration-v3.md`）和 Check Script（`bin/oasis-http-migrate-v3-check`）做一轮端到端手工验证，确认文档可读性和脚本实际可用性
- 整理 `docs/changes/3.0/CHANGELOG.md`，将 unreleased 下的两份变更记录重新组织为统一格式，按变更类型（Added / Changed / Removed）分 section
- 更新全局 `docs/changes/CHANGELOG.md`，追加 v3.0 摘要
- 确认活跃 spec（`.kiro/specs/php85-migration-guide/`）对应的 unreleased 变更记录（`docs/changes/unreleased/php85-migration-guide.md`）内容完整后，归档到 `docs/changes/3.0/specs/`
- 归档 unreleased specs 到 `docs/changes/3.0/specs/`
- 清理 `docs/changes/unreleased/` 中本次 release 包含的变更记录
- 将 PRP-002 ~ PRP-008 状态从 `implemented` 更新为 `released`，并归档到 `docs/proposals/archive/`
- 确保 `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 内容与 release 版本一致（Phase 5 已更新，release 阶段做最终确认）

## Clarification 记录

### Q1: unreleased 变更的验证深度

develop 上的代码已经过各 Phase 的全量测试和 Phase 5 的最终验证（560 tests, 21182 assertions + PHPStan level 8）。release 分支上的验证深度是什么？

- 选项: A) 仅运行 `phpunit` + `phpstan analyse`，确认零失败即可 / B) 除 A 外，还需对 Migration Guide 和 Check Script 做一轮端到端手工验证 / C) 补充说明
- 回答: B — 除全量测试和静态分析外，还需对 Migration Guide 和 Check Script 做一轮端到端手工验证

### Q2: `docs/changes/3.0/CHANGELOG.md` 的整理方式

unreleased 下有两份变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`）。CHANGELOG 整理方式是什么？

- 选项: A) 直接合并两份文件内容，按 feature 分 section，保留原有的 Added/Changed/Removed 结构 / B) 重新组织为统一的 CHANGELOG 格式，按变更类型（Added/Changed/Removed/Fixed）而非 feature 分 section / C) 补充说明
- 回答: B — 重新组织为统一的 CHANGELOG 格式，按变更类型分 section

### Q3: 活跃 spec 归档前是否需要额外处理

`.kiro/specs/php85-migration-guide/` 的 tasks 全部完成，但它目前还在 `.kiro/specs/` 下（活跃位置）。归档时是否有额外步骤？

- 选项: A) 直接移入 `docs/changes/3.0/specs/php85-migration-guide/`，与 unreleased specs 同等处理 / B) 先确认其 unreleased 变更记录（`docs/changes/unreleased/php85-migration-guide.md`）内容完整，再归档 / C) 补充说明
- 回答: B — 先确认 unreleased 变更记录内容完整，再归档

## 不做的事情（Non-Goals）

- 不引入新功能或代码变更（release 分支仅做发布准备）
- 不修复 bug（如发现 bug 应回到 develop 修复后重新合并）
- 不变更版本号管理方式（版本号由 Packagist / tag 管理，`composer.json` 中无显式 `version` 字段）

## 约束与决策

- Release 分支 `release/3.0` 从 `develop` 创建，完成后 merge 回 `develop` 和 `master`，并在 `master` 上打 `v3.0.0` tag
- 遵循 `docs/changes/README.md` 定义的 release 流程：创建版本目录 → 合并变更记录 → 归档 specs → 更新全局 CHANGELOG → 清理 unreleased
- CHANGELOG 按变更类型（Added / Changed / Removed）统一组织，不按 feature 拆分 section（Q2=B）
- release 验证除全量测试和静态分析外，还需对 Migration Guide 和 Check Script 做端到端手工验证（Q1=B）
- 活跃 spec 归档前先确认其 unreleased 变更记录内容完整（Q3=B）
- Proposal 归档遵循 `docs/proposals/README.md`：`released` 状态的 proposal 在 release finish 阶段移入 `docs/proposals/archive/`
- spec 级 DoD：全量测试通过 + 静态分析通过 + 手工验证通过 + 文档收敛完成 + specs 归档完成 + proposals 归档完成

## Socratic Review

1. **unreleased 变更是否完整识别？**
   - 是。`docs/changes/unreleased/` 下有两份变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`），对应 PRP-002 ~ PRP-008 的全部工作。无遗漏。

2. **specs 归档范围是否完整？**
   - 是。unreleased specs 目录下有 6 个 phase spec（Phase 0–5），加上活跃 spec（`.kiro/specs/php85-migration-guide/`），共 7 个 spec 需要归档到 `docs/changes/3.0/specs/`。

3. **proposal 归档范围是否完整？**
   - 是。PRP-002 ~ PRP-008 共 7 个 proposal，全部处于 `implemented` 状态，release 完成后应更新为 `released` 并归档。

4. **是否存在未完成的 feature 需要排除？**
   - 否。所有 unreleased 变更均已完成（7 个 proposal 全部 `implemented`，活跃 spec tasks 全部 `[x]`），本次 release 包含全部 unreleased 内容。

5. **版本号选择是否合理？**
   - 是。本次 release 包含大量 breaking changes（框架替换、公共 API 重写、PHP 版本要求提升），符合 semver major version bump 的标准。v3.0.0 是正确的版本号。

6. **Clarification 决策是否已完整体现在目标和约束中？**
   - Q1（端到端手工验证）→ 目标中新增 Migration Guide 和 Check Script 手工验证项，约束中明确验证深度；Q2（按变更类型组织 CHANGELOG）→ 目标中明确整理方式，约束中记录决策；Q3（归档前确认变更记录完整）→ 目标中调整归档步骤顺序，约束中记录决策。三个决策均已体现。
