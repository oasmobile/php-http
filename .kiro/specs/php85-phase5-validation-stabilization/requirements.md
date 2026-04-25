# Requirements Document

> PHP 8.5 Phase 5: Validation & Stabilization — `.kiro/specs/php85-phase5-validation-stabilization/`

---

## Introduction

Phase 0–4 已完成内部依赖升级（PHPUnit 13.x、oasis/utils ^2.0、oasis/logging ^2.0）、框架替换（Silex → Symfony MicroKernel + Symfony 7.x）、Twig 3.x / Guzzle 7.x 升级、Security 组件 authenticator 系统重写，以及 PHP 语言层面 breaking changes 修复和代码现代化。项目代码已在 PHP 8.5 下可编译运行，`phpunit` 全量通过且无 deprecation notice。

本 Phase 作为 PHP 8.5 升级的收尾阶段，目标分为四部分：

1. **内部依赖 ^3.0 升级**：将 `oasis/utils` 和 `oasis/logging` 从 `^2.0` 升级到 `^3.0`，排查并适配所有 API 变更
2. **静态分析引入**：引入 PHPStan 并配置到 level 8，发现并修复潜在类型问题；如果问题量过大，与用户商量是否降到 level 5
3. **全量验证**：作为 `feature/php85-upgrade` 分支级 DoD 的最终验证点，确保所有测试通过、PHPStan 通过、无 deprecation notice
4. **文档全面 review 与更新**：全面更新 `PROJECT.md`、`README.md`，全面 review 并更新 `docs/state/`（SSOT）和 `docs/manual/`，确保所有文档准确反映 Phase 0–5 完成后的系统现状

**关键决策**（来自 goal.md Clarification）：

- Q1=A: `oasis/utils` ^3.0 直接升级 + 逐一修复
- Q2=A: `oasis/logging` ^3.0 直接升级 + 按需适配
- Q3=B: PHPStan level 8，如果问题量过大则与用户商量是否降到 level 5
- Q4=移除: 不配置 CI
- Q5=A+: 全面更新 `PROJECT.md` + 全面 review `docs/state/` 和 `docs/manual/`

**不涉及的内容**：

- 不引入新功能
- 不进行代码现代化重构（已在 Phase 4 完成）
- 不涉及性能优化
- 不配置 CI
- 不变更公共 API 的外部行为（除 `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 升级可能带来的必要适配外）

**约束**：

- C-1: 在 `feature/php85-upgrade` 分支上推进
- C-2: 依赖 Phase 0–4 全部完成（测试框架可用、框架已替换、Twig/Guzzle 已升级、Security 已重写、语言适配已完成）
- C-3: PBT 使用 Eris 1.x
- C-4: spec 级 DoD：tasks 全部完成 + `phpunit` 全量通过 + PHPStan 通过 + 无 deprecation notice
- C-5: 本 Phase 是 branch 级 DoD 的最终验证点，全部通过后 `feature/php85-upgrade` merge 回 develop
- C-6: `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 升级是用户明确要求的新增 scope（超出 PRP-007 原始范围）
- C-7: 本 Phase 完成后，整个 PHP 8.5 升级工作结束，各 Phase 的 proposal 可标记为 `implemented`

---

## Glossary

- **MicroKernel**: 项目的核心 HTTP 内核类（`src/MicroKernel.php`），继承 Symfony `HttpKernel`，通过 bootstrap config 数组驱动初始化
- **Oasis_Utils**: 内部工具库（`oasis/utils`），提供 ArrayDataProvider、DataProviderInterface、AbstractDataProvider、StringUtils、DataValidationException、ExistenceViolationException 等
- **Oasis_Logging**: 内部日志库（`oasis/logging`），提供 LocalFileHandler 等日志处理器
- **ArrayDataProvider**: Oasis_Utils 提供的配置数据容器类，广泛用于 MicroKernel 和各 Service Provider 的配置传递；核心 API 包括 `get()`、`has()`、`getOptional()`
- **DataProviderInterface**: Oasis_Utils 提供的数据提供者接口，ArrayDataProvider 实现此接口
- **AbstractDataProvider**: Oasis_Utils 提供的数据提供者抽象基类
- **StringUtils**: Oasis_Utils 提供的字符串工具类，用于测试文件
- **DataValidationException**: Oasis_Utils 提供的数据校验异常类，用于 ExceptionWrapper 的异常类型判断
- **ExistenceViolationException**: Oasis_Utils 提供的存在性违规异常类，用于 ExceptionWrapper 的异常类型判断
- **LocalFileHandler**: Oasis_Logging 提供的本地文件日志处理器，仅在 `ut/bootstrap.php` 中使用
- **PHPStan**: PHP 静态分析工具，通过类型推断和控制流分析发现代码中的潜在错误
- **PHPStan_Level**: PHPStan 的分析严格程度级别（0–9），数字越大越严格；level 8 要求所有方法调用和属性访问的类型必须明确
- **PHPStan_Baseline**: PHPStan 的基线文件，记录已知的分析错误，允许在不修复历史问题的情况下对新代码执行严格检查
- **ExceptionWrapper**: 异常包装器（`src/ErrorHandlers/ExceptionWrapper.php`），使用 Oasis_Utils 的 DataValidationException 和 ExistenceViolationException
- **Architecture_Doc**: 架构文档（`docs/state/architecture.md`），记录系统架构与模块划分，属于 SSOT
- **PROJECT_Doc**: 项目文档（`PROJECT.md`），记录技术栈、构建命令、测试 suite 等项目元信息
- **All_Suite**: PHPUnit 默认全量测试（不指定 `--testsuite` 时运行 `phpunit.xml` 中所有 testsuite），包含 `ut/` 目录下所有测试
- **PBT**: Property-Based Testing，使用 Eris 1.x 生成随机输入验证系统属性
- **PBT_Suite**: `phpunit.xml` 中定义的 `pbt` 测试套件（`--testsuite pbt`），包含 `ut/PBT/` 目录下所有 PBT 测试
- **Public_API**: 库对外暴露的公共接口集合，包括 routing 配置、controller 参数注入、view handler、security 配置、CORS 配置、bootstrap config 结构、error handling 行为。使用者通过这些接口与库交互
- **Migration_Guide**: 迁移指南（PRP-008），记录 2.5 → 3.x 升级中的 breaking changes 和迁移步骤

---

## Requirements

### Requirement 1: `oasis/utils` ^3.0 升级

**User Story:** 作为库维护者，我希望将 `oasis/utils` 从 `^2.0` 升级到 `^3.0`，以便项目使用最新版本的内部工具库。

#### Acceptance Criteria

1. WHEN `composer require oasis/utils:^3.0` is executed, THE Composer SHALL successfully resolve and install the `^3.0` version
2. IF Oasis_Utils `^3.0` introduces breaking API changes to ArrayDataProvider, DataProviderInterface, or AbstractDataProvider, THEN THE affected code in `src/` and `ut/` SHALL be adapted to the new API
3. IF Oasis_Utils `^3.0` introduces breaking API changes to StringUtils, THEN THE affected code in `ut/` SHALL be adapted to the new API
4. IF Oasis_Utils `^3.0` introduces breaking API changes to DataValidationException or ExistenceViolationException, THEN THE ExceptionWrapper SHALL be adapted to the new API
5. WHEN `phpunit` is executed after the upgrade, THE test runner SHALL report all tests passing
6. THE `composer.json` SHALL declare `oasis/utils` version constraint as `^3.0`

### Requirement 2: `oasis/logging` ^3.0 升级

**User Story:** 作为库维护者，我希望将 `oasis/logging` 从 `^2.0` 升级到 `^3.0`，以便项目使用最新版本的内部日志库。

#### Acceptance Criteria

1. WHEN `composer require oasis/logging:^3.0` is executed, THE Composer SHALL successfully resolve and install the `^3.0` version
2. IF Oasis_Logging `^3.0` introduces breaking API changes to LocalFileHandler, THEN THE `ut/bootstrap.php` SHALL be adapted to the new API
3. WHEN `phpunit` is executed after the upgrade, THE test runner SHALL report all tests passing
4. THE `composer.json` SHALL declare `oasis/logging` version constraint as `^3.0`

### Requirement 3: PHPStan 引入与配置

**User Story:** 作为库维护者，我希望引入 PHPStan 静态分析工具并配置到合理级别，以便发现代码中的潜在类型问题。

#### Acceptance Criteria

1. THE `composer.json` SHALL declare `phpstan/phpstan` as a dev dependency in `require-dev`
2. THE project root SHALL contain a `phpstan.neon` (or `phpstan.neon.dist`) configuration file
3. THE PHPStan configuration SHALL set analysis level to PHPStan_Level 8
4. THE PHPStan configuration SHALL include `src/` as analysis path
5. IF PHPStan_Level 8 produces an excessive number of errors that cannot be reasonably fixed, THEN THE level MAY be negotiated down to PHPStan_Level 5 with user approval
6. WHEN `vendor/bin/phpstan analyse` is executed, THE tool SHALL complete without configuration errors

### Requirement 4: PHPStan 错误修复

**User Story:** 作为库维护者，我希望修复 PHPStan 发现的所有类型问题，以确保代码的类型安全性。

#### Acceptance Criteria

1. WHEN `vendor/bin/phpstan analyse` is executed at the configured level, THE tool SHALL report zero errors
2. IF a PHPStan error requires code modification to fix, THEN THE modification SHALL NOT change the external behavior of the affected method or class
3. IF a PHPStan error is a false positive that cannot be resolved without compromising code quality, THEN THE error SHALL be suppressed via PHPStan_Baseline or inline `@phpstan-ignore` annotation with justification comment
4. FOR ALL PHPStan fixes, THE existing test suite SHALL continue to pass without behavior changes

### Requirement 5: 全量测试通过

**User Story:** 作为库维护者，我希望 Phase 5 完成后 `phpunit` 全量通过，以确认所有升级和修复工作成功。

#### Acceptance Criteria

1. WHEN All_Suite is executed under PHP 8.5, THE test runner SHALL report all tests passing
2. WHEN PBT_Suite is executed, THE PBT tests SHALL pass
3. FOR ALL existing test suites defined in `phpunit.xml`, THE tests SHALL continue to pass

### Requirement 6: 零 Deprecation Notice 验证

**User Story:** 作为库使用者，我希望代码在 PHP 8.5 下运行时不产生任何 deprecation notice，以确保升级后的代码质量。

#### Acceptance Criteria

1. WHEN All_Suite is executed under PHP 8.5, THE test runner SHALL report zero deprecation notices from project code（`src/` 和 `ut/`）
2. THE codebase SHALL NOT contain any PHP 8.x deprecated syntax or API usage
3. IF third-party dependencies produce deprecation notices, THEN THE notices SHALL be documented but not required to be fixed in this Phase

### Requirement 7: PHPStan 通过验证

**User Story:** 作为库维护者，我希望 PHPStan 静态分析在配置级别下零错误通过，作为 branch 级 DoD 的验证条件之一。

#### Acceptance Criteria

1. WHEN `vendor/bin/phpstan analyse` is executed at the configured level, THE tool SHALL report zero errors（或仅有 baseline 中记录的已知 false positive）
2. THE PHPStan analysis result SHALL be reproducible（相同代码 + 相同配置 = 相同结果）

### Requirement 8: `PROJECT.md` 全面更新

**User Story:** 作为开发者，我希望 PROJECT_Doc 准确反映 Phase 0–5 完成后的技术栈和项目信息，以便新成员快速了解项目现状。

#### Acceptance Criteria

1. THE PROJECT_Doc 的技术栈表格 SHALL 反映当前实际版本：PHP ≥ 8.5、Symfony MicroKernel（Symfony 7.x 组件）、Twig 3.x、Guzzle 7.x、PHPUnit 13.x、oasis/utils ^3.0、oasis/logging ^3.0
2. THE PROJECT_Doc 的核心入口 SHALL 引用 `MicroKernel`（`src/MicroKernel.php`），移除对 `SilexKernel` 的引用
3. THE PROJECT_Doc 的构建与测试命令 SHALL 包含 PHPStan 分析命令
4. THE PROJECT_Doc 的测试 Suite 表格 SHALL 反映当前 `phpunit.xml` 中定义的所有 suite
5. THE PROJECT_Doc SHALL NOT contain any references to Silex, Pimple, or other replaced technologies
6. THE PROJECT_Doc 的项目描述 SHALL 反映当前框架为 Symfony MicroKernel

### Requirement 9: `README.md` 更新

**User Story:** 作为库使用者，我希望 `README.md` 反映当前的 PHP 版本要求和依赖版本，以便正确使用本库。

#### Acceptance Criteria

1. THE `README.md` SHALL reflect the current PHP version requirement（`>=8.5`）
2. THE `README.md` SHALL reflect the current framework（Symfony MicroKernel）
3. THE `README.md` SHALL NOT contain references to Silex or other replaced technologies
4. IF `README.md` contains dependency version information, THEN THE versions SHALL match `composer.json`

### Requirement 10: `docs/state/` SSOT 全面 Review 与更新

**User Story:** 作为开发者，我希望 `docs/state/` 中的架构文档准确反映 Phase 0–5 完成后的系统现状，以保持 SSOT 的可靠性。

#### Acceptance Criteria

1. THE Architecture_Doc SHALL 准确反映当前的模块结构、类层次和公共 API 签名
2. THE Architecture_Doc SHALL NOT contain references to Silex, Pimple, Symfony 4.x, or other replaced technologies
3. THE Architecture_Doc 的技术栈描述 SHALL 与 `composer.json` 中的依赖版本一致
4. IF Architecture_Doc 中存在与当前代码不一致的描述（如过时的类名、方法签名、流程描述）, THEN THE Architecture_Doc SHALL be corrected
5. FOR ALL files in `docs/state/`, THE content SHALL be reviewed and updated to reflect the current system state

### Requirement 11: `docs/manual/` 使用文档全面 Review 与更新

**User Story:** 作为库使用者，我希望 `docs/manual/` 中的使用文档与当前系统行为一致，以便正确理解和使用本库。

#### Acceptance Criteria

1. FOR ALL files in `docs/manual/`, THE content SHALL be reviewed for accuracy against the current codebase
2. WHEN a manual document references API, configuration, or behavior that has changed in Phase 0–5, THE document SHALL be updated to reflect the current state
3. THE manual documents SHALL NOT contain references to Silex, Pimple, or other replaced technologies
4. THE manual documents 中的代码示例 SHALL be valid under the current framework and PHP version
5. THE manual documents 中的配置说明 SHALL match the current `MicroKernel` bootstrap config structure

### Requirement 12: PBT — `oasis/utils` ^3.0 升级后 API 兼容性验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 `oasis/utils` ^3.0 升级后，项目中使用的核心 API 行为保持正确。

#### Acceptance Criteria

1. FOR ALL valid configuration arrays, THE ArrayDataProvider constructed from the array SHALL correctly retrieve values via `get()` / `has()` / `getOptional()` methods（round-trip property：构造 → 取值 → 与原始数据一致）
2. FOR ALL valid key-value pairs stored in ArrayDataProvider, THE `get()` method SHALL return the stored value, and `has()` SHALL return `true`（invariant property：存入的数据可正确取出）
3. FOR ALL non-existent keys, THE ArrayDataProvider `get()` SHALL throw an exception, and `has()` SHALL return `false`（error condition property）

### Requirement 13: 公共 API 兼容性验证（2.5 → 3.x）

**User Story:** 作为库使用者，我希望确认 2.5 版本的公共 API（routing、controller、view、security、CORS、bootstrap config、error handling）在 3.x 下行为一致，或有明确的迁移说明，以便无缝升级。

#### Acceptance Criteria

1. WHEN a 2.5-compatible routing configuration（route registration via config array）is used with 3.x MicroKernel, THE routing SHALL resolve and dispatch requests identically
2. WHEN a 2.5-compatible controller（parameter injection, return types）is used with 3.x MicroKernel, THE controller SHALL execute without errors and produce identical responses
3. WHEN a 2.5-compatible view handler configuration（JSON, HTML, Twig rendering）is used with 3.x, THE view rendering SHALL produce identical output
4. WHEN a 2.5-compatible security configuration（firewall rules, access rules, authenticator）is used with 3.x, THE security layer SHALL enforce identical access control behavior
5. WHEN a 2.5-compatible CORS configuration is used with 3.x, THE CORS headers SHALL be identical
6. WHEN a 2.5-compatible bootstrap config array is used with 3.x MicroKernel, THE kernel SHALL initialize without errors
7. WHEN a 2.5-compatible error handling scenario（exceptions triggering ExceptionWrapper / JsonErrorHandler）occurs in 3.x, THE error response SHALL be identical in structure and HTTP status code
8. IF any public API behavior differs between 2.5 and 3.x, THEN THE difference SHALL be documented as a breaking change for inclusion in PRP-008 migration guide


---

## Socratic Review

**Q1: Requirements 是否完整覆盖了 goal.md 的所有目标？**
A: 是。goal.md 列出的目标逐一对应：(1) oasis/utils ^3.0 升级 → R1; (2) oasis/logging ^3.0 升级 → R2; (3) PHPStan 引入与配置 → R3; (4) PHPStan 错误修复 → R4; (5) 全量测试通过 → R5; (6) 零 deprecation notice → R6; (7) PHPStan 通过验证 → R7; (8) PROJECT.md 更新 → R8; (9) README.md 更新 → R9; (10) docs/state/ review → R10; (11) docs/manual/ review → R11。PBT 验证 → R12。公共 API 兼容性验证 → R13（用户在 design 阶段讨论后新增）。全部覆盖。

**Q2: Clarification 决策是否已体现在 Requirements 中？**
A: Q1=A（直接升级 oasis/utils）→ R1 AC1 使用 `composer require` 直接升级；Q2=A（直接升级 oasis/logging）→ R2 AC1 使用 `composer require` 直接升级；Q3=B（PHPStan level 8，可降级）→ R3 AC3 设定 level 8，AC5 允许降级到 level 5；Q4=移除（不配置 CI）→ Introduction 不涉及内容中明确排除；Q5=A+（全面更新 + 全面 review）→ R8–R11 分别覆盖 PROJECT.md、README.md、docs/state/、docs/manual/。五个决策均已体现。

**Q3: R1 和 R2 为什么使用 IF...THEN 模式而非直接列出具体 API 变更？**
A: 因为 `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 的具体 API 变更在 requirements 阶段尚未确定——需要在 design 阶段实际执行 `composer require` 后才能知道哪些 API 发生了变化。使用 IF...THEN 模式（EARS Unwanted Event pattern）确保无论 ^3.0 引入什么变更，都有对应的适配要求。具体的适配清单将在 design 阶段通过编译错误和测试失败确定。

**Q4: R3 和 R4 为什么分开而非合并为一个 Requirement？**
A: R3 聚焦于 PHPStan 的引入和配置（工具层面），R4 聚焦于修复 PHPStan 发现的错误（代码层面）。两者的验收标准不同：R3 验证的是"工具能正常运行"，R4 验证的是"代码通过分析"。分开后 design 和 tasks 阶段可以更清晰地编排工作顺序（先配置工具，再修复问题）。

**Q5: R7 是否与 R3/R4 重复？**
A: 不重复。R3 是"引入和配置 PHPStan"，R4 是"修复 PHPStan 发现的错误"，R7 是"作为 branch 级 DoD 验证条件的最终确认"。R7 的定位是验证标准（与 R5 全量测试通过、R6 零 deprecation notice 并列），确保 PHPStan 通过是 merge 回 develop 的前提条件之一。

**Q6: R8–R11 的文档更新范围是否过大？**
A: 不过大。经过 Phase 0–4 的全面改造（框架替换、依赖升级、Security 重写、语言适配），PROJECT.md 中的技术栈描述已严重过时（仍写着 PHP ≥ 7.0、Silex 2.x、Twig 1.x 等）。docs/state/architecture.md 虽然在各 Phase 中有局部更新，但需要一次全面 review 确保一致性。docs/manual/ 中的代码示例和配置说明可能引用了已替换的 API。作为收尾 Phase，全面 review 是必要的。

**Q7: PBT（R12）为什么只覆盖 oasis/utils 而不覆盖 oasis/logging？**
A: oasis/logging 在项目中仅在 `ut/bootstrap.php` 使用 `LocalFileHandler` 进行测试环境日志初始化，使用面极窄且逻辑简单。对其进行 PBT 测试的价值很低——100 次迭代不会比 1-2 次发现更多问题。相比之下，oasis/utils 的 `ArrayDataProvider` 广泛用于配置传递，输入空间有意义的变化（不同类型的 key、不同嵌套深度的配置数组），适合 PBT 验证。这符合 PBT 决策指南中"测试 YOUR code 的逻辑"和"100 次迭代能发现更多 bug"的标准。

**Q8: PHPStan 为什么只分析 `src/` 而不包含 `ut/`？**
A: R3 AC4 将分析路径设为 `src/`，这是因为测试代码（`ut/`）中大量使用 mock、stub 和测试辅助类，PHPStan 对测试代码的分析往往产生大量 false positive（如 mock 对象的类型推断、测试中的故意类型错误等）。将 `ut/` 纳入分析会显著增加噪音，降低 PHPStan 的实用价值。如果 design 阶段评估后认为 `ut/` 也应纳入，可以在 design 中扩展范围。

**Q9: Requirements 之间是否存在矛盾或重叠？**
A: 无矛盾。R1–R2 是依赖升级，R3–R4 是静态分析，R5–R7 是验证标准，R8–R11 是文档更新，R12 是 PBT。各 Requirement 关注层次不同。R5（全量测试通过）与 R1 AC5、R2 AC3 在测试通过要求上有关联——R1/R2 要求各自升级后测试通过，R5 要求最终全量通过，属于递进关系而非重复。

**Q10: 与 PRP-007 的 scope 是否一致？**
A: 基本一致，有两个明确的扩展：(1) PRP-007 原始范围不包含 oasis/utils ^3.0 和 oasis/logging ^3.0 升级，由用户在 goal.md 中明确要求新增（R1、R2）；(2) PRP-007 的文档更新范围仅提到"更新项目文档"，用户在 Q5 中扩展为全面 review docs/state/ 和 docs/manual/（R10、R11）。PRP-007 中的 CI 配置目标已由用户决定移除（Q4）。用户指令优先于 proposal。

**Q11: 是否存在隐含的前置假设？**
A: 有两个隐含假设：(1) `oasis/utils` ^3.0 和 `oasis/logging` ^3.0 已发布且可通过 Composer 安装——如果尚未发布，R1/R2 将无法执行；(2) PHPStan 与当前项目的 PHP 8.5 + Symfony 7.x 技术栈兼容——PHPStan 对 PHP 8.5 的支持取决于其版本。这两个假设在 design 阶段执行 `composer require` 时会自然验证，无需在 requirements 中显式约束。


---

## Gatekeep Log

**校验时间**: 2026-04-25
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [术语] 移除 7 个孤立术语（ConfigurationValidationTrait、CacheableRouterProvider、SimpleSecurityProvider、SimpleFirewall、SimpleAccessRule、CrossOriginResourceSharingStrategy、SimpleTwigServiceProvider）——这些术语仅在 Glossary 中定义，未在任何 AC 中作为 Subject 使用。它们承载的信息（哪些类使用 Oasis_Utils）已在 goal.md 背景摘要中记录，design 阶段可从 goal.md 获取
- [术语] 在 ArrayDataProvider 的 Glossary 定义中补充核心 API 方法（`get()`、`has()`、`getOptional()`），使 R12 AC 中引用的方法有术语表支撑
- [语体] R3 AC1 中英混杂（`THE composer.json SHALL 在 require-dev 中添加...`）→ 统一为 EARS 语体（`THE composer.json SHALL declare phpstan/phpstan as a dev dependency in require-dev`）
- [语体] R3 AC3/AC5 中 `level 8` / `level 5` 改为引用 Glossary 术语 PHPStan_Level 8 / PHPStan_Level 5，保持术语使用一致性
- [语体] R5 AC1/AC2、R6 AC1 中 `phpunit` / `phpunit --testsuite pbt` 改为引用 Glossary 术语 All_Suite / PBT_Suite，消除 All_Suite 和 PBT_Suite 的孤立术语问题
- [内容] Socratic Review 补充 Q11（隐含前置假设分析），覆盖 steering 要求的"隐含假设"维度

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表中的术语在正文中使用）
- [x] 无 markdown 格式错误

**结构校验**
- [x] 一级标题 `# Requirements Document` 存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空
- [x] Requirements section 存在且包含 13 条 requirement
- [x] Socratic Review 存在且覆盖充分（11 个 Q&A）
- [x] 各 section 之间使用 `---` 分隔

**术语表校验**
- [x] Glossary 中的术语在正文 AC 中被实际使用（已移除孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 术语格式为 `- **Term**: 定义`

**Requirement 条款校验**
- [x] 每条 requirement 包含 User Story 和 Acceptance Criteria
- [x] User Story 使用中文行文（`作为 <角色>，我希望 <能力>，以便 <价值>`）
- [x] AC 使用 EARS 语体（THE...SHALL / WHEN...THEN / IF...THEN / FOR ALL）
- [x] Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续，无跳号

**内容边界校验**
- [x] AC 聚焦外部可观察行为，未混入实现策略
- [x] 引用的具体名称（composer.json、phpstan.neon、src/）属于 proposal 已明确的选型或标准配置文件，非实现细节
- [x] R12 PBT AC 中引用的 `get()` / `has()` / `getOptional()` 是 ArrayDataProvider 的公共 API（已在 Glossary 中定义），用于描述行为属性而非实现

**目的性审查**
- [x] Goal CR 回应：goal.md 中 5 个 Clarification 决策（Q1–Q5）均已体现在 Requirements 中
- [x] Goal 清晰度：Introduction 清楚传达了 Phase 5 的四部分目标
- [x] Non-goal / Scope 边界：明确列出 5 项不涉及内容，无模糊地带
- [x] 完成标准：C-4 定义了 spec 级 DoD，R5/R6/R7 构成验证三件套
- [x] 可 design 性：requirements 提供了充分信息，design 阶段可开始技术方案设计

### Clarification Round

**状态**: 已完成

**Q1:** R4 AC3 允许通过 PHPStan_Baseline 或 `@phpstan-ignore` 抑制 false positive。如果 PHPStan level 8 下存在较多 false positive（例如 10+ 个），倾向于哪种抑制策略？

- A) 优先使用 PHPStan_Baseline 集中管理，仅在 baseline 无法覆盖的场景使用 inline annotation
- B) 优先使用 inline `@phpstan-ignore` annotation，每处附带 justification comment，便于代码阅读时理解上下文
- C) 混合使用：对第三方库类型问题用 baseline，对项目代码中的 false positive 用 inline annotation
- D) 其他（请说明）

**A:** A — 优先使用 PHPStan_Baseline 集中管理，仅在 baseline 无法覆盖的场景使用 inline annotation

**Q2:** R8–R11 要求全面更新 PROJECT.md、README.md、docs/state/、docs/manual/。文档更新与代码变更（R1–R4）之间的执行顺序会影响 design 编排。文档更新应在什么时机执行？

- A) 所有代码变更（R1–R4）和验证（R5–R7）完成后，最后统一更新文档
- B) 代码变更和文档更新交叉进行——每完成一个代码变更就更新相关文档
- C) 文档更新作为独立的最后阶段，但在 PHPStan 引入后、全量验证前执行（因为文档更新不影响代码）
- D) 其他（请说明）

**A:** B — 代码变更和文档更新交叉进行，每完成一个代码变更就更新相关文档

**Q3:** R1 AC2 要求适配 Oasis_Utils ^3.0 的 breaking API changes。如果 ^3.0 对 ArrayDataProvider 的 `get()` 方法签名做了不兼容变更（如返回类型从 `mixed` 变为 `string|int|array`），且项目中有大量调用点，适配策略倾向于：

- A) 逐一修改所有调用点以匹配新签名，即使改动量大
- B) 在项目中引入一个 adapter / wrapper 层，封装 ^3.0 的新 API，减少对现有代码的侵入
- C) 视具体变更规模决定——小规模直接改，大规模引入 adapter
- D) 其他（请说明）

**A:** A — 逐一修改所有调用点以匹配新签名，即使改动量大

**Q4:** R3 AC4 将 PHPStan 分析路径设为 `src/`，不包含 `ut/`（测试代码）。Design 阶段是否需要评估将 `ut/` 纳入分析的可行性，还是直接确定只分析 `src/`？

- A) 直接确定只分析 `src/`，不评估 `ut/`
- B) Design 阶段先对 `ut/` 做一次试探性分析，根据 false positive 数量决定是否纳入
- C) 分析 `src/` + `ut/`，但对 `ut/` 使用较低的 PHPStan_Level（如 level 5）
- D) 其他（请说明）

**A:** A — 直接确定只分析 `src/`，不评估 `ut/`
