# PHP 8.5 Upgrade — Phase 0: Prerequisites

> Proposal：PHP 8.5 升级前置准备——升级内部依赖与测试框架，并全面补足现有功能的测试覆盖，为后续 breaking change 迁移建立行为基线。

## Status

`accepted`

## Background

项目计划从 PHP `>=7.0.0` 升级到 PHP `>=8.5`。后续 Phase 1–4 将引入框架替换、依赖大版本升级、Security 组件重写、语言层面适配等大量 breaking change。这些变更的正确性验证完全依赖测试套件——现有测试必须足够全面，才能在迁移后作为行为 SSOT 目标，确保功能不退化。

当前存在两个前置阻塞：

1. 内部依赖（`oasis/logging`、`oasis/utils`）和测试框架（PHPUnit 5.x）均不兼容 PHP 8.5，必须先行升级
2. 现有测试覆盖不足，多个核心模块缺少测试，无法为后续迁移提供可靠的行为基线

## Problem

- `oasis/logging` `^1.1` 和 `oasis/utils` `^1.6` 的 PHP 8.5 兼容性未知，可能存在阻塞
- PHPUnit `^5.2` 不支持 PHP 8.x，无法在目标 PHP 版本上运行测试
- 现有测试覆盖存在明显缺口——以下模块缺少或测试不足：
  - `src/Configuration/` — 8 个配置类，无独立测试
  - `src/ErrorHandlers/` — `ExceptionWrapper`、`JsonErrorHandler`、`WrappedExceptionInfo`，无测试
  - `src/Exceptions/` — `UniquenessViolationHttpException`，无测试
  - `src/Middlewares/` — `AbstractMiddleware`、`MiddlewareInterface`，无测试
  - `src/ExtendedArgumentValueResolver.php` — 无测试
  - `src/ExtendedExceptionListnerWrapper.php` — 无测试
  - `src/ChainedParameterBagDataProvider.php` — 无测试
  - `src/Views/` — 9 个视图类，仅 `FallbackViewHandlerTest` 覆盖了 1 个
  - `src/ServiceProviders/Cookie/` — 无测试
  - `src/ServiceProviders/Routing/` — 无独立测试
- 如果不在迁移前补足测试，后续 Phase 的 breaking change 可能引入无法被发现的行为退化

## Goals

### 依赖与测试框架升级

- 将 `oasis/logging` 升级到 PHP 8.5 兼容版本
- 将 `oasis/utils` 升级到 PHP 8.5 兼容版本
- 将 PHPUnit 从 `^5.2` 升级到 `^13.0`
- 适配所有现有测试文件以兼容 PHPUnit 13.x API 变化（`setUp`/`tearDown` 返回类型、assertion 方法签名、mock API、data provider attribute 等）
- 确保升级后所有现有测试在当前 PHP 版本下仍能通过

### 测试覆盖补全

在当前代码（迁移前）基础上，为所有缺少测试的模块补充全面的单元测试和集成测试，建立完整的行为基线。具体覆盖目标：

- `src/Configuration/` — 所有配置类的验证逻辑、默认值、边界条件
- `src/ErrorHandlers/` — 异常包装、JSON 错误处理的各种场景
- `src/Exceptions/` — 自定义异常的构造与属性
- `src/Middlewares/` — 中间件的执行链、前置/后置逻辑
- `src/ExtendedArgumentValueResolver.php` — 参数解析的各种输入场景
- `src/ExtendedExceptionListnerWrapper.php` — 异常监听的触发与处理
- `src/ChainedParameterBagDataProvider.php` — 链式参数包的合并与优先级
- `src/Views/` — 所有视图处理器和渲染器（`JsonViewHandler`、`JsonApiRenderer`、`DefaultHtmlRenderer`、`RouteBasedResponseRendererResolver` 等）
- `src/ServiceProviders/Cookie/` — Cookie 容器与 provider
- `src/ServiceProviders/Routing/` — 路由 provider 的注册与解析
- 现有测试（`SilexKernel`、`Cors`、`Security`、`Twig`、`Aws`）的场景补充——边界条件、异常路径、配置变体

测试补全的原则：

- 测试记录的是当前系统的实际行为，不是期望行为——迁移后这些测试就是行为 SSOT
- 覆盖正常路径、异常路径、边界条件
- 集成测试覆盖模块间的交互（如 Configuration → ServiceProvider → Kernel 的完整链路）
- 测试应在当前 PHP 版本 + 升级后的 PHPUnit 13 下全部通过

## Non-Goals

- 不涉及 Silex 框架替换
- 不涉及 Symfony 组件升级
- 不涉及 PHP 语言层面 breaking changes 修复
- 不修改现有业务逻辑——仅升级依赖和补充测试

## Scope

- `composer.json` — `oasis/logging`、`oasis/utils`、`phpunit/phpunit` 版本约束更新
- `phpunit.xml` — 配置适配
- `ut/` — 现有测试文件的 PHPUnit API 适配 + 新增测试文件

## Risks

- `oasis/logging` 和 `oasis/utils` 为内部包，若无 PHP 8.5 兼容版本则存在外部阻塞
- PHPUnit 5 → 13 跨度大，中间可能需要分步升级（5 → 10 → 13），视实际兼容性决定
- 测试补全工作量可能较大，但这是后续所有 Phase 正确性的基础，不可跳过

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note

## Notes

- 测试补全必须在框架替换（Phase 1）之前完成——替换后旧 API 不再可用，届时无法再针对旧行为编写测试
- 补全的测试在后续 Phase 中可能需要适配新 API，但测试的断言（期望行为）应保持不变，这正是行为基线的意义
