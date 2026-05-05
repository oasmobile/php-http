# Changelog — v3.3.1

> Release date: 2025-07-18

---

## v3.3.1

Hotfix：修正 migration guide 中 `AccessRuleInterface::getRequiredChannel()` channel enforcement 的描述，反映 v3.3 已恢复该能力（此前文档错误地标注为"不再生效"）。

### Documentation

- `docs/manual/migration-v3.md`：将 channel enforcement entry 从 🔴（不再生效）修正为 🟢（行为恢复），补充 `**After**` 代码示例
- `docs/changes/3.3/audit/security-audit-matrix.md`：同步更新 channel enforcement 审计结论

### 测试覆盖

- 全量测试：686 tests, 21537 assertions（全部通过）
