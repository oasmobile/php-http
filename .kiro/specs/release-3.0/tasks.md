# Implementation Plan: Release 3.0

## Overview

将 PRP-002 ~ PRP-008（7 个 proposal）的成果发布为正式版本 3.0。流程包括：自动化验证（phpunit + phpstan + 覆盖率）、端到端手工验证（Migration Guide + Check Script）、文档收敛（一个 top-level task，内部 7 个 sub-task）、归档结果验证、Code Review。Release spec 归档由 gitflow-finisher 在 finish 阶段处理，不在本 plan 范围内。

## Tasks

- [x] 1. 自动化验证
  - [x] 1.1 在 `release/3.0` 分支上执行全量测试，确认 560 tests / 21182 assertions 全部通过，零 failures、零 errors、零 deprecation notices。测试命令：`php vendor/bin/phpunit`（Ref: Requirement 1, AC 1/3）
  - [x] 1.2 执行静态分析，确认 PHPStan level 8 零错误。命令：`php vendor/bin/phpstan analyse`（Ref: Requirement 1, AC 2）
  - [x] 1.3 执行覆盖率采集，命令：`php vendor/bin/phpunit --coverage-text`，记录覆盖率百分比用于 CHANGELOG 测试覆盖 section。如因缺少 Xdebug/PCOV 扩展无法运行，记录"覆盖率工具不可用"及原因，不阻塞后续流程（Ref: Requirement 4, AC 4）
  - [x] 1.4 Checkpoint: phpunit 输出 560 tests / 21182 assertions / 0 failures / 0 errors / 0 deprecation notices，phpstan 零错误，覆盖率结果已记录（覆盖率工具不可用：无 Xdebug/PCOV 扩展）。Commit message: `test: release 3.0 自动化验证通过`

- [x] 2. 端到端手工验证
  - [x] 2.1 Increment alpha tag：查询已有 alpha tag（`git tag -l 'v3.0.0-alpha*'`），取最大序号 +1，打新 tag（如 `git tag v3.0.0-alpha1`）
  - [x] 2.2 Migration Guide 手工验证 — 验证对象：`docs/manual/migration-v3.md`
    - [x] 验证文件存在且包含全部 12 个模块章节（PHP Version → Dependencies → Kernel API → DI Container → Bootstrap Config → Routing → Security → Middleware → Views → Twig → CORS → Cookie）+ PHP 语言适配 + 附录
    - [x] 验证 TOC 中所有锚点链接解析到有效 heading
    - [x] 验证每个 breaking change 条目包含 severity marker（🔴/🟡/🟢）、before/after 代码块、action 描述
    - [x] 验证 Bootstrap Config Key 参考表覆盖 `docs/state/architecture.md` 中定义的所有 key
    - [x] `[脚本]` 编排为自动化测试脚本，解析 markdown 结构验证上述各项
    - （Ref: Requirement 2, AC 1–4）
  - [x] 2.3 Check Script 手工验证 — 验证对象：`bin/oasis-http-migrate-v3-check`
    - [x] 验证 `--help` 输出 usage 信息
    - [x] 对包含已知 Removed API 引用的测试 PHP 文件，验证正确检测到 finding
    - [x] 对包含 Pimple 访问模式（`$app['...']`）的测试 PHP 文件，验证正确检测到 finding
    - [x] 验证 `--format=json` 输出有效 JSON
    - [x] 验证存在 🔴 finding 时 exit code 为 1，无 🔴 finding 时 exit code 为 0
    - [x] `[脚本]` 编排为自动化测试脚本，创建临时测试 PHP 文件并执行 Check Script 验证
    - （Ref: Requirement 3, AC 1–5）
  - [x] 2.4 Checkpoint: Migration Guide 验证全部通过，Check Script 验证全部通过，alpha tag 已打。Commit message: `test: release 3.0 端到端手工验证通过`

- [-] 3. 文档收敛
  - [x] 3.1 活跃 Spec 变更记录完整性确认 — 确认 `docs/changes/unreleased/php85-migration-guide.md` 准确反映 PRP-008 的全部交付物：Migration Guide 文档（`docs/manual/migration-v3.md`）、Check Script（`bin/oasis-http-migrate-v3-check`）、三层测试（Document Validation Tests + PBT Properties 5–11 + Unit Tests）、`composer.json` bin 配置、`phpunit.xml` suite 注册（`migration-guide-validation`、`migrate-check-pbt`、`migrate-check-unit`）。如不完整须补充后再继续（Ref: Requirement 6, AC 1–2）
  - [x] 3.2 版本 CHANGELOG 生成 — 创建 `docs/changes/3.0/CHANGELOG.md`，将两份 unreleased 变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`）合并重组为统一格式。按变更类型（Added / Changed / Removed）组织，不按 feature 拆分。包含：标题、release date、Summary、Added / Changed / Removed sections、Resolved Notes section、测试覆盖 section（最终统计 + 各 Phase 测试类型与数量 + 覆盖率百分比）（Ref: Requirement 4, AC 1–5）
  - [x] 3.3 全局 CHANGELOG 更新 — 在 `docs/changes/CHANGELOG.md` 顶部追加 v3.0 摘要条目，遵循现有格式：版本 heading + 一句话摘要 + 链接到 `3.0/CHANGELOG.md`（Ref: Requirement 5, AC 1–2）
  - [x] 3.4 Spec 归档 — 将 7 个 spec 归档到 `docs/changes/3.0/specs/`，使用 `mv` 整目录移动：6 个 unreleased specs（`php85-phase0-prerequisites` ~ `php85-phase5-validation-stabilization`）从 `docs/changes/unreleased/specs/` 移入，1 个活跃 spec（`.kiro/specs/php85-migration-guide/`）从 `.kiro/specs/` 移入。归档后验证：`.kiro/specs/php85-migration-guide/` 已移除，`docs/changes/unreleased/specs/` 下无剩余 spec 目录（Ref: Requirement 7, AC 1–5）
  - [x] 3.5 变更记录归档与清理 — 将 `docs/changes/unreleased/php85-upgrade.md` 移入 `docs/changes/3.0/php85-upgrade.md`，将 `docs/changes/unreleased/php85-migration-guide.md` 移入 `docs/changes/3.0/php85-migration-guide.md`。归档后验证：`docs/changes/unreleased/` 下无本次 release 的剩余变更记录（Ref: Requirement 8, AC 1–3）
  - [x] 3.6 Proposal 状态更新与归档 — 更新 PRP-002 ~ PRP-008（7 个 proposal）状态从 `implemented` → `released`，然后移入 `docs/proposals/archive/`，保留原文件名。归档后验证：`docs/proposals/` 下无 PRP-002 ~ PRP-008 文件。此操作与 spec 归档、变更记录归档无顺序依赖，可并行执行（Ref: Requirement 9, AC 1–4）
  - [x] 3.7 项目文档一致性确认 — 确认以下文档与 v3.0 版本一致：`PROJECT.md` 反映 v3.0 技术栈（PHP >=8.5, Symfony 7.x, Twig 3.x, Guzzle 7.x, PHPStan level 8）；`README.md` 反映 v3.0 版本要求；`docs/state/` 反映当前架构（Symfony MicroKernel, Symfony DI, Symfony Security 7.x authenticator system）；`docs/manual/` 与 v3.0 代码库一致（Ref: Requirement 10, AC 1–4）
  - [-] 3.8 Checkpoint: 活跃 spec 变更记录已确认完整，`docs/changes/3.0/CHANGELOG.md` 已生成且内容完整，全局 CHANGELOG 已更新，7 个 spec 已归档到 `docs/changes/3.0/specs/`，2 份变更记录已归档，7 个 proposal 已更新状态并归档到 `docs/proposals/archive/`，项目文档一致性已确认。Commit message: `docs: release 3.0 文档收敛完成`

- [~] 4. Final checkpoint — 归档结果验证
  - [ ] 4.1 确认归档后目录结构符合预期：
    - [ ] `docs/changes/3.0/CHANGELOG.md` 存在且内容完整
    - [ ] `docs/changes/3.0/php85-upgrade.md` 存在
    - [ ] `docs/changes/3.0/php85-migration-guide.md` 存在
    - [ ] `docs/changes/3.0/specs/` 下包含 7 个 spec 目录（`php85-phase0-prerequisites`、`php85-phase1-framework-replacement`、`php85-phase2-twig-guzzle-upgrade`、`php85-phase3-security-refactor`、`php85-phase4-language-adaptation`、`php85-phase5-validation-stabilization`、`php85-migration-guide`）
    - [ ] `docs/proposals/archive/` 下包含 PRP-002 ~ PRP-008（7 个文件）
    - [ ] `.kiro/specs/php85-migration-guide/` 已移除
    - [ ] `docs/changes/unreleased/specs/` 下无剩余 spec 目录
    - [ ] `docs/changes/unreleased/` 下无本次 release 的变更记录
    - [ ] `docs/proposals/` 下无 PRP-002 ~ PRP-008 文件
  - [ ] 4.2 执行全量测试确认归档操作未影响测试结果：`php vendor/bin/phpunit`，预期 560 tests / 21182 assertions / 0 failures / 0 errors
  - [ ] 4.3 Checkpoint: 目录结构符合预期，全量测试通过。Commit message: `docs: release 3.0 归档结果验证通过`

- [~] 5. Code Review
  - 委托给 code-reviewer sub-agent 执行。Review 范围为 `release/3.0` 分支上 Task 1–4 的所有变更。

## Issues

（stabilize 阶段新发现的 issue 记录于此，初始为空）

无

## Notes

- 执行时须遵循 `spec-execution` 规范
- Commit 随各 top-level task 的 checkpoint 一起执行，以 top-level task 为 commit 粒度
- 本 plan 不包含 release spec 自身（`.kiro/specs/release-3.0/`）的归档，该操作由 gitflow-finisher 在 finish 阶段执行
- Design CR Q1=C：文档收敛合并为一个 top-level task（Task 3），内部按 sub-task 拆分
- Design CR Q2=B：自动化验证（Task 1）和手工验证（Task 2）分开为两个独立 top-level task
- Design CR Q3=A：独立的 Final checkpoint task（Task 4），统一验证目录结构 + 全量测试
- Requirements CR Q1=C：CHANGELOG 测试覆盖 section 包含覆盖率百分比（Task 1.3 采集，Task 3.2 写入）
- Requirements CR Q2=C：Spec 归档使用 `mv` 整目录移动，不做文件级验证（Task 3.4）
- Requirements CR Q3=A：三类归档（spec / 变更记录 / proposal）可并行执行（Task 3.4 / 3.5 / 3.6）
- Goal CR Q1=B：端到端手工验证（Task 2）
- Goal CR Q2=B：CHANGELOG 按变更类型组织（Task 3.2）
- Goal CR Q3=B：活跃 spec 归档前确认变更记录完整（Task 3.1）
- Task 1 → Task 2 → Task 3 → Task 4 → Task 5 为顺序依赖；Task 3 内部的 3.4 / 3.5 / 3.6 可并行执行
- CHANGELOG 中的 release date 在 task 执行时填入实际日期
- 覆盖率采集依赖 Xdebug 或 PCOV 扩展，如不可用则在 CHANGELOG 中标注原因，不阻塞 release

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有 Phase？**
A: 覆盖完整。Phase 1（全量测试 + 静态分析）→ Task 1；Phase 2（端到端手工验证）→ Task 2；Phase 3（文档收敛 7 个操作）→ Task 3（3.1–3.7）；Phase 4（Finish）由 gitflow-finisher 执行，不在本 plan 范围内。另有 Task 4（Final checkpoint）和 Task 5（Code Review）作为收尾。

**Q: task 之间的依赖顺序是否正确？**
A: Task 1（自动化验证）必须最先执行，确认代码质量。Task 2（手工验证）依赖 Task 1 通过。Task 3（文档收敛）依赖 Task 1 和 Task 2 全部通过。Task 4（归档结果验证）依赖 Task 3 完成。Task 5（Code Review）在最后执行。Task 3 内部的 3.4 / 3.5 / 3.6 可并行执行（Requirements CR Q3=A）。

**Q: 每个 top-level task 的最后一个 sub-task 是否为 checkpoint？**
A: 是。Task 1.4、Task 2.4、Task 3.8、Task 4.3 均为 checkpoint，包含验证步骤和 commit message。Task 5（Code Review）委托给 code-reviewer sub-agent，无需 checkpoint。

**Q: Design CR 的三项决策是否都在 tasks 中体现？**
A: 均已体现。Q1=C（文档收敛合并为一个 top-level task）→ Task 3 内部 8 个 sub-task（含 checkpoint）；Q2=B（自动化验证和手工验证分开）→ Task 1 和 Task 2 独立；Q3=A（独立 Final checkpoint）→ Task 4。

**Q: Requirements CR 的三项决策是否都在 tasks 中体现？**
A: 均已体现。Q1=C（CHANGELOG 测试覆盖含覆盖率百分比）→ Task 1.3 采集覆盖率，Task 3.2 写入 CHANGELOG；Q2=C（mv 整目录移动不做文件级验证）→ Task 3.4 明确使用 `mv`；Q3=A（三类归档可并行）→ Task 3.4 / 3.5 / 3.6 无顺序依赖。

**Q: Goal CR 的三项决策是否都在 tasks 中体现？**
A: 均已体现。Q1=B（端到端手工验证）→ Task 2；Q2=B（CHANGELOG 按变更类型组织）→ Task 3.2；Q3=B（归档前确认变更记录完整）→ Task 3.1。

**Q: 手工测试类 top-level task 的第一个 sub-task 是否为 "Increment alpha tag"？**
A: 是。Task 2.1 为 "Increment alpha tag"，遵循 spec-planning 规则。虽然 Design 的 Stabilize 策略决定不打 alpha/beta tag，但 spec-planning 规则要求 release 手工测试类 task 的第一个 sub-task 为 "Increment alpha tag"，此处遵循规则。

**Q: 所有 requirements 是否都有对应 task 覆盖？**
A: 覆盖完整。Requirement 1（全量测试）→ Task 1.1/1.2；Requirement 2（Migration Guide 手工验证）→ Task 2.2；Requirement 3（Check Script 手工验证）→ Task 2.3；Requirement 4（版本 CHANGELOG）→ Task 3.2；Requirement 5（全局 CHANGELOG）→ Task 3.3；Requirement 6（活跃 spec 变更记录完整性）→ Task 3.1；Requirement 7（spec 归档）→ Task 3.4；Requirement 8（变更记录归档）→ Task 3.5；Requirement 9（proposal 归档）→ Task 3.6；Requirement 10（项目文档一致性）→ Task 3.7。

**Q: 是否存在 optional task？**
A: 不存在。所有 task 和 sub-task 均为 mandatory，使用 `- [ ]` 语法，无 `*` 后缀。

## Gatekeep Log

**校验时间**: 2026-04-26
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] Task 2.2（Migration Guide 手工验证）的三级测试项补充 `- [ ]` checkbox 语法（5 项验证 + 1 项脚本编排），与 release 2.5.0 tasks 的三级测试项格式一致
- [格式] Task 2.3（Check Script 手工验证）的三级测试项补充 `- [ ]` checkbox 语法（6 项验证 + 1 项脚本编排），与 release 2.5.0 tasks 的三级测试项格式一致
- [格式] Task 4.1（归档后目录结构验证）的三级验证项补充 `- [ ]` checkbox 语法（9 项验证），与 release 2.5.0 Task 6.1 的验证项格式一致

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Requirement 编号、AC 编号、CR 决策编号、spec 路径、Design Phase 编号均正确）
- [x] checkbox 语法正确（`- [ ]`），含三级测试项（已修正）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] `## Issues` section 存在（初始为空）
- [x] `## Notes` section 存在
- [x] `## Socratic Review` section 存在且覆盖充分（8 个问题：Design Phase 覆盖、依赖顺序、checkpoint、Design CR 体现、Requirements CR 体现、Goal CR 体现、alpha tag、requirements 全覆盖、optional task）
- [x] top-level task 有序号（1–5），连续无跳号
- [x] sub-task 有层级序号（1.1–1.4, 2.1–2.4, 3.1–3.8, 4.1–4.3），连续无跳号
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] 所有 task 均为 mandatory（无 `*` 后缀）
- [x] 最后一个 top-level task（Task 5）为 Code Review，描述为委托给 code-reviewer sub-agent 执行
- [x] Code Review task 未展开 review checklist
- [x] 手工测试类 top-level task（Task 2）的第一个 sub-task（2.1）为 "Increment alpha tag"
- [x] 每个 top-level task 的最后一个 sub-task 为 checkpoint（1.4, 2.4, 3.8, 4.3），包含验证步骤和 commit message
- [x] 实现类 sub-task 引用了具体的 requirements 条款（Ref: Requirement X, AC Y 格式）
- [x] Requirements 全覆盖：10 条 Requirement 均有对应 task（R1→1.1/1.2, R2→2.2, R3→2.3, R4→1.3/3.2, R5→3.3, R6→3.1, R7→3.4, R8→3.5, R9→3.6, R10→3.7）
- [x] 引用的 Requirement 编号和 AC 编号在 requirements.md 中确实存在，无悬空引用
- [x] Design 全覆盖：Phase 1→Task 1, Phase 2→Task 2, Phase 3（7 个操作）→Task 3（3.1–3.7）, Phase 4 由 gitflow-finisher 执行不在范围内
- [x] top-level task 按依赖关系排序（Task 1→2→3→4→5 顺序依赖）
- [x] 无循环依赖
- [x] Task 3 内部 3.4/3.5/3.6 标注可并行执行，条件成立（操作目标文件不重叠）
- [x] Graphify 跨模块依赖校验：不适用（release spec 不涉及代码模块变更，操作为文件移动、文档生成和状态确认）
- [x] Notes 明确提到执行时须遵循 `spec-execution` 规范
- [x] Notes 明确说明 commit 随 checkpoint 一起执行，以 top-level task 为 commit 粒度
- [x] Notes 包含当前 spec 特有的执行要点（覆盖率工具依赖、release date 填入时机、并行提示、CR 决策引用）
- [x] Design CR Q1=C 体现：文档收敛合并为一个 top-level task（Task 3），内部 8 个 sub-task
- [x] Design CR Q2=B 体现：自动化验证（Task 1）和手工验证（Task 2）分开为两个独立 top-level task
- [x] Design CR Q3=A 体现：独立的 Final checkpoint task（Task 4），统一验证目录结构 + 全量测试
- [x] Requirements CR Q1=C 体现：CHANGELOG 测试覆盖 section 包含覆盖率百分比（Task 1.3 采集，Task 3.2 写入）
- [x] Requirements CR Q2=C 体现：Spec 归档使用 `mv` 整目录移动，不做文件级验证（Task 3.4）
- [x] Requirements CR Q3=A 体现：三类归档（spec / 变更记录 / proposal）可并行执行（Task 3.4 / 3.5 / 3.6）
- [x] Goal CR Q1=B 体现：端到端手工验证（Task 2）
- [x] Goal CR Q2=B 体现：CHANGELOG 按变更类型组织（Task 3.2）
- [x] Goal CR Q3=B 体现：活跃 spec 归档前确认变更记录完整（Task 3.1）
- [x] 验收闭环完整：checkpoint（每个 top-level task）+ 手工验证（Task 2）+ Code Review（Task 5）
- [x] 执行路径无歧义：Task 1→2→3→4→5 顺序依赖，Task 3 内部 3.4/3.5/3.6 可并行，其余顺序执行
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
