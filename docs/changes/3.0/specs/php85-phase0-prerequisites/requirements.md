# Requirements Document

> PHP 8.5 升级 Phase 0：前置依赖与测试框架升级 — `.kiro/specs/php85-phase0-prerequisites/`

---

## Introduction

`oasis/http` 当前基于 PHP >=7.0，测试框架为 PHPUnit 5.x，内部依赖 `oasis/logging` `^1.1` 和 `oasis/utils` `^1.6`。项目计划升级到 PHP >=8.5，后续 Phase 1–5 将引入框架替换、依赖大版本升级、Security 组件重写等大量 breaking change。

这些变更的正确性验证完全依赖测试套件。当前存在前置阻塞：PHPUnit 5.x 不支持 PHP 8.x，内部依赖也不兼容 PHP 8.5，必须先行升级。

本 spec 的目标是：升级 `composer.json` 中的 PHP 版本约束、内部依赖版本和 PHPUnit 版本，适配所有现有测试文件和 `phpunit.xml` 配置以兼容 PHPUnit 13.x，确保不依赖框架运行时的测试 suite 在 PHP 8.5 下通过。

**不涉及的内容**：

- Silex 框架替换（Phase 1 / PRP-003）
- Symfony 组件升级（Phase 1 / PRP-003）
- Twig、Guzzle 升级（Phase 2 / PRP-004）
- Security 组件重写（Phase 3 / PRP-005）
- PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 现有业务逻辑修改
- 测试覆盖补全（已在 PRP-001 完成）

**约束**：

- C-1: PHP 版本约束直接改到 `>=8.5`，不做中间过渡
- C-2: PHPUnit 直接从 `^5.2` 升到 `^13.0`，不分步
- C-3: `oasis/logging` 和 `oasis/utils` 已有 PHP 8.5 兼容版本，不存在外部阻塞
- C-4: 内部包版本约束使用 `^` 语义化约束，具体版本由 composer 解析
- C-5: spec 级 DoD：tasks 全部完成 + 不依赖框架运行时的 suite 通过
- C-6: 依赖框架运行时的 suite 预期失败，留给后续 Phase 解决
- C-7: 不修改现有业务逻辑，仅做测试框架适配

---

## Glossary

- **Composer_JSON**: 项目根目录的 `composer.json` 文件，定义 PHP 版本约束和所有依赖的版本约束
- **PHPUnit_13**: PHPUnit 13.x 版本，要求 PHP >=8.2，相对于 PHPUnit 5.x 有大量 API breaking change
- **PHPUnit_Config**: 项目根目录的 `phpunit.xml` 配置文件，定义 test suite 结构、bootstrap 路径和 XML schema
- **Test_Adaptation**: 将现有测试代码从 PHPUnit 5.x API 迁移到 PHPUnit 13.x API 的过程
- **Return_Type_Declaration**: PHPUnit 13.x 要求 `setUp()` / `tearDown()` 等 fixture 方法声明 `: void` 返回类型
- **Data_Provider_Attribute**: PHPUnit 13.x 使用 PHP 8 Attribute `#[DataProvider('methodName')]` 替代 `@dataProvider` 注解
- **SetExpectedException_Migration**: PHPUnit 13.x 移除了 `setExpectedException()`，需迁移到 `expectException()` + `expectExceptionMessage()`
- **Mock_API**: PHPUnit 的 mock/stub 创建 API，包括 `getMockBuilder()`、`createMock()` 等方法
- **Framework_Independent_Suite**: 不依赖 Silex/Symfony 框架运行时的测试 suite，包括 `configuration`、`error-handlers`、`views`、`misc`、`exceptions`、`cookie`、`middlewares`
- **Framework_Dependent_Suite**: 依赖 Silex/Symfony 框架运行时的测试 suite，包括 `cors`、`security`、`twig`、`aws`、`routing`、`integration`、`all` 中的 `SilexKernelTest` 等
- **Bootstrap_File**: `ut/bootstrap.php`，PHPUnit 启动时加载的引导文件

---

## Requirements

### Requirement 1: PHP 版本约束升级

**User Story:** 作为迁移开发者，我希望将 PHP 版本约束升级到 >=8.5，以便项目可以在 PHP 8.5 环境下运行。

#### Acceptance Criteria

1. THE Composer_JSON SHALL declare the `php` version constraint as `>=8.5`.
2. WHEN `composer validate` is executed THEN THE Composer_JSON SHALL pass validation without errors.

### Requirement 2: 内部依赖升级

**User Story:** 作为迁移开发者，我希望将 `oasis/logging` 和 `oasis/utils` 升级到 PHP 8.5 兼容版本，以便消除内部依赖对 PHP 8.5 的阻塞。

#### Acceptance Criteria

1. THE Composer_JSON SHALL declare `oasis/logging` with a `^` semantic version constraint pointing to a PHP 8.5 compatible major version.
2. THE Composer_JSON SHALL declare `oasis/utils` with a `^` semantic version constraint pointing to a PHP 8.5 compatible major version.
3. WHEN `composer update oasis/logging oasis/utils` is executed in a PHP 8.5 environment THEN the resolution SHALL succeed without conflicts.

### Requirement 3: PHPUnit 版本升级

**User Story:** 作为迁移开发者，我希望将 PHPUnit 从 5.x 直接升级到 13.x，以便测试框架兼容 PHP 8.5。

#### Acceptance Criteria

1. THE Composer_JSON SHALL declare `phpunit/phpunit` in `require-dev` with constraint `^13.0`.
2. WHEN `composer update phpunit/phpunit` is executed in a PHP 8.5 environment THEN the resolution SHALL succeed.
3. WHEN `vendor/bin/phpunit --version` is executed THEN the output SHALL indicate PHPUnit 13.x.

### Requirement 4: PHPUnit_Config 适配

**User Story:** 作为迁移开发者，我希望 `phpunit.xml` 配置格式兼容 PHPUnit 13.x，以便 PHPUnit 能正确加载并执行测试。

#### Acceptance Criteria

1. THE PHPUnit_Config SHALL use a PHPUnit 13.x compatible XML schema reference.
2. THE PHPUnit_Config SHALL retain all existing test suite definitions (`all`、`exceptions`、`cors`、`security`、`twig`、`aws`、`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`).
3. IF PHPUnit 13.x has deprecated or removed any XML configuration elements used in the current `phpunit.xml` THEN THE PHPUnit_Config SHALL replace them with the PHPUnit 13.x equivalent.
4. THE PHPUnit_Config SHALL reference `ut/bootstrap.php` as the bootstrap file.

### Requirement 5: Bootstrap_File 适配

**User Story:** 作为迁移开发者，我希望测试引导文件兼容 PHP 8.5 和 PHPUnit 13.x，以便测试能正确启动。

#### Acceptance Criteria

1. THE Bootstrap_File SHALL be loadable without errors under PHP 8.5.
2. IF the Bootstrap_File uses any API deprecated or removed in PHP 8.5 THEN THE Bootstrap_File SHALL be updated to use the PHP 8.5 equivalent.
3. THE Bootstrap_File SHALL correctly initialize the autoloader and logging for the test environment.

### Requirement 6: Test_Adaptation — Return_Type_Declaration

**User Story:** 作为迁移开发者，我希望所有测试文件的 fixture 方法声明正确的返回类型，以便兼容 PHPUnit 13.x 的类型要求。

#### Acceptance Criteria

1. WHEN a test class overrides `setUp()` THEN the method signature SHALL include `: void` Return_Type_Declaration.
2. WHEN a test class overrides `tearDown()` THEN the method signature SHALL include `: void` Return_Type_Declaration.
3. WHEN a test class overrides `setUpBeforeClass()` THEN the method signature SHALL include `: void` Return_Type_Declaration.
4. WHEN a test class overrides `tearDownAfterClass()` THEN the method signature SHALL include `: void` Return_Type_Declaration.

### Requirement 7: Test_Adaptation — SetExpectedException_Migration

**User Story:** 作为迁移开发者，我希望所有 `setExpectedException()` 调用迁移到 PHPUnit 13.x API，以便测试能在新版本下正确运行。

#### Acceptance Criteria

1. THE test codebase SHALL contain zero calls to `setExpectedException()`.
2. WHEN the original code uses `setExpectedException(ExceptionClass::class)` THEN the replacement SHALL use `expectException(ExceptionClass::class)`.
3. WHEN the original code uses `setExpectedException(ExceptionClass::class, 'message')` THEN the replacement SHALL use `expectException(ExceptionClass::class)` followed by `expectExceptionMessage('message')`.
4. THE migrated exception expectations SHALL preserve the original test semantics — the same exception class and message (if specified) SHALL be expected.

### Requirement 8: Test_Adaptation — Data_Provider_Attribute

**User Story:** 作为迁移开发者，我希望所有 `@dataProvider` 注解迁移到 PHPUnit 13.x 的 Attribute 语法，以便兼容新版本的 data provider 机制。

#### Acceptance Criteria

1. THE test codebase SHALL contain zero `@dataProvider` doc-block annotations.
2. WHEN the original code uses `@dataProvider methodName` THEN the replacement SHALL use `#[DataProvider('methodName')]` PHP Attribute.
3. WHEN a data provider method exists THEN it SHALL be declared as `public static`.
4. THE migrated data providers SHALL return the same test data sets as the original.

### Requirement 9: Test_Adaptation — Mock_API 兼容

**User Story:** 作为迁移开发者，我希望所有 mock/stub 创建代码兼容 PHPUnit 13.x 的 Mock_API，以便测试中的 test double 能正确工作。

#### Acceptance Criteria

1. IF PHPUnit 13.x has removed or changed any Mock_API methods used in the test codebase THEN the affected calls SHALL be migrated to the PHPUnit 13.x equivalent.
2. THE `getMockBuilder()` chain pattern SHALL remain functional under PHPUnit 13.x, or SHALL be replaced with `createMock()` / `createStub()` where the builder pattern is no longer supported.
3. THE migrated mock expectations (`expects()`, `method()`, `willReturn()`, `willThrowException()`) SHALL preserve the original test semantics.

### Requirement 10: Test_Adaptation — 其他 API 变更

**User Story:** 作为迁移开发者，我希望所有测试代码中使用的 PHPUnit API 均兼容 13.x，以便不遗漏任何 breaking change。

#### Acceptance Criteria

1. IF any assertion method has been renamed or removed in PHPUnit 13.x THEN the affected calls SHALL be migrated to the PHPUnit 13.x equivalent.
2. IF any test class extends a base class that has been removed or renamed in PHPUnit 13.x THEN the `extends` declaration SHALL be updated.
3. IF PHPUnit 13.x requires additional `use` imports for Attributes or other new API THEN the affected test files SHALL include the necessary imports.
4. WHEN all Test_Adaptation requirements (6–10) are applied THEN `vendor/bin/phpunit --testsuite configuration` SHALL execute without PHP fatal errors or PHPUnit configuration warnings.

### Requirement 11: Framework_Independent_Suite 验证

**User Story:** 作为迁移开发者，我希望所有不依赖框架运行时的测试 suite 在 PHP 8.5 + PHPUnit 13.x 下通过，以便确认前置升级未破坏纯逻辑测试。

#### Acceptance Criteria

1. WHEN `vendor/bin/phpunit --testsuite configuration` is executed under PHP 8.5 THEN all tests SHALL pass.
2. WHEN `vendor/bin/phpunit --testsuite error-handlers` is executed under PHP 8.5 THEN all tests SHALL pass.
3. WHEN `vendor/bin/phpunit --testsuite views` is executed under PHP 8.5 THEN all tests SHALL pass.
4. WHEN `vendor/bin/phpunit --testsuite misc` is executed under PHP 8.5 THEN all tests SHALL pass.
5. WHEN `vendor/bin/phpunit --testsuite exceptions` is executed under PHP 8.5 THEN all tests SHALL pass.
6. WHEN `vendor/bin/phpunit --testsuite cookie` is executed under PHP 8.5 THEN all tests SHALL pass.
7. WHEN `vendor/bin/phpunit --testsuite middlewares` is executed under PHP 8.5 THEN all tests SHALL pass.

### Requirement 12: Framework_Dependent_Suite 预期失败确认

**User Story:** 作为迁移开发者，我希望明确记录依赖框架运行时的 suite 在本 Phase 预期失败，以便后续 Phase 有清晰的修复目标。

#### Acceptance Criteria

1. THE Framework_Dependent_Suite（`cors`、`security`、`twig`、`aws`、`routing`、`integration`）SHALL be expected to fail under PHP 8.5 due to Silex/Symfony 4.x/Twig 1.x incompatibility.
2. WHEN a Framework_Independent_Suite test unexpectedly fails due to indirect framework dependency THEN the test SHALL be identified and documented, and the failure SHALL be deferred to the appropriate subsequent Phase.

---

## Socratic Review

**Q: 为什么 PHP 版本约束直接改到 >=8.5 而不是 >=8.2（PHPUnit 13 的最低要求）？**
A: goal.md Clarification Q1 中用户明确选择了方案 C — 直接改到 `>=8.5`。项目的最终目标是 PHP 8.5，中间过渡版本没有实际意义，只会增加后续再次修改的成本。

**Q: PHPUnit 5 → 13 跨度极大，为什么不分步升级（如 5 → 10 → 13）？**
A: goal.md Clarification Q2 中用户明确选择了方案 A — 直接一步到位。分步升级意味着每一步都要做一轮 API 适配，而中间版本的适配工作在最终升级到 13 时会被覆盖，属于浪费。直接升级虽然单次适配工作量大，但总工作量更小。

**Q: `oasis/logging` 和 `oasis/utils` 的具体目标版本号是什么？**
A: goal.md Clarification Q3/Q4 中用户确认这两个包已有 PHP 8.5 兼容版本，且选择保持 `^` 约束，具体版本由 `composer update` 解析。Requirements 中不硬编码版本号，只要求使用 `^` 语义化约束指向兼容版本。

**Q: Test_Adaptation 拆成多个 Requirement（6–10）是否过于细碎？**
A: PHPUnit 5 → 13 的 API breaking change 涉及多个独立维度：fixture 方法返回类型、异常期望 API、data provider 机制、mock API、其他 assertion 变更。每个维度的适配规则不同，拆分后每个 Requirement 的 AC 更聚焦、更可测试。合并成一个大 Requirement 会导致 AC 过多且混杂。

**Q: Requirement 12 的"预期失败确认"是否属于 Non-Goal？**
A: 不是。虽然修复这些失败是后续 Phase 的工作，但本 Phase 需要明确识别哪些 suite 预期失败、哪些意外失败。意外失败（如某个"纯逻辑"测试间接依赖了框架类）需要在本 Phase 识别并记录，以便正确归类到后续 Phase。这是 DoD 的一部分。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: 不存在矛盾。Requirement 1–3 是依赖版本变更，Requirement 4–5 是配置/引导适配，Requirement 6–10 是测试代码适配（按 API 维度拆分，互不重叠），Requirement 11 是通过性验证，Requirement 12 是失败预期记录。它们形成一个线性依赖链：1–3 → 4–5 → 6–10 → 11–12。

**Q: 与 proposal（PRP-002）的 scope 是否一致？**
A: 完全一致。PRP-002 定义的 Goals（升级内部依赖、升级 PHPUnit、适配测试、确保纯逻辑 suite 通过）和 Non-Goals（不涉及框架替换、Symfony 升级、业务逻辑修改）均已体现在 Requirements 中。Scope 限定在 `composer.json`、`phpunit.xml`、`ut/` 目录，与 PRP-002 一致。

**Q: Requirement 6–10 中引用了大量具体的 PHPUnit 方法名（`setUp()`、`setExpectedException()`、`getMockBuilder()` 等），是否属于实现细节？**
A: 本 spec 的核心目标就是 PHPUnit API 迁移，这些方法名是迁移的领域词汇——它们描述的是"从什么迁移到什么"的外部可观察行为，而非内部实现策略。Glossary 中已将关键迁移概念（Return_Type_Declaration、SetExpectedException_Migration、Data_Provider_Attribute、Mock_API）定义为术语。AC 中引用具体方法名是为了精确描述迁移规则，属于合理使用。

**Q: 是否有遗漏的边界场景？例如测试文件中可能存在的其他 PHPUnit 5.x 特有 API？**
A: Requirement 10（其他 API 变更）作为兜底条款，使用 IF 条件句覆盖了 R6–R9 未显式列出的 breaking change。这种设计是合理的——PHPUnit 5 → 13 的变更清单很长，逐一列举不现实，R6–R9 覆盖了已知的高频变更，R10 兜底处理剩余情况。实际边界在 design 阶段通过代码扫描确认。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [语体] R8 AC3：移除括号内的解释性注释 `(PHPUnit 13.x 要求 data provider 为 static 方法)`，AC 应为纯行为规格，解释已在 Glossary 的 Data_Provider_Attribute 定义中体现
- [内容] Socratic Review：补充两条 Q&A——(1) R6–R10 引用具体方法名是否属于实现细节；(2) 是否有遗漏的边界场景

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空
- [x] Requirements section 存在且包含 12 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 术语在 AC 中被实际使用
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 术语格式正确（`- **Term**: 定义`）
- [x] 每条 requirement 包含 User Story 和 Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 使用 SHALL / WHEN-THEN / IF-THEN 语体
- [x] AC Subject 使用 Glossary 术语
- [x] AC 编号连续无跳号
- [x] Socratic Review 覆盖充分（决策依据、拆分合理性、scope 一致性、实现细节边界、遗漏场景）
- [x] Goal CR 决策已体现在 requirements 中（C-1 至 C-4）
- [x] Goal 清晰度达标
- [x] Non-goal / Scope 边界明确
- [x] 完成标准充分（R11 + R12 构成 DoD）
- [x] 可 design 性达标

### Clarification Round

**状态**: 已回答

**Q1:** R11 列出了 7 个 Framework_Independent_Suite，但 PRP-002 的"预期通过"列表是基于初步预判。如果在 design 阶段代码扫描后发现某个"纯逻辑" suite 中的个别测试间接依赖了框架类（如 import 了 Silex 的类型），应如何处理？
- A) 将该测试从 Framework_Independent_Suite 的 AC 中移除，归入 Framework_Dependent_Suite，R11 对应 suite 的 AC 改为"除已识别的框架依赖测试外，其余测试 SHALL pass"
- B) 在本 Phase 中修复该测试的间接依赖（如替换 import），使其真正独立于框架
- C) 保持 R11 AC 不变，在 R12 AC2 中记录该测试为"意外失败"，留给后续 Phase
- D) 其他（请说明）

**A:** B — 在本 Phase 中修复该测试的间接依赖，使其真正独立于框架

**Q2:** R4 AC2 要求保留所有现有 test suite 定义。当前 `phpunit.xml` 的 `all` suite 以逐文件 `<file>` 方式列出所有测试。PHPUnit 13.x 支持 `<directory>` 方式。design 阶段是否允许将 `all` suite 改为 `<directory>ut</directory>` 以简化维护，还是必须保持逐文件列出？
- A) 保持逐文件列出，与现有配置结构一致，避免引入不在 scope 内的变更
- B) 改为 `<directory>` 方式，只要 suite 覆盖的测试集合不变即可
- C) 其他（请说明）

**A:** B — 改为 `<directory>` 方式，只要 suite 覆盖的测试集合不变即可

**Q3:** R5 要求 Bootstrap_File 在 PHP 8.5 下可加载。当前 `ut/bootstrap.php` 会加载 Composer autoloader，而 autoloader 会注册所有依赖的命名空间（包括 Silex、Symfony 4.x 等不兼容 PHP 8.5 的包）。R5 AC1 的"loadable without errors"是指 bootstrap 文件本身执行不报错（autoloader 注册命名空间不会触发错误），还是指 bootstrap 加载后所有已注册的类都能被实例化？
- A) 仅要求 bootstrap 文件本身执行不报错——autoloader 注册命名空间是惰性的，不会在 bootstrap 阶段触发不兼容类的加载
- B) 需要确保 bootstrap 加载后，Framework_Independent_Suite 所需的类能被正常加载（不要求框架类可加载）
- C) 其他（请说明）

**A:** B — 需要确保 bootstrap 加载后，Framework_Independent_Suite 所需的类能被正常加载（不要求框架类可加载）
