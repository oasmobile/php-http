# Tasks

> PHP 8.5 升级前测试基线补全 — `.kiro/specs/php85-test-baseline/`

## Tasks

- [x] 1. 基础设施：phpunit.xml 空 suite 结构 + Helper 文件
  - [x] 1.1 在 `phpunit.xml` 中创建 8 个空 suite（`error-handlers`、`configuration`、`views`、`routing`、`cookie`、`middlewares`、`misc`、`integration`），暂不添加文件。现有 suite 结构不变（Ref: Requirement 13, AC 1）
  - [x] 1.2 创建 `ut/Helpers/Views/ConcreteSmartViewHandler.php`：`AbstractSmartViewHandler` 的 concrete Test_Double，将 `shouldHandle()` 暴露为 public，`getCompatibleTypes()` 返回可配置的类型列表（Ref: Requirement 3, AC 1 前置）
  - [x] 1.3 创建 `ut/Helpers/Middlewares/TestMiddleware.php`：`AbstractMiddleware` 的 concrete Test_Double，实现 `before()` / `after()` 方法，记录调用信息（Ref: Requirement 6, AC 1 前置）
  - [x] 1.4 Checkpoint: 运行 `vendor/bin/phpunit`，确认现有测试全部通过且无 warning，commit

- [x] 2. P0 — ErrorHandlers 单元测试（R1）
  - [x] 2.1 创建 `ut/ErrorHandlers/WrappedExceptionInfoTest.php`，覆盖所有场景：构造函数（正常 code / code=0 转 500）、`toArray()` normal/rich mode、`jsonSerialize()`、`getAttribute()`/`setAttribute()`、`getAttributes()`、`getCode()`/`setCode()`、`getOriginalCode()`、`getShortExceptionType()`、`serializeException()` 嵌套 previous 链、exception code 0 vs 非 0（Ref: Requirement 1, AC 1）
  - [x] 2.2 创建 `ut/ErrorHandlers/ExceptionWrapperTest.php`，覆盖所有场景：基本包装、`ExistenceViolationException`（404 + key）、`DataValidationException`（400 + key）、普通 Exception（保持原始 code）。直接引用 `oasis/utils` 外部异常类（Ref: Requirement 1, AC 2）
  - [x] 2.3 创建 `ut/ErrorHandlers/JsonErrorHandlerTest.php`，覆盖所有场景：返回数组结构、`type` 为完整类名、不同 code 值传递（Ref: Requirement 1, AC 3）
  - [x] 2.4 将 3 个测试文件注册到 `phpunit.xml` 的 `error-handlers` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 2.5 Checkpoint: 运行 `vendor/bin/phpunit --testsuite error-handlers`，全部通过且无 warning，commit

- [x] 3. P0 — Configuration 单元测试（R2）
  - [x] 3.1 创建 `ut/Configuration/HttpConfigurationTest.php`：默认值、variable 节点接受任意值、未知 key 抛 `InvalidConfigurationException`（Ref: Requirement 2, AC 1）
  - [x] 3.2 创建 `ut/Configuration/SecurityConfigurationTest.php`：array prototype、`role_hierarchy` beforeNormalization、空配置（Ref: Requirement 2, AC 2）
  - [x] 3.3 创建 `ut/Configuration/CrossOriginResourceSharingConfigurationTest.php`：`pattern` 必填、`origins` beforeNormalization、`max_age` 默认值、`credentials_allowed` 默认值、可选节点（Ref: Requirement 2, AC 3）
  - [x] 3.4 创建 `ut/Configuration/TwigConfigurationTest.php`：`template_dir` 可选、`cache_dir` 默认 null、`asset_base` 默认空字符串、`globals` 默认空数组（Ref: Requirement 2, AC 4）
  - [x] 3.5 创建 `ut/Configuration/CacheableRouterConfigurationTest.php`：`path` 可选、`cache_dir` 默认 null、`namespaces` beforeNormalization（Ref: Requirement 2, AC 5）
  - [x] 3.6 创建 `ut/Configuration/SimpleAccessRuleConfigurationTest.php`：`pattern`/`roles` 必填、`roles` beforeNormalization、`channel` enum 默认 null（Ref: Requirement 2, AC 6）
  - [x] 3.7 创建 `ut/Configuration/SimpleFirewallConfigurationTest.php`：`pattern`/`policies`/`users` 必填、`stateless` 默认 false、`misc` 默认空数组（Ref: Requirement 2, AC 7）
  - [x] 3.8 创建 `ut/Configuration/ConfigurationValidationTraitTest.php`：返回 `ArrayDataProvider`、合法配置处理、非法配置抛异常（Ref: Requirement 2, AC 8）
  - [x] 3.9 将 8 个测试文件注册到 `phpunit.xml` 的 `configuration` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 3.10 Checkpoint: 运行 `vendor/bin/phpunit --testsuite configuration`，全部通过且无 warning，commit

- [x] 4. P1 — Views 单元测试（R3）
  - [x] 4.1 创建 `ut/Views/AbstractSmartViewHandlerTest.php`，使用 `ConcreteSmartViewHandler` Test_Double：兼容类型匹配、`*/*`、空 Accept、不兼容、通配符（Ref: Requirement 3, AC 1）
  - [x] 4.2 创建 `ut/Views/JsonViewHandlerTest.php`：Accept 兼容返回 `JsonResponse`、不兼容返回 null、scalar/null 包装、数组直接返回、`getCompatibleTypes()`（Ref: Requirement 3, AC 2）
  - [x] 4.3 创建 `ut/Views/DefaultHtmlRendererTest.php`：`renderOnSuccess()` 各类型处理、`renderOnException()` 无 Twig / 有 Twig / 模板不存在 fallback。使用 mock 或最小 SilexKernel 实例（Ref: Requirement 3, AC 3）
  - [x] 4.4 创建 `ut/Views/JsonApiRendererTest.php`：数组直接返回、非数组包装、异常渲染 status code（Ref: Requirement 3, AC 4）
  - [x] 4.5 创建 `ut/Views/PrefilightResponseTest.php`：204 status + header、`addAllowedMethod()`/`getAllowedMethods()`、`freeze()`/`isFrozen()`（Ref: Requirement 3, AC 5）
  - [x] 4.6 创建 `ut/Views/RouteBasedResponseRendererResolverTest.php`：html/page → `DefaultHtmlRenderer`、api/json → `JsonApiRenderer`、未知 format 抛异常、format 优先级（Ref: Requirement 3, AC 6）
  - [x] 4.7 将 6 个测试文件注册到 `phpunit.xml` 的 `views` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 4.8 Checkpoint: 运行 `vendor/bin/phpunit --testsuite views`，全部通过且无 warning，commit

- [x] 5. P1 — Routing 单元测试（R4）
  - [x] 5.1 创建 `ut/Routing/GroupUrlMatcherTest.php`：首个 matcher 成功、fallback 到下一个、全部失败抛异常、`matchRequest()` 委托、context 管理（Ref: Requirement 4, AC 1）
  - [x] 5.2 创建 `ut/Routing/GroupUrlGeneratorTest.php`：首个 generator 成功、fallback、全部失败、context 传递、context 管理（Ref: Requirement 4, AC 2）
  - [x] 5.3 创建 `ut/Routing/CacheableRouterUrlMatcherWrapperTest.php`：委托 match、namespace 前缀、类已存在不修改、context 委托（Ref: Requirement 4, AC 3）
  - [x] 5.4 创建 `ut/Routing/InheritableRouteCollectionTest.php`：构造复制路由、`addDefaults()` 添加/不覆盖（Ref: Requirement 4, AC 4）
  - [x] 5.5 创建 `ut/Routing/InheritableYamlFileLoaderTest.php`：`import()` 返回 `InheritableRouteCollection`。使用真实 YAML 文件（Ref: Requirement 4, AC 5）
  - [x] 5.6 创建 `ut/Routing/CacheableRouterTest.php`：`%param%` 替换、参数不存在保留、`%%` 转义、只替换一次。使用 mock SilexKernel 和 LoaderInterface（Ref: Requirement 4, AC 6）
  - [x] 5.7 创建 `ut/Routing/CacheableRouterProviderTest.php`：`register()` 注册服务、`getConfigDataProvider()` 注册前抛异常（Ref: Requirement 4, AC 7）
  - [x] 5.8 将 7 个测试文件注册到 `phpunit.xml` 的 `routing` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 5.9 Checkpoint: 运行 `vendor/bin/phpunit --testsuite routing`，全部通过且无 warning，commit

- [x] 6. P1 — Cookie + Middlewares + NullEntryPoint 单元测试（R5, R6, R7）
  - [x] 6.1 创建 `ut/Cookie/ResponseCookieContainerTest.php`：addCookie/getCookies、多次累积、初始空数组（Ref: Requirement 5, AC 1）
  - [x] 6.2 创建 `ut/Cookie/SimpleCookieProviderTest.php`：非 SilexKernel 抛 LogicException、boot 注册 after middleware（Ref: Requirement 5, AC 2）
  - [x] 6.3 创建 `ut/Middlewares/AbstractMiddlewareTest.php`，使用 `TestMiddleware` Test_Double：`onlyForMasterRequest()` 默认 true、`getAfterPriority()` 默认 LATE_EVENT、`getBeforePriority()` 默认 EARLY_EVENT（Ref: Requirement 6, AC 1）
  - [x] 6.4 创建 `ut/Security/NullEntryPointTest.php`：传入 AuthenticationException 抛 AccessDeniedHttpException（含 message）、不传异常抛 AccessDeniedHttpException（'Access Denied'）（Ref: Requirement 7, AC 1）
  - [x] 6.5 将 4 个测试文件注册到 `phpunit.xml` 对应 suite（`cookie`、`middlewares`、`security`）和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 6.6 Checkpoint: 运行 `vendor/bin/phpunit --testsuite cookie --testsuite middlewares`，全部通过且无 warning；运行 `vendor/bin/phpunit --testsuite security` 确认新增 + 现有测试全部通过，commit

- [x] 7. P2 — 独立模块单元测试（R8）
  - [x] 7.1 创建 `ut/Misc/ExtendedArgumentValueResolverTest.php`：构造非对象抛异常、`supports()` 精确匹配/instanceof 匹配/不存在类/无匹配、`resolve()` 精确/instanceof yield（Ref: Requirement 8, AC 1）
  - [x] 7.2 创建 `ut/Misc/ExtendedExceptionListnerWrapperTest.php`：response null + event 无 response 不调 parent、response 非 null 调 parent。使用 Reflection 或 test subclass 访问 protected 方法（Ref: Requirement 8, AC 2）
  - [x] 7.3 创建 `ut/Misc/ChainedParameterBagDataProviderTest.php`：构造非法参数抛异常、bag 顺序优先级、ParameterBag get()、HeaderBag 单值/多值/零值、所有 bag 无 key 返回 null（Ref: Requirement 8, AC 3）
  - [x] 7.4 创建 `ut/Misc/UniquenessViolationHttpExceptionTest.php`：getStatusCode() 400、getMessage()、getPrevious()（Ref: Requirement 8, AC 4）
  - [x] 7.5 将 4 个测试文件注册到 `phpunit.xml` 的 `misc` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 7.6 Checkpoint: 运行 `vendor/bin/phpunit --testsuite misc`，全部通过且无 warning，commit

- [x] 8. 集成测试基础设施（R9–R11 前置）
  - [x] 8.1 创建 `ut/Integration/integration.routes.yml`：定义集成测试所需的路由（含 secured 路由、cookie 路由、middleware 测试路由）（Ref: Requirement 9–11 前置）
  - [x] 8.2 创建 `ut/Integration/app.integration-security.php`：Security_Authentication_Flow 集成测试的 SilexKernel 配置，包含完整的 Policy → Firewall → AccessRule → Role Hierarchy 配置（Ref: Requirement 10 前置）
  - [x] 8.3 创建 `ut/Integration/app.integration-kernel.php`：SilexKernel 跨社区集成测试的 SilexKernel 配置，包含 Cookie provider + Middleware + 基本路由（Ref: Requirement 11 前置）
  - [x] 8.4 Checkpoint: 确认配置文件语法正确（PHP 文件无 parse error，YAML 文件格式正确），commit

- [x] 9. 集成测试 — Bootstrap Configuration + Security Flow + 跨社区（R9, R10, R11）
  - [x] 9.1 创建 `ut/Integration/BootstrapConfigurationIntegrationTest.php`（继承 `TestCase`），在测试方法内直接构造 SilexKernel，分别验证 routing/security/cors/twig/middlewares 配置后的 provider 注册和行为（Ref: Requirement 9, AC 1–5）
  - [x] 9.2 创建 `ut/Integration/SecurityAuthenticationFlowIntegrationTest.php`（继承 `WebTestCase`），使用 `app.integration-security.php`，验证完整认证授权链路、认证失败、403、Role Hierarchy（Ref: Requirement 10, AC 1–4）
  - [x] 9.3 创建 `ut/Integration/SilexKernelCrossCommunityIntegrationTest.php`（继承 `WebTestCase`），使用 `app.integration-kernel.php`，验证 Cookie 写入 response、Middleware 执行顺序、配置校验（Ref: Requirement 11, AC 1–3）
  - [x] 9.4 将 3 个测试文件注册到 `phpunit.xml` 的 `integration` suite 和 `all` suite（Ref: Requirement 13, AC 1–2）
  - [x] 9.5 Checkpoint: 运行 `vendor/bin/phpunit --testsuite integration`，全部通过且无 warning，commit

- [x] 10. 现有测试场景补充（R12）— SilexKernel + Cors
  - [x] 10.1 分析 `src/SilexKernel.php` 所有未覆盖分支，在 `ut/SilexKernelTest.php` 和 `ut/SilexKernelWebTest.php` 中补充 test method：`__set()` magic properties、`handle()` ELB/CloudFront、`boot()` middleware 注册、getters 各状态、`isGranted()`、`getCacheDirectories()`（Ref: Requirement 12, AC 1）
  - [x] 10.2 分析 `src/ServiceProviders/Cors/` 所有未覆盖分支，在 `ut/Cors/CrossOriginResourceSharingTest.php` 和 `ut/Cors/CrossOriginResourceSharingAdvancedTest.php` 中补充 test method：多策略优先级、credentials、headers_exposed、非 preflight（Ref: Requirement 12, AC 2）
  - [x] 10.3 Checkpoint: 运行 `vendor/bin/phpunit --testsuite all`（含新增 + 现有），全部通过且无 warning，commit

- [x] 11. 现有测试场景补充（R12）— Security + Twig + Aws
  - [x] 11.1 分析 `src/ServiceProviders/Security/` 所有未覆盖分支，在 `ut/Security/SecurityServiceProviderTest.php` 中补充 test method：认证失败路径、AccessRule 边界、Role Hierarchy 多层（Ref: Requirement 12, AC 3）
  - [x] 11.2 分析 `src/ServiceProviders/Twig/` 所有未覆盖分支，在 `ut/Twig/TwigServiceProviderTest.php` 中补充 test method：globals 变体、asset_base、无 cache_dir（Ref: Requirement 12, AC 4）
  - [x] 11.3 分析 AWS 相关代码所有未覆盖分支，在 `ut/AwsTests/ElbTrustedProxyTest.php` 中补充 test method：behind_elb、trust_cloudfront_ips、两者同时（Ref: Requirement 12, AC 5）
  - [x] 11.4 Checkpoint: 运行 `vendor/bin/phpunit`（全量），全部通过且无 warning，commit

- [-] 12. 手工测试
  - [x] 12.1 编排手工测试脚本，验证测试套件的完整性：确认所有新增 suite 可独立运行、`all` suite 包含所有测试、各 suite 无遗漏文件
  - [x] 12.2 手工验证集成测试的 app 配置文件与现有 `app.php`、`app.security.php` 等不冲突
  - [-] 12.3 Checkpoint: 手工测试全部通过，记录测试结果，commit

- [ ] 13. Code Review
  - [ ] 13.1 委托 `code-reviewer` sub-agent 对当前分支的所有变更进行 code review

---

## Notes

- 执行时须遵循 `spec-execution.md` 中的规范，包括 Pre-execution Review、并行执行策略、Checkpoint 执行标准、Blocker Escalation 规则
- Commit 随 checkpoint 一起执行，每个 top-level task 完成时在 checkpoint sub-task 中 commit
- 本 spec 仅新增测试，不修改现有业务逻辑（约束 C-2）。如果测试发现现有代码的行为与预期不符，记录实际行为作为 Behavior_Baseline，不修改代码
- 测试框架为 PHPUnit 5.x（约束 C-1），不使用 PHPUnit 5.x 不支持的 API
- `ExceptionWrapper` 测试直接引用 `oasis/utils` 包的外部异常类（CR Q3 = A），确保 `composer install` 已执行
- R12 场景补充的具体 test case 在 task 执行阶段分析源代码分支后确定（CR Q1 = B），不预先列出
- 当前环境默认 PHP 8.5.3，但 PHPUnit 5.x 及 Symfony 4.x 依赖仅兼容 PHP 7.x。运行测试须使用 PHP 7.1：`/usr/local/opt/php@7.1/bin/php vendor/bin/phpunit`；安装依赖须加 `--ignore-platform-reqs`
- `ut/cache/` 存放 Symfony Router 路由缓存，已在 `ut/.gitignore` 中排除。涉及路由的测试类应在 `setUp()` 中清理缓存文件（`Project*.php` / `Project*.php.meta`），不要在测试代码外部用 shell 命令清理
- 集成测试配置文件在 Task 8 中独立创建（CR Q4 = A），确保 Task 9 执行时基础设施就绪

---

## Socratic Review

**Q: Task 粒度是否合适？13 个 top-level task 是否过多？**
A: 按模块关联性分批（CR Q2 = B），P0 模块（ErrorHandlers、Configuration）各自独立 task，P1 中小模块（Cookie + Middlewares + NullEntryPoint）合并为一个 task，R12 按关联性分为两批。粒度适中，每个 task 可在一个 session 内完成。

**Q: Task 1 创建空 suite 结构是否有必要？**
A: 用户选择了 CR Q3 = C（先创建空 suite 再逐步填充）。空 suite 不影响现有测试运行，但为后续 task 提供了注册目标，避免每个 task 都需要创建 suite。

**Q: Task 8 独立创建集成测试配置文件是否增加了不必要的 task？**
A: 用户选择了 CR Q4 = A（独立基础设施 task）。配置文件是 Task 9 的前置依赖，独立创建确保 Task 9 可以专注于测试逻辑。

**Q: R12 分为 Task 10 和 Task 11 两批的依据是什么？**
A: Task 10 覆盖 SilexKernel（核心类，betweenness 最高）和 Cors，Task 11 覆盖 Security + Twig + Aws。按模块关联性和工作量均衡分批。SilexKernel 分支最多，单独一批合理。

**Q: 是否遗漏了 requirements 中的任何 AC？**
A: 逐条核对：R1（Task 2）、R2（Task 3）、R3（Task 4）、R4（Task 5）、R5（Task 6.1–6.2）、R6（Task 6.3）、R7（Task 6.4）、R8（Task 7）、R9（Task 9.1）、R10（Task 9.2）、R11（Task 9.3）、R12（Task 10–11）、R13（Task 1.1 + 各 task 的注册 sub-task）。无遗漏。

**Q: Task 之间的依赖顺序是否正确？是否存在隐含的前置依赖未体现在排序中？**
A: Task 1（基础设施）→ Task 2–7（单元测试，P0 优先）→ Task 8（集成测试基础设施）→ Task 9（集成测试）→ Task 10–11（现有测试补充）→ Task 12（手工测试）→ Task 13（Code Review）。Task 4.1 依赖 Task 1.2（ConcreteSmartViewHandler），Task 6.3 依赖 Task 1.3（TestMiddleware），均已通过 Task 1 前置保证。无隐含依赖遗漏。

**Q: 手工测试是否覆盖了 requirements 中的关键用户场景？**
A: 手工测试（Task 12）聚焦于测试套件的完整性验证（suite 可独立运行、all suite 无遗漏、配置文件不冲突），这是本 spec 的核心交付物——测试基线的完整性。具体的功能场景已由自动化测试覆盖，手工测试补充自动化难以验证的结构性检查。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] 所有实现类 sub-task 的 requirement 引用从非规范格式（"覆盖 R1 AC 1"）修正为 `Ref: Requirement X, AC Y` 格式。注册类 sub-task 补充 `Ref: Requirement 13, AC 1–2`，基础设施类 sub-task 补充前置引用标注
- [结构] Task 12（手工测试）补充 checkpoint sub-task（12.3），确保每个 top-level task 的最后一个 sub-task 均为 checkpoint
- [内容] Notes section 补充 commit 时机说明："Commit 随 checkpoint 一起执行，每个 top-level task 完成时在 checkpoint sub-task 中 commit"
- [内容] Socratic Review 补充两个问题：task 间依赖顺序正确性检查、手工测试对 requirements 关键场景的覆盖度检查

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1–R13 编号、AC 编号与 requirements.md 一致）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] 最后一个 top-level task 是 Code Review（Task 13）
- [x] 倒数第二个 top-level task 是手工测试（Task 12）
- [x] 自动化实现 task（Task 1–11）排在手工测试和 Code Review 之前
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 序号连续（1–13），sub-task 层级序号连续
- [x] 每个实现类 sub-task 引用了具体的 Requirement 和 AC
- [x] requirements.md 中 R1–R13 每条 requirement 至少被一个 task 引用，无遗漏
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在，无悬空引用
- [x] top-level task 按依赖关系排序，无循环依赖
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 包含具体验证命令和 commit 动作
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在，场景具体可执行
- [x] Code Review 委托给 code-reviewer sub-agent，未展开 review checklist
- [x] `## Notes` section 存在，引用了 `spec-execution.md`，明确了 commit 时机
- [x] `## Socratic Review` 存在且覆盖充分
- [x] Design CR 决策（Q1=B, Q2=B, Q3=C, Q4=A）均在 tasks 编排中体现
- [x] Tasks 完整覆盖 design 中所有模块、接口和实现项
- [x] 验收闭环完整：checkpoint + 手工测试 + code review
- [x] 执行路径无歧义，依赖关系清晰
