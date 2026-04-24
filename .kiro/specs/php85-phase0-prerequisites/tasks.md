# Tasks

> PHP 8.5 升级 Phase 0：前置依赖与测试框架升级 — `.kiro/specs/php85-phase0-prerequisites/`

## Tasks

- [x] 1. Composer_JSON 变更 + 依赖安装 + Silex 可加载性验证（R1–R3）
  - [x] 1.1 修改 `composer.json`：`php` 版本约束从 `>=7.0.0` 改为 `>=8.5`
    - _Requirements: R1 AC1_
  - [x] 1.2 修改 `composer.json`：`oasis/logging` 从 `^1.1` 改为 PHP 8.5 兼容的 `^` 语义化约束，`oasis/utils` 从 `^1.6` 改为 PHP 8.5 兼容的 `^` 语义化约束
    - Design §1.2 使用 `^3.0` 为示意，实际版本号在 `composer update` 时确认
    - _Requirements: R2 AC1, R2 AC2_
  - [x] 1.3 修改 `composer.json`：`require-dev` 中 `phpunit/phpunit` 从 `^5.2` 改为 `^13.0`
    - _Requirements: R3 AC1_
  - [x] 1.4 执行 `composer update`，确认依赖解析成功
    - 验证 `composer validate` 通过
    - 验证 `vendor/bin/phpunit --version` 输出 PHPUnit 13.x
    - _Requirements: R1 AC2, R2 AC3, R3 AC2, R3 AC3_
  - [x] 1.5 验证 `Silex\Application` 类在 PHP 8.5 下的可加载性（CR Q2=B）
    - 编写或执行简单脚本，确认 `Silex\Application` 类是否可加载（不触发 fatal error）
    - 记录结果，供后续 Task 5（间接框架依赖修复）选择修复路径
    - _Requirements: R12 AC2（前置信息收集）_
  - [x] 1.6 Checkpoint: `composer validate` 通过、`vendor/bin/phpunit --version` 输出 13.x、Silex 可加载性结果已记录，commit

- [x] 2. PHPUnit_Config 适配 + Bootstrap_File 适配（R4–R5）
  - [x] 2.1 修改 `phpunit.xml`：XML schema 引用从 `http://schema.phpunit.de/5.7/phpunit.xsd` 改为 `vendor/phpunit/phpunit/phpunit.xsd`
    - _Requirements: R4 AC1_
  - [x] 2.2 修改 `phpunit.xml`：`all` suite 从逐文件 `<file>` 列表改为 `<directory>ut</directory>`（CR Q2=B from requirements）
    - 确保 suite 覆盖的测试集合不变
    - _Requirements: R4 AC2_
  - [x] 2.3 修改 `phpunit.xml`：`exceptions` suite 移除 `ut/HttpExceptionTest.php`，改为引用 `ut/Misc/UniquenessViolationHttpExceptionTest.php`（Design §5.1）
    - `HttpExceptionTest` 继承 `Silex\WebTestCase`，属于 Framework_Dependent，不应在 Framework_Independent 的 `exceptions` suite 中
    - _Requirements: R4 AC2, R11 AC5_
  - [x] 2.4 检查 PHPUnit 13.x 移除/变更的 XML 配置元素，确认当前 `phpunit.xml` 无需额外处理（Design §2.3）
    - _Requirements: R4 AC3_
  - [x] 2.5 确认 `phpunit.xml` 保留 `bootstrap="ut/bootstrap.php"` 引用
    - _Requirements: R4 AC4_
  - [x] 2.6 修改 `ut/bootstrap.php`：恢复 autoloader 加载（取消注释 `require __DIR__ . "/../vendor/autoload.php"`），清理已注释的旧代码（`Debug::enable()` 等），确保 PHP 8.5 下可加载
    - 验证 `LocalFileHandler::install()` 在 `oasis/logging` 新版本下正常工作
    - _Requirements: R5 AC1, R5 AC2, R5 AC3_
  - [x] 2.7 Checkpoint: `vendor/bin/phpunit --list-suites` 输出所有 14 个 suite、bootstrap 加载无错误，commit

- [x] 3. Test_Adaptation — Configuration 模块（R6–R10）
  - [x] 3.1 适配 `ut/Configuration/HttpConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `setExpectedException` → `expectException`（1 处，R7 AC1–AC2）
    - `@dataProvider variableNodeProvider` → `#[DataProvider('variableNodeProvider')]`（R8 AC1–AC2）
    - `variableNodeProvider()` 改为 `public static function variableNodeProvider(): array`（R8 AC3）
    - 添加 `use PHPUnit\Framework\Attributes\DataProvider;`（R10 AC3）
    - _Requirements: R6 AC1, R7 AC1–AC2, R8 AC1–AC4, R10 AC3_
  - [x] 3.2 适配 `ut/Configuration/SecurityConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型
    - _Requirements: R6 AC1_
  - [x] 3.3 适配 `ut/Configuration/CacheableRouterConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型
    - _Requirements: R6 AC1_
  - [x] 3.4 适配 `ut/Configuration/TwigConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型
    - _Requirements: R6 AC1_
  - [x] 3.5 适配 `ut/Configuration/SimpleAccessRuleConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `setExpectedException` → `expectException`（3 处，R7 AC1–AC2）
    - _Requirements: R6 AC1, R7 AC1–AC2_
  - [x] 3.6 适配 `ut/Configuration/SimpleFirewallConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `setExpectedException` → `expectException`（3 处，R7 AC1–AC2）
    - _Requirements: R6 AC1, R7 AC1–AC2_
  - [x] 3.7 适配 `ut/Configuration/CrossOriginResourceSharingConfigurationTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `setExpectedException` → `expectException`（1 处，R7 AC1–AC2）
    - _Requirements: R6 AC1, R7 AC1–AC2_
  - [x] 3.8 适配 `ut/Configuration/ConfigurationValidationTraitTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `setExpectedException` → `expectException`（1 处，R7 AC1–AC2）
    - _Requirements: R6 AC1, R7 AC1–AC2_
  - [x] 3.9 Checkpoint: 运行 `vendor/bin/phpunit --testsuite configuration`，全部通过无 fatal error，commit
    - _Requirements: R10 AC4, R11 AC1_

- [x] 4. Test_Adaptation — ErrorHandlers + Views + Misc 模块（R6–R10）
  - [x] 4.1 适配 `ut/ErrorHandlers/JsonErrorHandlerTest.php`
    - `setUp()` 添加 `: void` 返回类型（R6 AC1）
    - `assertInternalType('array', ...)` → `assertIsArray(...)`（1 处，R10 AC1）
    - _Requirements: R6 AC1, R10 AC1_
  - [x] 4.2 适配 `ut/ErrorHandlers/ExceptionWrapperTest.php`
    - `setUp()` 添加 `: void` 返回类型
    - _Requirements: R6 AC1_
  - [x] 4.3 适配 `ut/Views/DefaultHtmlRendererTest.php`
    - `assertContains` → `assertStringContainsString`（6 处字符串 haystack，R10 AC1）
    - `getMockBuilder(\Twig_Environment::class)->...->getMock()` → `createMock(\Twig_Environment::class)`（2 处，R9 AC1–AC3）
    - 注意：`\Twig_Environment` 属于 Twig 1.x 遗留类名，如 PHP 8.5 下不可加载则该测试归入 Framework_Dependent（Design §4.4）
    - _Requirements: R9 AC1–AC3, R10 AC1_
  - [x] 4.4 适配 `ut/Views/RouteBasedResponseRendererResolverTest.php`
    - `setExpectedException(X::class, 'message')` → `expectException(X::class)` + `expectExceptionMessage('message')`（1 处，R7 AC1–AC4）
    - _Requirements: R7 AC1–AC4_
  - [x] 4.5 适配 `ut/Misc/ChainedParameterBagDataProviderTest.php`
    - `assertInternalType('array', ...)` → `assertIsArray(...)`（1 处，R10 AC1）
    - `assertContains` 用于数组 haystack — 无需迁移
    - _Requirements: R10 AC1_
  - [x] 4.6 Checkpoint: 运行 `vendor/bin/phpunit --testsuite error-handlers --testsuite views --testsuite misc`，全部通过无 fatal error，commit
    - _Requirements: R10 AC4, R11 AC2, R11 AC3, R11 AC4_

- [x] 5. 间接框架依赖修复（Design §5, CR Q1=B）
  - [x] 5.1 修复 `ut/Cookie/SimpleCookieProviderTest.php`（Design §5.2）
    - 根据 Task 1.5 的 Silex 可加载性验证结果选择修复路径：
      - 如果 `Silex\Application` 可加载：保持现状，仅做 PHPUnit API 适配（`setUp(): void` 等）
      - 如果不可加载：`testBootThrowsLogicExceptionForNonSilexKernel` 中 `new Application()` 替换为 mock；`testBootRegistersAfterMiddlewareThatWritesCookiesToResponse` 从 `cookie` suite 移出或条件跳过
    - _Requirements: R11 AC6, R12 AC2_
  - [x] 5.2 修复 `ut/Middlewares/AbstractMiddlewareTest.php`（Design §5.3）
    - 根据 Task 1.5 的 Silex 可加载性验证结果选择修复路径：
      - 如果 `Silex\Application` 可加载（常量 `LATE_EVENT`/`EARLY_EVENT` 可访问）：保持现状
      - 如果不可加载：将常量值硬编码到测试中（`LATE_EVENT = -512`，`EARLY_EVENT = 512`），移除对 `Silex\Application` 的 `use` 引用
    - _Requirements: R11 AC7, R12 AC2_
  - [x] 5.3 Checkpoint: 运行 `vendor/bin/phpunit --testsuite cookie --testsuite middlewares`，全部通过，commit
    - _Requirements: R11 AC6, R11 AC7_

- [x] 6. Test_Adaptation — Exceptions 模块验证 + 全量 Framework_Independent 验证（R11）
  - [x] 6.1 确认 `ut/Misc/UniquenessViolationHttpExceptionTest.php` 无需 PHPUnit API 适配（Design §8.1 确认无 PHPUnit 5.x API 使用）
    - _Requirements: R11 AC5_
  - [x] 6.2 逐一运行全部 7 个 Framework_Independent_Suite，确认全部通过：
    - `vendor/bin/phpunit --testsuite configuration`
    - `vendor/bin/phpunit --testsuite error-handlers`
    - `vendor/bin/phpunit --testsuite views`
    - `vendor/bin/phpunit --testsuite misc`
    - `vendor/bin/phpunit --testsuite exceptions`
    - `vendor/bin/phpunit --testsuite cookie`
    - `vendor/bin/phpunit --testsuite middlewares`
    - 如有失败，按 Design §6.2 失败处理流程修复
    - _Requirements: R11 AC1–AC7_
  - [x] 6.3 运行 `vendor/bin/phpunit --list-suites`，确认输出所有 14 个 suite（承接 Task 2.7 延迟验证）
    - _Requirements: R4 AC2_
  - [x] 6.4 Checkpoint: 7 个 Framework_Independent_Suite 全部通过、`--list-suites` 输出 14 个 suite，commit

- [-] 7. Framework_Dependent_Suite 预期失败确认（R12）
  - [x] 7.1 运行 `vendor/bin/phpunit --testsuite cors`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.2 运行 `vendor/bin/phpunit --testsuite security`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.3 运行 `vendor/bin/phpunit --testsuite twig`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.4 运行 `vendor/bin/phpunit --testsuite aws`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.5 运行 `vendor/bin/phpunit --testsuite routing`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.6 运行 `vendor/bin/phpunit --testsuite integration`，确认预期失败并记录失败原因
    - _Requirements: R12 AC1_
  - [x] 7.7 汇总所有预期失败和意外失败（如有），记录到 CHANGELOG 或 spec notes 中
    - 包含：失败的测试类和方法、失败原因分析、归属的后续 Phase
    - _Requirements: R12 AC1, R12 AC2_
  - [-] 7.8 Checkpoint: 预期失败记录完成，commit

- [ ] 8. 手工测试
  - [ ] 8.1 验证 `phpunit.xml` 中 14 个 suite 定义完整（`all`、`exceptions`、`cors`、`security`、`twig`、`aws`、`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`）
    - _Requirements: R4 AC2_
  - [ ] 8.2 验证 `all` suite 使用 `<directory>ut</directory>` 后覆盖的测试集合与原逐文件列表一致（无遗漏、无多余）
    - _Requirements: R4 AC2_
  - [ ] 8.3 验证 `composer.json` 中 PHP 版本约束、内部依赖版本、PHPUnit 版本均已正确更新
    - _Requirements: R1 AC1, R2 AC1–AC2, R3 AC1_
  - [ ] 8.4 验证 `ut/bootstrap.php` 在 PHP 8.5 下加载无错误，autoloader 正常工作
    - _Requirements: R5 AC1–AC3_
  - [ ] 8.5 Checkpoint: 手工测试全部通过，记录测试结果，commit

- [ ] 9. Code Review
  - [ ] 9.1 委托 `code-reviewer` sub-agent 对当前分支的所有变更进行 code review

---

## Notes

- 执行时须遵循 `spec-execution.md` 中的规范，包括 Pre-execution Review、并行执行策略、Checkpoint 执行标准、Blocker Escalation 规则
- Commit 随 checkpoint 一起执行，每个 top-level task 完成时在 checkpoint sub-task 中 commit
- **CR 决策体现**：
  - CR Q1=B：PHPUnit API 迁移按文件/模块拆分 task（Task 3 Configuration、Task 4 ErrorHandlers+Views+Misc、Task 5 间接依赖修复含 Cookie+Middlewares）
  - CR Q2=B：Silex 可加载性验证合并到 Task 1（composer update task）的 sub-task 1.5 中
  - CR Q3=B：本 spec 只适配 Framework_Independent 文件（Task 3–5），Framework_Dependent 文件的 PHPUnit API 适配留给后续 Phase
- **不修改 Framework_Dependent 测试文件**：`ut/SilexKernelTest.php`、`ut/SilexKernelWebTest.php`、`ut/Cors/`、`ut/Security/`、`ut/Twig/`、`ut/AwsTests/`、`ut/Integration/` 等文件的 PHPUnit API 适配不在本 spec scope 内
- **`oasis/logging` 和 `oasis/utils` 版本号**：Design 中 `^3.0` 为示意，实际版本号在 Task 1.4 执行 `composer update` 时确认
- **间接框架依赖修复策略**：Task 5 的具体修复路径取决于 Task 1.5 的 Silex 可加载性验证结果，Design §5 提供了多种备选方案
- **`\Twig_Environment` mock**：Task 4.3 中 `DefaultHtmlRendererTest` 的 Twig mock 如果因 PHP 8.5 下类不存在而失败，该测试归入 Framework_Dependent 范畴，从 `views` suite 移出
- 当前环境为 PHP 8.5，执行 `composer update` 和 `vendor/bin/phpunit` 时直接使用系统 PHP

---

## Socratic Review

**Q: Task 粒度是否合适？9 个 top-level task 是否过多或过少？**
A: Task 1（依赖变更）→ Task 2（配置适配）→ Task 3–4（PHPUnit API 迁移，按模块拆分）→ Task 5（间接依赖修复）→ Task 6（全量验证）→ Task 7（预期失败记录）→ Task 8（手工测试）→ Task 9（Code Review）。每个 task 有明确的交付物和验证标准，粒度适中。

**Q: 为什么 PHPUnit API 迁移拆成 Task 3 和 Task 4 两个 top-level task？**
A: CR Q1=B 要求按文件/模块拆分。Configuration 模块有 8 个文件且变更类型最丰富（涵盖 R6–R8、R10），独立为 Task 3。ErrorHandlers + Views + Misc 模块文件较少且变更类型较简单，合并为 Task 4。这样每个 task 的工作量均衡，且完成后可立即通过对应 suite 验证。

**Q: Task 5（间接框架依赖修复）为什么不合并到 Task 3 或 Task 4 中？**
A: 间接框架依赖修复的具体方案取决于 Task 1.5 的 Silex 可加载性验证结果，属于条件性修复。独立为 Task 5 可以在 Task 3–4 完成后、全量验证前集中处理，逻辑更清晰。

**Q: Task 6 的全量验证是否与 Task 3–5 的 checkpoint 重复？**
A: 不重复。Task 3–5 的 checkpoint 验证各自模块的适配结果，Task 6 是全量 Framework_Independent_Suite 的端到端验证，确保所有模块组合后仍然通过。这是 R11 的直接验证。

**Q: CR Q3=B（只适配 Framework_Independent 文件）是否会导致后续 Phase 工作量增加？**
A: 会略微增加，但这是用户的明确选择。好处是本 spec 的 scope 更清晰，每个 Phase 只改自己 scope 内的文件，避免跨 Phase 的变更交叉。Framework_Dependent 文件的 PHPUnit API 适配工作量不大（主要是 `setUp(): void` 和 `assertContains` 迁移），可以在各自 Phase 的框架替换过程中一并完成。

**Q: 是否遗漏了 requirements 中的任何 AC？**
A: 逐条核对：R1（Task 1.1, 1.4）、R2（Task 1.2, 1.4）、R3（Task 1.3, 1.4）、R4（Task 2.1–2.5）、R5（Task 2.6）、R6（Task 3.1–3.8, 4.1–4.2）、R7（Task 3.1, 3.5–3.8, 4.4）、R8（Task 3.1）、R9（Task 4.3）、R10（Task 3.1, 4.1, 4.3, 4.5, 6.1）、R11（Task 3.9, 4.6, 5.3, 6.2）、R12（Task 1.5, 7.1–7.7）。无遗漏。

**Q: Task 之间的依赖顺序是否正确？**
A: Task 1（composer update）→ Task 2（phpunit.xml + bootstrap）→ Task 3–4（PHPUnit API 迁移，可并行但建议顺序执行以便逐步验证）→ Task 5（间接依赖修复，依赖 Task 1.5 结果）→ Task 6（全量验证）→ Task 7（预期失败记录）→ Task 8（手工测试）→ Task 9（Code Review）。无循环依赖，顺序合理。


## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ✅ 通过

### 修正项
无

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号 R1–R12、design section 引用、Glossary 术语）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task（Task 9）是 Code Review
- [x] 倒数第二个 top-level task（Task 8）是手工测试
- [x] 自动化实现 task（Task 1–7）排在手工测试和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 序号连续（1–9）
- [x] sub-task 层级序号连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements.md 中的每条 requirement（R1–R12）至少被一个 task 引用
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在
- [x] top-level task 按依赖关系排序（Task 1 → 2 → 3–4 → 5 → 6 → 7 → 8 → 9）
- [x] 无循环依赖
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint（Task 9 Code Review 为委托任务，豁免）
- [x] checkpoint 包含具体验证命令和 commit 动作
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task（Task 8）存在，覆盖关键场景
- [x] Code Review 是最后一个 top-level task，委托给 code-reviewer sub-agent
- [x] `## Notes` section 存在
- [x] Notes 明确提到遵循 `spec-execution.md`
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含 spec 特有的执行要点（CR 决策体现、Framework_Dependent 不修改、版本号说明、间接依赖修复策略、Twig mock 注意事项、PHP 环境说明）
- [x] `## Socratic Review` 存在且覆盖充分（粒度、模块拆分理由、间接依赖独立性、全量验证必要性、CR Q3 影响、AC 覆盖完整性、依赖顺序）
- [x] Design CR 决策在 tasks 中得到体现（Q1=B 按模块拆分 Task 3–5、Q2=B Silex 可加载性验证在 Task 1.5、Q3=B 只适配 Framework_Independent 文件）
- [x] Design 全覆盖（§1–§7 均有对应 task）
- [x] 每个 sub-task 描述自包含，可凭 task 描述 + Ref 完成实现
- [x] checkpoint + 手工测试 + code review 构成完整验收闭环
- [x] 执行路径无歧义，task 排序和依赖关系清晰
