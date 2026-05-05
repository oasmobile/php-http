# Spec Goal: Silex Migration Behavior Audit & Scenario Test Hardening

## 来源

- 分支: `release/3.3.0`
- 需求文档: `docs/proposals/PRP-009-silex-migration-behavior-audit.md`

## 背景摘要

oasis/http v3.0 完成了 Silex → Symfony MicroKernel 的框架替换，后续版本逐步完成 Security 重写、Twig/Guzzle 升级、Symfony 8.x 适配等工作。当前版本为 v3.2.1，测试覆盖率 89%，全量测试 650 tests / 21850 assertions。

v3.0 ~ v3.2 期间暴露了三个 issue（ISS-3.0-L01、ISS-3.0-L02、ISS-3.2-L01），揭示了一个结构性盲区：迁移时做了形式上的替换，但没有验证行为等价性。Silex 自动做的事在 Symfony 中需要手动组装，手动组装时容易遗漏组件。现有测试从实现出发验证实现，缺少从用户场景出发验证行为的测试，无法捕获"能力丢失"类问题。

这三个 issue 虽已在 v3.2 / v3.2.1 中修复，但同类问题可能仍潜伏在其他模块中。本次 release 的核心目标是系统性地消除这一盲区。

## 目标

- 对迁移涉及的每个模块，基于 Silex 官方文档和源码，系统性梳理 Silex 时代的 API surface（公开方法、可配置项、事件、隐含行为），逐项对比当前 MicroKernel 实现是否覆盖
- 按风险排序逐模块推进（Security → Routing → Middleware → CORS → Error Handling → Twig → Cookie），每个模块完成审计后立即补充场景级集成测试
- 对审计发现的差异分类处置：非 breaking 的缺失能力直接补回；需要 breaking change 的缺失能力仅文档化到 migration guide；有意移除的能力确认已在 migration guide 中标注
- 补充从用户场景出发的行为测试（构造 MicroKernel → 配置 → boot → 发请求 → 验证响应），建立行为基准
- 各模块审计完成后，做一轮 MicroKernel 聚合层汇总审计，检查是否有模块审计遗漏的聚合层问题
- 更新 migration guide 和 architecture 文档（如审计发现遗漏或不准确之处）

## 不做的事情（Non-Goals）

- 不做功能新增——审计发现的缺失能力仅恢复到 Silex 时代的等价行为，不借机扩展
- 不重构现有实现——除非审计发现的问题必须通过重构修复
- 不追求 100% 行覆盖率——场景测试的目标是行为完整性，不是数字
- 不涉及下游项目的适配工作
- 不处理需要 breaking change 才能修复的缺失能力（仅文档化）

## Clarification 记录

### Q1: 审计的 Silex 行为基准来源

- 选项: A) Git 历史 / B) Silex 官方文档 + 源码阅读 / C) 已有 issue 驱动 / D) 补充说明
- 回答: B — 基于 Silex 的 GitHub 存档和官方文档，逐模块梳理 API surface

### Q2: Release 分支上的工作编排方式

- 选项: A) 按风险排序逐模块推进 / B) 高风险独立 + 中低风险合并 / C) Part 1/Part 2 分离 / D) 补充说明
- 回答: A — 按 PRP-009 的风险排序（Security → Routing → Middleware → CORS → Error Handling → Twig → Cookie），每个模块完成审计+测试后 checkpoint commit

### Q3: 审计发现缺失能力时的处置策略

- 选项: A) 仅补测试 + 文档化 / B) 非 breaking 补回来，breaking 文档化 / C) 全部尝试补回来 / D) 补充说明
- 回答: B — 非 breaking 的缺失能力直接在本次 release 中修复；需要 breaking change 的仅文档化

### Q4: MicroKernel 核心入口的审计定位

- 选项: A) 融入各模块审计 / B) 独立审计 / C) 作为最终汇总 / D) 补充说明
- 回答: A+C — MicroKernel 上暴露的方法归入对应模块一起审计，各模块完成后再做一轮聚合层汇总审计

## 约束与决策

- **基准来源**：以 Silex 官方文档和 GitHub 源码存档为审计基准，不依赖本仓库 Git 历史中的迁移前快照
- **工作编排**：按风险排序逐模块推进，每个模块审计+测试完成后 checkpoint commit，全部在 release/3.3.0 分支上线性推进
- **处置策略**：非 breaking 缺失能力 → 补代码；breaking 缺失能力 → 文档化到 migration guide；有意移除 → 确认 migration guide 已标注
- **MicroKernel 审计**：各模块审计时覆盖 MicroKernel 上对应的方法，最后做聚合层汇总
- **测试视角**：场景级集成测试从用户场景出发（boot → 请求 → 响应），不是从实现出发验证实现
