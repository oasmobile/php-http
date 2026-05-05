# Changelog v3.6.3

MicroKernel 内部重构：拆分为 `Kernel/` 子 namespace traits，提升可维护性；提取 CloudFront IP 解析为独立类；重组 phpunit suites。

---

## Changed

- `MicroKernel` 拆分为 7 个 trait（`BootstrapTrait`、`CloudfrontTrustedProxyResolver`、`ConvenienceTrait`、`ErrorHandlerTrait`、`MiddlewareTrait`、`RoutingTrait`、`ServicesTrait`），公共 API 不变
- CloudFront 可信代理 IP 解析逻辑提取为独立类 `Kernel\CloudfrontTrustedProxyResolver`
- `phpunit.xml` suite 重组，新增 `kernel-lifecycle` suite

## Added

- `tests/CoverageGapSupplementaryTest.php` — 补充覆盖率测试
- `tests/Helpers/KernelLifecycleTestTrait.php` — Kernel 生命周期测试辅助 trait

## Internal

- `docs/notes/silex-layer2-convenience-methods-analysis.md` 归档至 `docs/notes/archive/`

---

## Migration Impact

本版本为纯内部重构，公共 API 无变更，下游代码无需修改。
