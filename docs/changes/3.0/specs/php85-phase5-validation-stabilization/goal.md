# Spec Goal: PHP 8.5 Upgrade — Phase 5: Validation & Stabilization

## 来源

- 分支: `feature/php85-upgrade`
- 需求文档: `docs/proposals/PRP-007-php85-phase5-validation-stabilization.md`

## 背景摘要

项目从 PHP 7.0 升级到 8.5，跨越 5 个大版本。Phase 0 完成了内部依赖升级（`oasis/logging` `^2.0`、`oasis/utils` `^2.0`）和 PHPUnit 13.x 适配，Phase 1 完成了 Silex → Symfony MicroKernel 替换和 Symfony 组件升级到 7.x，Phase 2 完成了 Twig 3.x 和 Guzzle 7.x 升级，Phase 3 完成了 Security 组件的 authenticator 系统重写，Phase 4 完成了 PHP 语言层面 breaking changes 修复和代码现代化（隐式 nullable 参数、松散比较、类型声明、constructor promotion、match 表达式、str_contains 等）。

经过 Phase 0–4 的全面改造，项目代码已在 PHP 8.5 下可编译运行，`phpunit` 全量通过且无 deprecation notice。本 Phase 作为收尾阶段，需要完成以下收尾工作：

1. **内部依赖 ^3.0 升级**：`oasis/utils` 和 `oasis/logging` 在 Phase 0 从 `^1.x` 升级到 `^2.0`，现在需要进一步升级到 `^3.0`。`^3.0` 版本可能引入 API 变更，需要排查并适配项目中的所有使用点
2. **静态分析引入**：项目当前缺少 PHPStan / Psalm 等静态分析工具，需要引入并配置到合理级别，发现并修复潜在类型问题
3. **全量验证**：作为 branch 级 DoD 的最终验证点，确保所有测试通过、静态分析通过、无 deprecation notice
4. **文档全面 review 与更新**：更新 `PROJECT.md`、`README.md`、`docs/state/`（SSOT）、`docs/manual/`，确保所有文档准确反映 Phase 0–5 完成后的系统现状

### 当前依赖使用情况

**`oasis/utils`**（当前 `^2.0`）在项目中的使用：
- `ArrayDataProvider` / `DataProviderInterface` / `AbstractDataProvider`：广泛用于配置数据传递（`MicroKernel`、`ConfigurationValidationTrait`、`CacheableRouterProvider`、`SimpleSecurityProvider`、`SimpleFirewall`、`SimpleAccessRule`、`CrossOriginResourceSharingStrategy`、`SimpleTwigServiceProvider`、测试辅助类等）
- `StringUtils`：用于 `SilexKernelWebTest`（测试文件）
- `DataValidationException` / `ExistenceViolationException`：用于 `ExceptionWrapper` 的异常类型判断和测试

**`oasis/logging`**（当前 `^2.0`）在项目中的使用：
- `LocalFileHandler`：仅在 `ut/bootstrap.php` 中用于测试环境日志初始化
- `MicroKernel` 中注释引用（使用 `NullLogger` 替代框架默认 logger，因为 oasis/logging 处理应用级日志）

### 当前测试状态

Phase 4 完成后，`phpunit` 全量通过，无 deprecation notice。项目代码已完成 PHP 8.5 语言适配和代码现代化。

## 目标

- 将 `oasis/utils` 从 `^2.0` 升级到 `^3.0`，排查并适配所有 API 变更
- 将 `oasis/logging` 从 `^2.0` 升级到 `^3.0`，排查并适配所有 API 变更
- 引入 PHPStan 静态分析工具，目标 level 8（如果问题量过大，与用户商量是否降到 level 5）
- 修复静态分析发现的所有问题
- 全量运行测试套件，确保所有测试在 PHP 8.5 下通过
- 确认无 deprecation notice 输出
- 全面更新 `PROJECT.md`，反映当前技术栈（PHP ≥ 8.5、Symfony 7.x、Twig 3.x、Guzzle 7.x、PHPUnit 13.x、oasis/utils ^3.0、oasis/logging ^3.0 等）
- 更新 `README.md`，反映新的 PHP 版本要求和依赖版本
- 全面 review 并更新 `docs/state/`（SSOT），确保架构文档准确反映 Phase 0–5 完成后的系统现状
- 全面 review 并更新 `docs/manual/`，确保使用文档与当前系统行为一致

## 不做的事情（Non-Goals）

- 不引入新功能
- 不进行代码现代化重构（已在 Phase 4 完成）
- 不涉及性能优化
- 不变更公共 API 的外部行为（除 `oasis/utils` `^3.0` 和 `oasis/logging` `^3.0` 升级可能带来的必要适配外）

## Clarification 记录

### Q1: `oasis/utils` ^3.0 升级策略

`oasis/utils` 从 `^2.0` 升级到 `^3.0` 可能引入 breaking changes。项目中广泛使用了 `ArrayDataProvider`、`DataProviderInterface`、`AbstractDataProvider`、`StringUtils`、`DataValidationException`、`ExistenceViolationException`。升级策略是什么？

- 选项: A) 先执行 `composer require oasis/utils:^3.0`，根据编译错误和测试失败逐一修复 / B) 先查阅 ^3.0 CHANGELOG / 升级指南，制定完整适配计划后再升级 / C) 补充说明
- 回答: A — 直接升级，根据编译错误和测试失败逐一修复

### Q2: `oasis/logging` ^3.0 升级策略

`oasis/logging` 从 `^2.0` 升级到 `^3.0`，项目中仅在 `ut/bootstrap.php` 使用 `LocalFileHandler`。升级策略是什么？

- 选项: A) 直接升级，如果 `LocalFileHandler` API 变更则适配 / B) 先查阅 CHANGELOG 再升级 / C) 补充说明
- 回答: A — 直接升级，API 变更则适配

### Q3: 静态分析工具选择和目标级别

PHPStan 和 Psalm 都是主流选择。工具选择和目标级别是什么？

- 选项: A) PHPStan，从 level 5 开始，逐步提升 / B) PHPStan，直接 level 8（最严格） / C) Psalm，从 level 4 开始 / D) 补充说明
- 回答: B — PHPStan level 8，如果问题量过大则与用户商量是否降到 level 5

### Q4: CI 配置

项目当前没有 CI 配置文件。是否配置 CI？

- 选项: A) GitHub Actions / B) GitLab CI / C) 暂不配置 CI / D) 补充说明
- 回答: 移除 — 不考虑 CI，从 goal 和 PRP 中移除 CI 相关内容

### Q5: `PROJECT.md` 和文档 review 范围

`PROJECT.md` 中的技术栈描述在 Phase 0–4 完成后已严重过时。是否在本 Phase 更新？

- 选项: A) 全面更新 `PROJECT.md`，反映当前技术栈 / B) 仅更新 PHP 版本要求 / C) 补充说明
- 回答: A+ — 全面更新 `PROJECT.md` + 全面 review `docs/state/`（SSOT）和 `docs/manual/` 的内容准确性

## 约束与决策

- PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- 依赖 Phase 0–4 全部完成（测试框架可用、框架已替换、Twig/Guzzle 已升级、Security 已重写、语言适配已完成）
- 本 Phase 是 branch 级 DoD 的最终验证点：全量 PHPUnit 通过（`phpunit`）+ PHPStan 通过 + 无 deprecation notice → `feature/php85-upgrade` merge 回 develop
- `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 升级是本 Phase 新增的 scope（超出 PRP-007 原始范围），由用户明确要求
- `oasis/utils` ^3.0 升级采用直接升级 + 逐一修复策略（Q1=A）
- `oasis/logging` ^3.0 升级采用直接升级 + 按需适配策略（Q2=A）
- 静态分析使用 PHPStan level 8（Q3=B），如果问题量过大则与用户商量是否降到 level 5
- 不配置 CI（Q4=移除），CI 相关内容从 goal 和 PRP-007 中移除
- 全面更新 `PROJECT.md` + 全面 review `docs/state/` 和 `docs/manual/`（Q5=A+）
- spec 级 DoD：tasks 全部完成 + `phpunit` 全量通过 + PHPStan 通过 + 无 deprecation notice
- 本 Phase 完成后，整个 PHP 8.5 升级工作结束，各 Phase 的 proposal 可标记为 `implemented`

## Socratic Review

1. **goal 是否完整覆盖了 PRP-007 的 Goals？**
   - PRP-007 列出 6 个目标：全量测试通过、引入/提升静态分析、配置 CI 矩阵、修复验证中发现的问题、确认无 deprecation notice、更新文档。其中 CI 矩阵已由用户决定移除（Q4），其余 5 个目标均已覆盖。此外，用户明确要求新增 `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 升级（超出 PRP-007 原始 scope），以及全面 review `docs/state/` 和 `docs/manual/`（超出 PRP-007 原始文档更新范围）。用户指令优先于 proposal。

2. **Non-Goals 是否与 PRP-007 一致？**
   - 基本一致。PRP-007 排除了新功能引入、代码现代化重构、性能优化。goal 中已体现。新增了"不变更公共 API 外部行为"的例外说明（oasis/utils ^3.0 升级可能带来必要适配）。CI 配置从 Goals 移到了 Non-Goals 的隐含范围（不做）。

3. **背景摘要是否准确反映了代码现状？**
   - 是。通过读取 `composer.json` 确认 `oasis/utils` 和 `oasis/logging` 当前为 `^2.0`。通过 grep 搜索确认了两个包在项目中的所有使用点。Phase 4 tasks 已全部完成（所有 checkbox 为 `[x]`），`phpunit` 全量通过。

4. **`oasis/utils` ^3.0 升级的风险是否充分识别？**
   - `oasis/utils` 在项目中使用广泛（`ArrayDataProvider`、`DataProviderInterface`、`AbstractDataProvider` 贯穿配置层，`StringUtils` 用于测试，异常类用于错误处理）。^3.0 的 API 变更可能影响多个文件。用户选择直接升级 + 逐一修复策略（Q1=A），风险通过测试套件兜底。

5. **`oasis/logging` ^3.0 升级的风险是否充分识别？**
   - `oasis/logging` 在项目中使用极少（仅 `ut/bootstrap.php` 的 `LocalFileHandler`），风险较低。用户选择直接升级 + 按需适配（Q2=A）。

6. **Clarification 决策是否已完整体现在约束中？**
   - Q1（直接升级 oasis/utils）→ 约束中明确；Q2（直接升级 oasis/logging）→ 约束中明确；Q3（PHPStan level 8，可降级）→ 约束中明确；Q4（移除 CI）→ 约束中明确，PRP-007 同步更新；Q5（全面更新 PROJECT.md + review docs/state/ 和 docs/manual/）→ 约束和目标中均已体现。五个决策均已体现。
