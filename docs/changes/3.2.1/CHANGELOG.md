# Changelog — v3.2.1

> Release date: 2026-05-05

---

## v3.2.1

Hotfix：修复 `SimpleSecurityProvider` 的 `AccessDecisionManager` 缺少 `AuthenticatedVoter`，导致 `isGranted('IS_AUTHENTICATED_FULLY')` 在认证成功后仍返回 `false`。同时修复 `MigrationGuideValidationTest` 三个测试逻辑问题。

### Fixed

- ISS-3.2-L01：`AccessDecisionManager` 缺少 `AuthenticatedVoter`，`isGranted('IS_AUTHENTICATED_FULLY')` 在用户已通过 firewall 认证后仍返回 `false`
- `MigrationGuideValidationTest`：修复验证性子节 item 提取逻辑、增强英文短语关键词匹配、允许 🟢 条目仅含 After

### 测试覆盖

- 全量测试：650 tests, 21850 assertions（全部通过）
