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
- 引入 Property-Based Testing（Eris 1.x），为路由解析、middleware 链、请求分发等核心行为建立 property 验证，确保框架替换前后行为不变

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

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`phpunit`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 1 完成后的测试预期

Silex 被移除，Symfony 组件升级到 7.x，`SilexKernel` 重写为 Symfony MicroKernel。框架层面的测试应恢复，但 Twig 1.x 和 Security authenticator 尚未适配。

**预期通过的 suite（在 Phase 0 基础上新增）：**

- `cors` — CORS 逻辑迁移到新框架后恢复
- `aws` — 信任代理逻辑迁移后恢复
- `routing` — Symfony Routing 7.x 适配完成
- `cookie` — 保持通过
- `middlewares` — 中间件机制迁移后恢复
- `all` 中的 `SilexKernelTest`、`SilexKernelWebTest`、`FallbackViewHandlerTest` — Kernel 重写后恢复

**预期失败的 suite：**

- `security` — authenticator 系统未重写（等 Phase 3）
- `twig` — Twig 1.x 未升级（等 Phase 2）
- `integration` — 部分集成测试依赖 Security + Twig 完整链路

> 注：Phase 1 对 Security 组件仅做最小可编译适配，`NullEntryPointTest` 等不依赖 authenticator 系统的测试可能通过。

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note
- `docs/state/architecture.md` — 当前架构

## Notes

- 依赖 PRP-001（测试覆盖补全）和 PRP-002（Phase 0 依赖升级）完成
- Symfony Security 组件虽然在此 Phase 升级到 7.x，但 authenticator 系统的重写放在 Phase 3 单独处理；本 Phase 仅做最小可编译的适配
- `symfony/twig-bridge` 版本约束随 Symfony 组件统一升级到 7.x，但 Twig 本体（`twig/twig`）的升级和模板适配放在 Phase 2
- 本 Phase 引入 Eris 1.x 作为 PBT 框架，`composer.json` 的 `require-dev` 中添加 `giorgiosironi/eris`；Phase 3 Security 重写时将大量补充 access rule / firewall 组合的 property test
