# Release 2.5.0 Design

> Release 2.5.0 发布流程设计 — `.kiro/specs/release-2.5.0/`

---

## Introduction

本文档描述 Release 2.5.0 的发布流程技术方案。Release 仅包含 PRP-001（测试基线补全），无功能变更。

采用简化 stabilize 流程（CR Q1 = B）：打一个 alpha tag → 手工测试确认 suite 完整性 → finish。Feature spec 归档到 `docs/changes/2.5.0/specs/`（CR Q2 = A）。生成完整 CHANGELOG.md（CR Q3 = A）。

---

## Release 流程概览

```
release/2.5.0 分支
  ├── 1. 全量测试确认
  ├── 2. 简化 Stabilize
  │     ├── 打 alpha tag（v2.5.0-alpha1）
  │     └── 手工测试确认 suite 完整性
  ├── 3. 文档收敛
  │     ├── 生成 CHANGELOG.md
  │     ├── 归档 feature spec
  │     ├── 归档 change 记录
  │     └── 更新 proposal 状态（如需）
  └── 4. Finish（由 gitflow-finisher 执行）
        ├── merge → master
        ├── 打 v2.5.0 tag
        └── merge → develop
```

---

## 全量测试确认

在 release 分支上执行一次全量测试，确认所有 333 tests 通过。

测试命令（需使用 PHP 7.1，因 PHPUnit 5.x 不兼容 PHP 8.5）：

```bash
/usr/local/opt/php@7.1/bin/php vendor/bin/phpunit
```

预期结果：333 tests, 597 assertions, 0 failures, 0 errors。

---

## 简化 Stabilize 流程（CR Q1 = B）

### Alpha Tag

查询已有 alpha tag，取最大序号 +1，打新 tag。本次为首个 alpha，预期为 `v2.5.0-alpha1`。

```bash
git tag v2.5.0-alpha1
```

### 手工测试

确认以下内容：

1. 所有 8 个新增 suite 可独立运行（`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）
2. `all` suite 包含所有测试文件，无遗漏
3. 各 suite 测试数量与 feature spec 记录一致
4. 集成测试 app 配置文件与现有配置文件不冲突

---

## 文档收敛

### CHANGELOG.md 生成（CR Q3 = A）

在 `docs/changes/2.5.0/CHANGELOG.md` 中生成完整的变更汇总，基于 `docs/changes/unreleased/php85-test-baseline.md` 的内容。

CHANGELOG 结构：

```markdown
# Changelog — v2.5.0

> Release date: <release-date>

## Summary

<一段话概述本次 release>

## Features

### PHP 8.5 升级前测试基线补全（PRP-001）

<从 php85-test-baseline.md 提取的 Added / Changed / Test Coverage>
```

### Feature Spec 归档（CR Q2 = A）

将 `.kiro/specs/php85-test-baseline/` 整个目录移动到 `docs/changes/2.5.0/specs/php85-test-baseline/`。

归档文件清单：
- `goal.md`
- `requirements.md`
- `design.md`
- `tasks.md`
- `.config.kiro`
- `tests/` 目录（如有内容）

### Change 记录归档

将 `docs/changes/unreleased/php85-test-baseline.md` 移动到 `docs/changes/2.5.0/php85-test-baseline.md`。

### Issue 修复方案

`issues/` 目录下无已知 issue，requirements 发布判定中已确认无 P0/P1 open issue。不涉及 issue 修复。

### Proposal 状态

PRP-001 当前状态已为 `implemented`，无需更新。

---

## 归档后目录结构

```
docs/changes/2.5.0/
├── CHANGELOG.md
├── php85-test-baseline.md
└── specs/
    └── php85-test-baseline/
        ├── .config.kiro
        ├── goal.md
        ├── requirements.md
        ├── design.md
        ├── tasks.md
        └── tests/
```

---

## Impact Analysis

### 受影响的文件

| 文件 / 目录 | 变更类型 | 说明 |
|-------------|---------|------|
| `docs/changes/2.5.0/CHANGELOG.md` | 新增 | Release 变更汇总 |
| `docs/changes/2.5.0/php85-test-baseline.md` | 移动 | 从 `unreleased/` 移入 |
| `docs/changes/2.5.0/specs/php85-test-baseline/` | 移动 | 从 `.kiro/specs/` 归档 |
| `.kiro/specs/php85-test-baseline/` | 删除 | 归档后移除 |
| `docs/changes/unreleased/php85-test-baseline.md` | 删除 | 归档后移除 |

### State 文档影响

不涉及。本次 release 仅包含测试补全，不修改系统行为，无需更新 `docs/state/`。

### 配置项变更

不涉及。本次 release 不新增、删除或修改任何配置项。

### 外部系统交互

不涉及。本次 release 不改变与外部系统的交互方式。

### 风险点

- Feature spec 归档后，后续 Phase（PRP-002 等）如需参考 PRP-001 的 spec，需从 `docs/changes/2.5.0/specs/` 查找。
- 全量测试依赖 PHP 7.1 环境，需确保 release 分支上 PHP 7.1 可用。

---

## Socratic Review

**Q: Release spec 本身（`.kiro/specs/release-2.5.0/`）是否也需要归档？**
A: Release spec 在 finish 阶段由 gitflow-finisher 处理，通常也归档到 `docs/changes/2.5.0/specs/release-2.5.0/`。但这属于 finish 流程的职责，不在本 design 范围内。

**Q: 简化 stabilize 是否足够？是否有遗漏的验证项？**
A: 本次 release 仅包含测试补全，无功能变更。手工测试确认 suite 完整性已覆盖核心交付物。全量自动化测试覆盖了所有行为验证。简化 stabilize 足够。

**Q: CHANGELOG.md 的 release date 如何确定？**
A: 在 finish 阶段确定。Design 中使用 `<release-date>` 占位，task 执行时填入实际日期。

**Q: design 是否完整覆盖了 requirements 中的所有检查项？**
A: 发布判定中的 6 个检查项：tasks 完成（已确认）、全量测试（本 design 覆盖）、无 P0/P1 issue（已确认）、changes 记录（本 design 覆盖归档）、code review（feature 阶段已完成）、proposal 状态（已确认）。全部覆盖。


---

## Gatekeep Log

**校验时间**: 2025-07-16
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [结构] 一级标题从通用的 `# Design Document` 修正为 `# Release 2.5.0 Design`，与 release spec 命名规范一致
- [结构] 将 `### Issue 收敛` 重命名为 `### Issue 修复方案`，补充与 requirements 发布判定的关联说明，符合 release spec design 必须包含 Issue 修复方案 section 的要求
- [内容] Impact Analysis 补充"配置项变更"和"外部系统交互"两个维度（均标注"不涉及"），确保影响分析维度完整

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirements 编号、CR 决策编号、spec 路径）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Release 2.5.0 Design`，明确文件定位
- [x] 技术摘要汇总存在（Introduction + Release 流程概览）
- [x] Issue 修复方案 section 存在（标注不涉及，与 requirements 一致）
- [x] 测试策略存在（全量测试确认 + 简化 Stabilize 手工测试）
- [x] 收敛计划存在（CHANGELOG 生成、Feature Spec 归档、Change 记录归档、Proposal 状态）
- [x] 各 section 之间使用 `---` 分隔
- [x] Requirements 发布判定 6 项检查全部在 design 中有对应覆盖
- [x] Requirements CR 三项决策（Q1=B 简化 stabilize / Q2=A 归档到 docs/changes / Q3=A 生成 CHANGELOG）均在 design 中体现
- [x] Impact Analysis 覆盖：受影响文件、State 文档、配置项变更、外部系统交互、风险点
- [x] 技术选型有明确理由（简化 stabilize 基于 CR Q1=B 决策）
- [x] 流程步骤清晰可执行，可直接拆分为独立 task
- [x] 无过度设计
- [x] 归档后目录结构明确，文件清单完整
- [x] Socratic Review 存在且覆盖充分（requirements 覆盖度、stabilize 充分性、release date 处理、design 完整性）

### Clarification Round

**状态**: 已回答

**Q1:** 简化 stabilize 中的手工测试（确认 8 个 suite 独立运行、all suite 无遗漏等），是作为一个独立 task 执行，还是合并到全量测试确认 task 中一起完成？
- A) 独立 task：先执行全量自动化测试（task N），再执行手工测试确认（task N+1），两者分开记录
- B) 合并为一个 task：全量测试 + 手工测试确认在同一个 task 中完成，减少 task 数量
- C) 手工测试确认不作为 task，而是作为 alpha tag 打完后的验收步骤，记录在 alpha tag task 的验收标准中
- D) 其他（请说明）

**A:** B — 合并为一个 task，全量测试 + 手工测试确认在同一个 task 中完成。

**Q2:** 文档收敛涉及多个操作（生成 CHANGELOG、归档 feature spec、归档 change 记录、确认 proposal 状态），这些操作如何拆分为 task？
- A) 按操作类型拆分：每个操作一个 task（4 个 task），便于独立验证
- B) 合并为一个"文档收敛" task：所有文档操作在一个 task 中完成，因为它们之间有逻辑关联且都是简单的文件操作
- C) 按阶段拆分：归档操作（spec + change 记录）为一个 task，生成操作（CHANGELOG）为另一个 task，确认操作（proposal 状态）合并到最终检查 task
- D) 其他（请说明）

**A:** A — 按操作类型拆分，每个操作一个 task，便于独立验证。

**Q3:** Release spec 本身（`.kiro/specs/release-2.5.0/`）的归档时机和方式——design 的 Socratic Review 中提到这属于 finish 流程的职责。tasks 中是否需要包含一个 release spec 归档的 task，还是完全留给 gitflow-finisher？
- A) 完全留给 gitflow-finisher，tasks 中不包含 release spec 归档
- B) 在 tasks 中包含一个 release spec 归档 task，归档到 `docs/changes/2.5.0/specs/release-2.5.0/`，与 feature spec 归档一起完成
- C) 在 tasks 中包含，但标记为"finish 阶段执行"，作为 gitflow-finisher 的前置检查清单
- D) 其他（请说明）

**A:** A — 完全留给 gitflow-finisher，tasks 中不包含 release spec 归档。
