# PHP 8.5 Migration Guide & Check Script (PRP-008)

为下游消费者提供完整的迁移文档和预升级检查脚本，帮助依赖 `oasis/http` 的项目平滑过渡到 v3（PHP 8.5 升级后的新版本）。

---

## Added

### Migration Guide (`docs/manual/migration-v3.md`)

- 单文件迁移指南，按模块分 12 个章节（PHP Version → Dependencies → Kernel API → DI Container → Bootstrap Config → Routing → Security → Middleware → Views → Twig → CORS → Cookie）+ PHP 语言适配 + 附录
- 每个 breaking change 条目标注严重程度（🔴 必须改 / 🟡 建议改 / 🟢 可选），提供 before/after 代码示例和操作指引
- 包含 TOC 导航、快速评估清单、完整 Bootstrap Config Key 参考表、API 变更速查表

### Check Script (`bin/oasis-http-migrate-v3-check`)

- 纯 PHP 预升级检查脚本，通过 `composer.json` `bin` 配置暴露到 `vendor/bin/`
- 基于 `token_get_all()` 的 token 级扫描，检测对已移除/已变更 API 的引用
- 检测范围：Removed API（SilexKernel、Pimple、旧 Twig 类等）、Changed API（Security 接口、Middleware 接口等）、Pimple 访问模式、旧 Symfony 事件类、旧包引用、Guzzle 6.x 模式
- 支持 `--format=text`（默认）和 `--format=json` 输出格式
- 退出码：0（无 🔴 问题）、1（存在 🔴 问题）、2（输入错误）

### 测试

- Document Validation Tests（`ut/MigrationGuideValidationTest.php`）：验证 Migration Guide 的 TOC 锚点完整性、Breaking Change 覆盖完整性、条目格式完整性、Bootstrap Config Key 覆盖完整性
- Check Script PBT（`ut/PBT/MigrateCheckPropertyTest.php`）：Properties 5–11，使用 Eris 验证规则检测完整性、递归扫描完整性、Finding 字段完整性、Severity 分组排序、退出码正确性、JSON 输出有效性、二进制文件容错
- Check Script Unit Tests（`ut/MigrateCheckScriptTest.php`）：覆盖 CLI 交互、错误处理、各类规则检测

## Changed

### `composer.json`

- 新增 `"bin": ["bin/oasis-http-migrate-v3-check"]` 配置

### `phpunit.xml`

- 新增 `migration-guide-validation`、`migrate-check-pbt`、`migrate-check-unit` 三个 test suite
