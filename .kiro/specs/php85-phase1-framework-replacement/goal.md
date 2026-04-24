# Spec Goal: PHP 8.5 Upgrade — Phase 1: Framework Replacement

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-003-php85-phase1-framework-replacement.md`

## 背景摘要

`oasis/http` 的核心框架 `silex/silex` 自 2018 年归档，不兼容 PHP 8.x。`SilexKernel` 继承 `Silex\Application`，是整个应用的入口和 DI 容器，通过 bootstrap config 数组驱动初始化，config 经 Symfony Config 组件校验后分发给各 Service Provider。Silex 基于 Pimple + Symfony 4 组件，所有 Symfony 组件锁定在 `^4.0` / `~4.2.0`，均已 EOL。

Phase 0（PRP-002）已完成 PHP 版本约束升级到 `>=8.5`、PHPUnit 升级到 13.x、内部依赖升级。但由于 Silex、Symfony 4.x、Twig 1.x 等框架依赖尚未替换，大量依赖框架运行时的测试预期失败。Phase 1 是整个升级中工作量最大、风险最高的阶段，需要移除 Silex 并用 Symfony MicroKernel 替换，同时将全部 Symfony 组件从 4.x 升级到 7.x。

当前系统的请求处理流程为：`SilexKernel::run()` 创建 Request → `handle()` 处理可信代理 → EventDispatcher 按优先级触发 routing / CORS / firewall / middleware → 路由匹配 → 控制器执行 → View Handler 链处理非 Response 返回值 → Error Handler 链处理异常 → after middleware → Response 发送。这套流程需要在新框架下完整保留。

## 目标

- 移除 `silex/silex` 和 `silex/providers` 依赖
- 用 Symfony MicroKernel 替换 `SilexKernel`，重命名为新类名（如 `HttpKernel` / `OasisKernel`），构造函数签名可以调整
- 将全部 Symfony 组件从 `^4.0` 升级到 `^7.2`
- 全面迁移到 Symfony DI Container，移除 Pimple，所有 service provider 重写为 Symfony DI 的注册方式（CompilerPass / Extension / services 配置），不保留任何 Pimple 风格的兼容层
- 迁移路由注册机制到 Symfony Routing 7.x
- 迁移中间件机制（before / after middleware）到 Symfony EventSubscriber
- 迁移 View Handler 链到 Symfony `KernelEvents::VIEW` EventSubscriber，保持链式调用和 content negotiation 的可扩展性
- 迁移所有 service provider 到新框架的等价模式
- 引入 Eris 1.x 作为 PBT 框架，在本 Phase 中为路由解析、middleware 链、请求分发编写 property test，作为框架替换正确性的验证手段
- 确保迁移后现有功能行为不变

## 不做的事情（Non-Goals）

- 不涉及 Twig 本体（`twig/twig`）升级和模板适配（Phase 2 / PRP-004）
- 不涉及 Guzzle 升级（Phase 2 / PRP-004）
- 不涉及 Security 组件的 authenticator 系统重写（Phase 3 / PRP-005）；本 Phase 对 Security 组件仅做最小可编译适配
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 不引入新功能，仅做框架平迁
- 不保留 Pimple 或 Silex 的任何兼容层

## Clarification 记录

### Q1: 替换后的 `SilexKernel` 公共 API 兼容策略

当前 `SilexKernel` 继承 `Silex\Application`，是整个应用的入口和 DI 容器，下游消费者通过 `new SilexKernel($config)` + bootstrap config 数组来初始化。替换后的公共 API 兼容策略是什么？

- 选项: A) 保持类名和构造函数签名不变 / B) 重命名为新类名，构造函数签名可以调整 / C) 保持类名但允许构造函数签名变化 / D) 补充说明
- 回答: B — 重命名为新类名，下游消费者需要适配新类名和新初始化方式

### Q2: DI 容器迁移后 service 的注册和获取方式

当前 Silex 基于 Pimple，service 注册方式是 `$app['service_name'] = function() { ... }` 这种数组式访问。迁移后如何处理？

- 选项: A) 全面迁移到 Symfony DI / B) 引入适配层渐进迁移 / C) 全面迁移但保持 bootstrap config 驱动 / D) 补充说明
- 回答: A — 全面迁移到 Symfony DI，不保留任何 Pimple 风格的兼容层

### Q3: Eris PBT 的引入范围和定位

当前项目测试全部是传统的 example-based test（PHPUnit）。Eris PBT 的引入范围和定位是什么？

- 选项: A) 本 Phase 引入 Eris 并编写核心 PBT / B) 仅引入依赖和基础设施，具体 PBT 留给各 Phase / C) 引入 Eris 并编写全量 PBT / D) 补充说明
- 回答: A — 本 Phase 引入 Eris 并为路由解析、middleware 链、请求分发编写 property test，作为框架替换正确性的验证手段

### Q4: View Handler 链的迁移策略

当前 View Handler 通过 Silex 的 `$app->view()` 注册，底层是 `KernelEvents::VIEW` listener，支持链式调用和 content negotiation。迁移策略是什么？

- 选项: A) 迁移到 Symfony EventSubscriber / B) 迁移到 ArgumentResolver + ValueResolver / C) 简化为单一 ViewListener / D) 补充说明
- 回答: A — 迁移到 Symfony `KernelEvents::VIEW` EventSubscriber，保持链式调用和 content negotiation 的可扩展性

## 约束与决策

- Kernel 类重命名，不保留 `SilexKernel` 类名，下游消费者需要适配
- 全面迁移到 Symfony DI，不保留 Pimple 兼容层，所有 `$app['xxx']` 风格代码需重写
- Eris PBT 在本 Phase 就编写核心 property test（路由解析、middleware 链、请求分发），不推迟到后续 Phase
- View Handler 迁移到 EventSubscriber，保持链式处理和可扩展性
- Security 组件仅做最小可编译适配，authenticator 系统重写留给 Phase 3
- `symfony/twig-bridge` 随 Symfony 组件统一升级到 7.x，但 Twig 本体升级留给 Phase 2
- PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- spec 级 DoD：tasks 全部完成 + PRP-003 中定义的预期通过 suite 实际通过（`cors`、`aws`、`routing`、`cookie`、`middlewares`、`SilexKernelTest`、`SilexKernelWebTest`、`FallbackViewHandlerTest`）
