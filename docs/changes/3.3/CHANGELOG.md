# Changelog — v3.3

> Release date: 2025-07-17

---

## v3.3.0

Silex Migration Behavior Audit & Scenario Test Hardening：对 Silex → Symfony MicroKernel 迁移涉及的 7 个模块 + MicroKernel 聚合层进行系统性行为审计与场景测试加固，消除"形式替换但行为缺失"的系统性风险。

### Added

- `ScenarioTestCase` 基类（`tests/Helpers/ScenarioTestCase.php`）：封装 boot + handle + assert 的通用流程，所有场景测试继承
- Security 场景测试（`tests/Security/SecurityScenarioTest.php`）：完整认证流程、认证失败、`isGranted()` 各属性、多 firewall、多 access rule、未认证访问、stateless 行为
- Routing 场景测试（`tests/Routing/RoutingScenarioTest.php`）：YAML 路由加载、编程式注入、混合路由优先级、参数替换、boot 后冻结、路由缓存、404
- Middleware 场景测试（`tests/Middlewares/MiddlewareScenarioTest.php`）：before/after 执行、优先级排序、短路、master-request-only、异常行为
- CORS 场景测试（`tests/Cors/CorsScenarioTest.php`）：preflight 处理、正常 CORS 请求、多策略匹配、credentials、Security 交互、非匹配 origin
- Error Handling 场景测试（`tests/ErrorHandlers/ErrorHandlerScenarioTest.php`）：自定义 handler、链式调用顺序、passthrough、HTTP 状态码保留、FallbackViewHandler
- Twig 场景测试（`tests/Twig/TwigScenarioTest.php`）：模板渲染、Twig 缺失、strict variables、auto-reload
- Cookie 场景测试（`tests/Cookie/CookieScenarioTest.php`）：cookie 写入、container 注入、多 cookie
- MicroKernel 聚合层场景测试（`tests/Integration/MicroKernelAggregationScenarioTest.php`）：完整 pipeline、最小配置、无可选模块、controller 参数注入、extra parameters、慢请求检测
- 8 份 Audit_Matrix（`docs/changes/3.3/audit/`）：Security、Routing、Middleware、CORS、Error Handling、Twig、Cookie、MicroKernel 聚合层

### Changed

- `SimpleSecurityProvider`：移除 `SimpleFirewall` 的 `stateless` 配置项（v3.x 为 stateless-only 架构）
- `AccessDecisionManager` strategy：改回 `AffirmativeStrategy`（与 Silex 时代一致）
- `ExceptionListenerWrapper`：恢复异常类型过滤机制（`shouldRunErrorHandler`）
- 测试目录：`ut/` 重命名为 `tests/`
- PHPUnit 测试：迁移旧特性到 PHP 8 attributes + `createMock`

### Fixed

- Error Handling：恢复 `shouldRunErrorHandler` 异常类型过滤——error handler 可通过类型提示限定只处理特定异常类型
- Cookie：修复 `SimpleCookieProvider` 相关能力（审计发现的 non-breaking 缺失）

### Documentation

- `docs/manual/migration-v3.md`：补充 `FirewallInterface::isStateless()` 移除说明和 `AccessRuleInterface::getRequiredChannel()` channel enforcement 行为变更说明
- `docs/state/architecture.md`：确认与当前代码一致

### 测试覆盖

- PHPUnit：686 tests, 21793 assertions（全部通过）
- PHPStan level 8：零错误
- 新增场景测试覆盖 7 模块 + 聚合层的行为基准

