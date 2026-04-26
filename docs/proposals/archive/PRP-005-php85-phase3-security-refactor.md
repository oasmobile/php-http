# PHP 8.5 Upgrade — Phase 3: Security Component Refactor

> Proposal：适配 Symfony Security 7.x 的新 authenticator 系统，重写项目中的自定义安全组件。

## Status

`released`

## Background

Symfony Security 组件从 5.x 起经历了重大重构，引入了新的 authenticator 系统，替代了旧的 guard authenticator 和 simple pre-authenticator 模式。项目中的 `SimplePreAuthenticator`、`SimpleSecurityProvider`、`SimpleFirewall` 等自定义安全组件基于 Symfony 4.x Security API，在 7.x 中已不可用。

## Problem

- Symfony Security 4.x → 7.x 的 authenticator 系统完全重写，旧 API 已移除
- 项目中的 `AbstractSimplePreAuthenticator`、`AbstractSimplePreAuthenticateUserProvider`、`AbstractSimplePreAuthenticationPolicy` 等抽象类基于旧 API
- `SimpleSecurityProvider` 中的 firewall 和 access rule 注册机制需要适配新的 Security 架构
- `NullEntryPoint`、`SimpleAccessRule`、`SimpleFirewall` 等实现类需要重写

## Goals

- 将项目中所有自定义安全组件适配到 Symfony Security 7.x 的 authenticator 系统
- 重写 `AbstractSimplePreAuthenticator` 及其子类，使用新的 `AuthenticatorInterface`
- 重写 `AbstractSimplePreAuthenticateUserProvider`，适配新的 `UserProviderInterface`
- 适配 `SimpleSecurityProvider` 的 firewall 和 access rule 注册机制
- 重写 `AuthenticationPolicyInterface`、`FirewallInterface`、`AccessRuleInterface` 等接口定义
- 确保安全功能行为不变（认证、授权、防火墙规则）
- 大量补充 Property-Based Testing（Eris 1.x，Phase 1 已引入），为 access rule 组合、firewall 匹配、认证策略等建立 property 验证

## Non-Goals

- 不引入新的安全功能（如 OAuth、JWT 等）
- 不变更现有的安全策略逻辑
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4）

## Scope

- `src/ServiceProviders/Security/` — 全部安全相关 service provider 和组件
- `src/Configuration/SecurityConfiguration.php` — 安全配置
- `src/Configuration/SimpleAccessRuleConfiguration.php` — 访问规则配置
- `src/Configuration/SimpleFirewallConfiguration.php` — 防火墙配置
- `ut/Security/` — 安全相关测试
- `ut/Helpers/Security/` — 测试辅助安全类

## Risks

- Security 组件的 API 变化是所有 Symfony 组件中最大的，重写工作量不可低估
- 认证和授权逻辑的正确性至关重要，需要充分的测试覆盖
- 新 authenticator 系统的概念模型与旧系统差异较大，可能需要重新设计部分接口

## Branch Strategy

PRP-002 至 PRP-007（Phase 0–5）共享同一个长生命周期 feature branch `feature/php85-upgrade`。

- 各 Phase 在该 branch 上按依赖顺序逐个推进，每个 PRP 独立开 spec
- **branch 级 DoD**：全量 PHPUnit 通过（`phpunit`）+ PRP-007 scope 完成后，才 merge 回 develop
- **spec 级 DoD**：该 spec 的 tasks 全部完成 + 下列预期通过的 suite 实际通过
- 期间需定期将 develop 合入，避免最终 merge 时冲突过大

### Phase 3 完成后的测试预期

Security 组件完整重写，authenticator 系统适配 Symfony 7.x。

**预期通过的 suite（在 Phase 2 基础上新增）：**

- `security` — authenticator 系统重写完成
- `integration` — Security + Twig + 框架完整链路恢复

**预期仍失败的测试：**

- PHP 语言层面 deprecation 导致的零星失败（等 Phase 4）——如隐式 nullable 参数、动态属性等触发的 warning/error

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note

## Notes

- 依赖 Phase 1 完成（Symfony 组件已升级到 7.x）
- Phase 1 中对 Security 组件仅做最小可编译适配，本 Phase 完成完整重写
- Eris 1.x 已在 Phase 1 引入，本 Phase 是 PBT 的主要产出阶段——Security 组件的组合爆炸（access rule × firewall × 认证策略）天然适合 property-based 验证
