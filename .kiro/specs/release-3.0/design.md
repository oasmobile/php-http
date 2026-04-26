# Release 3.0 Design

> Release 3.0 发布流程设计 — `.kiro/specs/release-3.0/`

---

## Introduction

本文档描述 Release 3.0 的发布流程技术方案。Release 包含 PRP-002 ~ PRP-008（7 个 proposal），涵盖 PHP 8.5 全面升级（框架替换、依赖升级、Security 重构、语言适配、验证稳定化）和面向下游消费者的 Migration Guide & Check Script。这是一个 major version bump（2.5.0 → 3.0），包含大量 breaking changes。

验证策略：全量测试（phpunit 560 tests）+ 静态分析（phpstan level 8）+ Migration Guide 端到端手工验证 + Check Script 端到端手工验证（Goal CR Q1=B）。

CHANGELOG 按变更类型（Added / Changed / Removed）统一组织，不按 feature 拆分（Goal CR Q2=B）。测试覆盖 section 包含最终统计、各 Phase 测试类型/数量和覆盖率百分比（Requirements CR Q1=C）。

归档策略：spec 归档使用 `mv` 整目录移动，不做文件级验证（Requirements CR Q2=C）。Proposal 归档、spec 归档、变更记录归档三类可并行执行（Requirements CR Q3=A）。活跃 spec 归档前先确认其 unreleased 变更记录内容完整（Goal CR Q3=B）。

---

## Release 流程概览

```
release/3.0 分支
  ├── Phase 1: 全量测试确认 + 静态分析
  │     ├── phpunit 全量测试（560 tests, 21182 assertions）
  │     ├── phpstan analyse level 8
  │     └── 确认零 deprecation notice
  ├── Phase 2: 端到端手工验证（Goal CR Q1=B）
  │     ├── Migration Guide 手工验证
  │     └── Check Script 手工验证
  ├── Phase 3: 文档收敛
  │     ├── 活跃 spec 变更记录完整性确认（Goal CR Q3=B）
  │     ├── 生成版本 CHANGELOG.md（Goal CR Q2=B）
  │     ├── 更新全局 CHANGELOG.md
  │     ├── 归档 specs（7 个，mv 整目录移动）
  │     ├── 归档变更记录（2 份）
  │     ├── 更新 proposal 状态 + 归档（7 个）
  │     └── 项目文档一致性确认
  └── Phase 4: Finish（由 gitflow-finisher 执行）
        ├── merge → master
        ├── 打 v3.0.0 tag
        ├── merge → develop
        └── release spec 归档
```

---

## Phase 1: 全量测试确认 + 静态分析

在 release 分支上执行全量测试和静态分析，确认代码质量。

### 全量测试

```bash
php vendor/bin/phpunit
```

预期结果：560 tests, 21182 assertions, 0 failures, 0 errors, 0 deprecation notices。

_Ref: Requirement 1, AC 1/3_

### 静态分析

```bash
php vendor/bin/phpstan analyse
```

预期结果：PHPStan level 8，零错误。

_Ref: Requirement 1, AC 2_


---

## Phase 2: 端到端手工验证（Goal CR Q1=B）

全量测试和静态分析通过后，对 Migration Guide 和 Check Script 做一轮端到端手工验证。这两个产物面向下游消费者，手工验证确保文档可读性和脚本实际可用性。

### Migration Guide 手工验证

验证对象：`docs/manual/migration-v3.md`

验证项：

1. 文件存在且包含全部 12 个模块章节（PHP Version → Dependencies → Kernel API → DI Container → Bootstrap Config → Routing → Security → Middleware → Views → Twig → CORS → Cookie）+ PHP 语言适配 + 附录
2. TOC 中所有锚点链接解析到有效 heading
3. 每个 breaking change 条目包含 severity marker（🔴/🟡/🟢）、before/after 代码块、action 描述
4. Bootstrap Config Key 参考表覆盖 `docs/state/architecture.md` 中定义的所有 key

_Ref: Requirement 2, AC 1–4_

### Check Script 手工验证

验证对象：`bin/oasis-http-migrate-v3-check`

验证项：

1. `--help` 输出 usage 信息
2. 对包含已知 Removed API 引用的测试 PHP 文件，正确检测到 finding
3. 对包含 Pimple 访问模式（`$app['...']`）的测试 PHP 文件，正确检测到 finding
4. `--format=json` 输出有效 JSON
5. 存在 🔴 finding 时 exit code 为 1，无 🔴 finding 时 exit code 为 0

_Ref: Requirement 3, AC 1–5_


---

## Phase 3: 文档收敛

所有验证通过后，进入文档收敛阶段。本阶段包含 7 个操作，其中 spec 归档、变更记录归档、proposal 归档三类可并行执行（Requirements CR Q3=A）。

### 3.1 活跃 Spec 变更记录完整性确认（Goal CR Q3=B）

归档前，确认 `.kiro/specs/php85-migration-guide/` 对应的 unreleased 变更记录（`docs/changes/unreleased/php85-migration-guide.md`）准确反映 PRP-008 的全部交付物：

- Migration Guide 文档（`docs/manual/migration-v3.md`）
- Check Script（`bin/oasis-http-migrate-v3-check`）
- 三层测试（Document Validation Tests + PBT Properties 5–11 + Unit Tests）
- `composer.json` bin 配置
- `phpunit.xml` suite 注册（`migration-guide-validation`、`migrate-check-pbt`、`migrate-check-unit`）

如变更记录不完整，须在归档前补充。

_Ref: Requirement 6, AC 1–2_

### 3.2 版本 CHANGELOG 生成（Goal CR Q2=B, Requirements CR Q1=C）

创建 `docs/changes/3.0/CHANGELOG.md`，将两份 unreleased 变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`）合并重组为统一格式。

**组织方式**：按变更类型（Added / Changed / Removed）统一组织，不按 feature 拆分。原因是 7 个 proposal 的变更高度交织（如 `oasis/utils` 在 Phase 0 和 Phase 5 分别升级），按类型组织更便于下游消费者快速定位影响。

**CHANGELOG 结构**：

```markdown
# Changelog — v3.0

> Release date: <release-date>

## Summary

<一段话概述本次 release：PHP 8.5 全面升级，major version bump>

## Added

<合并两份变更记录中的 Added 条目>
- Eris PBT 框架
- PHPStan 静态分析
- Migration Guide
- Check Script
- PBT 测试套件
- ...

## Changed

<合并两份变更记录中的 Changed 条目>
- 框架替换（Silex → Symfony MicroKernel）
- 依赖升级（Symfony 7.x, Twig 3.x, Guzzle 7.x, ...）
- Security 组件重构
- PHP 语言适配
- ...

## Removed

<合并两份变更记录中的 Removed 条目>
- silex/silex
- silex/providers
- twig/extensions

## Resolved Notes

<列出升级过程中解决的 notes>
- `docs/notes/php85-upgrade.md`：升级调研中识别的所有问题已在 Phase 0–5 中解决
- `docs/notes/php85-phase0-framework-dependent-failures.md`：Phase 0 记录的所有预期失败已在后续 Phase 中修复

## 测试覆盖

### 最终统计

- 全量测试：560 tests, 21182 assertions（全部通过）
- PHPStan level 8：零错误
- 零 deprecation notice

### 各 Phase 测试类型与数量

| Phase | 测试类型 | 数量 |
|-------|---------|------|
| Phase 0（PRP-002） | PHPUnit API 适配 | 现有测试全部适配（333 tests → PHPUnit 13.x API） |
| Phase 1（PRP-003） | 单元测试 + PBT | 单元测试适配 + PBT 3 文件（CP1–CP5，路由/中间件/请求分发） |
| Phase 2（PRP-004） | 单元测试 | TwigConfiguration 5 tests + TwigServiceProvider 5 tests（PBT 不适用） |
| Phase 3（PRP-005） | 单元测试 + PBT | PBT 4 文件（Properties 1–16，认证/配置/Firewall/RoleHierarchy）+ 单元测试适配 |
| Phase 4（PRP-006） | PBT + 现有测试适配 | PBT 3 文件（Properties 1–8，异常序列化/ViewHandler/构造器等价性）+ 全量回归 |
| Phase 5（PRP-007） | 全量验证 + PHPStan | 560 tests, 21182 assertions |
| PRP-008 | Document Validation + PBT + Unit Tests | Properties 1–11 + Unit Tests |

### 覆盖率

运行 `php vendor/bin/phpunit --coverage-text` 获取覆盖率百分比。如因缺少 Xdebug/PCOV 扩展无法运行，标注"覆盖率工具不可用"并说明原因，不阻塞 release 流程。
```

_Ref: Requirement 4, AC 1–5_

### 3.3 全局 CHANGELOG 更新

在 `docs/changes/CHANGELOG.md` 顶部追加 v3.0 摘要条目，遵循现有格式：

```markdown
## v3.0 - <release-date>

PHP 8.5 全面升级（PRP-002 ~ PRP-008）：框架替换、依赖升级、Security 重构、语言适配、Migration Guide & Check Script。详见 [3.0/CHANGELOG.md](3.0/CHANGELOG.md)。
```

_Ref: Requirement 5, AC 1–2_

### 3.4 Spec 归档

将所有 7 个 spec 归档到 `docs/changes/3.0/specs/`，使用 `mv` 整目录移动（Requirements CR Q2=C）。

**unreleased specs（6 个）**：

| 源路径 | 目标路径 |
|--------|---------|
| `docs/changes/unreleased/specs/php85-phase0-prerequisites/` | `docs/changes/3.0/specs/php85-phase0-prerequisites/` |
| `docs/changes/unreleased/specs/php85-phase1-framework-replacement/` | `docs/changes/3.0/specs/php85-phase1-framework-replacement/` |
| `docs/changes/unreleased/specs/php85-phase2-twig-guzzle-upgrade/` | `docs/changes/3.0/specs/php85-phase2-twig-guzzle-upgrade/` |
| `docs/changes/unreleased/specs/php85-phase3-security-refactor/` | `docs/changes/3.0/specs/php85-phase3-security-refactor/` |
| `docs/changes/unreleased/specs/php85-phase4-language-adaptation/` | `docs/changes/3.0/specs/php85-phase4-language-adaptation/` |
| `docs/changes/unreleased/specs/php85-phase5-validation-stabilization/` | `docs/changes/3.0/specs/php85-phase5-validation-stabilization/` |

**活跃 spec（1 个）**：

| 源路径 | 目标路径 |
|--------|---------|
| `.kiro/specs/php85-migration-guide/` | `docs/changes/3.0/specs/php85-migration-guide/` |

归档后验证：
- `.kiro/specs/php85-migration-guide/` 已移除
- `docs/changes/unreleased/specs/` 下无剩余 spec 目录

_Ref: Requirement 7, AC 1–5_

### 3.5 变更记录归档与清理

将两份 unreleased 变更记录移入 `docs/changes/3.0/`：

| 源路径 | 目标路径 |
|--------|---------|
| `docs/changes/unreleased/php85-upgrade.md` | `docs/changes/3.0/php85-upgrade.md` |
| `docs/changes/unreleased/php85-migration-guide.md` | `docs/changes/3.0/php85-migration-guide.md` |

归档后验证：`docs/changes/unreleased/` 下无本次 release 的剩余变更记录。

_Ref: Requirement 8, AC 1–3_

### 3.6 Proposal 状态更新与归档

更新 PRP-002 ~ PRP-008（7 个 proposal）状态从 `implemented` → `released`，然后移入 `docs/proposals/archive/`。

| 文件 | 操作 |
|------|------|
| `docs/proposals/PRP-002-php85-phase0-prerequisites.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-003-php85-phase1-framework-replacement.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-004-php85-phase2-twig-guzzle-upgrade.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-005-php85-phase3-security-refactor.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-006-php85-phase4-language-adaptation.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-007-php85-phase5-validation-stabilization.md` | status → `released`，mv → `archive/` |
| `docs/proposals/PRP-008-php85-migration-guide.md` | status → `released`，mv → `archive/` |

归档后验证：`docs/proposals/` 下无 PRP-002 ~ PRP-008 文件。保留原文件名。

此操作与 spec 归档、变更记录归档无顺序依赖，可并行执行（Requirements CR Q3=A）。

_Ref: Requirement 9, AC 1–4_

### 3.7 项目文档一致性确认

确认以下文档与 v3.0 版本一致（Phase 5 已更新，release 阶段做最终确认）：

| 文档 | 确认内容 |
|------|---------|
| `PROJECT.md` | 反映 v3.0 技术栈（PHP >=8.5, Symfony 7.x, Twig 3.x, Guzzle 7.x, PHPStan level 8） |
| `README.md` | 反映 v3.0 版本要求 |
| `docs/state/` | 反映当前架构（Symfony MicroKernel, Symfony DI, Symfony Security 7.x authenticator system） |
| `docs/manual/` | 与 v3.0 代码库一致 |

_Ref: Requirement 10, AC 1–4_


---

## Phase 4: Finish（由 gitflow-finisher 执行）

Finish 阶段由 gitflow-finisher 执行，不在本 design 范围内。包括：

- merge `release/3.0` → `master`
- 在 `master` 上打 `v3.0.0` tag
- merge `release/3.0` → `develop`
- release spec（`.kiro/specs/release-3.0/`）归档到 `docs/changes/3.0/specs/release-3.0/`

---

## Stabilize 策略

Release 2.5.0 采用了简化 stabilize（alpha tag + 手工测试 suite 完整性）。Release 3.0 的情况不同：

- 7 个 proposal 的代码变更已在各自 feature 分支上经过完整的自动化测试和 code review
- Phase 5（PRP-007）已完成全量验证（560 tests + PHPStan level 8 + 零 deprecation）
- PRP-008 的 Migration Guide 和 Check Script 已有三层测试覆盖（Document Validation + PBT + Unit Tests）

**决策**：不打 alpha/beta tag。Phase 1（全量测试 + 静态分析）和 Phase 2（端到端手工验证）已提供充分的 release 级验证。如果 Phase 1 或 Phase 2 发现问题，应回到 develop 修复后重新合并到 release 分支，而非在 release 分支上修复。

---

## Issue 修复方案

`issues/` 目录下无已知 issue，requirements 发布判定中已确认无 P0/P1 open issue。不涉及 issue 修复。

如 Phase 1 或 Phase 2 中发现新问题，应记录到 `issues/` 并回到 develop 修复，不在 release 分支上直接修复。

---

## 归档后目录结构

```
docs/changes/3.0/
├── CHANGELOG.md
├── php85-upgrade.md
├── php85-migration-guide.md
└── specs/
    ├── php85-phase0-prerequisites/
    ├── php85-phase1-framework-replacement/
    ├── php85-phase2-twig-guzzle-upgrade/
    ├── php85-phase3-security-refactor/
    ├── php85-phase4-language-adaptation/
    ├── php85-phase5-validation-stabilization/
    └── php85-migration-guide/

docs/proposals/archive/
├── PRP-002-php85-phase0-prerequisites.md
├── PRP-003-php85-phase1-framework-replacement.md
├── PRP-004-php85-phase2-twig-guzzle-upgrade.md
├── PRP-005-php85-phase3-security-refactor.md
├── PRP-006-php85-phase4-language-adaptation.md
├── PRP-007-php85-phase5-validation-stabilization.md
└── PRP-008-php85-migration-guide.md
```

---

## Impact Analysis

### 受影响的文件

| 文件 / 目录 | 变更类型 | 说明 |
|-------------|---------|------|
| `docs/changes/3.0/CHANGELOG.md` | 新增 | Release 变更汇总 |
| `docs/changes/3.0/php85-upgrade.md` | 移动 | 从 `unreleased/` 移入 |
| `docs/changes/3.0/php85-migration-guide.md` | 移动 | 从 `unreleased/` 移入 |
| `docs/changes/3.0/specs/`（7 个 spec 目录） | 移动 | 6 个从 `unreleased/specs/` 移入，1 个从 `.kiro/specs/` 归档 |
| `docs/changes/CHANGELOG.md` | 修改 | 追加 v3.0 摘要条目 |
| `docs/changes/unreleased/php85-upgrade.md` | 删除 | 归档后移除 |
| `docs/changes/unreleased/php85-migration-guide.md` | 删除 | 归档后移除 |
| `docs/changes/unreleased/specs/`（6 个 spec 目录） | 删除 | 归档后移除 |
| `.kiro/specs/php85-migration-guide/` | 删除 | 归档后移除 |
| `docs/proposals/PRP-002 ~ PRP-008`（7 个文件） | 修改 + 移动 | status → `released`，移入 `archive/` |

### State 文档影响

不涉及。Phase 5（PRP-007）已全面更新 `docs/state/`，release 阶段仅做一致性确认，不修改。

### 配置项变更

不涉及。本次 release 的所有配置变更（`composer.json`、`phpunit.xml`、`phpstan.neon` 等）已在各 feature 分支完成。

### 外部系统交互

不涉及。本次 release 不改变与外部系统的交互方式。版本号由 git tag 管理，Packagist 根据 tag 自动识别。

### 风险点

- **覆盖率工具运行**：CHANGELOG 测试覆盖 section 需要覆盖率百分比（Requirements CR Q1=C），需确保 `phpunit --coverage-text` 可正常运行（依赖 Xdebug 或 PCOV 扩展）
- **unreleased 目录清理**：归档后需确认 `docs/changes/unreleased/` 和 `docs/changes/unreleased/specs/` 已清空本次 release 的内容
- **Proposal 归档范围**：仅归档 PRP-002 ~ PRP-008，PRP-001 已在 release 2.5.0 中处理（当前状态为 `released`，已在 `archive/` 中）


---

## Testing Strategy

本 release spec 不涉及代码开发，测试策略聚焦于 release 级验证。

### 自动化测试

- **全量测试**：`php vendor/bin/phpunit`，预期 560 tests, 21182 assertions, 0 failures, 0 errors
- **静态分析**：`php vendor/bin/phpstan analyse`，预期 level 8 零错误
- **覆盖率**：`php vendor/bin/phpunit --coverage-text`，获取覆盖率百分比用于 CHANGELOG

### 手工验证

- **Migration Guide 端到端验证**：验证文档结构完整性、TOC 锚点有效性、breaking change 条目格式、Bootstrap Config Key 覆盖
- **Check Script 端到端验证**：验证 CLI 交互（`--help`、错误处理）、规则检测正确性（Removed API、Pimple 模式）、输出格式（text/JSON）、退出码正确性

### 归档验证

- 归档后目录结构符合预期
- 源目录已清空
- 全量测试确认归档操作未影响测试结果

**PBT 不适用说明**：本 release spec 的操作为文件移动、文档生成和状态确认，不涉及纯函数或算法逻辑，不适用 property-based testing。

---

## Socratic Review

**Q: Design 是否完整覆盖了 requirements 中的所有 10 条 requirement？**
A: 完整覆盖。Requirement 1（全量测试）→ Phase 1；Requirement 2（Migration Guide 手工验证）→ Phase 2 Migration Guide 验证；Requirement 3（Check Script 手工验证）→ Phase 2 Check Script 验证；Requirement 4（版本 CHANGELOG）→ Phase 3.2；Requirement 5（全局 CHANGELOG）→ Phase 3.3；Requirement 6（活跃 spec 变更记录完整性）→ Phase 3.1；Requirement 7（spec 归档）→ Phase 3.4；Requirement 8（变更记录归档）→ Phase 3.5；Requirement 9（proposal 归档）→ Phase 3.6；Requirement 10（项目文档一致性）→ Phase 3.7。

**Q: 不打 alpha/beta tag 是否合理？**
A: 合理。Release 2.5.0 打 alpha tag 是因为仅包含测试补全，需要手工确认 suite 完整性。Release 3.0 的代码变更已在各 Phase 经过完整验证（包括 PBT），Phase 1 的全量测试 + 静态分析和 Phase 2 的端到端手工验证已提供充分保障。alpha/beta tag 在此场景下不增加额外价值。

**Q: CHANGELOG 按变更类型组织是否会丢失 Phase 上下文？**
A: 不会。两份原始变更记录（`php85-upgrade.md` 和 `php85-migration-guide.md`）归档到 `docs/changes/3.0/` 后仍可查阅，保留了完整的 Phase 上下文。CHANGELOG 作为面向消费者的文档，按类型组织更实用。

**Q: 三类归档并行执行是否有风险？**
A: 无风险。spec 归档（文件移动）、变更记录归档（文件移动）、proposal 归档（状态更新 + 文件移动）操作的目标文件完全不重叠，不存在竞争条件。Requirements CR Q3=A 已明确此决策。

**Q: 覆盖率工具运行失败怎么办？**
A: 如果 `phpunit --coverage-text` 因缺少 Xdebug/PCOV 扩展而无法运行，CHANGELOG 中的覆盖率 section 应标注"覆盖率工具不可用"并说明原因，不阻塞 release 流程。覆盖率百分比是 CHANGELOG 的补充信息，不是 release 的 blocking 条件。

**Q: Release spec 本身的归档如何处理？**
A: 与 release 2.5.0 一致，release spec（`.kiro/specs/release-3.0/`）的归档由 gitflow-finisher 在 finish 阶段执行，不在本 design 范围内。

**Q: Requirements CR 和 Goal CR 的决策是否已完整体现？**
A: 已完整体现。Requirements CR Q1=C（CHANGELOG 测试覆盖含覆盖率百分比）→ Phase 3.2 CHANGELOG 结构中的测试覆盖 section；Requirements CR Q2=C（mv 整目录移动不做文件级验证）→ Phase 3.4 归档策略；Requirements CR Q3=A（三类归档可并行）→ Phase 3.6 明确标注。Goal CR Q1=B（端到端手工验证）→ Phase 2；Goal CR Q2=B（按变更类型组织 CHANGELOG）→ Phase 3.2；Goal CR Q3=B（归档前确认变更记录完整）→ Phase 3.1。


---

## Gatekeep Log

**校验时间**: 2026-04-26
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] CHANGELOG 模板中"各 Phase 测试类型与数量"表格的 4 处 `<从 spec 提取>` 占位符，替换为从各 Phase spec 提取的具体测试数据（Phase 0: 333 tests API 适配；Phase 1: PBT 3 文件 CP1–CP5；Phase 2: TwigConfiguration 5 tests + TwigServiceProvider 5 tests；Phase 3: PBT 4 文件 Properties 1–16；Phase 4: PBT 3 文件 Properties 1–8）
- [格式] CHANGELOG 模板中"覆盖率"section 的 `<运行覆盖率工具后填入实际百分比>` 占位符及嵌套的 ` ```bash ` 代码块，替换为具体指引文本（含覆盖率工具不可用时的 fallback 说明），同时修复了 ` ```bash ` 嵌套在 ` ```markdown ` 内导致的渲染异常

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符（`<release-date>` 为运行时填入值，与 release 2.5.0 design 一致，可保留）
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（Requirement 编号、AC 编号、CR 决策编号、spec 路径均正确）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题为 `# Release 3.0 Design`，明确文件定位
- [x] 技术摘要汇总存在（Introduction + Release 流程概览）
- [x] Issue 修复方案 section 存在（标注不涉及，与 requirements 发布判定一致）
- [x] 测试策略存在（自动化测试 + 手工验证 + 归档验证 + PBT 不适用说明）
- [x] 收敛计划存在（Phase 3 包含 7 个操作：活跃 spec 确认、CHANGELOG 生成、全局 CHANGELOG 更新、spec 归档、变更记录归档、proposal 归档、文档一致性确认）
- [x] Stabilize 策略存在且论证充分（不打 alpha/beta tag，与 release 2.5.0 的简化 stabilize 做了对比说明）
- [x] 各 section 之间使用 `---` 分隔
- [x] Requirements 全覆盖：10 条 Requirement 均有对应设计方案（R1→Phase 1, R2→Phase 2 Migration Guide, R3→Phase 2 Check Script, R4→Phase 3.2, R5→Phase 3.3, R6→Phase 3.1, R7→Phase 3.4, R8→Phase 3.5, R9→Phase 3.6, R10→Phase 3.7）
- [x] Ref 引用全部正确（逐条核对 Requirement 编号和 AC 编号）
- [x] design 中的方案不超出 requirements 的范围
- [x] Impact Analysis 覆盖 5 个维度：受影响文件、State 文档、配置项变更、外部系统交互、风险点
- [x] 技术选型有明确理由（不打 alpha/beta tag 基于各 Phase 已完成完整验证）
- [x] 无过度设计
- [x] 归档后目录结构明确，文件清单完整
- [x] Socratic Review 存在且覆盖充分（7 个问题：requirements 覆盖、stabilize 合理性、CHANGELOG 组织、并行归档风险、覆盖率工具失败、release spec 归档、CR 决策体现）
- [x] Goal CR 三项决策均已体现：Q1=B（端到端手工验证）→ Phase 2；Q2=B（按变更类型组织 CHANGELOG）→ Phase 3.2；Q3=B（归档前确认变更记录完整）→ Phase 3.1
- [x] Requirements CR 三项决策均已体现：Q1=C（CHANGELOG 测试覆盖含覆盖率百分比）→ Phase 3.2 测试覆盖 section；Q2=C（mv 整目录不做文件级验证）→ Phase 3.4；Q3=A（三类归档可并行）→ Phase 3.6
- [x] 可 task 化：Phase 1–3 的步骤清晰可执行，Phase 3 内部 7 个操作可独立拆分为 task，并行关系已标注

### Clarification Round

**状态**: 已回答

**Q1:** Phase 3 文档收敛中的 7 个操作，在拆分为 tasks 时如何组织？
- A) 每个操作一个独立 top-level task（7 个 task），最大化并行度和独立验证能力
- B) 按依赖关系分组：活跃 spec 确认（前置）→ CHANGELOG 生成 + 全局 CHANGELOG 更新（一组）→ 三类归档并行（一组）→ 文档一致性确认（收尾），共 4 个 top-level task
- C) 合并为一个"文档收敛" top-level task，内部按 sub-task 拆分（与 release 2.5.0 不同，2.5.0 按操作类型拆分为 4 个独立 task）
- D) 其他（请说明）

**A:** C — 合并为一个"文档收敛" top-level task，内部按 sub-task 拆分

**Q2:** Phase 1（全量测试 + 静态分析）和 Phase 2（端到端手工验证）是合并为一个 top-level task 还是分开？
- A) 合并为一个 task：全量测试 + 静态分析 + 手工验证在同一个 task 中完成（类似 release 2.5.0 的 Task 1 合并了全量测试和手工测试）
- B) 分开为两个 task：Task 1 为自动化验证（phpunit + phpstan），Task 2 为手工验证（Migration Guide + Check Script），手工验证依赖自动化验证通过
- C) 分开为三个 task：phpunit、phpstan、手工验证各自独立
- D) 其他（请说明）

**A:** B — 分开为两个 task：自动化验证 + 手工验证

**Q3:** 归档操作完成后，是否需要一个独立的"归档结果验证"task（类似 release 2.5.0 的 Task 6: Final checkpoint），还是将验证合并到各归档 task 的 checkpoint 中？
- A) 独立的 Final checkpoint task：归档全部完成后，统一验证目录结构 + 运行全量测试确认归档未影响测试结果
- B) 不需要独立 task：各归档 task 的 checkpoint 已包含验证，最后一个 task 的 checkpoint 运行全量测试即可
- C) 独立 task，但仅验证目录结构，不重复运行全量测试（Phase 1 已确认通过，归档操作不涉及代码变更）
- D) 其他（请说明）

**A:** A — 独立的 Final checkpoint task：统一验证目录结构 + 全量测试
