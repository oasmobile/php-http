# Release 3.0 Requirements

> Release 3.0 的发布范围、Feature 概要、已知 Issue 评估与发布判定。

---

## 发布范围

| Feature 名称 | Spec 路径 | Proposal 引用 | Proposal Status | Tasks 完成状态 |
|--------------|-----------|---------------|-----------------|---------------|
| PHP 8.5 升级前置准备 | `docs/changes/unreleased/specs/php85-phase0-prerequisites/` | `docs/proposals/PRP-002-php85-phase0-prerequisites.md` | `implemented` | 全部完成 |
| 框架替换（Silex → Symfony MicroKernel） | `docs/changes/unreleased/specs/php85-phase1-framework-replacement/` | `docs/proposals/PRP-003-php85-phase1-framework-replacement.md` | `implemented` | 全部完成 |
| Twig & Guzzle 升级 | `docs/changes/unreleased/specs/php85-phase2-twig-guzzle-upgrade/` | `docs/proposals/PRP-004-php85-phase2-twig-guzzle-upgrade.md` | `implemented` | 全部完成 |
| Security 组件重构 | `docs/changes/unreleased/specs/php85-phase3-security-refactor/` | `docs/proposals/PRP-005-php85-phase3-security-refactor.md` | `implemented` | 全部完成 |
| PHP 语言适配 | `docs/changes/unreleased/specs/php85-phase4-language-adaptation/` | `docs/proposals/PRP-006-php85-phase4-language-adaptation.md` | `implemented` | 全部完成 |
| 验证与稳定化 | `docs/changes/unreleased/specs/php85-phase5-validation-stabilization/` | `docs/proposals/PRP-007-php85-phase5-validation-stabilization.md` | `implemented` | 全部完成 |
| Migration Guide & Check Script | `.kiro/specs/php85-migration-guide/` | `docs/proposals/PRP-008-php85-migration-guide.md` | `implemented` | 全部完成 |

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
| `.kiro/specs/php85-migration-guide/` | PRP-008 | tasks 全部完成 |


---

## Feature 概要

### Phase 0: 前置准备（PRP-002）

将内部依赖和测试框架升级到 PHP 8.5 兼容版本，为后续 Phase 扫清前置阻塞。

**核心变更：**

- `oasis/logging` 升级到 PHP 8.5 兼容版本
- `oasis/utils` 升级到 PHP 8.5 兼容版本
- PHPUnit 从 `^5.2` 升级到 `^13.0`
- 全部现有测试文件适配 PHPUnit 13.x API（`setUp(): void`、`expectException`、`createMock`、data provider attribute 等）

### Phase 1: 框架替换（PRP-003）

移除已 abandoned 的 Silex 框架，用 Symfony MicroKernel 替换，同时升级全部 Symfony 组件到 7.x。这是整个升级中工作量最大、影响面最广的 Phase。

**核心变更：**

- 移除 `silex/silex` `^2.3` 和 `silex/providers` `^2.3`
- `SilexKernel` 重写为 `MicroKernel`，基于 Symfony `HttpKernel`，实现 `AuthorizationCheckerInterface`
- DI 容器从 Pimple 迁移到 Symfony DependencyInjection
- 路由注册机制迁移到 Symfony Routing 7.x
- 中间件机制迁移到 Symfony EventDispatcher
- 所有 Service Provider 迁移到新框架模式
- 全部 Symfony 组件从 `^4.0` 升级到 `^7.2`
- 引入 Eris 1.x 作为 Property-Based Testing 框架

### Phase 2: Twig & Guzzle 升级（PRP-004）

将 Twig 从 1.x 直接升级到 3.x，Guzzle 从 6.x 升级到 7.x，移除已 abandoned 的 `twig/extensions`。

**核心变更：**

- `twig/twig` 从 `^1.24` 升级到 `^3.0`
- 移除 `twig/extensions` `^1.3`
- 适配 Twig 3.x 扩展 API（`Twig_Extension` → `AbstractExtension` 等）
- `guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`

### Phase 3: Security 组件重构（PRP-005）

适配 Symfony Security 7.x 的新 authenticator 系统，重写项目中的全部自定义安全组件。Security 组件的 API 变化是所有 Symfony 组件中最大的。

**核心变更：**

- `AbstractSimplePreAuthenticator` 重写，适配 Symfony 7.x `AuthenticatorInterface`
- `AbstractSimplePreAuthenticateUserProvider` 适配新 `UserProviderInterface`
- `SimpleSecurityProvider` 的 firewall / access rule 注册机制适配
- `AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface` 接口重写
- `NullEntryPoint` 适配新 Security 架构
- 大量补充 PBT（Eris），为 access rule 组合、firewall 匹配、认证策略建立 property 验证

### Phase 4: PHP 语言适配（PRP-006）

修复从 PHP 7.0 到 8.5 跨 5 个大版本的语言层面 breaking changes。

**核心变更：**

- 修复所有隐式 nullable 参数（`Type $param = null` → `?Type $param = null`）
- 修复动态属性使用
- `composer.json` PHP 版本约束从 `>=7.0.0` 更新为 `>=8.5`
- 确保零 deprecation notice

### Phase 5: 验证与稳定化（PRP-007）

全量测试、静态分析提升、内部依赖最终升级和文档全面更新，确保项目在 PHP 8.5 下稳定运行。

**核心变更：**

- `oasis/utils` 从 `^2.0` 升级到 `^3.0`
- `oasis/logging` 从 `^2.0` 升级到 `^3.0`
- 引入 PHPStan `^2.1`，level 8 静态分析零错误
- 全量测试通过：560 tests, 21182 assertions
- `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 全面更新

### Migration Guide & Check Script（PRP-008）

为下游消费者提供完整的迁移文档和预升级检查脚本，帮助依赖 `oasis/http` 的项目平滑过渡到 v3。

**核心产物：**

- Migration Guide（`docs/manual/migration-v3.md`）：按模块分 12 个章节，每个 breaking change 标注严重程度（🔴/🟡/🟢），提供 before/after 代码示例
- Check Script（`bin/oasis-http-migrate-v3-check`）：基于 `token_get_all()` 的 token 级扫描，检测对已移除/已变更 API 的引用，支持 text/JSON 输出
- Document Validation Tests + PBT（Properties 1–11）+ Unit Tests 三层测试覆盖


---

## 已知 Issue 评估

`issues/` 目录下无已知 issue。本次发布不受任何已知问题影响。

---

## Glossary

- **Release_Process**：`docs/changes/README.md` 定义的发布流程，包括创建版本目录、合并变更记录、归档 specs、更新全局 CHANGELOG、清理 unreleased
- **Release_Branch**：`release/3.0` 分支，从 `develop` 创建，完成后 merge 回 `develop` 和 `master`
- **CHANGELOG_Generator**：将 Unreleased_Changes 重新组织为统一 CHANGELOG 格式的过程
- **Spec_Archiver**：将已完成的 specs 从活跃位置移入 `docs/changes/<version>/specs/` 的过程
- **Proposal_Archiver**：将 `released` 状态的 proposal 移入 `docs/proposals/archive/` 的过程
- **Migration_Guide**：`docs/manual/migration-v3.md`，面向下游消费者的迁移文档
- **Check_Script**：`bin/oasis-http-migrate-v3-check`，预升级检查脚本
- **Unreleased_Changes**：`docs/changes/unreleased/` 下的变更记录文件
- **Active_Spec**：`.kiro/specs/` 下尚未归档的已完成 spec

---

## Requirements

### Requirement 1: 全量测试确认

**User Story:** 作为 release manager，我希望确认 release 分支上所有测试通过，以便确保发布前的代码质量。

#### Acceptance Criteria

1. WHEN Release_Branch `release/3.0` is created from `develop`, THE Release_Process SHALL execute `phpunit` and confirm 560 tests and 21182 assertions pass with zero failures and zero errors
2. WHEN Release_Branch `release/3.0` is created from `develop`, THE Release_Process SHALL execute `phpstan analyse` and confirm zero errors at level 8
3. WHEN Release_Branch `release/3.0` is created from `develop`, THE Release_Process SHALL confirm zero deprecation notices in the test output

### Requirement 2: Migration Guide 端到端手工验证

**User Story:** 作为 release manager，我希望验证 Migration Guide 的可读性和完整性，以便下游消费者可以依赖它进行升级。

#### Acceptance Criteria

1. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Migration_Guide exists and contains all 12 module chapters plus PHP 语言适配 and 附录
2. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that all TOC anchor links in the Migration_Guide resolve to valid headings
3. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that every breaking change entry in the Migration_Guide contains a severity marker（🔴/🟡/🟢）, before/after code blocks, and an action description
4. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that the Bootstrap Config Key reference table in the Migration_Guide covers all keys defined in `docs/state/architecture.md`

### Requirement 3: Check Script 端到端手工验证

**User Story:** 作为 release manager，我希望验证 Check Script 的正确性，以便下游消费者可以用它评估升级影响。

#### Acceptance Criteria

1. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Check_Script `--help` outputs usage information
2. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Check_Script correctly detects known Removed API references in a test PHP file
3. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Check_Script correctly detects Pimple access patterns in a test PHP file
4. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Check_Script `--format=json` produces valid JSON output
5. WHEN Release_Branch passes automated tests, THE Release_Process SHALL verify that Check_Script returns exit code 1 when 🔴 findings exist and exit code 0 when no 🔴 findings exist

### Requirement 4: 版本 CHANGELOG 生成

**User Story:** 作为 release manager，我希望生成 v3.0 的统一 CHANGELOG，以便 release 有完整且组织良好的变更记录。

#### Acceptance Criteria

1. WHEN all validations pass, THE CHANGELOG_Generator SHALL create `docs/changes/3.0/CHANGELOG.md`
2. THE CHANGELOG_Generator SHALL organize changes by type（Added / Changed / Removed）, not by feature
3. THE CHANGELOG_Generator SHALL merge content from Unreleased_Changes（`php85-upgrade.md` and `php85-migration-guide.md`）into the unified CHANGELOG
4. THE CHANGELOG_Generator SHALL include a release date, a summary paragraph, and a test coverage section in the CHANGELOG. The test coverage section SHALL include: final test statistics（560 tests, 21182 assertions, PHPStan level 8 零错误）, test types and counts per Phase（PBT properties, integration tests, etc.）, and test coverage percentage（requires running coverage tool）（CR Q1=C）
5. THE CHANGELOG_Generator SHALL include a Resolved Notes section listing notes resolved during the upgrade

### Requirement 5: 全局 CHANGELOG 更新

**User Story:** 作为 release manager，我希望更新全局 CHANGELOG，以便项目有完整的版本索引。

#### Acceptance Criteria

1. WHEN `docs/changes/3.0/CHANGELOG.md` is created, THE Release_Process SHALL prepend a v3.0 summary entry to `docs/changes/CHANGELOG.md`
2. THE Release_Process SHALL follow the existing format: version heading, one-line summary, and a link to `3.0/CHANGELOG.md`

### Requirement 6: 活跃 Spec 变更记录完整性确认

**User Story:** 作为 release manager，我希望在归档前确认 Active_Spec 的变更记录完整，以便归档过程中不丢失信息。

#### Acceptance Criteria

1. WHEN preparing to archive the Active_Spec `.kiro/specs/php85-migration-guide/`, THE Release_Process SHALL verify that the corresponding Unreleased_Changes（`php85-migration-guide.md`）accurately reflects all deliverables of PRP-008（Migration_Guide 文档、Check_Script、三层测试、composer.json bin 配置、phpunit.xml suite 注册）
2. IF the change record is incomplete, THEN THE Release_Process SHALL update the Unreleased_Changes before proceeding with archival

### Requirement 7: Spec 归档

**User Story:** 作为 release manager，我希望将所有 spec 归档到 release 目录，以便 release 自包含且 spec 不再被视为活跃系统事实。

#### Acceptance Criteria

1. WHEN change records are confirmed complete, THE Spec_Archiver SHALL move all 6 unreleased specs from `docs/changes/unreleased/specs/` to `docs/changes/3.0/specs/`
2. WHEN change records are confirmed complete, THE Spec_Archiver SHALL move the Active_Spec from `.kiro/specs/php85-migration-guide/` to `docs/changes/3.0/specs/php85-migration-guide/`
3. THE Spec_Archiver SHALL use `mv` to move entire spec directories, preserving all contents without file-level verification（CR Q2=C）
4. THE Spec_Archiver SHALL verify that `.kiro/specs/php85-migration-guide/` is removed after archival
5. THE Spec_Archiver SHALL verify that `docs/changes/unreleased/specs/` contains no remaining spec directories after archival

### Requirement 8: 变更记录归档与清理

**User Story:** 作为 release manager，我希望归档变更记录并清理 unreleased 目录，以便 release 目录完整且 unreleased 目录干净。

#### Acceptance Criteria

1. WHEN specs are archived, THE Release_Process SHALL move Unreleased_Changes `php85-upgrade.md` to `docs/changes/3.0/php85-upgrade.md`
2. WHEN specs are archived, THE Release_Process SHALL move Unreleased_Changes `php85-migration-guide.md` to `docs/changes/3.0/php85-migration-guide.md`
3. THE Release_Process SHALL verify that `docs/changes/unreleased/` contains no remaining Unreleased_Changes for this release after cleanup

### Requirement 9: Proposal 状态更新与归档

**User Story:** 作为 release manager，我希望更新 proposal 状态并归档，以便 proposal 生命周期正确关闭。

#### Acceptance Criteria

1. WHEN all documentation convergence tasks are complete, THE Proposal_Archiver SHALL update the status of PRP-002 through PRP-008（7 proposals）from `implemented` to `released`（CR Q3=A: 无顺序要求，可与 spec 归档和变更记录归档并行执行）
2. WHEN proposal statuses are updated, THE Proposal_Archiver SHALL move all 7 proposal files to `docs/proposals/archive/`
3. THE Proposal_Archiver SHALL verify that `docs/proposals/` contains no PRP-002 through PRP-008 files after archival
4. THE Proposal_Archiver SHALL preserve original filenames during archival

### Requirement 10: 项目文档一致性确认

**User Story:** 作为 release manager，我希望确认项目文档与 release 版本一致，以便用户看到准确的信息。

#### Acceptance Criteria

1. WHEN all archival tasks are complete, THE Release_Process SHALL verify that `PROJECT.md` reflects the v3.0 technology stack（PHP >=8.5, Symfony 7.x, Twig 3.x, Guzzle 7.x, PHPStan level 8）
2. WHEN all archival tasks are complete, THE Release_Process SHALL verify that `README.md` reflects the v3.0 version requirements
3. WHEN all archival tasks are complete, THE Release_Process SHALL verify that `docs/state/` documents reflect the current architecture（Symfony MicroKernel, Symfony DI, Symfony Security 7.x authenticator system）
4. WHEN all archival tasks are complete, THE Release_Process SHALL verify that `docs/manual/` documents are consistent with the v3.0 codebase


---

## 发布判定

| 检查项 | 状态 | 说明 |
|--------|------|------|
| 所有包含的 feature spec tasks 全部完成 | ✅ | PRP-002 ~ PRP-007: unreleased specs 全部完成；PRP-008: 活跃 spec tasks 全部完成 |
| 全量测试通过（phpunit） | ✅ | 560 tests, 21182 assertions, 0 failures, 0 errors, 0 deprecation notices |
| 静态分析通过（phpstan analyse level 8） | ✅ | PHPStan level 8 零错误 |
| Migration Guide 端到端手工验证通过 | ⏳ | 需在 release 分支上执行手工验证（Q1=B） |
| Check Script 端到端手工验证通过 | ⏳ | 需在 release 分支上执行手工验证（Q1=B） |
| 无 P0/P1 open issue | ✅ | 无已知 issue |
| Changes 记录完整 | ✅ | `docs/changes/unreleased/` 下两份变更记录已就绪 |
| 活跃 spec 变更记录完整性已确认 | ⏳ | 需在归档前确认（Q3=B） |
| Code Review 完成 | ✅ | 各 feature spec 均已完成 code review |
| Proposal 状态为 implemented | ✅ | PRP-002 ~ PRP-008 全部 `implemented` |
| CHANGELOG 按变更类型组织 | ⏳ | 需生成统一 CHANGELOG（Q2=B） |

**结论**：待 release 分支全量测试确认、静态分析确认、Migration Guide 和 Check Script 端到端手工验证通过后，可进入文档收敛和发布流程。

---

## Socratic Review

**Q: Release 3.0 的发布范围是否完整识别？**
A: 完整。`docs/changes/unreleased/` 下有两份变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`），对应 PRP-002 ~ PRP-008 的全部工作。unreleased specs 目录下有 6 个 phase spec，加上活跃 spec（`.kiro/specs/php85-migration-guide/`），共 7 个 spec。7 个 proposal 全部 `implemented`。无遗漏。

**Q: 版本号选择是否合理？**
A: 合理。本次 release 包含大量 breaking changes：PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`、核心框架从 Silex 替换为 Symfony MicroKernel、DI 容器从 Pimple 迁移到 Symfony DI、Security 组件完全重写、多个公共 API 接口重新设计。符合 semver major version bump 标准，v3.0.0 是正确的版本号。

**Q: 验证深度是否充分？**
A: 充分。除全量自动化测试（phpunit 560 tests + phpstan level 8）外，还包含 Migration Guide 和 Check Script 的端到端手工验证（Q1=B）。考虑到 3.0 是 major release 且包含面向下游消费者的迁移工具，手工验证是必要的。

**Q: CHANGELOG 组织方式是否合理？**
A: 合理。按变更类型（Added / Changed / Removed）统一组织（Q2=B），而非按 feature 拆分。原因是 7 个 proposal 的变更高度交织（如 `oasis/utils` 在 Phase 0 和 Phase 5 分别升级），按类型组织更便于下游消费者快速定位影响。

**Q: 活跃 spec 归档流程是否有遗漏？**
A: 无遗漏。归档前先确认 `docs/changes/unreleased/php85-migration-guide.md` 内容完整（Q3=B），确认后再移入 `docs/changes/3.0/specs/`。这确保了变更记录与 spec 归档的一致性。

**Q: Proposal 归档范围是否完整？**
A: 完整。PRP-002 ~ PRP-008 共 7 个 proposal，全部处于 `implemented` 状态。release 完成后应更新为 `released` 并归档到 `docs/proposals/archive/`。归档遵循 `docs/proposals/README.md` 定义的流程。

**Q: 是否存在未完成的 feature 需要排除？**
A: 不存在。所有 unreleased 变更均已完成（7 个 proposal 全部 `implemented`，活跃 spec tasks 全部完成），本次 release 包含全部 unreleased 内容。

**Q: Clarification 决策是否已完整体现在 requirements 中？**
A: 已完整体现。Q1（端到端手工验证）→ Requirement 2 和 Requirement 3 明确手工验证范围，发布判定表中标注 ⏳；Q2（按变更类型组织 CHANGELOG）→ Requirement 4 AC 2 明确组织方式；Q3（归档前确认变更记录完整）→ Requirement 6 专门处理活跃 spec 变更记录完整性确认。三个决策均已体现。

**Q: `composer.json` 不更新 version 字段，如何确保版本号正确？**
A: 版本号由 git tag 管理。release finish 时在 master 上打 `v3.0.0` tag，Packagist 根据 tag 自动识别版本。`composer.json` 中无显式 `version` 字段，这是项目既有的版本管理方式。


---

## Gatekeep Log

**校验时间**: 2026-04-26
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [语体] 全部 10 条 Requirement 的 User Story 从英文改为中文（`As a release manager, I want...` → `作为 release manager，我希望...`）
- [术语] AC 中的自由文本引用统一替换为 Glossary 术语：`the release branch` → `Release_Branch`、`the active spec` → `Active_Spec`、`Check Script` → `Check_Script`、`Migration Guide` → `Migration_Guide`、unreleased 变更记录路径 → `Unreleased_Changes`
- [术语] Glossary 中 `CHANGELOG_Generator` 定义更新，引用 `Unreleased_Changes` 术语替代自由文本

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Spec 路径、Proposal 引用、feature spec task 状态均可追溯到实际文件）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Release 3.0 Requirements`，符合 release spec 格式
- [x] `## 发布范围` 存在，表格包含 Feature 名称 / Spec 路径 / Proposal 引用 / Proposal Status / Tasks 完成状态（7 个 feature 全部列出）
- [x] `## Feature 概要` 存在，7 个 feature 各有核心变更概述
- [x] `## 已知 Issue 评估` 存在，正确反映 `issues/` 目录状态（无已知 issue）
- [x] `## Glossary` 存在，9 个术语全部在 AC 中被引用，无孤立术语
- [x] `## Requirements` 存在，包含 10 条 requirement，覆盖 goal.md 全部 10 个目标
- [x] `## 发布判定` 存在，包含 11 项检查项表格和结论
- [x] 发布判定检查项覆盖：tasks 完成、全量测试、静态分析、Migration Guide 手工验证、Check Script 手工验证、open issue、changes 记录、活跃 spec 变更记录完整性、code review、proposal 状态、CHANGELOG 组织方式
- [x] 待确认项标记为 ⏳，符合当前阶段实际状态
- [x] 各 section 之间使用 `---` 分隔
- [x] Socratic Review 存在且覆盖充分（发布范围、版本号、验证深度、CHANGELOG 组织、归档流程、proposal 归档、未完成 feature、CR 决策体现、版本号管理）
- [x] Goal CR 三项决策均已体现：Q1=B（端到端手工验证）→ Req 2/3 + 发布判定 ⏳；Q2=B（按变更类型组织 CHANGELOG）→ Req 4 AC 2；Q3=B（归档前确认变更记录完整）→ Req 6
- [x] 发布范围与 goal.md 一致（7 个 feature、2 份 unreleased 变更记录、6+1 个 spec、7 个 proposal），未越界或缩水
- [x] Feature 概要数据与 `docs/changes/unreleased/php85-upgrade.md` 和 `docs/changes/unreleased/php85-migration-guide.md` 一致
- [x] User Story 使用中文行文
- [x] AC 使用 `THE <Subject> SHALL` / `WHEN...SHALL` / `IF...THEN SHALL` 语体，Subject 使用 Glossary 术语

### Clarification Round

**状态**: 已回答

**Q1:** Release 3.0 的 CHANGELOG 中 "测试覆盖" section 应包含哪些信息？
- A) 仅列出最终测试统计数据（560 tests, 21182 assertions, PHPStan level 8 零错误）
- B) 除 A 外，还列出各 Phase 新增的测试类型和数量（如 PBT properties 数量、集成测试数量等）
- C) 除 B 外，还包含测试覆盖率百分比（需额外运行覆盖率工具）
- D) 其他（请说明）

**A:** C — 除最终统计和各 Phase 测试类型/数量外，还包含测试覆盖率百分比

**Q2:** Spec 归档时，`docs/changes/unreleased/specs/` 下的 6 个 phase spec 中部分包含 `tests/` 目录（测试辅助文件），部分不包含。归档后是否需要验证 `tests/` 目录的完整性？
- A) 仅验证核心文件（`goal.md`、`requirements.md`、`design.md`、`tasks.md`、`.config.kiro`）存在即可，`tests/` 有则保留、无则忽略
- B) 对包含 `tests/` 的 spec，额外验证 `tests/` 目录内容非空且文件可读
- C) 不做文件级验证，归档操作使用 `mv` 整目录移动，天然保持完整性
- D) 其他（请说明）

**A:** C — 不做文件级验证，`mv` 整目录移动天然保持完整性

**Q3:** Release finish 阶段的 proposal 归档（PRP-002 ~ PRP-008 移入 `docs/proposals/archive/`）与 spec 归档、变更记录归档之间是否有严格的执行顺序要求？
- A) 无顺序要求，三类归档可并行执行
- B) 先完成 spec 归档和变更记录归档，再执行 proposal 归档（确保所有产出物已归档后再关闭 proposal 生命周期）
- C) 先更新 proposal 状态为 `released`，再执行所有归档操作（状态更新与物理移动分离）
- D) 其他（请说明）

**A:** A — 无顺序要求，三类归档可并行执行
