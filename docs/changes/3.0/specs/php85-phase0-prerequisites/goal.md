# Spec Goal: PHP 8.5 Upgrade — Phase 0: Prerequisites

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-002-php85-phase0-prerequisites.md`

## 背景摘要

`oasis/http` 当前基于 PHP ≥ 7.0，核心框架为 Silex 2.x（已 abandoned），全部 Symfony 组件锁定在 4.x，测试框架为 PHPUnit 5.x。项目计划升级到 PHP ≥ 8.5，后续 Phase 1–5 将引入框架替换、依赖大版本升级、Security 组件重写、语言层面适配等大量 breaking change。

这些变更的正确性验证完全依赖测试套件。当前存在前置阻塞：内部依赖（`oasis/logging` `^1.1`、`oasis/utils` `^1.6`）和测试框架（PHPUnit `^5.2`）均不兼容 PHP 8.5，必须先行升级。

PRP-002 至 PRP-007 共享同一个长生命周期 feature branch `feature/php85-upgrade`。本 Phase（Phase 0）是该 branch 上的第一个 spec，完成后工程将运行在 PHP 8.5 环境，但由于 Silex、Symfony 4.x、Twig 1.x 等框架依赖尚未替换，大量依赖框架运行时的测试预期失败。

## 目标

- 将 `composer.json` 中 PHP 版本约束从 `>=7.0.0` 改为 `>=8.5`
- 将 `oasis/logging` 升级到 PHP 8.5 兼容的大版本（`^` 约束，具体版本由 composer 解析）
- 将 `oasis/utils` 升级到 PHP 8.5 兼容的大版本（`^` 约束，具体版本由 composer 解析）
- 将 PHPUnit 从 `^5.2` 直接升级到 `^13.0`（一步到位，不分步）
- 适配所有现有测试文件以兼容 PHPUnit 13.x API 变化（`setUp`/`tearDown` 返回类型、assertion 方法签名、mock API、data provider attribute 等）
- 适配 `phpunit.xml` 配置格式到 PHPUnit 13.x
- 确保不依赖框架运行时的测试 suite 在 PHP 8.5 下通过（`configuration`、`error-handlers`、`views`、`misc`、`exceptions`、`cookie`、`middlewares`）

## 不做的事情（Non-Goals）

- 不涉及 Silex 框架替换（Phase 1 / PRP-003）
- 不涉及 Symfony 组件升级（Phase 1 / PRP-003）
- 不涉及 Twig、Guzzle 升级（Phase 2 / PRP-004）
- 不涉及 Security 组件重写（Phase 3 / PRP-005）
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 不修改现有业务逻辑
- 不涉及测试覆盖补全（已在 PRP-001 完成）

## Clarification 记录

### Q1: PHP 版本约束的目标值

PRP-002 需要升级 PHPUnit 到 13.x（要求 PHP ≥ 8.2），但最终目标是 PHP ≥ 8.5。`composer.json` 的 `php` 约束应该改成什么？

- 选项: A) `>=8.2` / B) `>=8.4` / C) `>=8.5` 一步到位 / D) 补充说明
- 回答: C — 直接改到 `>=8.5`

### Q2: PHPUnit 升级路径

PHPUnit 5 → 13 跨度大，是否需要分步升级？

- 选项: A) 直接 5 → 13 / B) 5 → 10 → 13 / C) 5 → 11 → 13 / D) 补充说明
- 回答: A — 直接一步到位

### Q3: `oasis/logging` 和 `oasis/utils` 的升级策略

这两个内部包如果没有 PHP 8.5 兼容版本怎么处理？

- 选项: A) 先升级这两个包 / B) fork 或 patch 临时解决 / C) 已确认有兼容版本 / D) 补充说明
- 回答: C — 已确认有兼容版本，不存在阻塞

### Q4: `oasis/logging` 和 `oasis/utils` 的目标版本

目标版本约束如何确定？

- 选项: A) 保持 `^` 约束，提升大版本号 / B) 提供具体版本 / C) 补充说明
- 回答: A — 保持 `^` 约束，具体版本由 `composer update` 解析

## 约束与决策

- PHP 版本约束在本 Phase 直接改到 `>=8.5`，不做中间过渡
- PHPUnit 直接从 5.x 升到 13.x，不分步
- `oasis/logging` 和 `oasis/utils` 已有 PHP 8.5 兼容版本，不存在外部阻塞
- 内部包版本约束使用 `^` 语义化约束，具体版本由 composer 解析
- spec 级 DoD：tasks 全部完成 + 不依赖框架运行时的 suite 通过；依赖框架的 suite 预期失败，留给后续 Phase 解决
