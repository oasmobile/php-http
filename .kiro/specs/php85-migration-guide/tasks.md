# Implementation Plan: PHP 8.5 Migration Guide & Check Script

## Overview

按产物拆分（CR Q2→A）：先完成 Migration Guide（TDD 风格，CR Q3→C），再完成 Check Script（test-first）。Migration Guide 先写 Document Validation Tests 骨架（RED），再编写文档使测试通过（GREEN）。Check Script 先写 PBT + Unit Tests（RED），再实现脚本（GREEN）。

实现语言：PHP（项目本身为 PHP 库，Check Script 为纯 PHP 脚本，测试使用 PHPUnit + Eris）。

## Tasks

- [x] 1. Migration Guide — Document Validation Tests 骨架（RED）
  - [x] 1.1 创建 `ut/MigrationGuideValidationTest.php` 测试文件
    - 编写 Property 1 测试：TOC 锚点完整性 — 解析 `migration-v3.md` TOC 中的 `[text](#anchor)` 链接，验证每个 anchor 对应文档中的一个 heading
    - 编写 Property 2 测试：Breaking Change 覆盖完整性 — 解析 `docs/changes/unreleased/php85-upgrade.md` 提取条目，验证每个条目在 `migration-v3.md` 中有对应内容
    - 编写 Property 3 测试：条目格式完整性 — 解析每个 breaking change entry，验证包含 severity marker（🔴/🟡/🟢）+ before/after code blocks + action 描述
    - 编写 Property 4 测试：Bootstrap_Config Key 覆盖完整性 — 解析 `docs/state/architecture.md` 提取 Bootstrap_Config keys，验证每个 key 在参考表中出现
    - 此阶段测试预期全部 FAIL（RED），因为 `migration-v3.md` 尚不存在
    - _Ref: Requirement 1, AC 2/3; Requirement 2, AC 1/2/3; Requirement 9, AC 2; Design Correctness Properties 1–4_
  - [x] 1.2 在 `phpunit.xml` 中注册 `migration-guide-validation` test suite
    - 添加 `<testsuite name="migration-guide-validation"><file>ut/MigrationGuideValidationTest.php</file></testsuite>`
    - _Ref: Design Testing Strategy_
  - [x] 1.3 Checkpoint: 运行 `phpunit --testsuite migration-guide-validation`，确认测试文件可编译、测试用例存在且全部 FAIL（RED 状态）。Commit。

- [x] 2. Migration Guide — 编写迁移文档（GREEN）
  - [x] 2.1 创建 `docs/manual/migration-v3.md`，编写 TOC 和概述章节
    - 文件头部包含版本变更摘要、适用范围说明
    - TOC 使用 markdown 锚点链接导航到各模块章节（按 R1 AC4 定义的顺序：PHP Version → Dependencies → Kernel API → DI Container → Bootstrap Config → Routing → Security → Middleware → Views → Twig → CORS → Cookie → PHP 语言适配 → 附录）
    - 包含快速评估清单（按严重程度汇总所有 breaking change）
    - _Ref: Requirement 1, AC 1/2/4_
  - [x] 2.2 编写 PHP Version 和 Dependencies 章节
    - PHP Version：PHP `>=7.0.0` → `>=8.5`，Severity 🔴，before/after 示例（`composer.json` 中的 `"php"` 约束）
    - Dependencies：移除 `silex/silex`、`silex/providers`、`twig/extensions`（🔴）；Symfony `^4.0` → `^7.2`（🔴）；`twig/twig` `^1.24` → `^3.0`（🔴）；`guzzlehttp/guzzle` `^6.3` → `^7.0`（🔴）；`oasis/logging` `^1.1` → `^3.0`、`oasis/utils` `^1.6` → `^3.0`（🔴）
    - 每项均包含 severity marker + before/after 代码示例 + action 描述
    - _Ref: Requirement 7, AC 1–6; Requirement 2, AC 1–6; Design Data Models 映射表 #1–9_
  - [x] 2.3 编写 Kernel API 和 DI Container 章节
    - Kernel API：`SilexKernel` → `MicroKernel` 类名变更（🔴）、命名空间变更、构造函数签名变更（🔴）、公共 API 方法列表、`__set()` 移除（🔴）
    - DI Container：Pimple 移除（🔴）、`$app['xxx']` 访问模式移除（🔴）、`Pimple\ServiceProviderInterface` → `CompilerPassInterface`/`ExtensionInterface`（🔴）、新 service 注册模式
    - _Ref: Requirement 3, AC 1–5; Requirement 4, AC 1–3; Design Data Models 映射表 #10–14_
  - [x] 2.4 编写 Bootstrap Config 和 Routing 章节
    - Bootstrap Config：`providers` key 语义变更（🔴）、完整 Bootstrap_Config key 参考表（列出每个 key 的类型、默认值、是否变更）
    - Routing：迁移到 Symfony Routing 7.x（🟢），明确说明 `routing` key 行为保持不变，无需下游操作
    - _Ref: Requirement 9, AC 1–3; Requirement 10, AC 1/5; Design Data Models Bootstrap_Config Key 参考表, 映射表 #15–16_
  - [x] 2.5 编写 Security 章节
    - `AuthenticationPolicyInterface` 重写（🔴）、`FirewallInterface` 重写（🔴）、`AccessRuleInterface` 重写（🔴）
    - `AbstractSimplePreAuthenticator` → `AbstractPreAuthenticator`（🔴），新 template method pattern
    - `AbstractSimplePreAuthenticateUserProvider` 适配（🔴）
    - 完整的 before/after 自定义 pre-authentication policy 示例
    - `NullEntryPoint` 适配（🟢），明确说明无需下游操作
    - _Ref: Requirement 5, AC 1–6; Requirement 10, AC 5; Design Data Models 映射表 #17–21, 33_
  - [x] 2.6 编写 Middleware、Views、Twig 章节
    - Middleware：`MiddlewareInterface::before()` 签名变更（🔴）、`AbstractMiddleware` 移除 Silex 依赖（🔴）、事件优先级常量变更（🔴）、旧 Symfony 事件类移除（🔴，交叉引用）
    - Views：View Handler / `ResponseRendererInterface` 类型参数变更（🔴）
    - Twig：`Twig_Environment` 等类名变更（🔴）、`twig/extensions` 移除替代方案（🟡）、`SimpleTwigServiceProvider` 重写（🔴）、`twig.strict_variables` / `twig.auto_reload` 行为说明
    - _Ref: Requirement 6, AC 1–3; Requirement 10, AC 4; Requirement 8, AC 1–4; Design Data Models 映射表 #22–27, 32_
  - [x] 2.7 编写 CORS、Cookie、PHP 语言适配、附录章节
    - CORS：Provider → EventSubscriber（🟢），明确说明 `CrossOriginResourceSharingStrategy` API 保持不变，无需下游操作
    - Cookie：Provider → EventSubscriber（🟢），明确说明 `ResponseCookieContainer` API 保持不变，无需下游操作
    - PHP 语言适配：隐式 nullable 参数修复（🟡）、动态属性废弃（🟡）、建议下游运行自身 PHP 8.5 兼容性检查
    - 附录：完整 API 变更速查表，包含开发依赖参考（`phpunit/phpunit` `^5.2` → `^13.0`（🟢）、`phpstan/phpstan` 新增 `^2.1`（🟢））
    - _Ref: Requirement 10, AC 2/3/5; Requirement 11, AC 1–3; Design Data Models 映射表 #28–31, 34–35_
  - [x] 2.8 Checkpoint: 运行 `phpunit --testsuite migration-guide-validation`，确认 Property 1–4 测试全部通过（GREEN）。Commit。


- [x] 3. Check Script — PBT 测试骨架（RED）
  - [x] 3.1 创建 `ut/PBT/MigrateCheckPropertyTest.php` 测试文件
    - 编写 Property 5 测试：规则检测完整性 — 生成包含随机 Removed_API/Changed_API 引用的 PHP 文件内容（简单规则用模板拼接，复杂模式如 Pimple 访问、Guzzle 选项用预定义片段，CR Q4→C），验证 scanner 对每个文件产生至少一个 Finding
    - 编写 Property 6 测试：递归扫描完整性 — 生成随机深度（1–5 层）的目录结构，随机放置 `.php` 文件，验证发现的文件数等于放置的文件数
    - 编写 Property 7 测试：Finding 字段完整性 — 复用 P5 生成器，检查每个 Finding 包含 file（相对路径）、line（正整数）、issue（非空）、action（非空）四个必需字段
    - 编写 Property 8 测试：Severity 分组排序 — 生成包含混合 severity 规则匹配的文件集，验证 text 输出中 🔴 section 在 🟡 之前，🟡 在 🟢 之前
    - 编写 Property 9 测试：退出码正确性 — 生成有/无 🔴 finding 的随机场景，验证退出码与 🔴 存在性的一致性
    - 编写 Property 10 测试：输出格式有效性 — 复用 P5 生成器，使用 `--format=json`，验证输出为有效 JSON 且每个元素包含 `file`、`line`、`severity`、`issue`、`action` 字段
    - 编写 Property 11 测试：二进制文件与非 UTF-8 文件容错 — 生成混合二进制和 PHP 文件的目录，验证不崩溃且 PHP 文件的 Findings 正确
    - Eris 最小迭代次数 100 次，Tag 格式：`Feature: php85-migration-guide, Property {N}: {title}`
    - 此阶段测试预期全部 FAIL（RED），因为 Check Script 尚不存在
    - _Ref: Requirement 12, AC 1–8; Requirement 13, AC 2/3/6; Requirement 14, AC 5/6; Requirement 15, AC 3/4; Design Correctness Properties 5–11, Testing Strategy PBT_
  - [x] 3.2 在 `phpunit.xml` 中注册 `migrate-check-pbt` test suite
    - 添加 `<testsuite name="migrate-check-pbt"><file>ut/PBT/MigrateCheckPropertyTest.php</file></testsuite>`
    - _Ref: Design Testing Strategy_
  - [x] 3.3 Checkpoint: 运行 `phpunit --testsuite migrate-check-pbt`，确认测试文件可编译、测试用例存在且全部 FAIL（RED 状态）。Commit。

- [-] 4. Check Script — Unit Tests 骨架（RED）
  - [x] 4.1 创建 `ut/MigrateCheckScriptTest.php` 测试文件
    - 编写测试：目录不存在 → stderr 输出错误信息 + exit code 2
    - 编写测试：空目录（无 `.php` 文件）→ 提示信息 + exit code 0
    - 编写测试：`--help` 选项 → 输出 usage 帮助信息
    - 编写测试：无效 `--format` 值 → stderr 输出错误信息 + exit code 2
    - 编写测试：符号链接循环处理 → 不崩溃，正常完成扫描
    - 编写测试：文件权限错误 → warning 到 stderr + 继续扫描其他文件
    - 编写测试：无 🔴 finding → exit code 0，有 🔴 finding → exit code 1
    - 编写测试：已知 Removed_API 引用检测（`SilexKernel`、`Silex\Application`、`Pimple\Container` 等）
    - 编写测试：已知 Changed_API 引用检测（`AuthenticationPolicyInterface`、`FirewallInterface` 等）
    - 编写测试：Pimple 访问模式 `$app['...']` 检测
    - 编写测试：旧 Symfony 事件类检测（`FilterResponseEvent`、`GetResponseEvent` 等）
    - 编写测试：`composer.json` 中旧包引用检测（`silex/silex`、`silex/providers`、`twig/extensions`）
    - 编写测试：Guzzle 6.x 模式检测（`'exceptions' => false`）
    - 编写测试：无问题时输出 success message
    - 编写测试：`--format=json` 输出有效 JSON
    - 此阶段测试预期全部 FAIL（RED），因为 Check Script 尚不存在
    - _Ref: Requirement 12, AC 1–8; Requirement 13, AC 1–6; Requirement 14, AC 4/5/6; Requirement 15, AC 1–5; Design Error Handling, Testing Strategy Unit Tests_
  - [x] 4.2 在 `phpunit.xml` 中注册 `migrate-check-unit` test suite
    - 添加 `<testsuite name="migrate-check-unit"><file>ut/MigrateCheckScriptTest.php</file></testsuite>`
    - _Ref: Design Testing Strategy_
  - [-] 4.3 Checkpoint: 运行 `phpunit --testsuite migrate-check-unit`，确认测试文件可编译、测试用例存在且全部 FAIL（RED 状态）。Commit。

- [~] 5. Check Script — 实现脚本（GREEN）
  - [ ] 5.1 创建 `bin/oasis-http-migrate-v3-check` 脚本文件，实现 Rule Registry
    - 创建脚本文件，添加 `#!/usr/bin/env php` shebang
    - 实现 `getRules(): array` 函数，注册所有规则：
      - Removed_API 规则（8 条）：`removed-silex-kernel`、`removed-silex-app`、`removed-pimple-container`、`removed-pimple-provider`、`removed-bootable-provider`、`removed-twig-env`、`removed-twig-func`、`removed-twig-error`
      - Changed_API 规则（7 条）：`changed-auth-policy`、`changed-firewall`、`changed-access-rule`、`changed-pre-auth`、`changed-pre-auth-user`、`changed-middleware`、`changed-renderer`
      - Pimple 模式规则（1 条）：`pimple-access`
      - 旧 Symfony 事件类规则（4 条）：`old-event-filter-response`、`old-event-get-response`、`old-event-exception`、`old-event-master-request`
      - 旧包引用规则（3 条）：`old-pkg-silex`、`old-pkg-silex-providers`、`old-pkg-twig-ext`
      - Guzzle 6.x 模式规则（2 条）：`guzzle-exceptions-option`、`guzzle-new-client`
    - 实现可测试性方案（提取函数文件或条件守卫，CR Q1→A/B 自行选择）
    - _Ref: Requirement 12, AC 3–8; Design Architecture Rule Registry_
  - [ ] 5.2 实现 CLI 入口和参数解析
    - 实现 `main(array $argv): int` 函数
    - 解析 `--help`、`--format=FORMAT`、`<directory>` 参数
    - 无参数时输出 usage 到 stderr，exit code 2
    - `--help` 输出 usage 信息
    - 无效 `--format` 值输出错误到 stderr，exit code 2
    - 目标目录不存在输出错误到 stderr，exit code 2
    - _Ref: Requirement 14, AC 1/4/5; Requirement 15, AC 1; Design Components CLI 接口_
  - [ ] 5.3 实现 Token Scanner 和 Composer Scanner
    - 实现 `scanDirectory(string $dir, array $rules): array` — 递归遍历目录，使用 `realpath()` 去重避免符号链接循环
    - 实现 `scanPhpFile(string $file, array $rules): array` — 基于 `token_get_all()` 的 token 级扫描，跳过注释 token，识别 `use` 语句中的完整命名空间、`T_STRING` 中的类名引用、`$app['...']`/`$container['...']` Pimple 访问模式、Guzzle 6.x 选项模式
    - 实现 `scanComposerJson(string $file, array $rules): array` — `json_decode()` 解析，检查 `require` 和 `require-dev` 中的旧包引用
    - 实现辅助函数：`isPhpFile()`、`isBinaryFile()`、`isUtf8()`
    - 跳过二进制文件和非 UTF-8 文件（静默跳过）
    - `token_get_all()` 解析失败时输出 warning 到 stderr 并继续
    - `composer.json` JSON 解析失败时输出 warning 到 stderr 并继续
    - 文件无读取权限时输出 warning 到 stderr 并继续
    - _Ref: Requirement 12, AC 1–8; Requirement 15, AC 2–5; Design Architecture Token Scanner, Composer Scanner_
  - [ ] 5.4 实现 Reporter（text 和 JSON 格式）
    - 实现 `reportText(array $findings, string $targetDir): void` — 按 Severity 分组排序（🔴 → 🟡 → 🟢），输出每个 finding 的文件路径（相对）、行号、issue、action，末尾输出 summary（total + 各 severity 计数）
    - 实现 `reportJson(array $findings, string $targetDir): void` — 输出 JSON 数组，每个元素包含 `file`、`line`、`severity`、`issue`、`action` 字段
    - 无 finding 时输出 success message
    - _Ref: Requirement 13, AC 1–5; Requirement 14, AC 5/6; Design Architecture Reporter_
  - [ ] 5.5 串联 main 函数，实现退出码逻辑
    - `main()` 调用 `scanDirectory()` → `reportText()`/`reportJson()` → 返回退出码
    - 存在 🔴 finding → exit code 1；否则 → exit code 0
    - 目标目录无 `.php` 文件 → 输出提示信息，exit code 0
    - _Ref: Requirement 13, AC 5/6; Design Data Models 退出码模型_
  - [ ] 5.6 更新 `composer.json` 添加 `"bin"` 配置
    - 添加 `"bin": ["bin/oasis-http-migrate-v3-check"]`
    - 确保脚本文件有可执行权限
    - _Ref: Requirement 14, AC 1/2/3_
  - [ ] 5.7 Checkpoint: 运行 `phpunit --testsuite migrate-check-pbt --testsuite migrate-check-unit`，确认 PBT（Properties 5–11）和 Unit Tests 全部通过（GREEN）。运行 `phpunit` 全量测试确认无回归。Commit。

- [ ] 6. 手工测试
  - [ ] 6.1 Migration Guide 结构验证
    - [脚本] 验证 `docs/manual/migration-v3.md` 文件存在且非空
    - [脚本] 验证 TOC 中所有锚点链接可解析到文档内 heading
    - [脚本] 验证所有 breaking change 条目包含 severity marker（🔴/🟡/🟢）
    - [脚本] 验证所有 breaking change 条目包含 before/after 代码块
    - [脚本] 统计各 severity 级别的条目数量，输出汇总
  - [ ] 6.2 Check Script CLI 交互验证
    - [脚本] 验证 `bin/oasis-http-migrate-v3-check --help` 输出 usage 信息
    - [脚本] 验证对不存在的目录输出错误信息且 exit code 为 2
    - [脚本] 验证对空目录输出提示信息且 exit code 为 0
    - [脚本] 验证 `--format=json` 输出有效 JSON
    - [脚本] 验证 `--format=invalid` 输出错误信息且 exit code 为 2
  - [ ] 6.3 Check Script 端到端扫描验证
    - [脚本] 创建包含已知 Removed_API 引用的测试 PHP 文件，运行 Check Script，验证检测到预期 finding
    - [脚本] 创建包含 Pimple 访问模式的测试 PHP 文件，运行 Check Script，验证检测到预期 finding
    - [脚本] 创建包含旧包引用的测试 `composer.json`，运行 Check Script，验证检测到预期 finding
    - [脚本] 验证 text 输出中 🔴 findings 排在 🟡 之前
    - [脚本] 验证存在 🔴 finding 时 exit code 为 1
  - [ ] 6.4 Checkpoint: 确认所有手工测试场景通过，记录测试结果。Commit。

- [ ] 7. Code Review
  - [ ] 7.1 委托给 code-reviewer sub-agent 执行

## Notes

- 按 `spec-execution.md` 规范执行所有 task
- Commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint，通过后 commit
- 产物拆分顺序（CR Q2→A）：Migration Guide 全部章节一组 tasks（task 1–2），Check Script 另一组 tasks（task 3–5）
- Migration Guide 采用 TDD 风格（CR Q3→C）：先写 Document Validation Tests 骨架（RED，task 1），再写文档使测试通过（GREEN，task 2）
- Check Script 采用 test-first：先写 PBT（RED，task 3）+ Unit Tests（RED，task 4），再实现脚本（GREEN，task 5）
- PBT 生成器策略（CR Q4→C）：简单规则用模板拼接，复杂模式（Pimple 访问、Guzzle 选项）用预定义片段
- Check Script 可测试性方案（CR Q1→A/B）：提取函数文件或条件守卫，实现时自行选择
- Properties 1–4（Migration Guide 结构验证）使用普通 PHPUnit 测试，不使用 Eris PBT
- Properties 5–11（Check Script 扫描逻辑）使用 Eris PBT，最小迭代 100 次
- 本 spec 不修改任何现有源代码或 state 文档，仅新增文件
- spec 级 DoD：tasks 全部完成 + 三层测试全部通过 + 手工测试通过

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有实现项？**
A: 是。Migration Guide 文档架构 → task 2（各章节 sub-task 覆盖 12 个模块章节 + PHP 语言适配 + 附录）；Check Script Rule Registry → task 5.1；Token Scanner + Composer Scanner → task 5.3；Reporter → task 5.4；CLI 接口 → task 5.2；退出码逻辑 → task 5.5；composer.json bin 配置 → task 5.6。Document Validation Tests → task 1；PBT → task 3；Unit Tests → task 4。所有 design section 均有对应 task。

**Q: task 之间的依赖顺序是否正确？**
A: 是。遵循 CR Q2→A（按产物拆分）和 CR Q3→C（TDD 风格）：Migration Guide 测试骨架（task 1）→ Migration Guide 文档（task 2）→ Check Script PBT 骨架（task 3）→ Check Script Unit Tests 骨架（task 4）→ Check Script 实现（task 5）。每个 task 依赖前序 task 的产出。

**Q: requirements.md 中的每条 requirement 是否至少被一个 task 引用？**
A: 是。R1 → task 1.1, 2.1; R2 → task 1.1, 2.2–2.7; R3 → task 2.3; R4 → task 2.3; R5 → task 2.5; R6 → task 2.6; R7 → task 2.2; R8 → task 2.6; R9 → task 1.1, 2.4; R10 → task 2.4, 2.5, 2.6, 2.7; R11 → task 2.7; R12 → task 3.1, 4.1, 5.1, 5.3; R13 → task 3.1, 4.1, 5.4, 5.5; R14 → task 3.1, 4.1, 5.2, 5.6; R15 → task 3.1, 4.1, 5.3。全部 15 条 requirement 均被引用。

**Q: Design CR 四项决策是否已体现在 task 编排中？**
A: 是。CR Q1（可测试性方案）→ task 5.1 注明"提取函数文件或条件守卫，实现时自行选择"；CR Q2（按产物拆分）→ task 1–2 为 Migration Guide，task 3–5 为 Check Script；CR Q3（TDD 风格）→ task 1 先写测试（RED），task 2 写文档（GREEN）；CR Q4（混合生成器策略）→ task 3.1 注明"简单规则用模板拼接，复杂模式用预定义片段"。

**Q: checkpoint 的设置是否覆盖了关键阶段？**
A: 是。每个 top-level task 末尾都有 checkpoint：task 1.3（测试骨架 RED 确认）、task 2.8（文档 GREEN 确认）、task 3.3（PBT RED 确认）、task 4.3（Unit Tests RED 确认）、task 5.7（实现 GREEN 确认 + 全量测试无回归）、task 6.4（手工测试通过）。

**Q: 手工测试是否覆盖了两个产物的关键用户场景？**
A: 是。task 6.1 覆盖 Migration Guide 结构验证（TOC、severity、code blocks）；task 6.2 覆盖 Check Script CLI 交互（help、错误处理、格式选项）；task 6.3 覆盖 Check Script 端到端扫描（各类规则检测、输出排序、退出码）。

**Q: 所有 task 是否均为 mandatory？**
A: 是。所有 task 使用 `- [ ]` 格式，无 `- [ ]*` optional 标记。测试 task 和实现 task 均为 mandatory。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ✅ 通过

### 修正项
无

### 合规检查

**1. 机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名均与 requirements.md / design.md 一致）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误

**2. 结构校验**
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task（7）是 Code Review
- [x] 倒数第二个 top-level task（6）是手工测试
- [x] 自动化实现 task（1–5）排在手工测试和 Code Review 之前

**3. Task 格式校验**
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–7），连续无跳号
- [x] sub-task 有层级序号（1.1–1.3, 2.1–2.8, 3.1–3.3, 4.1–4.3, 5.1–5.7, 6.1–6.4, 7.1），连续无跳号

**4. Requirement 追溯校验**
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款（`Ref: Requirement X, AC Y` 格式）
- [x] requirements.md 中全部 15 条 requirement（R1–R15）均至少被一个 task 引用（Socratic Review 第三条 Q&A 提供了完整映射）
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在，无悬空引用

**5. 依赖与排序校验**
- [x] top-level task 按依赖关系排序：Migration Guide 测试（1）→ 文档（2）→ Check Script PBT（3）→ Unit Tests（4）→ 实现（5）→ 手工测试（6）→ Code Review（7）
- [x] 无循环依赖
- [x] 无并行标注（不适用）

**6. Graphify 跨模块依赖校验**
- [x] 已对核心模块执行 graphify 依赖查询（MicroKernel 为 god node，42 edges）
- [x] 本 spec 仅新增文件（migration-v3.md + check script），不修改现有源代码，无跨模块修改依赖
- [x] task 排序与 graphify 揭示的模块依赖一致，无遗漏

**7. Checkpoint 校验**
- [x] checkpoint 不作为独立 top-level task，而是每个 top-level task 的最后一个 sub-task
- [x] 每个 top-level task（1–6）的最后一个 sub-task 是 checkpoint（1.3, 2.8, 3.3, 4.3, 5.7, 6.4）
- [x] checkpoint 描述包含具体验证命令（`phpunit --testsuite ...`）和 commit 动作
- [x] checkpoint 非空泛"确认完成"，均有可执行的验证步骤

**8. Test-first 校验**
- [x] Migration Guide 遵循 TDD 风格（CR Q3→C）：task 1 写测试骨架（RED）→ task 2 写文档（GREEN）
- [x] Check Script 遵循 test-first：task 3 PBT（RED）+ task 4 Unit Tests（RED）→ task 5 实现（GREEN）

**9. Task 粒度校验**
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗的 task
- [x] 无过细的 task
- [x] 所有 task 均为 mandatory（无 optional 标记）

**10. 手工测试 Task 校验**
- [x] 手工测试 top-level task（6）存在
- [x] 覆盖两个产物的关键用户场景：Migration Guide 结构验证（6.1）、Check Script CLI 交互（6.2）、端到端扫描（6.3）
- [x] 场景描述具体，可执行

**11. Code Review Task 校验**
- [x] Code Review 是最后一个 top-level task（7）
- [x] 描述为"委托给 code-reviewer sub-agent 执行"
- [x] 未展开 review checklist 或 fix policy

**12. 执行注意事项校验**
- [x] `## Notes` section 存在
- [x] 明确提到按 `spec-execution.md` 规范执行
- [x] 明确说明 commit 随 checkpoint 一起执行
- [x] 包含当前 spec 特有的执行要点（CR 四项决策、PBT 配置、Properties 分层、产物拆分顺序、DoD）

**13. Socratic Review 校验**
- [x] `## Socratic Review` section 存在
- [x] 覆盖充分（7 个 Q&A）：design 覆盖、依赖顺序、requirement 追溯、CR 决策体现、checkpoint 覆盖、手工测试覆盖、mandatory 确认

**14. 目的性审查**
- [x] Design CR 四项决策均已在 task 编排中体现（Q1→task 5.1, Q2→task 1–2 vs 3–5, Q3→task 1 RED → task 2 GREEN, Q4→task 3.1）
- [x] Design 全覆盖：所有 design section（Architecture、Components、Data Models、Correctness Properties、Error Handling、Testing Strategy）均有对应 task
- [x] 可独立执行：每个 sub-task 描述自包含，配合 Ref 指向的 requirement 和 design section 即可完成实现
- [x] 验收闭环：checkpoint（自动化验证）+ 手工测试（端到端验证）+ code review 构成完整验收
- [x] 执行路径无歧义：task 排序清晰，无隐含依赖
