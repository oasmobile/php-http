# Spec Goal: PHP 8.5 Upgrade — Phase 2: Twig & HTTP Client Upgrade

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-004-php85-phase2-twig-guzzle-upgrade.md`

## 背景摘要

Phase 1 已完成 Silex → Symfony MicroKernel 替换和全部 Symfony 组件升级到 7.x。`composer.json` 中 `twig/twig` 的版本约束已在 Phase 1 期间更新为 `^3.0`，`symfony/twig-bridge` 已升级到 `^7.2`，但 Twig 相关的业务代码和测试尚未完成适配验证。`guzzlehttp/guzzle` 仍停留在 `^6.3`，不支持 PHP 8.4+，需升级到 7.x。

当前 Twig 的使用路径：`SimpleTwigServiceProvider` 读取 bootstrap config 的 `twig` key，创建 `Twig\Environment` 实例并注册到 `MicroKernel`；`DefaultHtmlRenderer` 在异常渲染时调用 `$kernel->getTwig()` 获取 Twig 环境渲染错误页模板；`TwigController`（测试用）演示了控制器中获取 Twig 并渲染模板的模式。项目中未使用已 abandoned 的 `twig/extensions`。

Guzzle 的使用集中在 `MicroKernel` 的 AWS IP ranges 获取逻辑中：通过 `new GuzzleHttp\Client()` 发起 HTTP 请求，并使用 `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 辅助函数处理 JSON。这些辅助函数在 Guzzle 7 中已移除，需替换为 PHP 原生 `json_decode()` / `json_encode()`。测试文件 `ElbTrustedProxyTest.php` 中也大量使用了这些辅助函数。

## 目标

- 验证并重构 Twig 相关代码以充分利用 Twig 3.x 特性（`SimpleTwigServiceProvider`、`DefaultHtmlRenderer`、`TwigConfiguration` 及相关测试）
- 将 `guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`
- 替换所有 `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 调用为 PHP 原生 `json_decode()` / `json_encode()` + `JSON_THROW_ON_ERROR`
- 确保升级后现有功能行为不变

## 不做的事情（Non-Goals）

- 不涉及框架替换（Phase 1 已完成）
- 不涉及 Security 组件的 authenticator 系统重写（Phase 3 / PRP-005）
- 不涉及 PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 不引入新的模板功能或 HTTP 客户端功能

## Clarification 记录

### Q1: Twig 3.x 适配范围确认

Phase 1 已将 `twig/twig` 版本约束更新为 `^3.0`，且当前 `SimpleTwigServiceProvider` 已使用 Twig 3.x 的命名空间（`Twig\Environment`、`Twig\Loader\FilesystemLoader`、`Twig\TwigFunction`）。本 Phase 对 Twig 的工作范围是什么？

- 选项: A) 仅验证现有代码在 Twig 3.x 下正常工作，修复发现的兼容性问题 / B) 除验证外，还需重构 Twig 相关代码以更好地利用 Twig 3.x 特性 / C) 补充说明
- 回答: B — 除验证外，还需重构 Twig 相关代码以更好地利用 Twig 3.x 特性

### Q2: `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 替换策略

这些辅助函数在 Guzzle 7 中已移除（Guzzle 6 的 `functions.php` 中定义，Guzzle 7 不再包含）。当前在 `MicroKernel` 和 `ElbTrustedProxyTest` 中共有约 10 处调用。替换策略是什么？

- 选项: A) 直接替换为 PHP 原生 `json_decode()` / `json_encode()`，不保留异常抛出行为 / B) 替换为原生函数 + `JSON_THROW_ON_ERROR` flag，保持解析失败时抛异常的行为 / C) 引入项目内的 JSON 辅助函数封装 / D) 补充说明
- 回答: A+B — 替换为 PHP 原生 `json_decode()` / `json_encode()`，同时加 `JSON_THROW_ON_ERROR` flag 保持解析失败时抛异常的行为

### Q3: Guzzle 7 的 Client 构造函数和异常处理

Guzzle 7 完整实现了 PSR-18（`Psr\Http\Client\ClientInterface`），部分构造函数参数和异常类有变化。当前使用方式较简单（`new Client()` + `request('GET', ...)`）。是否需要迁移到 PSR-18 接口？

- 选项: A) 保持使用 Guzzle Client 具体类，仅适配 API 变化 / B) 迁移到 PSR-18 `ClientInterface`，通过接口编程 / C) 补充说明
- 回答: A — 保持使用 Guzzle Client 具体类及其便捷方法（`$client->request()`），仅适配 API 变化（JSON 辅助函数替换）

## 约束与决策

- PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- 依赖 Phase 1 完成（Symfony 组件已升级到 7.x，`symfony/twig-bridge` 已就位）
- Twig 适配不仅验证兼容性，还需重构以利用 Twig 3.x 特性
- `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 替换为 PHP 原生函数 + `JSON_THROW_ON_ERROR`，保持解析失败时抛异常的行为
- 将 Guzzle 使用保持在具体类 `GuzzleHttp\Client`，不迁移到 PSR-18 接口编程，仅做版本升级和 JSON 函数替换
- spec 级 DoD：tasks 全部完成 + PRP-004 中定义的预期通过 suite 实际通过（在 Phase 1 基础上新增 `twig` suite）
- 预期仍失败的 suite：`security`（authenticator 系统未重写，等 Phase 3）、`integration`（部分集成测试依赖 Security 完整链路）

## Socratic Review

1. **goal 是否完整覆盖了 PRP-004 的 Goals？**
   - PRP-004 提到"移除 `twig/extensions`，替换为 `twig/extra-bundle` 或等价方案"，但代码搜索确认项目中未使用 `twig/extensions`，`composer.json` 中也无此依赖，因此无需处理。其余目标均已覆盖。

2. **Non-Goals 是否与 PRP-004 一致？**
   - 一致。PRP-004 明确排除了 Phase 1（框架替换）和 Phase 3（Security 重构），goal 中已体现。

3. **背景摘要是否准确反映了代码现状？**
   - 是。通过读取 `composer.json`、`SimpleTwigServiceProvider`、`DefaultHtmlRenderer`、`MicroKernel` 和测试文件确认了 Twig 和 Guzzle 的实际使用情况。

4. **Clarification 决策是否已完整体现在目标和约束中？**
   - Q1（Twig 重构）→ 目标中明确"验证并重构"；Q2（JSON 函数替换 + `JSON_THROW_ON_ERROR`）→ 目标和约束中均已体现；Q3（PSR-18 接口编程）→ 目标和约束中均已体现。

5. **约束与决策是否遗漏了关键信息？**
   - 已包含分支策略、Phase 依赖、DoD 定义、预期测试结果，以及三个 Clarification 的决策结论。
