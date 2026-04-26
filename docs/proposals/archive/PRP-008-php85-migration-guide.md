# PHP 8.5 Upgrade — Migration Guide & Tooling

> Proposal：为下游消费者提供完整的迁移文档、检查脚本和升级脚本，帮助依赖 `oasis/http` 的项目平滑过渡到新版本。

## Status

`released`

## Background

`oasis/http` 的 PHP 8.5 升级（Phase 0–5）涉及框架替换（Silex → Symfony MicroKernel）、Security 组件重写、Twig/Guzzle 大版本升级、PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5` 等大量 breaking change。这些变更不仅影响 `oasis/http` 自身，也直接影响所有依赖它的下游项目。

下游项目需要清晰的迁移指引和自动化工具来评估影响范围、执行升级。

## Problem

- 下游项目不清楚新版本引入了哪些 breaking change，以及各自需要做什么适配
- Bootstrap config 结构、公共 API（`SilexKernel` 构造函数、Service Provider 注册方式、中间件接口等）可能发生变化，下游代码需要对应修改
- Security 组件的接口完全重写（`AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface`、`SimplePreAuthenticator` 等），实现了这些接口的下游代码必须重写
- 缺少自动化工具帮助下游项目评估影响范围和执行机械性替换

## Goals

### 迁移文档

- 编写完整的 Migration Guide，覆盖所有 breaking change，按模块组织：
  - **Bootstrap Config**：config key 的增删改、默认值变化
  - **Kernel API**：`SilexKernel` 替换后的新入口类、构造方式、`run()` / `handle()` 行为变化
  - **Routing**：路由注册方式变化（如有）
  - **Security**：旧接口 → 新接口的映射关系、认证策略迁移、Firewall / AccessRule 适配
  - **Middleware**：`MiddlewareInterface` / `AbstractMiddleware` 的变化
  - **Views**：View Handler 注册和渲染器接口变化（如有）
  - **Twig**：Twig 集成方式变化、模板语法影响
  - **CORS**：CORS 配置和策略接口变化（如有）
  - **Cookie**：Cookie Provider 变化（如有）
  - **PHP 版本要求**：`>=7.0.0` → `>=8.5`，下游项目需同步升级 PHP
- 每个 breaking change 提供 before / after 代码示例
- 标注变更的严重程度（必须改 / 建议改 / 可选）

### 检查脚本

- 提供预升级检查脚本，下游项目可在升级前运行，自动扫描：
  - 对已移除 / 已变更 API 的引用
  - 对旧 Security 接口的实现
  - 对旧 Bootstrap config key 的使用
  - PHP 版本兼容性问题（隐式 nullable 参数等）
- 输出结构化报告，列出需要修改的文件和行号

### 升级脚本（可选）

- 对于机械性替换（类名变更、命名空间变更、方法签名变更等），提供自动化升级脚本
- 脚本应为幂等操作，可安全重复运行
- 无法自动处理的变更，脚本应输出 TODO 标记供人工处理

## Non-Goals

- 不负责下游项目的实际升级执行——仅提供文档和工具
- 不涉及 `oasis/http` 自身的代码变更（已在 Phase 0–5 完成）
- 不提供版本间的运行时兼容层或 shim

## Scope

- `docs/manual/` — Migration Guide 文档
- 检查脚本文件（位置待定，如 `tools/` 或 `bin/`）
- 升级脚本文件（如提供）

## Risks

- 迁移文档的完整性依赖 Phase 0–5 的最终产出，若各 Phase 的 breaking change 未被充分记录，文档可能遗漏
- 检查脚本的覆盖率取决于对所有 breaking change 的枚举，可能存在边缘情况
- 下游项目的使用方式多样，文档和脚本难以覆盖所有场景

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note
- `docs/state/architecture.md` — 当前架构（公共 API 和 Bootstrap Config 定义）
- `docs/proposals/PRP-002-php85-phase0-prerequisites.md` ~ `docs/proposals/PRP-007-php85-phase5-validation-stabilization.md` — 各 Phase 的 proposal

## Notes

- 迁移文档的内容应在各 Phase 推进过程中逐步积累，而非等到所有 Phase 完成后一次性编写
- 建议在每个 Phase 的 spec 中加入"记录 breaking change"的 task，产出汇总到本 PRP
- 本 PRP 的最终交付应在 Phase 5 完成后、release 前
