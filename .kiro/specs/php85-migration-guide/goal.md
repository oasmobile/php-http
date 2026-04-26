# Goal

## Proposal

`docs/proposals/PRP-008-php85-migration-guide.md`

## 概述

为依赖 `oasis/http` 的下游项目提供完整的 PHP 8.5 升级迁移指南和自动化检查工具，帮助下游消费者评估影响范围并平滑过渡到新版本。

## 背景

`oasis/http` 经过 Phase 0–5 的升级，引入了大量 breaking change：

- 框架从 Silex 替换为 Symfony MicroKernel
- DI 容器从 Pimple 迁移到 Symfony DependencyInjection
- Security 组件接口全面重写（`AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface`、`AbstractSimplePreAuthenticator` 等）
- Twig 1.x → 3.x、Guzzle 6.x → 7.x
- PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`
- 多个依赖移除（`silex/silex`、`silex/providers`、`twig/extensions`）

下游项目需要清晰的迁移文档和自动化工具来完成适配。

## 交付物

1. **Migration Guide**（`docs/manual/migration-v3.md`）：单文件，按模块分章节，带 TOC 导航，覆盖所有 breaking change，每项提供 before/after 代码示例和严重程度标注（🔴 必须改 / 🟡 建议改 / 🟢 可选）
2. **预升级检查脚本**（`bin/oasis-http-migrate-v3-check`）：通过 composer `bin` 配置暴露到 `vendor/bin/`，下游项目安装后可直接运行，扫描目标目录检测对已移除/已变更 API 的引用，输出结构化报告

## 不包含

- 下游项目的实际升级执行
- `oasis/http` 自身的代码变更
- 运行时兼容层或 shim
- 自动化升级脚本（proposal 中标注为可选，本次不实现；后续有需要可单独开 spec）

## 成功标准

- Migration Guide 覆盖 `docs/changes/unreleased/php85-upgrade.md` 中记录的所有 breaking change
- 检查脚本能检测对旧 API（已移除的类、已变更的接口、旧 config key）的引用
- 检查脚本输出包含文件路径、行号和建议操作
- 文档和脚本经 review 确认可用

## 约束

- Migration Guide 为单文件 `docs/manual/migration-v3.md`，用目录锚点导航各模块章节
- 检查脚本放 `bin/oasis-http-migrate-v3-check`，composer.json 添加 `"bin"` 配置
- 迁移文档内容基于 Phase 0–5 的实际产出，不臆造变更

## Clarification Round

1. **升级脚本**：本次不做自动化升级脚本，仅提供检查脚本。后续有需要可单独开 spec。→ ✅ 已确认
2. **检查脚本定位**：放 `bin/oasis-http-migrate-v3-check`，通过 composer `bin` 暴露到 `vendor/bin/`。→ ✅ 已确认
3. **文档粒度**：按模块分章节（Kernel/Bootstrap Config、Routing、Security、Middleware、Views、Twig、CORS、Cookie、PHP Version、依赖变更），每项标 🔴/🟡/🟢 严重程度，附 before/after 代码示例。→ ✅ 已确认
4. **文档数量**：单文件 `docs/manual/migration-v3.md`，用 TOC 导航。→ ✅ 已确认
