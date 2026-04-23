# Release 2.5.0 Requirements

> Release 2.5.0 的发布范围、feature 概要、issue 评估与发布判定。

---

## 发布范围

| Feature 名称 | Spec 路径 | Proposal 引用 | Proposal Status | Tasks 完成状态 |
|--------------|-----------|---------------|-----------------|---------------|
| PHP 8.5 升级前测试基线补全 | `.kiro/specs/php85-test-baseline/` | `docs/proposals/PRP-001-php85-test-baseline.md` | `implemented` | 13/13 top-level tasks 全部完成 |

---

## Feature 概要

### PHP 8.5 升级前测试基线补全（PRP-001）

为 `oasis/http` 项目在 PHP 8.5 升级前建立完整的测试行为基线。在框架替换（Phase 1）之前，为所有缺少测试的模块补充了单元测试和集成测试，确保后续 breaking change 迁移有可靠的行为 SSOT。

**核心能力：**

- 新增 35 个测试文件，覆盖 ErrorHandlers、Configuration（8 个类）、Views（6 个类）、Routing（7 个类）、Cookie、Middlewares、Security（NullEntryPoint）、Misc（4 个独立模块）
- 新增 3 个跨模块集成测试：Bootstrap Configuration 链路、Security Authentication Flow、SilexKernel 跨社区集成
- 全面补充 SilexKernel、CORS、Security、Twig、AWS 现有测试的未覆盖分支场景
- phpunit.xml 新增 8 个 test suite（`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）

**关键实现：**

- 测试总数：333 tests, 597 assertions，全部通过
- 覆盖了知识图谱分析识别的所有无测试社区和 god node（`WrappedExceptionInfo` 13 edges、`processConfiguration()` betweenness 0.078）
- 新增测试辅助类：`ConcreteSmartViewHandler`、`TestMiddleware`、`RouteCacheCleaner` trait
- 集成测试基础设施：`integration.routes.yml`、`app.integration-security.php`、`app.integration-kernel.php`、`IntegrationController.php`

---

## 已知 Issue 评估

`issues/` 目录下无已知 issue。本次发布不受任何已知问题影响。

---

## 发布判定

| 检查项 | 状态 | 说明 |
|--------|------|------|
| 所有包含的 feature spec tasks 全部完成 | ✅ | PRP-001: 13/13 tasks completed |
| 全量测试通过 | ⏳ | 需在 release 分支上执行一次全量测试确认 |
| 无 P0/P1 open issue | ✅ | 无已知 issue |
| Changes 记录完整 | ✅ | `docs/changes/unreleased/php85-test-baseline.md` 已记录 |
| Code Review 完成 | ✅ | Feature spec Task 13 已完成 code review |
| Proposal 状态为 implemented | ✅ | PRP-001 status = `implemented` |

**结论**：待 release 分支全量测试确认通过后，可进入发布流程。

---

## Socratic Review

**Q: Release 2.5.0 仅包含测试补全，没有功能变更，是否有必要作为独立 release？**
A: 有必要。测试基线是后续 PRP-002（依赖升级）及更高 Phase 的前置条件。独立发布确保基线版本可追溯，且 master 分支上有一个包含完整测试覆盖的稳定版本。

**Q: 全量测试在 feature 分支已通过，release 分支再跑一次是否冗余？**
A: 不冗余。Release 分支可能与 develop 分支有微小差异（merge 时机、分支点不同），一次全量测试确认是最低成本的保障。但用户已确认不需要额外的回归测试或多 PHP 版本检查（goal CR Q3 = A）。

**Q: `composer.json` 不更新 version 字段，如何确保版本号正确？**
A: 版本号由 git tag 管理（goal CR Q2 = A），release finish 时打 `v2.5.0` tag 即可。Packagist 根据 tag 自动识别版本。

**Q: Changes 归档路径是否明确？**
A: 明确。`docs/changes/unreleased/php85-test-baseline.md` 将归档到 `docs/changes/2.5.0/`，这是 release finish 流程的标准步骤。


---

## Gatekeep Log

**校验时间**: 2025-07-16
**校验结果**: ✅ 通过

### 修正项

无

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Spec 路径、Proposal 引用、feature spec task 编号均可追溯）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Release 2.5.0 Requirements`，符合 release spec 格式
- [x] `## 发布范围` 存在，表格包含 Feature 名称 / Spec 路径 / Proposal 引用 / Proposal Status / Tasks 完成状态
- [x] `## Feature 概要` 存在，概述了核心能力和关键实现
- [x] `## 已知 Issue 评估` 存在，正确反映 `issues/` 目录状态（无已知 issue）
- [x] `## 发布判定` 存在，包含检查项表格和结论
- [x] 发布判定检查项覆盖：tasks 完成、全量测试、open issue、changes 记录、code review、proposal 状态
- [x] 全量测试标记为 ⏳（待 release 分支确认），符合当前阶段实际状态
- [x] 各 section 之间使用 `---` 分隔
- [x] Socratic Review 存在且覆盖充分（release 必要性、测试冗余性、版本号管理、归档路径）
- [x] Goal CR 三项决策（Q1=A 仅 PRP-001 / Q2=A git tag 管理 / Q3=A 复用 feature 验证）均已体现
- [x] 发布范围与 goal.md 一致，未越界或缩水
- [x] Feature 概要数据与 `docs/changes/unreleased/php85-test-baseline.md` 和 feature spec tasks.md 一致

### Clarification Round

**状态**: 已回答

**Q1:** Release 2.5.0 是否需要 stabilize 阶段（alpha/beta 预发布 + 手工测试），还是在全量自动化测试通过后直接进入 finish 流程？考虑到本次 release 仅包含测试补全，无功能变更和用户可感知的行为变化。
- A) 跳过 stabilize，全量测试通过后直接 finish（打 tag、merge 回 master 和 develop）
- B) 执行简化的 stabilize：打一个 alpha tag，执行一轮手工测试确认 suite 完整性，然后 finish
- C) 完整 stabilize 流程：alpha → beta → RC → finish
- D) 其他（请说明）

**A:** B — 执行简化的 stabilize：打一个 alpha tag，执行一轮手工测试确认 suite 完整性，然后 finish

**Q2:** Release finish 时，feature spec（`.kiro/specs/php85-test-baseline/`）如何处理？
- A) 归档到 `docs/changes/2.5.0/specs/php85-test-baseline/`，与 changes 记录一起归档
- B) 保留在 `.kiro/specs/` 原位，不移动（后续 phase 可能需要参考）
- C) 复制一份到归档目录，原位也保留
- D) 其他（请说明）

**A:** A — 归档到 `docs/changes/2.5.0/specs/php85-test-baseline/`，与 changes 记录一起归档

**Q3:** `docs/changes/2.5.0/` 归档目录中，除了移入 `php85-test-baseline.md` 外，是否需要生成一个汇总的 `CHANGELOG.md`？
- A) 需要，生成 `docs/changes/2.5.0/CHANGELOG.md`，汇总本次 release 的所有变更（基于 unreleased 下的 change 文件）
- B) 不需要，`php85-test-baseline.md` 本身已足够详细，不额外生成 CHANGELOG
- C) 需要，但格式为简化版（一段话摘要 + 指向详细 change 文件的链接）
- D) 其他（请说明）

**A:** A — 需要，生成 `docs/changes/2.5.0/CHANGELOG.md`，汇总本次 release 的所有变更
