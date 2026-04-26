# Changelog — v3.0

> Release date: 2026-04-26

## Summary

Release 3.0 完成 `oasis/http` 项目的 PHP 8.5 全面升级（PRP-002 ~ PRP-008）。核心框架从 Silex 替换为 Symfony MicroKernel，DI 容器从 Pimple 迁移到 Symfony DependencyInjection，全部 Symfony 组件升级到 7.x，Twig 升级到 3.x，Guzzle 升级到 7.x，Security 组件完全重写以适配 Symfony 7.x authenticator 系统。PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`，引入 PHPStan level 8 静态分析。同时为下游消费者提供了完整的 Migration Guide 和预升级 Check Script。

## Added

- Symfony MicroKernel 框架（`MicroKernel` 基于 Symfony `HttpKernel`，实现 `AuthorizationCheckerInterface`）（Phase 1, PRP-003）
- Symfony DependencyInjection 容器（Phase 1, PRP-003）
- Symfony Routing 7.x 路由注册机制（Phase 1, PRP-003）
- Symfony EventDispatcher 中间件机制（Phase 1, PRP-003）
- `giorgiosironi/eris` `^1.0` Property-Based Testing 框架（Phase 1, PRP-003）
- `phpstan/phpstan` `^2.1` 静态分析工具（Phase 5, PRP-007）
- `phpstan.neon`、`phpstan-baseline.neon` 配置文件（Phase 5, PRP-007）
- PBT 测试套件（`ut/PBT/`）：路由/中间件/请求分发、认证/配置/Firewall/RoleHierarchy、异常序列化/ViewHandler/构造器等价性、Check Script 规则检测（Phase 1/3/4/PRP-008）
- Migration Guide（`docs/manual/migration-v3.md`）：按模块分 12 个章节，每个 breaking change 标注严重程度（🔴/🟡/🟢），提供 before/after 代码示例（PRP-008）
- Check Script（`bin/oasis-http-migrate-v3-check`）：基于 `token_get_all()` 的 token 级扫描，检测对已移除/已变更 API 的引用，支持 text/JSON 输出（PRP-008）
- Document Validation Tests（`ut/MigrationGuideValidationTest.php`）：验证 Migration Guide 的 TOC 锚点、Breaking Change 覆盖、条目格式、Bootstrap Config Key 覆盖（PRP-008）
- Check Script PBT（`ut/PBT/MigrateCheckPropertyTest.php`）：Properties 5–11（PRP-008）
- Check Script Unit Tests（`ut/MigrateCheckScriptTest.php`）：CLI 交互、错误处理、规则检测（PRP-008）
- `phpunit.xml` 新增 `migration-guide-validation`、`migrate-check-pbt`、`migrate-check-unit` 三个 test suite（PRP-008）

## Changed

- `SilexKernel` 重写为 `MicroKernel`，基于 Symfony `HttpKernel`（Phase 1, PRP-003）
- DI 容器从 Pimple 迁移到 Symfony DependencyInjection（Phase 1, PRP-003）
- 路由注册机制迁移到 Symfony Routing 7.x（Phase 1, PRP-003）
- 中间件机制迁移到 Symfony EventDispatcher（Phase 1, PRP-003）
- 所有 Service Provider 迁移到新框架模式（Phase 1, PRP-003）
- Symfony 全部组件：`^4.0` → `^7.2`（Phase 1, PRP-003）
- `twig/twig`：`^1.24` → `^3.0`，适配 Twig 3.x 扩展 API（Phase 2, PRP-004）
- `guzzlehttp/guzzle`：`^6.3` → `^7.0`（Phase 2, PRP-004）
- `oasis/logging`：`^1.1` → `^3.0`（Phase 0 + Phase 5, PRP-002/PRP-007）
- `oasis/utils`：`^1.6` → `^3.0`（Phase 0 + Phase 5, PRP-002/PRP-007）
- `phpunit/phpunit`：`^5.2` → `^13.0`，全部测试文件适配 PHPUnit 13.x API（Phase 0, PRP-002）
- `AbstractSimplePreAuthenticator` 重写，适配 Symfony 7.x `AuthenticatorInterface`（Phase 3, PRP-005）
- `AbstractSimplePreAuthenticateUserProvider` 适配新 `UserProviderInterface`（Phase 3, PRP-005）
- `SimpleSecurityProvider` 的 firewall / access rule 注册机制适配（Phase 3, PRP-005）
- `AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface` 接口重写（Phase 3, PRP-005）
- `NullEntryPoint` 适配新 Security 架构（Phase 3, PRP-005）
- 修复所有隐式 nullable 参数（`Type $param = null` → `?Type $param = null`）（Phase 4, PRP-006）
- 修复动态属性使用（Phase 4, PRP-006）
- `composer.json` PHP 版本约束：`>=7.0.0` → `>=8.5`（Phase 4, PRP-006）
- `composer.json` 新增 `"bin": ["bin/oasis-http-migrate-v3-check"]` 配置（PRP-008）
- `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 全面更新（Phase 5, PRP-007）

## Removed

- `silex/silex` `^2.3`（Phase 1, PRP-003）
- `silex/providers` `^2.3`（Phase 1, PRP-003）
- `twig/extensions` `^1.3`（Phase 2, PRP-004）

## Resolved Notes

- `docs/notes/php85-upgrade.md`：升级调研中识别的所有问题已在 Phase 0–5 中解决
- `docs/notes/php85-phase0-framework-dependent-failures.md`：Phase 0 记录的所有预期失败已在后续 Phase 中修复

## 测试覆盖

### 最终统计

- 全量测试：560 tests, 21182 assertions（全部通过）
- PHPStan level 8：零错误
- 零 deprecation notice

### 各 Phase 测试类型与数量

| Phase | 测试类型 | 数量 |
|-------|---------|------|
| Phase 0（PRP-002） | PHPUnit API 适配 | 现有测试全部适配（333 tests → PHPUnit 13.x API） |
| Phase 1（PRP-003） | 单元测试 + PBT | 单元测试适配 + PBT 3 文件（CP1–CP5，路由/中间件/请求分发） |
| Phase 2（PRP-004） | 单元测试 | TwigConfiguration 5 tests + TwigServiceProvider 5 tests（PBT 不适用） |
| Phase 3（PRP-005） | 单元测试 + PBT | PBT 4 文件（Properties 1–16，认证/配置/Firewall/RoleHierarchy）+ 单元测试适配 |
| Phase 4（PRP-006） | PBT + 现有测试适配 | PBT 3 文件（Properties 1–8，异常序列化/ViewHandler/构造器等价性）+ 全量回归 |
| Phase 5（PRP-007） | 全量验证 + PHPStan | 560 tests, 21182 assertions |
| PRP-008 | Document Validation + PBT + Unit Tests | Properties 5–11 + Unit Tests |

### 覆盖率

覆盖率工具不可用：当前环境无 Xdebug/PCOV 扩展，无法运行 `php vendor/bin/phpunit --coverage-text`。覆盖率百分比未采集，不阻塞 release 流程。
