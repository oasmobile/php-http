# Changelog

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
