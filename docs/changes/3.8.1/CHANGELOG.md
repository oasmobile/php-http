# Changelog v3.8.1

升级内部依赖 `oasis/utils` 最低版本要求至 `^3.2`（锁定 v3.2.0）。

---

## Changed

- **composer.json**：`oasis/utils` 版本约束从 `^3.0` 提升至 `^3.2`
- **composer.lock**：`oasis/utils` 锁定版本从 v3.0.2 更新至 v3.2.0

---

## Migration Impact

**向后兼容**：仅提升内部依赖最低版本，无 API 变更。使用者需确保环境中 `oasis/utils` 版本 ≥ 3.2.0。
