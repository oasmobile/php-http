# Requirements Document

> PHP 8.5 Upgrade — Phase 2: Twig & HTTP Client Upgrade — `.kiro/specs/php85-phase2-twig-guzzle-upgrade/`

---

## Introduction

Phase 1 已完成 Silex → Symfony MicroKernel 替换和全部 Symfony 组件升级到 7.x。`composer.json` 中 `twig/twig` 的版本约束已在 Phase 1 更新为 `^3.0`，`symfony/twig-bridge` 已升级到 `^7.2`，`twig/extensions` 已移除。`SimpleTwigServiceProvider` 已使用 Twig 3.x 命名空间（`Twig\Environment`、`Twig\Loader\FilesystemLoader`、`Twig\TwigFunction`），但 Twig 相关业务代码和测试尚未完成 Twig 3.x 特性重构。

`guzzlehttp/guzzle` 仍停留在 `^6.3`，不支持 PHP 8.4+。Guzzle 的使用集中在 `MicroKernel::setCloudfrontTrustedProxies()` 中：通过 `new GuzzleHttp\Client()` 发起 HTTP 请求获取 AWS IP ranges，并使用 `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 辅助函数处理 JSON。这些辅助函数在 Guzzle 7 中已移除。测试文件 `ElbTrustedProxyTest.php` 中也大量使用了这些辅助函数（12 处 `json_decode`）。

**不涉及的内容**：

- 框架替换（Phase 1 已完成）
- Security 组件的 authenticator 系统重写（Phase 3 / PRP-005）
- PHP 语言层面 breaking changes 修复（Phase 4 / PRP-006）
- 引入新的模板功能或 HTTP 客户端功能
- `twig/extensions` 处理（项目中未使用，Phase 1 已移除）

**约束**：

- C-1: PRP-002 至 PRP-007 共享 `feature/php85-upgrade` 分支，本 Phase 在该分支上推进
- C-2: 依赖 Phase 1 完成（Symfony 组件已升级到 7.x，`symfony/twig-bridge` 已就位）
- C-3: Twig 适配不仅验证兼容性，还需重构以利用 Twig 3.x 特性（goal Q1 决策 B）
- C-4: `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 替换为 PHP 原生函数 + `JSON_THROW_ON_ERROR`（goal Q2 决策 A+B）
- C-5: Guzzle 保持使用具体类 `GuzzleHttp\Client` 及其便捷方法（`$client->request()`），仅做版本升级和 JSON 函数替换，不迁移到 PSR-18 接口（goal Q3 决策改为 A）
- C-6: spec 级 DoD：tasks 全部完成 + PRP-004 中定义的预期通过 suite 实际通过（在 Phase 1 基础上新增 `twig` suite）
- C-7: 预期仍失败的 suite：`security`（authenticator 系统未重写，等 Phase 3）、`integration`（部分集成测试依赖 Security 完整链路）

---

## Glossary

- **MicroKernel**: 核心入口类（Phase 1 已从 `SilexKernel` 迁移），继承 Symfony `Kernel` + `MicroKernelTrait`
- **Bootstrap_Config**: `MicroKernel` 构造函数接受的关联数组，包含 `twig`、`behind_elb`、`trust_cloudfront_ips` 等顶层 key
- **SimpleTwigServiceProvider**: Twig 服务提供者，读取 Bootstrap_Config 的 `twig` key，创建 `Twig\Environment` 实例并注册到 MicroKernel
- **TwigConfiguration**: Symfony Config 定义类，校验 Bootstrap_Config 中 `twig` key 的结构（`template_dir`、`cache_dir`、`asset_base`、`globals`）
- **DefaultHtmlRenderer**: 异常渲染器，在 Twig 可用时使用 Twig 模板渲染错误页，不可用时回退到 JSON 序列化
- **Guzzle_Client**: `GuzzleHttp\Client`，当前用于 `setCloudfrontTrustedProxies()` 中获取 AWS IP ranges，升级后继续使用其便捷方法（`$client->request()`）
- **JSON_THROW_ON_ERROR**: PHP 7.3+ 引入的 `json_decode()` / `json_encode()` flag，解析失败时抛出 `\JsonException` 而非静默返回 `null` / `false`
- **Twig_3x_Feature**: Twig 3.x 引入的新特性，包括严格变量模式（`strict_variables`）、自动重载（`auto_reload`）等配置级改进
- **AWS_IP_Ranges**: AWS 公开的 IP 地址范围 JSON 数据，用于识别 CloudFront 可信代理 IP

---

## Requirements

### Requirement 1: P0 — Guzzle 依赖升级

**User Story:** 作为迁移开发者，我希望将 `guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`，以便项目在 PHP 8.5 上能正常使用 HTTP 客户端。

#### Acceptance Criteria

1. THE `composer.json` SHALL update `guzzlehttp/guzzle` version constraint from `^6.3` to `^7.0`.
2. WHEN `composer install` is executed THEN dependency resolution SHALL succeed without conflicts.
3. THE `composer.json` SHALL retain all other existing dependencies unchanged.

### Requirement 2: P0 — Guzzle JSON 辅助函数替换

**User Story:** 作为迁移开发者，我希望将所有 `\GuzzleHttp\json_decode()` / `\GuzzleHttp\json_encode()` 调用替换为 PHP 原生函数 + `JSON_THROW_ON_ERROR`，以便代码不再依赖 Guzzle 6 已移除的辅助函数。

#### Acceptance Criteria

1. THE source code SHALL replace all `\GuzzleHttp\json_decode()` calls with PHP native `\json_decode()` using JSON_THROW_ON_ERROR.
2. THE source code SHALL replace all `\GuzzleHttp\json_encode()` calls with PHP native `\json_encode()` using JSON_THROW_ON_ERROR.
3. THE test code SHALL replace all `\GuzzleHttp\json_decode()` calls with PHP native `\json_decode()` using JSON_THROW_ON_ERROR.
4. WHEN JSON parsing fails THEN `\json_decode()` with `JSON_THROW_ON_ERROR` SHALL throw `\JsonException`, preserving the error-on-failure behavior of the original `\GuzzleHttp\json_decode()`.
5. WHEN JSON encoding fails THEN `\json_encode()` with `JSON_THROW_ON_ERROR` SHALL throw `\JsonException`, preserving the error-on-failure behavior of the original `\GuzzleHttp\json_encode()`.
6. THE codebase SHALL contain zero remaining references to `\GuzzleHttp\json_decode` or `\GuzzleHttp\json_encode` after migration.

### Requirement 3: P1 — Twig 3.x 兼容性验证与代码适配

**User Story:** 作为迁移开发者，我希望验证现有 Twig 相关代码在 Twig 3.x 下正常工作，并修复发现的兼容性问题，以便 Twig 功能在升级后行为不变。

#### Acceptance Criteria

1. THE `SimpleTwigServiceProvider` SHALL use Twig 3.x API exclusively: `Twig\Environment`、`Twig\Loader\FilesystemLoader`、`Twig\TwigFunction`.
2. THE `DefaultHtmlRenderer` SHALL catch `Twig\Error\LoaderError`（Twig 3.x 异常类）when template loading fails.
3. THE `TwigConfiguration` SHALL continue to validate `template_dir`、`cache_dir`、`asset_base`、`globals` fields correctly under Twig 3.x.
4. THE Twig template files SHALL be compatible with Twig 3.x syntax: `{% extends %}`、`{% block %}`、`{% include %}`、`{% import %}`、`{% use %}`、`{% macro %}`、`{% verbatim %}`、`{{ escape }}`、`{{ escape('js') }}` SHALL render correctly.
5. THE `is_granted()` custom Twig function SHALL continue to work in templates.
6. THE `asset()` custom Twig function SHALL continue to work in templates, prepending `asset_base` and appending version query string.
7. WHEN the `twig` test suite is executed THEN all tests SHALL pass.

### Requirement 4: P1 — Twig 3.x 特性重构

**User Story:** 作为迁移开发者，我希望重构 Twig 相关代码以利用 Twig_3x_Feature，以便代码更符合 Twig 3.x 的最佳实践。

#### Acceptance Criteria

1. THE SimpleTwigServiceProvider SHALL enable `strict_variables` mode in Twig Environment options, leveraging Twig_3x_Feature 的严格变量检查.
2. THE SimpleTwigServiceProvider SHALL set `auto_reload` option to `true` when MicroKernel is in debug mode, leveraging Twig_3x_Feature 的自动重载特性.
3. THE `TwigConfiguration` SHALL add `strict_variables` as a configurable boolean option with default value `true`.
4. THE `TwigConfiguration` SHALL add `auto_reload` as a configurable boolean option with default value `null`（auto-detect based on debug mode）.
5. THE existing Twig template rendering behavior SHALL remain unchanged after refactoring — all existing tests SHALL continue to pass.

### Requirement 5: P1 — 测试适配

**User Story:** 作为迁移开发者，我希望所有受影响的测试适配到 Guzzle 7 和 Twig 3.x，以便 PRP-004 定义的预期通过 suite 实际通过。

#### Acceptance Criteria

1. THE Twig-related tests SHALL verify that Twig_3x_Feature options（`strict_variables`、`auto_reload`）are correctly applied to the Twig Environment.
2. THE Twig configuration tests SHALL verify that the new `strict_variables` and `auto_reload` configuration options are correctly validated.
3. THE following test suites SHALL pass after migration: `twig`、`aws`、`configuration`（Twig 相关）.
4. THE following test suites are expected to fail and SHALL NOT block Phase 2 completion: `security`、`integration`.

---

## Socratic Review

**Q: Phase 2 的 scope 与 Phase 1 的 Twig 工作是否有重叠？**
A: Phase 1 已完成 `twig/twig` 版本约束升级到 `^3.0`、`twig/extensions` 移除、`SimpleTwigServiceProvider` 的 Pimple 依赖移除和 Twig 3.x 命名空间迁移。Phase 2 的 Twig 工作聚焦于：(1) 验证现有代码和模板在 Twig 3.x 下的兼容性；(2) 重构以利用 Twig 3.x 新特性（`strict_variables`、`auto_reload`）。两者关注点不同，不存在重叠。

**Q: `\GuzzleHttp\json_decode()` 与 PHP 原生 `json_decode()` + `JSON_THROW_ON_ERROR` 的行为是否完全等价？**
A: 基本等价。Guzzle 6 的 `json_decode()` 在解析失败时抛出 `\InvalidArgumentException`，而 PHP 原生 `json_decode()` + `JSON_THROW_ON_ERROR` 抛出 `\JsonException`。异常类型不同，但"解析失败时抛异常"的行为语义一致。当前代码中对这些调用的异常处理使用的是 `\Throwable` catch，因此异常类型变化不会影响现有行为。

**Q: 为什么 Requirement 4 选择 `strict_variables` 和 `auto_reload` 作为 Twig 3.x 重构目标？**
A: 这两个选项是 Twig 3.x 中最具实际价值且风险可控的特性改进。`strict_variables` 在开发阶段帮助发现模板中的未定义变量引用，提高代码质量。`auto_reload` 在 debug 模式下自动检测模板变更并重新编译，改善开发体验。两者都是配置级别的改动，不涉及模板语法变更，对现有功能的影响可控。

**Q: 与 proposal（PRP-004）的 scope 是否一致？**
A: 基本一致。PRP-004 定义的 Goals 中"移除 `twig/extensions`"已在 Phase 1 完成（项目中未使用该包）；"适配 Guzzle 7 的 API 变化（PSR-18 兼容）"在 CR 讨论后决定不迁移到 PSR-18 接口，保持使用 Guzzle 具体类及其便捷方法，仅做版本升级和 JSON 函数替换。其余目标（Twig 适配、Guzzle 升级）均已体现在 Requirements 中。PRP-004 的 Non-Goals（不涉及框架替换、Security 重构、新功能引入）与 Requirements 的 Introduction 一致。spec 级 DoD 与 PRP-004 定义的预期通过 suite 一致。

**Q: 各 Requirement 之间是否存在矛盾或重叠？**
A: R1（依赖升级）是 R2（JSON 函数替换）的前提。R3（Twig 兼容性验证）和 R4（Twig 特性重构）关注 Twig 的不同层面——R3 确保现有功能不变，R4 引入新特性。R5（测试适配）依赖 R1–R4 的实现完成。各 Requirement 之间不存在矛盾。

**Q: 为什么放弃 PSR-18 接口编程？**
A: CR 讨论中用户希望保留 Guzzle 的便捷方法（`$client->request('GET', $url)`），而 PSR-18 `ClientInterface` 只定义了 `sendRequest(RequestInterface)` 方法。项目中只有一处 HTTP 调用（获取 AWS IP ranges），PSR-18 抽象的收益有限，且用户不需要替换 Guzzle 为其他 HTTP 客户端。因此 goal Q3 决策从 B（PSR-18 接口编程）改为 A（保持 Guzzle 具体类）。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] Introduction 中 `ElbTrustedProxyTest.php` 的 Guzzle JSON 辅助函数数量从"约 15 处"修正为"12 处 `json_decode`"（实际计数：测试文件 12 处 `\GuzzleHttp\json_decode`，无 `json_encode`）
- [内容] R2 AC1-2 移除了具体函数签名中的 depth 参数 `512`，改为描述行为层面的替换要求（"使用 JSON_THROW_ON_ERROR"），避免将实现细节写入 requirements
- [内容] 原 R6 AC1-2 移除：与 R2 AC3 重叠。R6（现 R5）精简为聚焦 Twig 测试适配和 suite 通过标准
- [术语] Glossary 中 `Twig_3x_Feature` 定义修正为聚焦本 spec 实际使用的特性（`strict_variables`、`auto_reload`），并在 R5 User Story 和 AC 中引用该术语，消除孤立术语
- [语体] R5 AC1-2 中的 Subject 从反引号包裹的类名改为 Glossary 术语形式

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（术语表术语在 AC 中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空，无孤立术语
- [x] Requirements section 存在且包含 5 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] AC 使用 THE...SHALL / WHEN...THEN 语体
- [x] AC 编号连续，无跳号
- [x] User Story 使用中文行文
- [x] AC 聚焦外部可观察行为，不含实现细节
- [x] Socratic Review 覆盖充分（scope 重叠、行为等价性、特性选择理由、proposal 一致性、requirement 矛盾、PSR-18 放弃理由）
- [x] Goal CR 决策已体现在 requirements 中（Q1→R3+R4, Q2→R2, Q3 改为 A→不迁移 PSR-18）
- [x] 与 PRP-004 scope / non-goals 一致

### Clarification Round

**状态**: 已完成

**Q1:** R3（原 PSR-18 迁移）要求 MicroKernel 通过 PSR-18_ClientInterface 发送请求，但 PSR-18 的 `sendRequest()` 需要一个 `RequestInterface` 实例。构造 `RequestInterface` 的方式会影响 design 选型。你倾向哪种方式？
- A) 直接使用 `GuzzleHttp\Psr7\Request`（Guzzle 7 自带的 PSR-7 实现），简单直接但保留了对 Guzzle PSR-7 包的依赖
- B) 引入 PSR-17 `RequestFactoryInterface`，通过工厂接口创建 Request，进一步解耦但增加一层抽象
- C) 不额外抽象，在 fallback 路径中直接使用 Guzzle PSR-7 Request，注入路径中由调用方负责提供 Request 构造方式
- D) 其他（请说明）

**A:** D — 放弃 PSR-18 接口编程，保持使用 Guzzle `Client` 具体类及其便捷方法（`$client->request('GET', $url)`），仅做版本升级。原 R3 已删除，goal Q3 决策从 B 改为 A。

**Q2:** R5（现 R4）引入 `strict_variables` 默认值为 `true`。这意味着所有模板中引用未定义变量时会抛出异常。当前模板是否已确认不存在未定义变量引用？如果存在，你倾向哪种处理方式？
- A) 默认 `true`，发现问题时修复模板（严格模式优先）
- B) 默认 `false`，保持与升级前一致的宽松行为，后续单独开启
- C) 默认 `true` 但仅在 debug 模式下生效，生产环境保持 `false`
- D) 其他（请说明）

**A:** A — 默认 `true`，发现问题时修复模板。

**Q3:** R5 AC2（现 R4 AC2）要求 `auto_reload` 在 debug 模式下设为 `true`。当前 SimpleTwigServiceProvider 的 `register()` 方法签名接受 `MicroKernel $kernel` 和 `array $twigConfig`，但 debug 模式信息需要从 `$kernel->isDebug()` 获取。如果 `auto_reload` 配置值为 `null`（auto-detect），provider 需要同时读取配置和 kernel 状态。这种"配置 + 运行时状态"的混合决策模式是否可接受？
- A) 可接受，provider 已经接受 `$kernel` 参数，读取 `isDebug()` 是合理的
- B) 不可接受，`auto_reload` 的最终值应在配置层面完全确定，不依赖运行时状态
- C) 将 `auto_reload` 的 auto-detect 逻辑移到 MicroKernel 侧，在调用 provider 前解析好最终值
- D) 其他（请说明）

**A:** A — 可接受，provider 已经接受 `$kernel` 参数，读取 `isDebug()` 是合理的。
