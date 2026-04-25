# PHP 8.5 Upgrade — Phase 0: Prerequisites

> Proposal：PHP 8.5 升级前置准备——升级内部依赖与测试框架，适配现有测试以兼容 PHPUnit 13.x。

## Status

`in-progress`

## Background

项目计划从 PHP `>=7.0.0` 升级到 PHP `>=8.5`。后续 Phase 1–4 将引入框架替换、依赖大版本升级、Security 组件重写、语言层面适配等大量 breaking change。这些变更的正确性验证完全依赖测试套件。

当前存在前置阻塞：内部依赖（`oasis/logging`、`oasis/utils`）和测试框架（PHPUnit 5.x）均不兼容 PHP 8.5，必须先行升级。

## Problem

- `oasis/logging` `^1.1` 和 `oasis/utils` `^1.6` 的 PHP 8.5 兼容性未知，可能存在阻塞
- PHPUnit `^5.2` 不支持 PHP 8.x，无法在目标 PHP 版本上运行测试

## Goals

- 将 `oasis/logging` 升级到 PHP 8.5 兼容版本
- 将 `oasis/utils` 升级到 PHP 8.5 兼容版本
- 将 PHPUnit 从 `^5.2` 升级到 `^13.0`
- 适配所有现有测试文件以兼容 PHPUnit 13.x API 变化（`setUp`/`tearDown` 返回类型、assertion 方法签名、mock API、data provider attribute 等）
- 确保升级后，不依赖框架运行时的测试 suite 在 PHP 8.x 下通过（详见 Branch Strategy）

## Non-Goals

- 不涉及 Silex 框架替换
- 不涉及 Symfony 组件升级
- 不涉及 PHP 语言层面 breaking changes 修复
- 不修改现有业务逻辑
- 不涉及测试覆盖补全——测试补全已拆分为独立的 PRP-001

## Scope

- `composer.json` — `oasis/logging`、`oasis/utils`、`phpunit/phpunit` 版本约束更新
- `phpunit.xml` — 配置适配
- `ut/` — 现有测试文件的 PHPUnit API 适配

## Risks

- `oasis/logging` 和 `oasis/utils` 为内部包，若无 PHP 8.5 兼容版本则存在外部阻塞
- PHPUnit 5 → 13 跨度大，中间可能需要分步升级（5 → 10 → 13），视实际兼容性决定

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`phpunit`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 0 完成后的测试预期

PHPUnit 升级到 13.x、PHP 约束改为 `>=8.2`，工程运行在 PHP 8.x 环境。Silex、Symfony 4.x、Twig 1.x 等均不兼容 PHP 8.x，大量测试预期失败。

**预期通过的 suite：**

- `configuration` — 纯配置验证逻辑，不依赖框架运行时
- `error-handlers` — 纯逻辑
- `views` — 纯逻辑
- `misc` — 纯逻辑
- `exceptions` — 纯逻辑
- `cookie` — 纯逻辑
- `middlewares` — 纯逻辑

**预期失败的 suite：**

- `cors` — 依赖 Silex Application 启动
- `security` — 依赖 Silex + Symfony Security 4.x
- `twig` — 依赖 Silex + Twig 1.x
- `aws` — 依赖 Silex Application 启动
- `routing` — 依赖 Symfony Routing 4.x
- `integration` — 依赖完整 Silex 启动链路
- `all` 中的 `SilexKernelTest`、`SilexKernelWebTest`、`FallbackViewHandlerTest` — 依赖 Silex

> 注：以上为初步预判，实际边界在 spec design 阶段确认。部分"纯逻辑"测试如果间接 import 了 Silex/Symfony 4.x 类，也可能失败。

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note
- `docs/proposals/PRP-001-php85-test-baseline.md` — 测试覆盖补全 proposal（从本 PRP 拆出）
