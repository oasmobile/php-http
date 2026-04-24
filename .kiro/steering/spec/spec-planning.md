---
inclusion: auto
description: 当开启一个新的 spec、生成 requirements、生成 design、生成 tasks 时必须读取
---

# Spec Planning 指引

---

## 通用

- 文档以中文行文，英文术语可直接使用
- 每份文档末尾包含 Socratic Review（自问自答式审查），记录审查 log
- **分段写入**：生成 requirements、design 或 tasks 文件时，如果预计内容较大（超过约 50 行），不应尝试一次性写入，而应先用 `fsWrite` 写入第一段，再用 `fsAppend` 逐段追加后续内容，避免单次写入过大导致截断或丢失

---

## Requirements

- 如果 goal.md 中存在 Clarification Round 且用户已回答，生成前先读取，确保 requirements 体现用户在 goal CR 中做出的决策
- 完成后做 Socratic Review 并记录 log
- 生成完成后提醒用户：可以运行 gatekeeper（`GK`）对 requirements 进行校验

---

## Design

- 如果 requirements.md Gatekeep Log 中存在 Clarification Round 且用户已回答，生成前先读取，确保 design 体现用户在 requirements CR 中做出的决策
- 必须覆盖 requirements 中的所有 Requirement 和 AC
- **架构参考**：如果 `graphify-out/GRAPH_REPORT.md` 存在，在编写技术方案和 Impact Analysis 前先读取，利用其中的模块依赖关系、god node、community 结构来辅助识别受影响范围和设计决策
- 完成后做 Socratic Review 并记录 log
- 生成完成后提醒用户：可以运行 gatekeeper（`GK`）对 design 进行校验

---

## Tasks

- 如果 design.md Gatekeep Log 中存在 Clarification Round 且用户已回答，生成前先读取，确保 tasks 编排体现用户在 design CR 中做出的决策
- **架构参考**：如果 `graphify-out/GRAPH_REPORT.md` 存在，在编排 task 依赖顺序前先读取，利用其中的模块依赖关系来确定 task 间的前后依赖和可并行性
- 所有 tasks 都是 mandatory，不应该存在 optional
- **Test First（RED → GREEN）**：先编排写测试的 task（RED），再编排实现的 task（GREEN）
- **Checkpoint 编排**：checkpoint 不单独作为 top-level task，而是作为每个 top-level task 的最后一个 sub-task（如 `N.x Checkpoint: 验证描述，commit`），checkpoint 须同时包含验证步骤和 commit 动作
- 完成后做 Socratic Review 并记录 log
- 生成完成后提醒用户：可以运行 gatekeeper（`GK`）对 tasks 进行校验
- 必须考虑是否需要手工测试
- Feature / Hotfix top-level task 结构：

| 序号 | 类型 |
|------|------|
| 1 ~ N | 实现 task（含 test-first 编排） |
| N+1 | 手工测试 task |
| 最后 | Code Review task |

- **Release top-level task 额外规则**：手工测试类 top-level task 的第一个 sub-task 为 "Increment alpha tag"（查询已有 alpha tag，取最大序号 +1，打新 tag）
- **Notes section**：`## Tasks` 之后须包含 `## Notes` section，至少明确提到执行时须遵循 `spec-execution.md`；如有当前 spec 特有的执行要点（特殊构建命令、环境前置条件、数据兼容注意事项等），一并列出
