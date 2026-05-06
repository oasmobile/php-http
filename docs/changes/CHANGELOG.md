# Changelog

## v3.6.4 - 2026-05-07

迁移文档可读性改进：增加最小迁移步骤、常见报错速查表，修正 Cookie 章节，补充便捷方法直升注意事项，消除重复内容。详见 [3.6.4/CHANGELOG.md](3.6.4/CHANGELOG.md)。

## v3.6.3 - 2026-05-06

MicroKernel 内部重构：拆分为 `Kernel/` 子 namespace traits，提升可维护性。公共 API 无变更。详见 [3.6.3/CHANGELOG.md](3.6.3/CHANGELOG.md)。

## v3.6.2 - 2026-05-06

提取 `createAwsIpRangesClient()` 工厂方法 + CloudFront HTTP 路径测试覆盖，lines 95.05%。详见 [3.6.2/CHANGELOG.md](3.6.2/CHANGELOG.md)。

## v3.6.1 - 2026-05-06

测试加固：启用 PHPUnit 严格模式，覆盖率从 87.85% 提升到 94.91%。详见 [3.6.1/CHANGELOG.md](3.6.1/CHANGELOG.md)。

## v3.6.0 - 2026-05-06

恢复 Silex 第二层便捷方法：`view()` / `abort()` / `redirect()` / `json()` / `stream()` / `sendFile()`。详见 [3.6/CHANGELOG.md](3.6/CHANGELOG.md)。

## v3.5.1 - 2026-05-06

补充 `before()` / `after()` 的 `masterRequestOnly` 参数 sub-request 行为测试。详见 [3.5.1/CHANGELOG.md](3.5.1/CHANGELOG.md)。

## v3.5.0 - 2026-05-06

恢复 Silex 时代的 `before()` / `after()` / `error()` 便捷方法。详见 [3.5/CHANGELOG.md](3.5/CHANGELOG.md)。

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

## v3.0.1 - 2026-05-01

代码质量加固：全量添加 `declare(strict_types=1)` + 覆盖率提升至 91.57%。详见 [3.0.1/CHANGELOG.md](3.0.1/CHANGELOG.md)。

## v3.0.0 - 2026-04-26

PHP 8.5 全面升级（PRP-002 ~ PRP-008）：框架替换、依赖升级、Security 重构、语言适配、Migration Guide & Check Script。详见 [3.0/CHANGELOG.md](3.0/CHANGELOG.md)。

## v2.5.0 - 2026-04-24

PHP 8.5 升级前测试基线补全（PRP-001）：为所有缺少测试的模块补充单元测试和集成测试，建立完整的行为 SSOT。详见 [2.5.0/CHANGELOG.md](2.5.0/CHANGELOG.md)。
