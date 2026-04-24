# Implementation Plan: PHP 8.5 Phase 2 — Twig & HTTP Client Upgrade

## Overview

将 `guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`，替换已移除的 Guzzle JSON 辅助函数为 PHP 原生 `json_decode()` / `json_encode()` + `JSON_THROW_ON_ERROR`，并重构 Twig 相关代码以利用 Twig 3.x 特性（`strict_variables`、`auto_reload`）。按 design CR 决策：R1+R2 合并为一个 task（紧密耦合）；R4 按模块拆分（TwigConfiguration + SimpleTwigServiceProvider）；state 文档更新作为最后一个实现 task。

## Tasks

- [x] 1. Guzzle 升级与 JSON 辅助函数替换（R1 + R2）
  - [x] 1.1 编写 JSON 函数替换的验证测试（RED）
    - 在 `ut/AwsTests/ElbTrustedProxyTest.php` 中将所有 12 处 `\GuzzleHttp\json_decode()` 替换为 `\json_decode()` + `JSON_THROW_ON_ERROR`
    - `loadAwsIpRanges()` 方法中 3 处：`\GuzzleHttp\json_decode($content, true)` → `\json_decode($content, true, 512, JSON_THROW_ON_ERROR)`
    - 各测试方法中 9 处：`\GuzzleHttp\json_decode($response->getContent(), true)` → `\json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)`
    - 此时测试应编译失败（Guzzle 6 仍在，但测试已不使用其 JSON 函数）——确认测试代码本身无语法错误即可
    - _Ref: R2 AC3/AC6, R5 AC3_
  - [x] 1.2 升级 `composer.json` 并替换源代码中的 JSON 函数（GREEN）
    - `composer.json` 中 `guzzlehttp/guzzle` 版本约束从 `^6.3` 改为 `^7.0`
    - `src/MicroKernel.php` 的 `setCloudfrontTrustedProxies()` 方法中替换 3 处 JSON 函数调用：
      - 缓存读取：`\GuzzleHttp\json_decode($content, true)` → `\json_decode($content, true, 512, JSON_THROW_ON_ERROR)`
      - AWS 响应解析：`\GuzzleHttp\json_decode($content, true)` → `\json_decode($content, true, 512, JSON_THROW_ON_ERROR)`
      - 缓存写入：`\GuzzleHttp\json_encode($awsIps, \JSON_PRETTY_PRINT)` → `\json_encode($awsIps, \JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)`
    - 移除 `src/MicroKernel.php` 中的 `use GuzzleHttp\Client;` import（如果 `new Client(...)` 改为 `new \GuzzleHttp\Client(...)` 内联引用，或保留 import 但确认无 `\GuzzleHttp\json_*` 引用）
    - 执行 `composer update guzzlehttp/guzzle` 确认依赖解析成功
    - 全局搜索确认 codebase 中零残留 `\GuzzleHttp\json_decode` / `\GuzzleHttp\json_encode` 引用
    - _Ref: R1 AC1/AC2/AC3, R2 AC1/AC2/AC4/AC5/AC6_
  - [x] 1.3 Checkpoint: 运行 `phpunit --testsuite aws` 确认 aws suite 通过。全局搜索 `\GuzzleHttp\json_decode` 和 `\GuzzleHttp\json_encode` 确认零残留。Commit。

- [x] 2. TwigConfiguration 扩展与测试（R4 模块 A）
  - [x] 2.1 编写 TwigConfiguration 新配置项测试（RED）
    - 在 `ut/Configuration/TwigConfigurationTest.php` 中新增以下测试方法：
      - `testStrictVariablesDefaultsToTrue`：验证不传入 `strict_variables` 时默认值为 `true`
      - `testStrictVariablesExplicitFalse`：验证 `strict_variables` 可显式设为 `false`
      - `testAutoReloadDefaultsToNull`：验证不传入 `auto_reload` 时默认值为 `null`
      - `testAutoReloadExplicitTrue`：验证 `auto_reload` 可显式设为 `true`
      - `testAutoReloadExplicitFalse`：验证 `auto_reload` 可显式设为 `false`
    - 此时测试应失败（RED）——TwigConfiguration 尚未添加新配置项
    - _Ref: R4 AC3/AC4, R5 AC2_
  - [x] 2.2 实现 TwigConfiguration 新配置项（GREEN）
    - 在 `src/Configuration/TwigConfiguration.php` 的 `getConfigTreeBuilder()` 中新增：
      - `booleanNode('strict_variables')->defaultTrue()`
      - `enumNode('auto_reload')->values([true, false, null])->defaultNull()->end()`
    - 确认现有配置项（`template_dir`、`cache_dir`、`asset_base`、`globals`）不受影响
    - _Ref: R4 AC3/AC4_
  - [x] 2.3 Checkpoint: 运行 `phpunit --testsuite configuration` 确认 configuration suite 通过（含新增的 `strict_variables` 和 `auto_reload` 测试）。Commit。

- [x] 3. SimpleTwigServiceProvider 特性重构与测试（R4 模块 B）
  - [x] 3.1 编写 SimpleTwigServiceProvider 新特性测试（RED）
    - 在 `ut/Twig/TwigServiceProviderTest.php` 中新增以下测试方法：
      - `testStrictVariablesEnabledByDefault`：默认配置下 `$twig->isStrictVariables()` 返回 `true`
      - `testStrictVariablesDisabledWhenConfigured`：配置 `strict_variables: false` 时 `$twig->isStrictVariables()` 返回 `false`
      - `testAutoReloadEnabledInDebugMode`：debug 模式 + `auto_reload` 未配置（null）时 `$twig->isAutoReload()` 返回 `true`
      - `testAutoReloadDisabledInNonDebugMode`：非 debug 模式 + `auto_reload` 未配置（null）时 `$twig->isAutoReload()` 返回 `false`
      - `testAutoReloadExplicitOverride`：显式配置 `auto_reload: true` 或 `false` 时覆盖 auto-detect 逻辑
    - 此时测试应失败（RED）——SimpleTwigServiceProvider 尚未读取新配置项
    - _Ref: R4 AC1/AC2, R5 AC1_
  - [x] 3.2 实现 SimpleTwigServiceProvider 新特性（GREEN）
    - 在 `src/ServiceProviders/Twig/SimpleTwigServiceProvider.php` 的 `register()` 方法中：
      - 读取 `strict_variables` 配置：`$dataProvider->getOptional('strict_variables', DataProviderInterface::BOOL_TYPE, true)`
      - 读取 `auto_reload` 配置：`$dataProvider->getOptional('auto_reload')`
      - 设置 `$options['strict_variables'] = $strictVariables`
      - `auto_reload` 为 `null` 时通过 `$kernel->isDebug()` auto-detect；否则使用显式值
    - 确认现有 Twig 功能（globals、asset 函数、is_granted 函数、cache_dir）不受影响
    - _Ref: R4 AC1/AC2, R4 AC5_
  - [x] 3.3 Checkpoint: 运行 `phpunit --testsuite twig` 确认 twig suite 通过（含新增的 `strict_variables` 和 `auto_reload` 测试，以及所有现有测试）。Commit。

- [x] 4. Twig 3.x 兼容性验证（R3）
  - [x] 4.1 验证现有 Twig 代码和模板的 Twig 3.x 兼容性
    - 确认 `SimpleTwigServiceProvider` 使用 Twig 3.x API：`Twig\Environment`、`Twig\Loader\FilesystemLoader`、`Twig\TwigFunction`
    - 确认 `DefaultHtmlRenderer` 捕获 `Twig\Error\LoaderError`（Twig 3.x 异常类）
    - 确认 `TwigConfiguration` 在 Twig 3.x 下正常校验配置
    - 确认所有模板文件（`a.twig`、`a2.twig`、`b.twig`、`footer.twig`、`macros.twig`、`side.twig`）使用 Twig 3.x 兼容语法
    - 确认 `is_granted()` 和 `asset()` 自定义 Twig 函数正常工作
    - 如发现兼容性问题，修复代码或模板
    - _Ref: R3 AC1/AC2/AC3/AC4/AC5/AC6_
  - [x] 4.2 Checkpoint: 运行 `phpunit --testsuite twig` 和 `phpunit --testsuite views` 确认 twig 和 views suite 通过。Commit。

- [x] 5. State 文档更新
  - [x] 5.1 更新 `docs/state/architecture.md` 的 Bootstrap Config 表
    - 在 `twig` 配置说明中补充 `twig.strict_variables`（boolean, 默认 `true`，严格变量模式）和 `twig.auto_reload`（boolean/null, 默认 `null`，auto-detect based on debug mode）
    - 仅补充本 Phase 新增的配置项，不做 state 文档的全面更新（Phase 1 遗留的 `SilexKernel` → `MicroKernel` 更新不在本 Phase scope 内）
    - _Ref: Design Impact Analysis — 受影响的 state 文档_
  - [x] 5.2 Checkpoint: 确认 `docs/state/architecture.md` 更新内容准确，与 TwigConfiguration 代码一致。运行 `phpunit --testsuite twig --testsuite aws --testsuite configuration` 确认所有预期通过 suite 仍然通过。Commit。

- [-] 6. 全量验证
  - [x] 6.1 运行全量测试并确认预期 suite 通过状态
    - 运行 `phpunit` 全量测试
    - 确认以下 suite 通过：`twig`、`aws`、`configuration`、`views`、`cors`、`routing`、`cookie`、`middlewares`、`exceptions`、`error-handlers`、`misc`、`pbt`
    - 确认以下 suite 预期失败且不阻塞：`security`（authenticator 系统未重写，等 Phase 3）、`integration`（部分集成测试依赖 Security 完整链路）
    - _Ref: R3 AC7, R5 AC3/AC4_
  - [-] 6.2 Checkpoint: 全量测试结果符合预期。Commit。

- [~] 7. 手工测试
  - [ ] 7.1 编写并执行手工测试场景
    - 场景 1: Guzzle 升级验证 — 确认 `composer.json` 中 `guzzlehttp/guzzle` 为 `^7.0`，`composer show guzzlehttp/guzzle` 显示 7.x 版本，`composer install` 无冲突
    - 场景 2: JSON 函数零残留 — 全局搜索 `\GuzzleHttp\json_decode` 和 `\GuzzleHttp\json_encode`，确认 codebase 中零残留
    - 场景 3: Twig strict_variables 生效 — 创建一个引用未定义变量的临时模板，确认渲染时抛出 `Twig\Error\RuntimeError`
    - 场景 4: Twig auto_reload auto-detect — 在 debug 模式下创建 MicroKernel 实例，确认 `$twig->isAutoReload()` 返回 `true`；在非 debug 模式下确认返回 `false`
    - 场景 5: 现有模板渲染不变 — 通过 WebTestCase 发送请求到 `/twig/2`，确认 HTML 输出包含预期内容（`WOW`、`haha`、escape 处理、macro、include、globals）
    - [脚本] 所有场景可通过脚本自动化执行

- [~] 8. Code Review
  - [ ] 8.1 委托给 code-reviewer sub-agent 执行

## Notes

- 按 `spec-execution.md` 规范执行所有 task
- Commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint，通过后 commit
- Design CR 决策已体现：Q1（R1+R2 合并为 task 1）、Q2（R4 按模块拆分为 task 2 + task 3）、Q3（state 文档更新为 task 5）
- Test First 编排：task 2 和 task 3 均先写测试（RED）再实现（GREEN）；task 1 的测试替换与实现替换紧密耦合，测试文件中的 JSON 函数替换先于源代码替换
- Guzzle 升级后 `GuzzleHttp\Client` 的使用模式（`new Client([...])` + `$client->request()`）保持不变，不迁移到 PSR-18 接口
- `strict_variables` 默认 `true`，现有模板和测试中所有变量均已正确传入，不影响现有行为
- `auto_reload` 的 auto-detect 通过 `$kernel->isDebug()` 实现
- 预期仍失败的 suite：`security`（Phase 3）、`integration`（依赖 Security 完整链路），不阻塞本 Phase 完成
- spec 级 DoD：tasks 全部完成 + PRP-004 中定义的预期通过 suite 实际通过（在 Phase 1 基础上新增 `twig` suite）

## Socratic Review

**Q: tasks 是否完整覆盖了 design 中的所有实现项？**
A: 是。Components §1（Guzzle 版本升级）→ task 1.2；Components §2（JSON 函数替换）→ task 1.1 + 1.2；Components §3（Twig 兼容性验证）→ task 4；Components §4.1（TwigConfiguration 扩展）→ task 2；Components §4.2（SimpleTwigServiceProvider 适配）→ task 3；Impact Analysis state 文档更新 → task 5；Testing Strategy → task 1.1 + 2.1 + 3.1 + 6。

**Q: Design CR 的三个决策是否已体现在 task 编排中？**
A: 是。Q1（R1+R2 合并）→ task 1 将 Guzzle 升级和 JSON 函数替换合并为一个 top-level task；Q2（R4 按模块拆分）→ task 2（TwigConfiguration）和 task 3（SimpleTwigServiceProvider）分别聚焦一个文件；Q3（state 文档更新作为最后一个实现 task）→ task 5。

**Q: task 之间的依赖顺序是否正确？**
A: 是。task 1（Guzzle 升级）独立于 Twig 相关 task，放在最前面。task 2（TwigConfiguration）必须在 task 3（SimpleTwigServiceProvider）之前，因为 provider 依赖 configuration 定义。task 4（兼容性验证）在 task 2+3 之后，确认整体兼容性。task 5（state 文档）在所有代码变更之后。task 6（全量验证）在所有实现之后。task 7（手工测试）和 task 8（Code Review）按 steering 要求排在最后。

**Q: Test First 编排是否正确？**
A: 是。task 2 先写 TwigConfiguration 测试（2.1 RED）再实现（2.2 GREEN）；task 3 先写 SimpleTwigServiceProvider 测试（3.1 RED）再实现（3.2 GREEN）。task 1 的特殊性在于测试文件本身也需要 JSON 函数替换，因此 1.1 先替换测试代码，1.2 再替换源代码和升级依赖。

**Q: 每个 task 的粒度是否合适？**
A: 合适。task 1 包含 3 个 sub-task（测试替换 + 源代码替换 + checkpoint），task 2 和 3 各包含 3 个 sub-task（RED + GREEN + checkpoint），task 4 和 5 各包含 2 个 sub-task。每个 sub-task 可在独立 session 中完成。

**Q: requirements 中的每条 requirement 是否至少被一个 task 引用？**
A: 是。R1 → task 1.2；R2 → task 1.1 + 1.2；R3 → task 4.1；R4 → task 2 + 3；R5 → task 2.1 + 3.1 + 6.1。

**Q: 手工测试是否覆盖了关键用户场景？**
A: 是。5 个场景覆盖了 Guzzle 升级验证、JSON 函数零残留、strict_variables 生效、auto_reload auto-detect、现有模板渲染不变，对应 R1–R5 的核心验收标准。

**Q: 所有 task 是否都是 mandatory？**
A: 是。无 optional 标记（`*`），所有 task 均为 mandatory。


## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ✅ 通过

### 修正项
无

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1-R5 编号与 requirements.md 一致，design 模块名引用正确）
- [x] checkbox 语法正确（`- [ ]` 格式）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（task 8）
- [x] 倒数第二个 top-level task 是手工测试（task 7）
- [x] 自动化实现 task（1-6）排在手工测试和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号，sub-task 有层级序号，序号连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款（Ref 格式）
- [x] requirements.md 中的每条 requirement（R1-R5）至少被一个 task 引用，无遗漏
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在，无悬空引用
- [x] top-level task 按依赖关系排序（task 1 独立在前，task 2 在 task 3 之前，task 4 在 2+3 之后，task 5 在所有代码变更之后，task 6 在所有实现之后）
- [x] 无循环依赖
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 描述中包含具体的验证命令和 commit 动作
- [x] Test-first 编排正确（task 2: RED→GREEN，task 3: RED→GREEN，task 1 测试替换先于源代码替换）
- [x] 每个 sub-task 足够具体，可在独立 session 中执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在，覆盖关键用户场景（5 个场景），描述具体可执行
- [x] Code Review 是最后一个 top-level task，描述为委托给 code-reviewer sub-agent 执行，未展开 review checklist
- [x] `## Notes` section 存在，明确提到 `spec-execution.md`，明确 commit 随 checkpoint 执行，包含 spec 特有执行要点
- [x] `## Socratic Review` section 存在且覆盖充分（design 覆盖、CR 决策、依赖顺序、test-first、粒度、requirement 追溯、手工测试、mandatory）
- [x] Design CR 三个决策（Q1 R1+R2 合并、Q2 R4 按模块拆分、Q3 state 文档最后更新）均已在 task 编排中体现
- [x] Design 全覆盖：Components §1-§4、Impact Analysis、Testing Strategy 均有对应 task
- [x] 验收闭环完整：checkpoint + 手工测试 + code review
- [x] 执行路径无歧义
