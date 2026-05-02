# Changelog — v3.1

> Release date: 2026-05-03

## Summary

Symfony 全系依赖从 `^7.2` 升级到 `^8.0`，适配 Symfony 8.0 的 breaking changes。

## Changed

- `composer.json` 所有 Symfony 组件版本约束：`^7.2` → `^8.0`（含 require 和 require-dev）
- `MicroKernel::isGranted()` 签名适配 Symfony 8.0 `AuthorizationCheckerInterface`：新增第三参数 `?AccessDecision $accessDecision = null`
- `docs/state/architecture.md` 移除 Symfony 版本号硬编码引用

## Removed

- 测试 user 类中的 `eraseCredentials()` 方法（Symfony 8.0 从 `UserInterface` 中移除了该方法）

## 测试覆盖

- PHPStan level 8：零错误
- 全量测试：593 tests, 21093 assertions（全部通过）
