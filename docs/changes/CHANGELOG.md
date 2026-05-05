# Changelog

## v3.6.3 - 2026-05-06

MicroKernel 内部重构：拆分为 `Kernel/` 子 namespace traits，提升可维护性。公共 API 无变更。详见 [3.6.3/CHANGELOG.md](3.6.3/CHANGELOG.md)。

## v3.4.0 - 2026-05-06

Hotfix：恢复 SilexKernel 时代通过 Trait 暴露的 `render` / `renderView` / `path` / `url` 便捷方法，在 MicroKernel 上补充对等实现。详见 [3.4/CHANGELOG.md](3.4/CHANGELOG.md)。

## v3.3.1 - 2025-07-18

Hotfix：修正 migration guide 中 channel enforcement 描述，反映 v3.3 已恢复该能力。详见 [3.3.1/CHANGELOG.md](3.3.1/CHANGELOG.md)。

## v3.3.0 - 2025-07-17

Silex Migration Behavior Audit & Scenario Test Hardening：对迁移涉及的 7 模块 + MicroKernel 聚合层进行系统性行为审计与场景测试加固（PRP-009）。详见 [3.3/CHANGELOG.md](3.3/CHANGELOG.md)。

## v3.2.1 - 2026-05-05

Hotfix：修复 `AccessDecisionManager` 缺少 `AuthenticatedVoter` 导致认证属性检查失败（ISS-3.2-L01），修复 `MigrationGuideValidationTest` 测试逻辑。详见 [3.2.1/CHANGELOG.md](3.2.1/CHANGELOG.md)。

## v3.2.0 - 2026-05-05

修复路由子系统编程式注入 API 缺失（ISS-3.0-L01）和 boot 后路由修改静默失效（ISS-3.0-L02），新增双层 matcher 架构实现缓存隔离。详见 [3.2/CHANGELOG.md](3.2/CHANGELOG.md)。

## v3.1.0 - 2026-05-03

Symfony 全系依赖 `^7.2` → `^8.0`。详见 [3.1/CHANGELOG.md](3.1/CHANGELOG.md)。

## v3.0 - 2026-04-26

PHP 8.5 全面升级（PRP-002 ~ PRP-008）：框架替换、依赖升级、Security 重构、语言适配、Migration Guide & Check Script。详见 [3.0/CHANGELOG.md](3.0/CHANGELOG.md)。

## v2.5.0 - 2026-04-24

PHP 8.5 升级前测试基线补全（PRP-001）：为所有缺少测试的模块补充单元测试和集成测试，建立完整的行为 SSOT。详见 [2.5.0/CHANGELOG.md](2.5.0/CHANGELOG.md)。
