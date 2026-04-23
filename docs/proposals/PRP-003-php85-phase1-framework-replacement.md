# PHP 8.5 Upgrade — Phase 1: Framework Replacement

> Proposal：用 Symfony MicroKernel（或等价方案）替换已 abandoned 的 Silex 框架，同时升级全部 Symfony 组件到 7.x。

## Status

`draft`

## Background

项目核心框架 `silex/silex` 自 2018 年归档，不兼容 PHP 8.x。`SilexKernel` 继承 `Silex\Application`，是整个应用的入口和 DI 容器。`silex/providers` 同样已 abandoned。Silex 基于 Pimple + Symfony 4 组件，官方建议迁移到 Symfony Flex。

## Problem

- `silex/silex` `^2.3` 已 abandoned，不会适配 PHP 8.x breaking changes
- `silex/providers` `^2.3` 随 Silex 一同 abandoned
- 当前所有 Symfony 组件锁定在 `^4.0` / `~4.2.0`，Symfony 4.x 已 EOL，不支持 PHP 8.4+
- Silex 的 DI 容器（Pimple）、路由注册、中间件机制、service provider 模式均需替换

## Goals

- 移除 `silex/silex` 和 `silex/providers` 依赖
- 用 Symfony MicroKernel（或等价的轻量方案）替换 `SilexKernel`
- 将全部 Symfony 组件从 `^4.0` 升级到 `^7.2`
- 替换 Pimple DI 容器为 Symfony DI 或等价方案
- 迁移路由注册机制到 Symfony Routing 7.x
- 迁移中间件机制
- 迁移所有 service provider 到新框架的等价模式
- 确保迁移后现有功能行为不变

## Non-Goals

- 不涉及 Twig 和 Guzzle 升级（Phase 2）
- 不涉及 Security 组件的 authenticator 系统重写（Phase 3）
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4）
- 不引入新功能，仅做框架平迁

## Scope

- `src/SilexKernel.php` — 核心入口重写
- `src/ServiceProviders/` — 全部 service provider 迁移
- `src/Middlewares/` — 中间件机制迁移
- `src/Configuration/` — 配置类适配
- `src/Views/` — 视图处理适配
- `composer.json` — 依赖替换与版本升级
- `ut/` — 测试适配

## Risks

- 这是整个升级中工作量最大、风险最高的 Phase
- Pimple → Symfony DI 的迁移可能影响所有 service 注册和获取方式
- 路由注册方式变化可能影响所有 controller 的挂载
- 下游消费者如果依赖 `SilexKernel` 的公共 API，会受到 breaking change 影响

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note
- `docs/state/architecture.md` — 当前架构

## Notes

- 依赖 PRP-001（测试覆盖补全）和 PRP-002（Phase 0 依赖升级）完成
- Symfony Security 组件虽然在此 Phase 升级到 7.x，但 authenticator 系统的重写放在 Phase 3 单独处理；本 Phase 仅做最小可编译的适配
- `symfony/twig-bridge` 版本约束随 Symfony 组件统一升级到 7.x，但 Twig 本体（`twig/twig`）的升级和模板适配放在 Phase 2
