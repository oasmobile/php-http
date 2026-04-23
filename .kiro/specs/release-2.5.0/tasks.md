# Implementation Plan: Release 2.5.0

## Overview

将 PRP-001（测试基线补全）的成果发布为正式版本 2.5.0。流程包括：全量测试确认 + 简化 stabilize、文档收敛（4 个独立 task）、最终检查。Release spec 归档由 gitflow-finisher 在 finish 阶段处理，不在本 plan 范围内。

## Tasks

- [x] 1. 全量测试确认与简化 Stabilize
  - [x] 1.1 在 release/2.5.0 分支上执行全量测试，确认 333 tests / 597 assertions 全部通过。测试命令：`/usr/local/opt/php@7.1/bin/php vendor/bin/phpunit`（Ref: 发布判定 — 全量测试通过）
  - [x] 1.2 手工测试确认 suite 完整性：
    - [ ] 所有 8 个新增 suite 可独立运行（`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）
    - [ ] `all` suite 包含所有测试文件，无遗漏
    - [ ] 各 suite 测试数量与 feature spec 记录一致
    - [ ] 集成测试 app 配置文件与现有配置文件不冲突
  - [x] 1.3 Increment alpha tag：查询已有 alpha tag，打 `v2.5.0-alpha1` tag（`git tag v2.5.0-alpha1`）
  - [x] 1.4 Checkpoint: 全量测试输出 333 tests / 597 assertions / 0 failures / 0 errors，手工测试各项确认通过，alpha tag 已打。Commit message: `test: release 2.5.0 全量测试确认与 alpha tag`

- [x] 2. 生成 CHANGELOG.md
  - [x] 2.1 创建 `docs/changes/2.5.0/` 目录，在其中创建 `CHANGELOG.md`（Ref: 发布判定 — Changes 记录完整；Requirements CR Q3 = A）
  - [x] 2.2 基于 `docs/changes/unreleased/php85-test-baseline.md` 的内容生成变更汇总。CHANGELOG 结构：标题（`# Changelog — v2.5.0`）、Release date（填入实际日期）、Summary（一段话概述）、Features section（从 change 记录提取 Added / Changed / Test Coverage）
  - [x] 2.3 Checkpoint: `docs/changes/2.5.0/CHANGELOG.md` 存在且内容完整，包含标题、日期、摘要、Features section。Commit message: `docs: 生成 release 2.5.0 CHANGELOG`

- [x] 3. 归档 Feature Spec
  - [x] 3.1 将 `.kiro/specs/php85-test-baseline/` 整个目录移动到 `docs/changes/2.5.0/specs/php85-test-baseline/`（Ref: Requirements CR Q2 = A；Design — Feature Spec 归档）
  - [x] 3.2 确认归档文件清单完整：`goal.md`、`requirements.md`、`design.md`、`tasks.md`、`.config.kiro`、`tests/` 目录（如有内容）
  - [x] 3.3 确认 `.kiro/specs/php85-test-baseline/` 已移除
  - [x] 3.4 Checkpoint: `docs/changes/2.5.0/specs/php85-test-baseline/` 存在且文件完整，原目录已移除。Commit message: `docs: 归档 feature spec php85-test-baseline`

- [x] 4. 归档 Change 记录
  - [x] 4.1 将 `docs/changes/unreleased/php85-test-baseline.md` 移动到 `docs/changes/2.5.0/php85-test-baseline.md`（Ref: 发布判定 — Changes 记录完整；Design — Change 记录归档）
  - [x] 4.2 确认 `docs/changes/unreleased/php85-test-baseline.md` 已移除
  - [x] 4.3 Checkpoint: `docs/changes/2.5.0/php85-test-baseline.md` 存在，原文件已移除。Commit message: `docs: 归档 change 记录 php85-test-baseline`

- [x] 5. 确认 Proposal 状态
  - [x] 5.1 确认 `docs/proposals/PRP-001-php85-test-baseline.md` 的 status 为 `implemented`，如状态不正确则更新（Ref: 发布判定 — Proposal 状态为 implemented）
  - [x] 5.2 Checkpoint: PRP-001 status = `implemented`。Commit message（仅在状态有变更时）: `docs: 更新 PRP-001 状态为 implemented`

- [x] 6. Final checkpoint — 归档结果验证
  - [x] 6.1 确认归档后目录结构符合预期：
    - [x] `docs/changes/2.5.0/CHANGELOG.md` 存在且内容完整
    - [x] `docs/changes/2.5.0/php85-test-baseline.md` 存在
    - [x] `docs/changes/2.5.0/specs/php85-test-baseline/` 存在且文件完整
    - [x] `.kiro/specs/php85-test-baseline/` 已移除
    - [x] `docs/changes/unreleased/php85-test-baseline.md` 已移除
  - [x] 6.2 执行全量测试确认归档操作未影响测试结果：`/usr/local/opt/php@7.1/bin/php vendor/bin/phpunit`
  - [x] 6.3 Checkpoint: 目录结构符合预期，全量测试通过。Commit message: `docs: release 2.5.0 归档结果验证`

- [~] 7. Code Review
  - 委托给 code-reviewer sub-agent 执行。Review 范围为 release/2.5.0 分支上 Task 1–6 的所有变更。

## Issues

（stabilize 阶段新发现的 issue 记录于此，初始为空）

无

## Notes

- 执行时须遵循 `spec-execution` 规范
- Commit 随各 top-level task 的 checkpoint 一起执行，以 top-level task 为 commit 粒度
- 本 plan 不包含 release spec 自身（`.kiro/specs/release-2.5.0/`）的归档，该操作由 gitflow-finisher 在 finish 阶段执行（Design CR Q3 = A）
- Task 1 合并了全量自动化测试、手工测试确认和 alpha tag（Design CR Q1 = B）
- 文档收敛按操作类型拆分为 4 个独立 task（Design CR Q2 = A）：CHANGELOG 生成（Task 2）、Feature Spec 归档（Task 3）、Change 记录归档（Task 4）、Proposal 状态确认（Task 5）
- Task 2–5 之间无依赖关系，可并行执行
- CHANGELOG 中的 release date 在 task 执行时填入实际日期
- 全量测试依赖 PHP 7.1 环境（PHPUnit 5.x 不兼容 PHP 8.5）

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有实现项？**
A: 覆盖完整。Design 中的 4 个阶段（全量测试确认、简化 Stabilize、文档收敛、Finish）中，前 3 个阶段的所有操作均有对应 task。Finish 阶段由 gitflow-finisher 执行，不在本 plan 范围内（Design CR Q3 = A）。

**Q: task 之间的依赖顺序是否正确？**
A: Task 1（全量测试 + stabilize）必须最先执行，确认代码质量后才进入文档收敛。Task 2–5 之间无依赖关系，可并行执行。Task 6（final checkpoint）依赖 Task 2–5 全部完成。Task 7（Code Review）在最后执行。

**Q: 每个 task 的粒度是否合适？**
A: Task 1 合并了全量测试、手工测试和 alpha tag，粒度适中（Design CR Q1 = B 明确要求合并）。Task 2–5 各自独立且操作明确。Task 6 作为归档验证是必要的收尾步骤。

**Q: checkpoint 的设置是否覆盖了关键阶段？**
A: 每个 top-level task 都有 checkpoint，覆盖了测试确认、文档生成、归档操作和最终验证。

**Q: 手工测试是否覆盖了 requirements 中的关键场景？**
A: Task 1.2 的手工测试覆盖了 suite 独立运行、all suite 完整性、测试数量一致性和配置文件冲突检查，与 design 中简化 stabilize 的手工测试清单一致。

**Q: Design CR 的三项决策是否都在 tasks 中体现？**
A: 均已体现。Q1=B（全量测试 + 手工测试合并为 Task 1）、Q2=A（文档收敛拆分为 Task 2–5 共 4 个独立 task）、Q3=A（release spec 归档不在 tasks 中，留给 gitflow-finisher）。

## Gatekeep Log

**校验时间**: 2025-07-16
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] 所有 sub-task 补充 `- [ ]` checkbox 语法和层级序号（1.1, 1.2, 2.1, 2.2...），原文仅使用无序列表
- [格式] 每个 top-level task 末尾补充 Checkpoint sub-task，包含具体验证方式和 commit message
- [结构] 补充 `## Issues` section（release spec 必须包含，初始为空）
- [结构] 补充 Task 7 Code Review 作为最后一个 top-level task，描述为委托给 code-reviewer sub-agent 执行
- [结构] 补充 `## Socratic Review` section，覆盖 design 全覆盖、依赖顺序、粒度、checkpoint、手工测试、CR 决策体现
- [结构] Task 1 中将 "Increment alpha tag" 拆分为独立的 sub-task 1.3（release spec 手工测试类 task 的规范要求）
- [内容] Notes section 补充 spec-execution 规范引用和 commit 粒度说明
- [内容] Requirement 引用格式统一为 `(Ref: ...)` 内联标注，替代原文的 `_Requirements:` 尾注格式

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（发布判定检查项、Design CR 编号、spec 路径）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] Task 1（手工测试类）的 sub-task 1.3 为 "Increment alpha tag"
- [x] 最后一个 top-level task（Task 7）为 Code Review
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–7），连续无跳号
- [x] sub-task 有层级序号（1.1–1.4, 2.1–2.3, ...），连续无跳号
- [x] 实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements 发布判定 6 项检查均有对应 task 覆盖
- [x] top-level task 按依赖关系排序（Task 1 → Task 2–5 可并行 → Task 6 → Task 7）
- [x] 无循环依赖
- [x] 每个 top-level task 的最后一个 sub-task 为 checkpoint
- [x] checkpoint 包含具体验证方式和 commit message
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试覆盖了 design 中简化 stabilize 的全部检查项
- [x] Code Review 为最后一个 top-level task，描述为委托 code-reviewer sub-agent
- [x] Code Review task 未展开 review checklist
- [x] `## Notes` section 存在
- [x] Notes 明确提到执行时须遵循 `spec-execution` 规范
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（PHP 7.1 环境、并行提示、CR 决策引用）
- [x] `## Issues` section 存在（初始为空）
- [x] `## Socratic Review` section 存在且覆盖充分
- [x] Design CR Q1=B 体现：全量测试 + 手工测试 + alpha tag 合并为 Task 1
- [x] Design CR Q2=A 体现：文档收敛拆分为 4 个独立 task（Task 2–5）
- [x] Design CR Q3=A 体现：release spec 归档不在 tasks 中，Notes 明确说明留给 gitflow-finisher
- [x] Design 全覆盖：全量测试确认、简化 Stabilize、文档收敛（CHANGELOG / Feature Spec / Change 记录 / Proposal 状态）、归档验证均有对应 task
- [x] 验收闭环完整：checkpoint（每个 task）+ 手工测试（Task 1.2）+ Code Review（Task 7）
- [x] 执行路径无歧义：Task 1 先行 → Task 2–5 可并行 → Task 6 收尾 → Task 7 review
