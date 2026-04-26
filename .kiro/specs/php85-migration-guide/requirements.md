# Requirements Document

> PHP 8.5 Upgrade — Migration Guide & Check Script — `.kiro/specs/php85-migration-guide/`

---

## Introduction

`oasis/http` 经过 Phase 0–5 的 PHP 8.5 升级，引入了大量 breaking change：框架从 Silex 替换为 Symfony MicroKernel、DI 容器从 Pimple 迁移到 Symfony DependencyInjection、Security 组件接口全面重写、Twig 1.x → 3.x、Guzzle 6.x → 7.x、PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`、多个依赖移除。

下游项目需要清晰的迁移文档和自动化检查工具来评估影响范围并完成适配。本 spec 交付两个产物：

1. **Migration Guide**（`docs/manual/migration-v3.md`）：单文件，按模块分章节，带 TOC 导航，覆盖所有 breaking change，每项提供 before/after 代码示例和严重程度标注（🔴 必须改 / 🟡 建议改 / 🟢 可选）
2. **预升级检查脚本**（`bin/oasis-http-migrate-v3-check`）：通过 composer `bin` 配置暴露到 `vendor/bin/`，扫描目标目录检测对已移除/已变更 API 的引用，输出结构化报告（文件路径、行号、建议操作）

**不涉及的内容**：

- 下游项目的实际升级执行
- `oasis/http` 自身的代码变更（已在 Phase 0–5 完成）
- 运行时兼容层或 shim
- 自动化升级脚本（仅提供检查脚本）

**约束**：

- C-1: Migration Guide 为单文件 `docs/manual/migration-v3.md`，用目录锚点导航各模块章节
- C-2: 检查脚本放 `bin/oasis-http-migrate-v3-check`，`composer.json` 添加 `"bin"` 配置
- C-3: 迁移文档内容基于 Phase 0–5 的实际产出（`docs/changes/unreleased/php85-upgrade.md`），不臆造变更
- C-4: 检查脚本为纯 PHP 脚本，不引入额外 composer 依赖

---

## Glossary

- **Migration_Guide**: 迁移指南文档（`docs/manual/migration-v3.md`），面向下游消费者，按模块分章节描述所有 breaking change 及适配方法
- **Check_Script**: 预升级检查脚本（`bin/oasis-http-migrate-v3-check`），扫描目标目录检测对已移除/已变更 API 的引用
- **Breaking_Change_Record**: `docs/changes/unreleased/php85-upgrade.md`，记录 Phase 0–5 所有 breaking change 的变更日志
- **Severity_Level**: 变更严重程度标注，分三级：🔴 必须改（代码无法编译或运行）、🟡 建议改（deprecation 或行为变化）、🟢 可选（改善性变更）
- **Structured_Report**: Check_Script 的输出格式，包含文件路径、行号、检测到的问题描述和建议操作
- **MicroKernel**: 替换 `SilexKernel` 的新核心入口类，基于 Symfony `HttpKernel`
- **Bootstrap_Config**: `MicroKernel` 构造函数接受的关联数组，包含 `routing`、`security`、`cors`、`twig`、`middlewares`、`providers`、`view_handlers`、`error_handlers` 等顶层 key
- **TOC**: Table of Contents，文档内目录，使用 markdown 锚点链接实现章节导航
- **Removed_API**: Phase 0–5 中被移除的类、接口或方法（如 `SilexKernel`、`Pimple\ServiceProviderInterface`）
- **Changed_API**: Phase 0–5 中签名或行为发生变化的接口（如 `AuthenticationPolicyInterface`、`MiddlewareInterface`）

---

## Requirements

### Requirement 1: Migration Guide 文档结构与完整性

**User Story:** 作为下游项目开发者，我希望获得一份结构清晰、覆盖完整的迁移指南，以便了解所有 breaking change 及其适配方法。

#### Acceptance Criteria

1. THE Migration_Guide SHALL be a single markdown file located at `docs/manual/migration-v3.md`.
2. THE Migration_Guide SHALL contain a TOC at the top, using markdown anchor links to navigate to each module section.
3. THE Migration_Guide SHALL cover ALL breaking changes recorded in Breaking_Change_Record.
4. THE Migration_Guide SHALL organize content by module sections: PHP Version、Dependencies、Kernel API、DI Container、Bootstrap Config、Routing、Security、Middleware、Views、Twig、CORS、Cookie.
5. WHEN a breaking change spans multiple modules THEN THE Migration_Guide SHALL describe the change in the most relevant module section and cross-reference from other affected sections.

### Requirement 2: Breaking Change 条目格式

**User Story:** 作为下游项目开发者，我希望每个 breaking change 都有统一的格式，包含严重程度、代码示例和操作指引，以便快速评估影响并执行适配。

#### Acceptance Criteria

1. THE Migration_Guide SHALL assign a Severity_Level to each breaking change entry: 🔴 必须改、🟡 建议改、🟢 可选.
2. THE Migration_Guide SHALL provide a before/after code example for each breaking change entry, showing the old usage and the new usage.
3. THE Migration_Guide SHALL provide a concise action description for each breaking change entry, explaining what the downstream developer needs to do.
4. THE Migration_Guide SHALL mark entries that cause compile-time or runtime errors as 🔴 必须改.
5. THE Migration_Guide SHALL mark entries that involve deprecated patterns or behavioral changes as 🟡 建议改.
6. THE Migration_Guide SHALL mark entries that are optional improvements as 🟢 可选.

### Requirement 3: Kernel API 迁移章节

**User Story:** 作为下游项目开发者，我希望了解 `SilexKernel` 到 `MicroKernel` 的迁移细节，以便更新应用入口代码。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the class rename from `SilexKernel` to `MicroKernel` with Severity_Level 🔴.
2. THE Migration_Guide SHALL document the namespace change and provide the new `use` statement.
3. THE Migration_Guide SHALL document the constructor signature change: `MicroKernel(array $httpConfig, bool $isDebug)`.
4. THE Migration_Guide SHALL list all public API methods preserved on `MicroKernel` (`run()`, `handle()`, `isGranted()`, `getToken()`, `getUser()`, `getTwig()`, `getParameter()`, `addExtraParameters()`, `addControllerInjectedArg()`, `addMiddleware()`, `getCacheDirectories()`).
5. THE Migration_Guide SHALL document the removal of `SilexKernel::__set()` magic method with Severity_Level 🔴.

### Requirement 4: DI Container 迁移章节

**User Story:** 作为下游项目开发者，我希望了解从 Pimple 到 Symfony DI 的迁移细节，以便更新 service 注册和获取代码。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the removal of Pimple container and all `$app['xxx']` style access with Severity_Level 🔴.
2. THE Migration_Guide SHALL document the replacement of `Pimple\ServiceProviderInterface` with Symfony `CompilerPassInterface` / `ExtensionInterface` for user-provided providers, with before/after code examples.
3. THE Migration_Guide SHALL document the new service registration pattern using Symfony DI.

### Requirement 5: Security 组件迁移章节

**User Story:** 作为下游项目开发者，我希望了解 Security 组件接口重写的完整映射关系，以便重写认证和授权代码。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the rewrite of `AuthenticationPolicyInterface` with Severity_Level 🔴, including the old and new method signatures.
2. THE Migration_Guide SHALL document the rewrite of `FirewallInterface` with Severity_Level 🔴, including the old and new method signatures.
3. THE Migration_Guide SHALL document the rewrite of `AccessRuleInterface` with Severity_Level 🔴, including the old and new method signatures.
4. THE Migration_Guide SHALL document the replacement of `AbstractSimplePreAuthenticator` with `AbstractPreAuthenticator`, including the new template method pattern (`getCredentialsFromRequest()` + `authenticateAndGetUser()`).
5. THE Migration_Guide SHALL document the adaptation of `AbstractSimplePreAuthenticateUserProvider` to Symfony 7.x `UserProviderInterface`.
6. THE Migration_Guide SHALL provide a complete before/after example of implementing a custom pre-authentication policy.

### Requirement 6: Middleware 迁移章节

**User Story:** 作为下游项目开发者，我希望了解中间件接口的变化，以便更新自定义中间件代码。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the removal of `Silex\Application` dependency from `MiddlewareInterface::before()` method signature with Severity_Level 🔴.
2. THE Migration_Guide SHALL document the removal of `Silex\Application` dependency from `AbstractMiddleware` with Severity_Level 🔴.
3. THE Migration_Guide SHALL document the event priority constant changes (from `Application::EARLY_EVENT` / `Application::LATE_EVENT` to Symfony event priority constants).


### Requirement 7: 依赖变更章节

**User Story:** 作为下游项目开发者，我希望了解所有依赖版本变更和移除，以便更新项目的 `composer.json`。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the removal of `silex/silex`, `silex/providers`, `twig/extensions` with Severity_Level 🔴.
2. THE Migration_Guide SHALL document the Symfony components upgrade from `^4.0` to `^7.2` with Severity_Level 🔴.
3. THE Migration_Guide SHALL document the `twig/twig` upgrade from `^1.24` to `^3.0` with Severity_Level 🔴.
4. THE Migration_Guide SHALL document the `guzzlehttp/guzzle` upgrade from `^6.3` to `^7.0` with Severity_Level 🔴.
5. THE Migration_Guide SHALL document the `oasis/logging` upgrade from `^1.1` to `^3.0` and `oasis/utils` upgrade from `^1.6` to `^3.0` with Severity_Level 🔴.
6. THE Migration_Guide SHALL document the PHP minimum version change from `>=7.0.0` to `>=8.5` with Severity_Level 🔴.

### Requirement 8: Twig 迁移章节

**User Story:** 作为下游项目开发者，我希望了解 Twig 集成方式的变化和模板语法影响，以便更新模板代码。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the Twig 1.x → 3.x class name changes (`Twig_Environment` → `\Twig\Environment`, `Twig_SimpleFunction` → `\Twig\TwigFunction`, `Twig_Error_Loader` → `\Twig\Error\LoaderError`) with Severity_Level 🔴.
2. THE Migration_Guide SHALL document the removal of `twig/extensions` and its replacement in Twig 3.x core with Severity_Level 🟡.
3. THE Migration_Guide SHALL document the `SimpleTwigServiceProvider` rewrite and new Twig service registration pattern.
4. THE Migration_Guide SHALL document the `twig.strict_variables` and `twig.auto_reload` Bootstrap_Config key behavior.

### Requirement 9: Bootstrap Config 变更章节

**User Story:** 作为下游项目开发者，我希望了解 Bootstrap Config 结构的变化，以便更新应用配置。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the `providers` key semantic change: from `Pimple\ServiceProviderInterface` instances to `CompilerPassInterface` / `ExtensionInterface` instances, with Severity_Level 🔴.
2. THE Migration_Guide SHALL provide a complete Bootstrap_Config key reference table, listing each key, its type, default value, and whether it changed.
3. WHEN a config key's semantics or accepted types changed THEN THE Migration_Guide SHALL document the change with before/after examples.

### Requirement 10: Routing、CORS、Cookie、Views 迁移章节

**User Story:** 作为下游项目开发者，我希望了解 Routing、CORS、Cookie、Views 模块的变化，以便评估是否需要适配。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the routing system migration to Symfony Routing 7.x, noting that the Bootstrap_Config `routing` key behavior is preserved.
2. THE Migration_Guide SHALL document the CORS provider rewrite to EventSubscriber, noting that the Bootstrap_Config `cors` key and `CrossOriginResourceSharingStrategy` API are preserved.
3. THE Migration_Guide SHALL document the Cookie provider rewrite to EventSubscriber, noting that `ResponseCookieContainer` API is preserved.
4. THE Migration_Guide SHALL document the View Handler and Response Renderer interface changes (accepting `MicroKernel` instead of `SilexKernel`).
5. WHEN a module's public API is preserved without change THEN THE Migration_Guide SHALL explicitly state that no downstream action is required for that module.

### Requirement 11: PHP 语言适配章节

**User Story:** 作为下游项目开发者，我希望了解 PHP 8.5 语言层面的适配要点，以便检查自身代码的兼容性。

#### Acceptance Criteria

1. THE Migration_Guide SHALL document the implicit nullable parameter fix pattern (`Type $param = null` → `?Type $param = null`) with Severity_Level 🟡.
2. THE Migration_Guide SHALL document the dynamic property deprecation and removal with Severity_Level 🟡.
3. THE Migration_Guide SHALL note that downstream projects should run their own PHP 8.5 compatibility checks.


### Requirement 12: Check_Script 核心扫描功能

**User Story:** 作为下游项目开发者，我希望运行检查脚本自动扫描项目代码，检测对已移除/已变更 API 的引用，以便在升级前评估影响范围。

#### Acceptance Criteria

1. THE Check_Script SHALL accept a target directory path as command-line argument.
2. THE Check_Script SHALL recursively scan all `.php` files in the target directory.
3. THE Check_Script SHALL detect references to Removed_API classes: `SilexKernel`, `Silex\Application`, `Pimple\Container`, `Pimple\ServiceProviderInterface`, `Silex\Api\BootableProviderInterface`, `Twig_Environment`, `Twig_SimpleFunction`, `Twig_Error_Loader`.
4. THE Check_Script SHALL detect references to Changed_API interfaces: `AuthenticationPolicyInterface`, `FirewallInterface`, `AccessRuleInterface`, `AbstractSimplePreAuthenticator`, `AbstractSimplePreAuthenticateUserProvider`, `MiddlewareInterface` (old signature), `ResponseRendererInterface` (old signature).
5. THE Check_Script SHALL detect usage of Pimple-style service access patterns (`$app['...']`).
6. THE Check_Script SHALL detect usage of old Symfony event classes (`FilterResponseEvent`, `GetResponseEvent`, `GetResponseForExceptionEvent`, `HttpKernelInterface::MASTER_REQUEST`).
7. THE Check_Script SHALL detect references to removed packages (`silex/silex`, `silex/providers`, `twig/extensions`) in `composer.json` files found in the target directory.
8. THE Check_Script SHALL detect references to old Guzzle 6.x patterns where identifiable (e.g., `GuzzleHttp\Psr7\Request` constructor changes).

### Requirement 13: Check_Script 输出格式

**User Story:** 作为下游项目开发者，我希望检查脚本输出结构化的报告，包含文件路径、行号和建议操作，以便有针对性地执行适配。

#### Acceptance Criteria

1. THE Check_Script SHALL output a Structured_Report to stdout.
2. THE Structured_Report SHALL include for each finding: file path (relative to target directory), line number, detected issue description, and suggested action.
3. THE Check_Script SHALL group findings by Severity_Level (🔴 必须改 → 🟡 建议改 → 🟢 可选).
4. THE Check_Script SHALL output a summary at the end: total findings count, count per Severity_Level.
5. WHEN no issues are found THEN THE Check_Script SHALL output a success message indicating the project appears compatible.
6. THE Check_Script SHALL return exit code 0 when no 🔴 findings exist, and exit code 1 when 🔴 findings exist.

### Requirement 14: Check_Script 分发与集成

**User Story:** 作为下游项目开发者，我希望通过 `composer` 安装 `oasis/http` 后直接使用检查脚本，以便无需额外配置即可运行检查。

#### Acceptance Criteria

1. THE Check_Script SHALL be located at `bin/oasis-http-migrate-v3-check`.
2. THE `composer.json` SHALL include `"bin": ["bin/oasis-http-migrate-v3-check"]` to expose the script to `vendor/bin/`.
3. THE Check_Script SHALL be a self-contained PHP script, not requiring additional composer dependencies beyond `oasis/http` itself.
4. THE Check_Script SHALL include a `--help` option that displays usage instructions.
5. THE Check_Script SHALL include a `--format` option supporting `text` (default) and `json` output formats.
6. WHEN `--format=json` is specified THEN THE Check_Script SHALL output findings as a JSON array, each element containing `file`, `line`, `severity`, `issue`, and `action` fields.

### Requirement 15: Check_Script 健壮性

**User Story:** 作为下游项目开发者，我希望检查脚本能正确处理各种边缘情况，以便在不同项目结构下可靠运行。

#### Acceptance Criteria

1. IF the target directory does not exist THEN THE Check_Script SHALL output an error message and return exit code 2.
2. IF the target directory contains no `.php` files THEN THE Check_Script SHALL output a message indicating no PHP files found and return exit code 0.
3. THE Check_Script SHALL skip binary files and non-UTF-8 files without crashing.
4. THE Check_Script SHALL handle symlinks gracefully, avoiding infinite loops.
5. IF a file cannot be read due to permissions THEN THE Check_Script SHALL report a warning and continue scanning other files.


---

## Socratic Review

**Q: Migration_Guide 的内容来源是否可靠？如何确保不臆造变更？**
A: C-3 约束明确要求内容基于 `docs/changes/unreleased/php85-upgrade.md`（Breaking_Change_Record）的实际产出。R1 AC3 要求覆盖 ALL breaking changes recorded in Breaking_Change_Record。Design 阶段应建立 Breaking_Change_Record 条目到 Migration_Guide 章节的映射表，确保无遗漏。

**Q: Check_Script 的检测规则如何确定？是否会产生大量误报？**
A: R12 列出的检测目标（Removed_API、Changed_API、Pimple 模式、旧 Symfony 事件类）都是确定性的 breaking change——引用这些符号的代码在新版本下必然无法编译或运行。基于文本模式匹配的检测可能存在误报（如注释中的引用、字符串中的类名），但误报优于漏报。Design 阶段可考虑简单的 AST 分析或 token 解析来减少误报。

**Q: Check_Script 为什么不使用 PHPStan 或 Rector 等现有工具？**
A: 目标是提供一个零依赖、开箱即用的检查工具。PHPStan 需要项目能编译（但升级前可能无法编译），Rector 是升级执行工具而非检查工具。Check_Script 的定位是"升级前的影响评估"，不需要完整的静态分析能力，基于模式匹配即可满足需求。

**Q: `--format=json` 输出是否属于过度设计？**
A: 不是。JSON 输出支持 CI/CD 集成（如在 pipeline 中解析结果、生成报告），这是检查工具的常见需求。实现成本低（将内部数据结构序列化为 JSON），但显著提升工具的可集成性。

**Q: R12 AC4 中检测 Changed_API 的"old signature"如何定义？**
A: 对于 Changed_API，Check_Script 检测的是对这些接口/类的引用本身——因为接口签名已变更，任何实现了旧接口的下游代码都需要审查。Check_Script 不需要判断下游代码是否已适配新签名，只需标记"此处引用了已变更的接口，请检查是否需要适配"。

**Q: 与 goal.md 的 scope 是否一致？**
A: 完全一致。goal.md 定义了两个交付物（Migration Guide + 检查脚本）和四项不包含内容（实际升级执行、oasis/http 代码变更、运行时兼容层、自动化升级脚本）。Requirements 覆盖了两个交付物的所有方面，不涉及任何不包含内容。goal.md 的 Clarification Round 四项决策均已体现在约束和 requirements 中。

**Q: R10 将 Routing、CORS、Cookie、Views 合并为一个 Requirement，是否粒度过粗？**
A: 这四个模块的下游影响较小——Routing 的 Bootstrap_Config key 行为保持不变，CORS 的 `CrossOriginResourceSharingStrategy` API 保持不变，Cookie 的 `ResponseCookieContainer` API 保持不变，Views 仅有类型参数从 `SilexKernel` 变为 `MicroKernel`。将它们合并为一个 Requirement 反映了实际影响程度，避免为低影响模块创建过多独立 Requirement。如果 design 阶段发现某个模块的变更比预期复杂，可以拆分。

**Q: Check_Script 的退出码设计是否合理？**
A: R13 AC6 定义了三种退出码：0（无 🔴 问题）、1（存在 🔴 问题）、2（输入错误）。这遵循 Unix 工具惯例，支持在 CI/CD 中通过退出码判断是否阻断 pipeline。🟡 和 🟢 问题不阻断退出码，因为它们不影响编译和运行。

**Q: R3–R11 列出的具体 API 变更是否与 Breaking_Change_Record 和 architecture SSOT 一致？是否有遗漏？**
A: 经交叉验证，R3–R11 覆盖了 Breaking_Change_Record 中所有面向下游的 breaking change。Breaking_Change_Record 中还提到 `phpunit/phpunit` 升级（`^5.2` → `^13.0`）和 `phpstan/phpstan` 新增（`^2.1`），但这些是开发依赖（dev-dependency），不影响下游项目的运行时代码，因此 R7 未将其列入是合理的。`giorgiosironi/eris` 同理。如果 design 阶段认为有必要在 Migration_Guide 中提及开发依赖变更（作为参考信息），可以在 R7 中补充一条 🟢 可选条目。



---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [术语] 移除 Glossary 中的孤立术语 `Deprecated_Config_Key`（在 AC 中从未使用）
- [内容] Socratic Review 补充一条 Q&A：R3–R11 列出的 API 变更与 Breaking_Change_Record / architecture SSOT 的交叉验证，确认覆盖完整，并说明开发依赖（phpunit、phpstan、eris）未列入 R7 的合理性

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（术语表与 AC 交叉引用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围，明确了不涉及的内容
- [x] Glossary 存在且非空，无孤立术语，无未定义术语
- [x] Requirements section 包含 15 条 requirement，编号连续
- [x] 各 section 之间使用 `---` 分隔
- [x] 所有 User Story 使用中文行文
- [x] 所有 AC 使用 SHALL / WHEN-THEN / IF-THEN 语体
- [x] AC Subject 使用 Glossary 中定义的术语
- [x] Socratic Review 存在且覆盖充分（8 个 Q&A）
- [x] Goal CR 四项决策均已体现在约束和 requirements 中
- [x] 与 Breaking_Change_Record 交叉验证覆盖完整
- [x] 与 architecture SSOT 一致
- [○] 内容边界：R3–R11 包含具体类名和方法签名，但作为迁移文档 spec 这是核心内容而非实现细节，判定合理

### Clarification Round

**状态**: ✅ 已回答

**Q1:** R12 AC4 将 `MiddlewareInterface` 和 `ResponseRendererInterface` 列为 Changed_API，Check_Script 检测到引用后会标记"请检查是否需要适配"。但这两个接口的变更性质不同——`MiddlewareInterface` 是方法签名变更（移除 `Silex\Application` 参数），`ResponseRendererInterface` 是类型参数变更（`SilexKernel` → `MicroKernel`）。Check_Script 对这两类变更的检测策略是否应有区别？

**A:** A) 统一检测：只要引用了这些接口名就标记，不区分变更类型，由开发者自行判断

**Q2:** R13 AC3 要求按 Severity_Level 分组输出。对于 Check_Script 检测到的 Changed_API 引用（如 `AuthenticationPolicyInterface`），这些接口在新版本中仍然存在但签名已变更——下游代码引用它们不会导致"找不到类"的编译错误，但实现了旧签名的代码会报错。这类 finding 应归为哪个 Severity_Level？

**A:** A) 🔴 必须改：因为实现了旧接口签名的代码必然报错

**Q3:** R14 AC2 要求在 `composer.json` 中添加 `"bin"` 配置。当前 `composer.json` 可能已有其他 `"bin"` 条目或没有 `"bin"` key。Design 阶段需要决定如何处理这个配置变更——是作为 task 的一部分在实现时修改，还是在 requirements 中明确约束 `"bin"` 数组的最终状态？

**A:** A) 仅约束"Check_Script 可通过 `vendor/bin/` 访问"这一外部行为，具体 composer.json 修改方式留给 design/task

**Q4:** R12 AC8 要求检测"old Guzzle 6.x patterns where identifiable"，但 Guzzle 6→7 的 breaking change 主要在行为层面（如异常处理、默认选项），纯文本模式匹配很难可靠检测。Design 阶段需要决定这条 AC 的实现深度。

**A:** B) 检测 `new Client()` 构造方式和常见的 Guzzle 6.x 选项名（如 `'exceptions' => false`）
