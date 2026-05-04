# Silex Migration Behavior Audit & Scenario Test Hardening

> Proposal：对 Silex → Symfony MicroKernel 迁移后的各模块进行行为等价性审计，补充场景级集成测试，消除"形式替换但行为缺失"的系统性风险。

## Status

`accepted`

## Background

v3.0 完成了 Silex → Symfony MicroKernel 的框架替换（PRP-003），后续 Phase 逐步完成了 Security 重写、Twig/Guzzle 升级等工作。升级过程中的测试策略以"新代码能跑通"为导向——单元测试验证新实现的内部逻辑，集成测试验证主流程可用。

但 v3.0 ~ v3.2 期间暴露的三个 issue 揭示了一个结构性盲区：

| Issue | 本质 | 发现方式 |
|-------|------|----------|
| ISS-3.0-L01 | Silex 天然支持的编程式路由 API 在迁移后丢失，API surface 缩水 | 用户侧 |
| ISS-3.0-L02 | boot 后路由修改静默失效，Silex 时代同样存在但 Symfony API 更具误导性 | 用户侧 |
| ISS-3.2-L01 | 手动重组 Symfony Security 组件时遗漏 `AuthenticatedVoter`，Silex Security Provider 内部自动注册的能力丢失 | 用户侧 |

三个问题的共同模式：

1. **迁移时做了形式上的替换，但没有验证行为等价性**——新代码能编译、能跑通主流程，但边缘行为或隐含能力丢失
2. **Silex 自动做的事，Symfony 需要手动组装**——手动组装时容易遗漏组件
3. **现有测试从实现出发验证实现**，缺少从用户场景出发验证行为的测试，无法捕获"能力丢失"类问题

这不是覆盖率数字的问题（当前 89% 行覆盖率不低），而是测试视角的问题。

## Problem

- 迁移后各模块可能仍存在未被发现的行为缺失或语义差异，只能等用户侧暴露
- 现有测试体系无法系统性地捕获"Silex 能做但 MicroKernel 不能做"的问题
- 缺少对 Silex 时代 API surface 和运行时行为的系统性对比基准

## Goals

### Part 1: 行为审计

对迁移涉及的每个模块，系统性对比 Silex 时代与当前 MicroKernel 的行为差异：

- 列出 Silex 时代每个模块对外暴露的 **API surface**（公开方法、可配置项、事件、隐含行为）
- 逐项对比 MicroKernel 当前实现是否覆盖
- 重点识别"Silex 自动做但 Symfony 需要手动组装"的部分
- 对发现的差异分类处置：
  - **缺失能力**：补实现
  - **有意移除**：确认已在 Migration Guide（`docs/manual/migration-v3.md`）中标注为 breaking change
  - **语义差异**：评估影响，决定是否修正或文档化

审计范围按风险排序：

| 模块 | 风险等级 | 理由 |
|------|----------|------|
| Security | 高 | 已暴露 ISS-3.2-L01；Silex Security Provider 内部自动注册大量组件，手动重组最易遗漏 |
| Routing | 高 | 已暴露 ISS-3.0-L01/L02；路由注册、匹配、生命周期语义差异大 |
| Middleware | 中 | 事件优先级、执行顺序可能存在隐含差异 |
| CORS | 中 | 依赖 Middleware 机制，间接受影响 |
| Twig | 低 | 集成方式相对简单，主要是版本升级 |
| Cookie | 低 | 逻辑简单 |
| Error Handling | 中 | 异常处理链在 Silex 和 Symfony HttpKernel 中机制不同 |

### Part 2: 场景级集成测试补充

基于审计结果，为每个模块补充**从用户场景出发**的行为测试：

- 测试视角：构造 MicroKernel → 配置 → boot → 发请求 → 验证响应（端到端行为）
- 不是追求行覆盖率数字，而是追求**场景覆盖的完整性**
- 重点场景：
  - Security：完整认证授权流程，包括 `isGranted()` 各种属性（`IS_AUTHENTICATED_FULLY`、角色、角色继承）、多 firewall、多 access rule 组合、认证失败后的行为
  - Routing：编程式路由注入、YAML 路由、混合路由、boot 后冻结行为、路由优先级
  - Middleware：before/after 执行顺序、优先级、异常时的行为
  - CORS：preflight 处理、多策略匹配、与 Security 的交互
  - Error Handling：异常链、自定义 error handler、fallback 行为

### 产出

- 场景级集成测试套件——审计的核心价值沉淀物，测试即行为基准
- 修复的代码变更（如有缺失能力）
- Migration Guide 补充（如有未文档化的 breaking change）
- 审计对比矩阵（记录在 spec design 中，作为过程产物；长期价值由测试承载，矩阵本身不需要持续维护）

## Non-Goals

- 不做功能新增——审计发现的缺失能力仅恢复到 Silex 时代的等价行为，不借机扩展
- 不重构现有实现——除非审计发现的问题必须通过重构修复
- 不追求 100% 行覆盖率——场景测试的目标是行为完整性，不是数字
- 不涉及下游项目的适配工作

## Scope

- `src/ServiceProviders/Security/` — Security 模块审计与可能的修复
- `src/ServiceProviders/Routing/` — Routing 模块审计
- `src/ServiceProviders/Cors/` — CORS 模块审计
- `src/Middlewares/` — Middleware 模块审计
- `src/ErrorHandlers/` — Error Handling 模块审计
- `src/ServiceProviders/Twig/` — Twig 模块审计（低优先级）
- `src/ServiceProviders/Cookie/` — Cookie 模块审计（低优先级）
- `src/MicroKernel.php` — 核心入口的 API surface 对比
- `ut/` — 新增场景级集成测试
- `docs/manual/migration-v3.md` — 补充遗漏的 breaking change 文档（如有）
- `docs/state/architecture.md` — 审计后如发现架构描述不准确则更新

## Risks

- Silex 源码已 abandoned，审计时需要依赖 vendor 中的存档或 Git 历史来还原 Silex 时代的行为，信息可能不完整
- 审计可能发现需要 breaking change 才能修复的问题，需要权衡是否在当前版本修复
- 场景测试的构造成本较高（需要完整 boot MicroKernel），可能需要提取测试辅助工具

## References

- `issues/ISS-3.0-L01-no-programmatic-route-injection.md`
- `issues/ISS-3.0-L02-route-add-after-boot-silently-ineffective.md`
- `issues/ISS-3.2-L01-missing-authenticated-voter.md`
- `docs/notes/coverage-improvement.md` — 覆盖率改进方向（与本 proposal 互补）
- `docs/state/architecture.md` — 当前架构
- `docs/changes/3.0/php85-upgrade.md` — v3.0 升级变更记录
- `docs/manual/migration-v3.md` — 现有迁移指南

## Notes

- 本 proposal 与 `docs/notes/coverage-improvement.md` 的方向互补但侧重不同：coverage-improvement 关注行覆盖率数字（89% → 95%），本 proposal 关注行为完整性。两者可以并行推进，场景测试的副产品也会提升覆盖率
- 审计工作需要参考 Silex 源码，建议在开始前确认 Git 历史中是否保留了迁移前的完整代码快照（`feature/php85-upgrade` 分支的初始 commit 或迁移前的 tag）
- Part 1 审计和 Part 2 测试建议按模块交替进行（审计一个模块 → 立即补测试），而非先全部审计再全部补测试，这样能更快暴露问题
