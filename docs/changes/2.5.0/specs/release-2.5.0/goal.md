# Spec Goal: Release 2.5.0

## 来源

- 分支: `release/2.5.0`
- 需求文档: `docs/proposals/PRP-001-php85-test-baseline.md`
- Feature Spec: `.kiro/specs/php85-test-baseline/`（已完成）
- Changes: `docs/changes/unreleased/php85-test-baseline.md`

## 背景摘要

`oasis/http` 是基于 Silex 微框架的 HTTP 组件，提供路由、安全、CORS、模板、中间件等 Web 应用基础能力。项目计划从 PHP >=7.0 升级到 PHP >=8.5，后续 Phase 1–4 将引入框架替换、依赖大版本升级等大量 breaking change。

PRP-001（测试基线补全）作为 PHP 8.5 升级的第一步，已在 `feature/php85-test-baseline` 分支上完成全部实现。该 feature 为所有缺少测试的模块补充了单元测试和集成测试，建立了完整的行为基线（333 tests / 597 assertions，全部通过）。覆盖范围包括 ErrorHandlers、Configuration（8 个类）、Views（6 个类）、Routing（7 个类）、Cookie、Middlewares、Security（NullEntryPoint）、Misc（4 个独立模块），以及 3 个跨模块集成测试和现有测试的全面场景补充。

Release 2.5.0 仅包含 PRP-001 这一个 feature，目标是将测试基线补全的成果发布为正式版本，为后续 PRP-002（依赖升级）及更高 Phase 的迁移工作奠定基础。

## 目标

- 将 PRP-001（测试基线补全）的全部变更纳入 release 2.5.0
- 执行一次全量测试确认，确保 release 分支上所有测试通过
- 整理 changes 记录，将 `docs/changes/unreleased/php85-test-baseline.md` 归档到 `docs/changes/2.5.0/`
- 完成 release 流程所需的文档收敛（spec 归档、proposal 状态更新等）

## 不做的事情（Non-Goals）

- 不修改 `composer.json` 的 version 字段（版本号由 git tag 管理）
- 不涉及 PRP-001 以外的任何功能变更
- 不涉及依赖升级（PRP-002 范畴）
- 不涉及额外的回归测试或多 PHP 版本兼容性检查——复用 feature 阶段的验证结果，release 阶段仅做一次全量测试确认

## Clarification 记录

### Q1: Release 2.5.0 的发布范围确认

- 选项: A) 仅包含 PRP-001，无其他变更 / B) 除 PRP-001 外还需包含 develop 上的其他小修改 / C) 补充说明
- 回答: A — 仅包含 PRP-001（测试基线补全），无其他变更

### Q2: Release 2.5.0 的版本号管理

- 选项: A) 不需要，继续由 git tag 管理 / B) 在 composer.json 中添加 version 字段 / C) 补充说明
- 回答: A — 不需要，继续由 git tag 管理版本号，`composer.json` 不变

### Q3: Release 验证范围

- 选项: A) 复用 feature 阶段验证，release 仅做一次全量测试确认 / B) 重新执行全量测试 + 手工测试 / C) 额外验证 / D) 补充说明
- 回答: A — 复用 feature 阶段的验证结果，release 阶段仅做一次全量测试确认即可

## 约束与决策

- **发布范围**：仅 PRP-001，无其他变更
- **版本号管理**：由 git tag 管理，不修改 composer.json
- **验证策略**：复用 feature 阶段验证结果，release 阶段仅做一次全量测试确认
- **Changes 归档**：`docs/changes/unreleased/php85-test-baseline.md` → `docs/changes/2.5.0/`
