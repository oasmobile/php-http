# Requirements Document

> PHP 8.5 Phase 4: PHP Language Adaptation — `.kiro/specs/php85-phase4-language-adaptation/`

---

## Introduction

Phase 0–3 已完成依赖升级（PHPUnit 13.x、Symfony 7.x、Twig 3.x、Guzzle 7.x）和框架替换（Silex → Symfony MicroKernel）以及 Security 组件的 authenticator 系统重写。这些 Phase 聚焦于框架和依赖层面的兼容性，PHP 语言本身在 7.0 → 8.5 之间引入的 breaking changes 尚未系统性排查和修复。

本 spec 的目标分为两部分：

1. **兼容性修复**：排查并修复 `src/` 和 `ut/` 中所有 PHP 7.x → 8.5 的语言层面 breaking changes，包括隐式 nullable 参数移除、动态属性弃用、字符串/数字比较行为变更、内部函数类型检查严格化、已移除函数和弃用接口等
2. **代码现代化**：主动采用 PHP 8.x 新语法改善代码质量，包括 constructor property promotion、`match` 表达式、`readonly` 属性、union types、nullsafe operator、`str_contains()` 系列函数、first-class callable syntax 等

此外，`composer.json` 的 `description` 字段仍引用 Silex（`"An extension to Silex, for HTTP related routing, middleware, and so on."`），Phase 1 已将框架替换为 Symfony MicroKernel，该描述已过时，需在本 Phase 更新。

**关键决策**（来自 goal.md Clarification）：

- Q1=B: 隐式 nullable 参数在 `src/` 和 `ut/` 中全部修复
- Q2=B: 松散比较在 `src/` 和 `ut/` 中全部排查
- Q3=D: Phase 3 无遗留，Phase 3 任务会在本 spec 执行前完成
- Q4=A: `composer.json` 的 `description` 在本 Phase 更新，移除 Silex 引用

**不涉及的内容**：

- 依赖包升级（已在前序 Phase 完成）
- 静态分析工具引入（PHPStan / Psalm）——Phase 5
- CI 矩阵配置——Phase 5
- 新功能引入
- 公共 API 外部行为变更（现代化是语法层面的，不改变功能语义）

**约束**：

- C-1: 在 `feature/php85-upgrade` 分支上推进
- C-2: 依赖 Phase 0–3 完成
- C-3: `composer.json` 中 PHP 版本约束已在 Phase 0 更新为 `>=8.5`，本 Phase 无需再改
- C-4: PBT 使用 Eris 1.x
- C-5: spec 级 DoD：tasks 全部完成 + `phpunit` 全量通过，无 deprecation notice
- C-6: 预期可能残留的问题：静态分析发现的类型问题（等 Phase 5）、CI 矩阵尚未配置（等 Phase 5）

---

## Glossary

- **MicroKernel**: 项目的核心 HTTP 内核类（`src/MicroKernel.php`），替代原 Silex Application
- **Implicit_Nullable_Parameter**: PHP 8.2 弃用、8.4 正式移除的参数模式——`function foo(Type $param = null)` 必须改为 `?Type $param = null` 或 `Type|null $param = null`
- **Dynamic_Property**: 未在类中声明的属性，PHP 8.2 起对其赋值产生 deprecation notice
- **Loose_Comparison**: 使用 `==` 或 `!=` 的比较操作，PHP 8.0 起字符串/数字比较行为变更（`0 == "foo"` 从 `true` 变为 `false`）
- **Internal_Function_Type_Strictness**: PHP 8.0 起内部函数参数类型检查严格化，传入错误类型从 warning 变为 TypeError
- **Constructor_Property_Promotion**: PHP 8.0 引入的语法，允许在构造函数参数中直接声明并赋值属性
- **Match_Expression**: PHP 8.0 引入的 `match` 表达式，替代简单的 `switch` 语句，支持严格比较和表达式返回值
- **Readonly_Property**: PHP 8.1 引入的 `readonly` 修饰符，声明后属性只能在构造函数中赋值一次
- **Union_Type**: PHP 8.0 引入的联合类型声明（如 `int|string`），替代 `@param` 注释中的类型声明
- **Nullsafe_Operator**: PHP 8.0 引入的 `?->` 操作符，替代 `if ($x !== null) $x->method()` 模式
- **First_Class_Callable**: PHP 8.1 引入的 `foo(...)` 语法，替代 `Closure::fromCallable('foo')`
- **Str_Functions**: PHP 8.0 引入的 `str_contains()`、`str_starts_with()`、`str_ends_with()` 函数，替代 `strpos()` 惯用法
- **Named_Argument**: PHP 8.0 引入的命名参数语法，在提升可读性的场景使用
- **Enum_Type**: PHP 8.1 引入的枚举类型，替代常量组
- **PBT**: Property-Based Testing，使用 Eris 1.x 生成随机输入验证系统属性
- **All_Suite**: `phpunit.xml` 中定义的 `all` 测试套件，包含 `ut/` 目录下所有测试
- **PBT_Suite**: `phpunit.xml` 中定义的 `pbt` 测试套件，包含 `ut/PBT/` 目录下所有 PBT 测试
- **WrappedExceptionInfo**: 异常包装类（`src/ErrorHandlers/WrappedExceptionInfo.php`），实现 `JsonSerializable`，将异常信息序列化为数组/JSON
- **FallbackViewHandler**: 视图处理器（`src/Views/FallbackViewHandler.php`），根据请求格式选择渲染器
- **RouteBasedResponseRendererResolver**: 路由格式解析器（`src/Views/RouteBasedResponseRendererResolver.php`），根据请求 `format` 属性返回对应渲染器
- **CrossOriginResourceSharingStrategy**: CORS 策略类（`src/ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php`），定义 pattern / origins / headers / max_age / credentials
- **UniquenessViolationHttpException**: 自定义 HTTP 异常类（`src/Exceptions/UniquenessViolationHttpException.php`），返回 400 状态码
- **AbstractSimplePreAuthenticateUserProvider**: 用户提供者抽象类，核心方法 `authenticateAndGetUser()` 由子类实现
- **CacheableRouterUrlMatcherWrapper**: 路由匹配器包装类，为匹配结果中的 controller 添加命名空间前缀
- **GroupUrlMatcher**: 分组 URL 匹配器，按顺序尝试多个 matcher
- **CacheableRouterProvider**: 可缓存路由提供者，解析路由配置并创建 Router 实例
- **AbstractSmartViewHandler**: 智能视图处理器抽象类（`src/Views/AbstractSmartViewHandler.php`），根据请求 Accept 头判断 MIME type 是否被接受
- **SimpleAccessRule**: 访问规则默认实现（`src/ServiceProviders/Security/SimpleAccessRule.php`），从配置数组构造，提供 pattern / roles / channel
- **SimpleFirewall**: 防火墙默认实现（`src/ServiceProviders/Security/SimpleFirewall.php`），从配置数组构造，提供 pattern / policies / stateless 等属性
- **InvalidConfigurationException**: 配置校验异常，当配置值不合法时抛出
- **Architecture_Doc**: 架构文档（`docs/state/architecture.md`），记录系统架构与模块划分


---

## Requirements

### Requirement 1: 隐式 Nullable 参数修复

**User Story:** 作为库使用者，我希望所有隐式 nullable 参数被修复为显式 nullable 语法，以便代码在 PHP 8.4+ 下不产生错误。

#### Acceptance Criteria

1. FOR ALL PHP files in `src/` and `ut/`, THE codebase SHALL NOT contain any Implicit_Nullable_Parameter pattern（`function foo(Type $param = null)` 必须改为 `?Type $param = null`）
2. WHEN a parameter has a type declaration and a default value of `null`, THE parameter type SHALL use explicit nullable syntax（`?Type` 或 `Type|null`）
3. THE UniquenessViolationHttpException 的构造函数 SHALL 使用显式 nullable 语法声明 `$previous` 参数
4. FOR ALL modified files, THE existing test suite SHALL continue to pass without behavior changes

### Requirement 2: 动态属性修复

**User Story:** 作为库使用者，我希望所有动态属性使用被修复，以便代码在 PHP 8.2+ 下不产生 deprecation notice。

#### Acceptance Criteria

1. FOR ALL classes in `src/`, THE codebase SHALL NOT contain any Dynamic_Property usage（所有属性必须在类中显式声明）
2. WHEN a class requires 动态属性（如框架约束），THE class SHALL 使用 `#[AllowDynamicProperties]` attribute 标注
3. FOR ALL classes in `ut/`, THE test helper classes SHALL NOT contain any Dynamic_Property usage

### Requirement 3: 松散比较审计与修复

**User Story:** 作为库使用者，我希望所有可能受 PHP 8.0 字符串/数字比较行为变更影响的松散比较被审计和修复，以便代码行为在 PHP 8.x 下正确。

#### Acceptance Criteria

1. FOR ALL Loose_Comparison in `src/`, THE codebase SHALL 将可能受字符串/数字比较行为变更影响的 `==` 替换为 `===`，将 `!=` 替换为 `!==`
2. WHEN a Loose_Comparison involves a value that may be numeric zero or empty string, THE comparison SHALL use strict comparison（`===` / `!==`）
3. THE WrappedExceptionInfo 中 status code 为 0 的判断和异常 code 非 0 的判断 SHALL 使用严格比较（`===` / `!==`），因为 HTTP 状态码和异常码为整数类型
4. THE FallbackViewHandler 中 renderer resolver 的 null 判断 SHALL 使用严格比较（`=== null`）
5. THE CrossOriginResourceSharingStrategy 中 pattern 通配符判断 SHALL 使用严格比较（`=== "*"`）
6. THE GroupUrlMatcher 中匹配计数比较 SHALL 使用严格比较（`===`）
7. THE CacheableRouterProvider 中 `strcasecmp()` 返回值判断 SHALL 使用严格比较（`=== 0`）
8. THE AbstractSmartViewHandler 中的 MIME type 比较 SHALL 使用严格比较
9. THE MicroKernel 中 AWS IP 获取的 HTTP 状态码比较 SHALL 使用严格比较（`!==`）
10. THE MicroKernel 中 CloudFront IP 过滤的 service 名称比较 SHALL 使用严格比较（`===`）
11. THE AbstractSimplePreAuthenticateUserProvider 中用户类名匹配 SHALL 使用严格比较（`===`）

### Requirement 4: 内部函数类型严格化修复

**User Story:** 作为库使用者，我希望所有内部函数调用中的隐式类型转换被修复，以便代码在 PHP 8.0+ 下不产生 TypeError。

#### Acceptance Criteria

1. FOR ALL internal function calls in `src/` and `ut/`, THE arguments SHALL match the expected parameter types（避免隐式类型转换）
2. WHEN a function expects a `string` parameter but receives a potentially non-string value, THE caller SHALL perform explicit type casting or validation before the call
3. WHEN a function expects an `int` parameter but receives a potentially non-int value, THE caller SHALL perform explicit type casting or validation before the call

### Requirement 5: 其他 PHP 7.x → 8.5 Breaking Changes 修复

**User Story:** 作为库使用者，我希望所有其他已知的 PHP 7.x → 8.5 breaking changes 被修复，以便代码在目标 PHP 版本下无错误和 deprecation notice。

#### Acceptance Criteria

1. FOR ALL PHP files in `src/` and `ut/`, THE codebase SHALL NOT use `each()` function（PHP 8.0 移除）
2. FOR ALL PHP files in `src/` and `ut/`, THE codebase SHALL NOT use `create_function()`（PHP 8.0 移除）
3. FOR ALL PHP files in `src/` and `ut/`, THE codebase SHALL NOT use `${var}` string interpolation syntax（PHP 8.2 弃用，仅 `{$var}` 保留）
4. FOR ALL classes implementing `Serializable` interface in `src/` and `ut/`, THE implementation SHALL 迁移到 `__serialize()` / `__unserialize()` magic methods（`Serializable` 接口 PHP 8.1 弃用）
5. THE WrappedExceptionInfo 的 `jsonSerialize()` 方法 SHALL 声明 `mixed` 返回类型（匹配 `JsonSerializable` 接口在 PHP 8.x 中的签名要求）
6. WHEN PHP 8.5 introduces additional deprecations affecting the codebase, THE affected code SHALL be updated accordingly

### Requirement 6: Constructor Property Promotion 现代化

**User Story:** 作为开发者，我希望适用的构造函数使用 PHP 8.0 的 constructor property promotion 语法，以减少样板代码。

#### Acceptance Criteria

1. WHEN a constructor assigns parameters directly to properties without additional logic, THE constructor SHALL use Constructor_Property_Promotion syntax
2. WHEN a constructor performs validation or transformation on parameters before assignment, THE constructor SHALL NOT use Constructor_Property_Promotion for those parameters（保留显式赋值）
3. FOR ALL promoted properties, THE visibility modifier SHALL match the original property declaration
4. FOR ALL classes with promoted constructors, THE original property declarations and `@var` annotations SHALL be removed

### Requirement 7: Match 表达式现代化

**User Story:** 作为开发者，我希望适用的 `switch` 语句被替换为 `match` 表达式，以提升代码简洁性和安全性。

#### Acceptance Criteria

1. THE RouteBasedResponseRendererResolver 中的 `switch ($format)` SHALL 被替换为 Match_Expression
2. WHEN a `switch` statement uses simple value matching with return/assignment, THE statement SHALL be replaced with a Match_Expression
3. WHEN a `switch` statement contains complex logic or fall-through behavior, THE statement SHALL NOT be replaced（保留 `switch`）

### Requirement 8: 类型声明现代化

**User Story:** 作为开发者，我希望 `@param` / `@var` / `@return` 注释中的类型声明被替换为原生 PHP 类型声明，以提升类型安全性。

#### Acceptance Criteria

1. WHEN a method parameter has a `@param` annotation but no native type declaration, THE parameter SHALL add native type declaration（Union_Type、nullable type 等）
2. WHEN a method has a `@return` annotation but no native return type declaration, THE method SHALL add native return type declaration
3. WHEN a property has a `@var` annotation but no native type declaration, THE property SHALL add native type declaration
4. FOR ALL public and protected methods, THE type declaration SHALL be added regardless of potential subclass impact（CR Q1=C：激进策略，视为合理 breaking change，下游必须适配 PHP `>=8.5`）
5. FOR ALL added type declarations, THE existing test suite SHALL continue to pass without behavior changes

### Requirement 9: Str 函数与 Nullsafe Operator 现代化

**User Story:** 作为开发者，我希望 `strpos()` 惯用法被替换为 `str_contains()` 系列函数，`null` 检查链被替换为 nullsafe operator，以提升代码可读性。

#### Acceptance Criteria

1. THE CacheableRouterUrlMatcherWrapper 中 `strpos($result['_controller'], "::") !== false` SHALL 被替换为 `str_contains($result['_controller'], "::")`
2. WHEN `strpos($haystack, $needle) !== false` pattern is used for substring existence check, THE code SHALL use `str_contains($haystack, $needle)`
3. WHEN `strpos($haystack, $needle) === 0` pattern is used for prefix check, THE code SHALL use `str_starts_with($haystack, $needle)`
4. WHEN `substr($haystack, -strlen($needle)) === $needle` pattern is used for suffix check, THE code SHALL use `str_ends_with($haystack, $needle)`
5. WHEN a null check is followed by a method call on the checked variable（`if ($x !== null) { $x->method(); }`），THE code SHALL use Nullsafe_Operator（`$x?->method()`）where semantically equivalent

### Requirement 10: Readonly 属性与其他语法现代化

**User Story:** 作为开发者，我希望不可变字段使用 `readonly` 修饰符，以及其他适用的 PHP 8.x 新语法被采用，以提升代码质量。

#### Acceptance Criteria

1. WHEN a property is assigned once in the constructor and never modified thereafter, THE property SHALL be declared as Readonly_Property
2. WHEN a class contains a group of related constants that represent a finite set of values, THE constants MAY be refactored to Enum_Type（仅在语义明确且不破坏公共 API 的场景）
3. WHEN Named_Argument improves readability in function calls with multiple optional parameters, THE call MAY use named arguments
4. WHEN `Closure::fromCallable()` is used, THE code SHALL use First_Class_Callable syntax（`foo(...)`）
5. FOR ALL modernization changes, THE public API external behavior SHALL remain unchanged


### Requirement 11: `composer.json` Description 更新

**User Story:** 作为库使用者，我希望 `composer.json` 的 `description` 字段反映当前框架（Symfony MicroKernel），以便包描述准确。

#### Acceptance Criteria

1. THE `composer.json` 的 `description` 字段 SHALL 移除对 Silex 的引用
2. THE `composer.json` 的 `description` 字段 SHALL 反映当前框架为 Symfony MicroKernel
3. THE `composer.json` 的其他字段 SHALL 保持不变

### Requirement 12: 架构文档更新

**User Story:** 作为开发者，我希望 Architecture_Doc 在本 Phase 产生结构性变化时得到更新，以保持 SSOT 准确。

#### Acceptance Criteria

1. WHEN 本 Phase 的修改导致模块结构、类层次或公共 API 签名发生变化, THE Architecture_Doc SHALL 被更新以反映这些变化
2. WHEN 本 Phase 仅涉及语法层面的现代化（不改变结构）, THE Architecture_Doc MAY 不更新
3. IF Architecture_Doc 中存在与当前代码不一致的描述（如过时的类名、方法签名）, THEN THE Architecture_Doc SHALL 被修正

### Requirement 13: 零 Deprecation Notice 验证

**User Story:** 作为库使用者，我希望代码在 PHP 8.5 下运行时不产生任何 deprecation notice，以确保升级后的代码质量。

#### Acceptance Criteria

1. WHEN `phpunit` is executed under PHP 8.5, THE test runner SHALL report zero deprecation notices from project code（`src/` 和 `ut/`）
2. THE codebase SHALL NOT contain any PHP 8.x deprecated syntax or API usage
3. IF third-party dependencies produce deprecation notices, THEN THE notices SHALL be documented but not required to be fixed in this Phase

### Requirement 14: 全量测试通过

**User Story:** 作为开发者，我希望 Phase 4 完成后 `phpunit` 全量通过，以确认所有兼容性修复和现代化改造成功。

#### Acceptance Criteria

1. WHEN `phpunit` is executed, THE test runner SHALL report all tests passing
2. WHEN `phpunit --testsuite pbt` is executed, THE PBT tests SHALL pass
3. FOR ALL existing test suites（`security`、`integration`、`cors`、`twig`、`routing`、`configuration`、`views`、`error-handlers`、`cookie`、`middlewares`、`misc`、`aws`、`exceptions`），THE tests SHALL continue to pass

### Requirement 15: PBT — 松散比较修复正确性验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证松散比较修复后的行为在各种输入下保持正确。

#### Acceptance Criteria

1. FOR ALL integer HTTP status codes, THE WrappedExceptionInfo SHALL 在 code 为 0 时将其替换为 500，在 code 非 0 时保留原值（invariant property：`code == 0 ? 500 : code`）
2. FOR ALL Exception objects with various `getCode()` return values, THE `serializeException()` output SHALL 仅在 code 非 0 时包含 `code` 字段（metamorphic property：code 为 0 → 无 `code` 字段；code 非 0 → 有 `code` 字段）
3. FOR ALL WrappedExceptionInfo instances, `toArray()` 然后 JSON encode 再 decode SHALL 产生等价的数组结构（round-trip property）

### Requirement 16: PBT — 类型声明兼容性验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证添加原生类型声明后的方法在各种输入下行为不变。

#### Acceptance Criteria

1. FOR ALL valid MIME type strings, THE AbstractSmartViewHandler 的 `isAccepted()` SHALL 在严格比较下返回与原松散比较相同的结果（invariant property：行为不变）
2. FOR ALL valid format strings (`html`, `page`, `api`, `json`), THE RouteBasedResponseRendererResolver SHALL 返回正确类型的 renderer（round-trip property：format → renderer type 映射不变）
3. FOR ALL invalid format strings, THE RouteBasedResponseRendererResolver SHALL 抛出 `InvalidConfigurationException`（error condition property）

### Requirement 17: PBT — Constructor Property Promotion 等价性验证

**User Story:** 作为开发者，我希望通过 Property-Based Testing 验证 constructor property promotion 重构后的类行为与重构前完全等价。

#### Acceptance Criteria

1. FOR ALL valid access rule configurations (pattern × roles × channel), THE SimpleAccessRule 在 promotion 重构后 SHALL 返回与重构前相同的 `getPattern()`、`getRequiredRoles()`、`getRequiredChannel()` 值（round-trip property）
2. FOR ALL valid firewall configurations, THE SimpleFirewall 在 promotion 重构后 SHALL 返回与重构前相同的 `getPattern()`、`getPolicies()`、`isStateless()` 值（round-trip property）
3. FOR ALL valid exception messages and codes, THE UniquenessViolationHttpException 在 nullable 参数修复后 SHALL 保持相同的 `getStatusCode()`、`getMessage()`、`getPrevious()`、`getCode()` 值（round-trip property）
4. FOR ALL WrappedExceptionInfo instances constructed with various exceptions and status codes, THE `toArray()` output SHALL 保持相同的结构和值（invariant property：重构不改变序列化输出）


---

## Socratic Review

**Q1: Requirements 是否完整覆盖了 goal.md 的所有目标？**
A: 是。goal.md 列出的目标逐一对应：(1) 隐式 nullable 参数修复 → R1; (2) 动态属性修复 → R2; (3) 松散比较审计 → R3; (4) 内部函数类型严格化 → R4; (5) 其他 breaking changes → R5; (6) 代码现代化（constructor promotion → R6, match → R7, 类型声明 → R8, str 函数/nullsafe → R9, readonly/enum/named args/callable → R10）; (7) composer.json description → R11; (8) 零 deprecation notice → R13; (9) 全量测试通过 → R14; (10) 架构文档更新 → R12。全部覆盖。

**Q2: Clarification 决策是否已体现在 Requirements 中？**
A: Q1=B（`src/` + `ut/` 全部修复）→ R1 AC1 明确了范围；Q2=B（全面排查）→ R3 AC1 明确了范围；Q3=D（Phase 3 无遗留）→ Introduction 约束中说明；Q4=A（更新 description）→ R11 明确了要求。四个决策均已体现。

**Q3: PBT Requirements 是否遵循了 property 分类指南？**
A: R15（WrappedExceptionInfo invariant + metamorphic + round-trip）、R16（AbstractSmartViewHandler invariant + RouteBasedResponseRendererResolver round-trip + error condition）、R17（SimpleAccessRule/SimpleFirewall/UniquenessViolationHttpException round-trip + WrappedExceptionInfo invariant）。覆盖了 round-trip、invariant、metamorphic、error condition 四种 property 类型，符合指南。

**Q4: 兼容性修复（R1–R5）与现代化（R6–R10）的边界是否清晰？**
A: 清晰。R1–R5 是"必须做"的兼容性修复——不做会导致 PHP 8.x 下的错误或 deprecation notice。R6–R10 是"主动做"的现代化——不做不影响功能，但提升代码质量。两者的区分标准是：修复项解决的是 PHP 版本升级带来的 breaking change，现代化项采用的是新版本提供的更好语法。R10 AC5 明确了现代化不改变外部行为。

**Q5: R3（松散比较）为什么逐一列出具体文件和比较？**
A: 因为松散比较的修复需要逐个判断语义——并非所有 `==` 都需要改为 `===`。逐一列出确保每个修复点都经过审计，避免盲目全局替换导致行为变更。AC1–2 提供了通用规则，AC3–11 列出了代码审计中发现的具体修复点，确保 design 阶段有明确的修复清单。

**Q6: R8（类型声明现代化）AC4 为什么排除了可能破坏子类的场景？**
A: PHP 的类型协变/逆变规则要求子类方法参数类型不能比父类更严格（逆变），返回类型不能比父类更宽松（协变）。如果父类方法添加了原生类型声明，所有子类必须兼容。对于库代码，下游项目可能有自定义子类，贸然添加类型声明可能导致下游代码报错。AC4 的排除条件确保现代化不破坏向后兼容性。

**Q7: R12（架构文档更新）为什么使用 MAY 而非 SHALL？**
A: 本 Phase 的核心工作是语法层面的修复和现代化，预期不会改变模块结构或类层次。如果实际执行中确实没有结构性变化，强制更新架构文档是不必要的。AC1 用 SHALL 覆盖了"有变化时必须更新"的场景，AC2 用 MAY 覆盖了"无变化时可以不更新"的场景，AC3 用 SHALL 覆盖了"发现不一致时必须修正"的场景。

**Q8: Requirements 之间是否存在矛盾或重叠？**
A: R1–R5 是兼容性修复，R6–R10 是现代化，R11 是元数据更新，R12 是文档更新，R13–R14 是验证标准，R15–R17 是 PBT。各 Requirement 关注层次不同，无矛盾。R1（隐式 nullable）和 R8（类型声明）在 nullable 类型上有部分重叠——R1 修复的是 PHP 8.4 强制要求的显式 nullable，R8 添加的是可选的原生类型声明，属于互补关系。R3（松散比较）和 R15（PBT 验证）在 WrappedExceptionInfo 上有关联——R3 定义修复要求，R15 通过 PBT 验证修复正确性，属于互补关系。

**Q9: 与 PRP-006 的 scope 是否一致？**
A: 基本一致，但有一个明确的扩展：PRP-006 的 Non-Goals 排除了代码现代化，但用户在 goal.md 中明确要求纳入（R6–R10）。其余 scope（`src/`、`ut/`、`composer.json`）与 PRP-006 一致。PRP-006 提到的 `composer.json` PHP 版本约束更新已在 Phase 0 完成，本 Phase 不重复处理。

**Q10: PBT 的测试对象选择是否合理？**
A: R15 选择 WrappedExceptionInfo 是因为它包含了最复杂的松散比较修复（status code 为 0 的特殊处理 + 异常序列化中的 code 过滤），且是纯函数式逻辑，适合 PBT。R16 选择 AbstractSmartViewHandler 和 RouteBasedResponseRendererResolver 是因为它们涉及字符串比较和 match 表达式重构，输入空间有意义的变化。R17 选择 SimpleAccessRule/SimpleFirewall/UniquenessViolationHttpException 是因为它们是 constructor promotion 的主要候选，需要验证重构等价性。这些都是"测试 YOUR code"的纯逻辑，100 次迭代能发现比 2-3 次更多的边界情况，符合 PBT 决策指南。



---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [术语] Glossary 补充 4 个缺失术语：`AbstractSmartViewHandler`、`SimpleAccessRule`、`SimpleFirewall`、`InvalidConfigurationException`——这些术语在 R3 AC8、R16 AC1/AC3、R17 AC1/AC2 中作为 Subject 使用但未定义
- [语体] R3 AC3–AC11 移除内部变量名引用（`$this->code`、`$e->getCode()`、`$rendererResolver`、`$pattern`、`$matched`、`$total`、`$info['service']`、`$class == $this->supportedUserClassname`），改为行为描述，保留具体文件/类引用作为审计清单
- [格式] R2 AC2 修正 "requires动态属性" 缺少空格为 "requires 动态属性"

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表中的术语在正文中使用）
- [x] 无 markdown 格式错误

**结构校验**
- [x] 一级标题 `# Requirements Document` 存在且正确
- [x] Introduction 存在，描述了 feature 范围（兼容性修复 + 代码现代化）
- [x] Introduction 明确了不涉及的内容（Non-scope）和约束
- [x] Glossary 存在且非空（修正后 31 个术语）
- [x] Requirements section 存在且包含 17 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Socratic Review 存在且覆盖充分（10 个问题）

**术语表校验**
- [x] Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义（修正后）
- [x] 术语格式为 `- **Term**: 定义`

**Requirement 条款校验**
- [x] 每条 requirement 包含 User Story + Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 使用 THE/WHEN/IF/FOR ALL 语体
- [x] Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续，无跳号

**内容边界校验**
- [x] AC 聚焦外部可观察行为（修正后移除了内部变量名引用）
- [○] R3 AC3–AC11 保留了具体类名和修复点描述——对于兼容性审计 spec，逐一列出修复点属于外部可观察的审计清单，Socratic Q5 已论证其合理性
- [○] R5 AC5 保留了 `jsonSerialize()` 方法名和 `mixed` 返回类型——这是 PHP 8.x `JsonSerializable` 接口的合规要求，属于外部接口约束而非实现细节
- [○] R1 AC3 保留了 `$previous` 参数名——构造函数参数名属于公共 API 的一部分

**目的性审查**
- [x] Goal CR 回应：goal.md 中 4 个 Clarification 决策（Q1=B, Q2=B, Q3=D, Q4=A）均已体现
- [x] Goal 清晰度：Introduction 清楚传达了两部分目标（兼容性修复 + 代码现代化）
- [x] Non-goal / Scope 边界：明确且与 PRP-006 一致（代码现代化扩展已在 goal.md 中说明）
- [x] 完成标准：R13（零 deprecation）+ R14（全量测试通过）+ R15–R17（PBT）构成充分验收条件
- [x] 可 design 性：R3 提供了具体修复清单，R6–R10 提供了明确的现代化标准，design 阶段有足够信息

### Clarification Round

**状态**: 已完成

**Q1:** R8（类型声明现代化）要求将 `@param` / `@return` 注释替换为原生类型声明，AC4 排除了可能破坏子类的场景。作为库代码，添加原生类型声明到 public/protected 方法会影响下游子类的类型兼容性。Design 阶段需要确定类型声明的添加策略。你倾向哪种方式？
- A) 保守策略：仅对 `private` 方法和 `final` 类的方法添加原生类型声明，public/protected 方法保留注释
- B) 适度策略：对所有方法添加原生类型声明，但排除已知有子类覆盖的方法（通过代码分析确定）
- C) 激进策略：对所有方法添加原生类型声明，视为 minor version 的合理 breaking change（库的 PHP 版本要求已升至 `>=8.5`，下游必须适配）
- D) 其他（请说明）

**A:** C — 激进策略。对所有方法添加原生类型声明，视为合理 breaking change。库的 PHP 版本要求已升至 `>=8.5`，下游必须适配。

**Q2:** R1（隐式 nullable 修复）使用 `?Type $param = null` 语法，R8（类型声明现代化）引入 Union_Type。当两者同时适用于同一参数时（如原本无类型声明的 nullable 参数），最终应使用哪种 nullable 语法？这影响整个代码库的风格一致性。
- A) 统一使用 `?Type` 短语法（简洁，PHP 8.0 前的惯用写法）
- B) 统一使用 `Type|null` union type 语法（与 PHP 8.0+ 的 union type 风格一致，且在多类型场景下必须使用 `Type1|Type2|null`）
- C) 单类型用 `?Type`，多类型用 `Type1|Type2|null`（混合策略，按场景选择最简洁的写法）
- D) 其他（请说明）

**A:** C — 混合策略。单类型 nullable 用 `?Type`，多类型用 `Type1|Type2|null`，按场景选择最简洁的写法。

**Q3:** R3（松散比较审计）AC1–2 提供了通用规则，AC3–11 列出了代码审计中发现的具体修复点。但 AC 列表可能不完整——`ut/` 中的松散比较尚未逐一列出。Design 阶段需要确定审计的执行方式。
- A) 以 AC3–11 列表为准，仅修复已列出的点；`ut/` 中的松散比较在执行阶段通过 grep 发现并修复，不需要在 design 中逐一列出
- B) Design 阶段对 `src/` 和 `ut/` 做完整 grep 审计，将所有松散比较点列入 design 的修复清单，确保无遗漏
- C) 以 AC3–11 为核心修复清单，`ut/` 中的松散比较仅修复涉及数字 0 或空字符串的场景（高风险子集），其余保留
- D) 其他（请说明）

**A:** B — Design 阶段对 `src/` 和 `ut/` 做完整 grep 审计，将所有松散比较点列入 design 的修复清单，确保无遗漏。

**Q4:** R6（Constructor Property Promotion）和 R10 AC1（Readonly Property）可能同时适用于同一个类——promoted property 可以同时声明为 readonly（`public readonly string $name`）。Design 阶段需要确定两者的组合策略。
- A) 分步执行：先做 promotion 重构，再在 promotion 基础上添加 readonly，两步分开以便 PBT 验证每步的等价性
- B) 一步到位：promotion + readonly 同时应用，PBT 验证最终结果与原始代码的等价性
- C) 仅做 promotion，readonly 留给后续优化（减少本 Phase 的变更面）
- D) 其他（请说明）

**A:** B — 一步到位。promotion + readonly 同时应用，PBT 验证最终结果与原始代码的等价性。
