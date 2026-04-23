# PHP 8.5 Upgrade — Phase 2: Twig & HTTP Client Upgrade

> Proposal：将 Twig 从 1.x 升级到 3.x，Guzzle 从 6.x 升级到 7.x，移除已 abandoned 的 `twig/extensions`。

## Status

`draft`

## Background

`twig/twig` `^1.24` 不兼容 PHP 8.x，Twig 2.x 已 EOL，需直接升级到 3.x。`twig/extensions` `^1.3` 已 abandoned，功能已合并到 Twig 3.x 核心或独立的 `twig/extra-bundle`、`twig/intl-extra` 等包。`guzzlehttp/guzzle` `^6.3` 部分兼容 PHP 8.x 但不支持 8.4+，需升级到 7.x。

## Problem

- Twig 1.x 不兼容 PHP 8.x，且 1 → 3 存在大量 breaking changes（模板语法、扩展 API）
- `twig/extensions` 已 abandoned，无法继续使用
- `symfony/twig-bridge` `^4.0` 需随 Symfony 组件一同升级（Phase 1 已处理版本约束，本 Phase 处理 Twig 侧适配）
- Guzzle 6.x 不支持 PHP 8.4+，且 Guzzle 7 基于 PSR-18，API 有变化

## Goals

- 将 `twig/twig` 从 `^1.24` 升级到 `^3.0`
- 移除 `twig/extensions`，替换为 `twig/extra-bundle` 或等价方案
- 适配所有 Twig 模板文件的语法变化
- 适配所有自定义 Twig 扩展的 API 变化
- 将 `guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`
- 适配 Guzzle 7 的 API 变化（PSR-18 兼容）
- 确保升级后现有功能行为不变

## Non-Goals

- 不涉及框架替换（Phase 1）
- 不涉及 Security 组件重构（Phase 3）
- 不引入新的模板功能或 HTTP 客户端功能

## Scope

- `composer.json` — `twig/twig`、`twig/extensions`、`guzzlehttp/guzzle` 版本更新
- `src/ServiceProviders/Twig/` — Twig service provider 适配
- `src/Views/` — 视图渲染相关适配
- `src/Configuration/TwigConfiguration.php` — Twig 配置适配
- 项目中所有 Twig 模板文件（如有）
- 使用 Guzzle 的代码路径
- `ut/Twig/` — Twig 相关测试适配

## Risks

- Twig 1 → 3 的模板语法变化可能影响所有模板文件，需逐一排查
- 自定义 Twig 扩展的 API 在 3.x 中有较大变化（`Twig_Extension` → `AbstractExtension` 等）
- Guzzle 7 的 API 变化相对较小，风险可控

## References

- `docs/notes/php85-upgrade.md` — 升级调研 note

## Notes

- 依赖 Phase 1 完成（Symfony 组件已升级到 7.x，`symfony/twig-bridge` 已就位）
- Guzzle 6 → 7 的 API 变化较小，主要是构造函数参数和异常处理方式的调整
