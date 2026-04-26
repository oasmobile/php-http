# PHP 8.5 Upgrade (PRP-002 ~ PRP-007)

PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`，完成框架替换、依赖升级、安全组件重构、语言适配和稳定化验证。

---

## Changed

### 框架替换（Phase 1, PRP-003）

- 移除 `silex/silex`、`silex/providers` 依赖
- `SilexKernel` 重写为 `MicroKernel`，基于 Symfony `HttpKernel`，实现 `AuthorizationCheckerInterface`
- DI 容器从 Pimple 迁移到 Symfony DependencyInjection
- 路由注册机制迁移到 Symfony Routing 7.x
- 中间件机制迁移到 Symfony EventDispatcher
- 所有 Service Provider 迁移到新框架模式

### 依赖升级

- Symfony 全部组件：`^4.0` → `^7.2`（Phase 1, PRP-003）
- `twig/twig`：`^1.24` → `^3.0`（Phase 2, PRP-004）
- `guzzlehttp/guzzle`：`^6.3` → `^7.0`（Phase 2, PRP-004）
- `oasis/logging`：`^1.1` → `^3.0`（Phase 0 + Phase 5, PRP-002/PRP-007）
- `oasis/utils`：`^1.6` → `^3.0`（Phase 0 + Phase 5, PRP-002/PRP-007）
- `phpunit/phpunit`：`^5.2` → `^13.0`（Phase 0, PRP-002）
- `phpstan/phpstan`：新增 `^2.1`（Phase 5, PRP-007）

### 安全组件重构（Phase 3, PRP-005）

- `AbstractSimplePreAuthenticator` 重写，适配 Symfony 7.x `AuthenticatorInterface`
- `AbstractSimplePreAuthenticateUserProvider` 适配新 `UserProviderInterface`
- `SimpleSecurityProvider` 的 firewall / access rule 注册机制适配
- `AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface` 接口重写
- `NullEntryPoint` 适配新 Security 架构

### PHP 语言适配（Phase 4, PRP-006）

- 修复所有隐式 nullable 参数（`Type $param = null` → `?Type $param = null`）
- 修复动态属性使用
- `composer.json` PHP 版本约束：`>=7.0.0` → `>=8.5`

### 验证与稳定化（Phase 5, PRP-007）

- 引入 PHPStan level 8 静态分析，零错误
- 全量测试通过：510 tests, 16642 assertions
- 零 deprecation notice
- `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 全面更新

## Removed

- `silex/silex` `^2.3`
- `silex/providers` `^2.3`
- `twig/extensions` `^1.3`

## Added

- `giorgiosironi/eris` `^1.0`（Property-Based Testing 框架，Phase 1 引入）
- `phpstan/phpstan` `^2.1`（静态分析，Phase 5 引入）
- `phpstan.neon`、`phpstan-baseline.neon` 配置文件
- PBT 测试套件（`ut/PBT/`）

## 测试前置工作（Phase 0, PRP-002）

- PHPUnit 5.x → 13.x API 适配（`setUp(): void`、`expectException`、`createMock` 等）
- 全部测试文件适配 PHPUnit 13.x

## Resolved Notes

- `docs/notes/php85-upgrade.md`：升级调研中识别的所有问题已在 Phase 0–5 中解决
- `docs/notes/php85-phase0-framework-dependent-failures.md`：Phase 0 记录的所有预期失败已在后续 Phase 中修复
