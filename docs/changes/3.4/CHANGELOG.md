# Changelog v3.4

恢复 SilexKernel 时代通过 Trait 暴露的便捷方法。

---

## Added

- `MicroKernel::render(string $view, array $parameters = [], ?Response $response = null): Response` — 渲染 Twig 模板并返回 Response，支持 `StreamedResponse` 流式输出
- `MicroKernel::renderView(string $view, array $parameters = []): string` — 渲染 Twig 模板并返回字符串
- `MicroKernel::path(string $route, array $parameters = []): string` — 生成相对 URL（`ABSOLUTE_PATH`）
- `MicroKernel::url(string $route, array $parameters = []): string` — 生成绝对 URL（`ABSOLUTE_URL`）

## Context

v3.0 迁移时，Silex `TwigTrait`（`render` / `renderView`）和 `UrlGeneratorTrait`（`path` / `url`）因属于 Silex Application Trait 而未在 MicroKernel 上重新实现。v3.3 行为审计将其归类为 "Silex 框架层能力，不在审计范围" 或 "intentionally-removed"。

经重新审计（`docs/changes/3.4/audit/silex-kernel-api-surface-audit.md`），确认这 4 个方法属于 SilexKernel 主动 `use` 的 Trait 公共 API，是核心 API surface 的一部分，应予保留。
