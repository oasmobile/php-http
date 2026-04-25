# Implementation Plan: PHP 8.5 Phase 5 — Validation & Stabilization

## Overview

Phase 5 是 PHP 8.5 升级的收尾阶段，覆盖四大工作块：内部依赖 ^3.0 升级、PHPStan 引入与修复、文档全面更新、最终验证。

执行策略基于 Design CR 决策：
- Q1=A: 按工作块拆分为粗粒度 task——一个 task 完成整个 oasis/utils ^3.0 升级，一个 task 完成整个 PHPStan 引入 + 修复
- Q2=B: 代码变更全部完成后，统一用一个 task 完成所有文档更新（R8–R11）
- Q3=C: PBT（R12）作为最终验证的一部分，在所有代码变更完成后统一执行

执行顺序：
```
R1 (oasis/utils ^3.0) → R2 (oasis/logging ^3.0) → R3+R4 (PHPStan 引入+修复)
→ R8/R9/R10/R11 (文档统一更新) → R12 (PBT) → R13 (公共 API 兼容性验证)
→ R5/R6/R7 (最终验证)
```

## Tasks

- [ ] 1. oasis/utils ^3.0 升级（R1）
  - [ ] 1.1 执行 `composer require oasis/utils:^3.0`，记录编译错误和测试失败
    - 运行 `composer require oasis/utils:^3.0`
    - 运行 `phpunit --testsuite all` 收集失败信息
    - 记录所有 API 变更点（方法签名、类型常量、异常类等）
    - _Ref: R1 AC1, R1 AC6_
  - [ ] 1.2 适配 `src/` 中所有 oasis/utils ^3.0 API 变更
    - 逐一修复 Design Architecture 节使用点清单中的 `src/` 文件：
      - `ConfigurationValidationTrait`（ArrayDataProvider 构造）
      - `MicroKernel`（ArrayDataProvider, DataProviderInterface 常量, getMandatory(), getOptional()）
      - `ChainedParameterBagDataProvider`（继承 AbstractDataProvider）
      - `SimpleSecurityProvider`（DataProviderInterface 常量, getOptional()）
      - `SimpleFirewall`（DataProviderInterface 常量, getMandatory()）
      - `SimpleAccessRule`（DataProviderInterface 常量, getMandatory(), getOptional()）
      - `CrossOriginResourceSharingStrategy`（DataProviderInterface 常量, getMandatory(), getOptional()）
      - `SimpleTwigServiceProvider`（DataProviderInterface 常量, getMandatory(), getOptional()）
      - `CacheableRouterProvider`（DataProviderInterface 常量, getMandatory(), getOptional()）
      - `ExceptionWrapper`（DataValidationException, ExistenceViolationException）
    - 适配策略（GK-CR Q3=A）：逐一修改调用点，不引入 adapter
    - _Ref: R1 AC2, R1 AC4_
  - [ ] 1.3 适配 `ut/` 中所有 oasis/utils ^3.0 API 变更
    - 逐一修复 Design Architecture 节使用点清单中的 `ut/` 文件：
      - `SilexKernelWebTest`（StringUtils::stringStartsWith()）
      - `ExceptionWrapperTest`（DataValidationException, ExistenceViolationException）
      - `TestController`（DataProviderInterface 常量, getMandatory(), getOptional()）
      - `ChainedParameterBagDataProviderTest`（getOptional()）
    - _Ref: R1 AC2, R1 AC3_
  - [ ] 1.4 Checkpoint: 运行 `phpunit --testsuite all` 确认全量通过，commit
    - _Ref: R1 AC5_

- [ ] 2. oasis/logging ^3.0 升级（R2）
  - [ ] 2.1 执行 `composer require oasis/logging:^3.0`，记录编译错误和测试失败
    - 运行 `composer require oasis/logging:^3.0`
    - 运行 `phpunit --testsuite all` 收集失败信息
    - _Ref: R2 AC1, R2 AC4_
  - [ ] 2.2 适配 oasis/logging ^3.0 API 变更
    - 影响范围极小，仅 `ut/bootstrap.php` 中 `LocalFileHandler` 的构造和 `install()` 调用
    - 如有 API 变更，适配 `(new LocalFileHandler('/tmp'))->install()` 调用
    - _Ref: R2 AC2_
  - [ ] 2.3 Checkpoint: 运行 `phpunit --testsuite all` 确认全量通过，commit
    - _Ref: R2 AC3_

- [ ] 3. PHPStan 引入与错误修复（R3、R4）
  - [ ] 3.1 安装 PHPStan 并创建配置文件
    - 执行 `composer require --dev phpstan/phpstan`
    - 在项目根目录创建 `phpstan.neon`，配置 level 8、分析路径 `src/`（GK-CR Q4=A：只分析 src/）
    - 运行 `vendor/bin/phpstan analyse` 确认工具正常运行，记录错误数量
    - 如果 level 8 错误量过大，与用户商量是否降到 level 5（R3 AC5）
    - _Ref: R3 AC1, R3 AC2, R3 AC3, R3 AC4, R3 AC5, R3 AC6_
  - [ ] 3.2 修复 PHPStan 发现的所有错误
    - 按错误类型分类处理：
      - 类型声明缺失：添加 `@param`、`@return`、`@var` PHPDoc 或原生类型声明
      - 类型不匹配：修正类型声明或添加类型断言
      - 未定义方法/属性：修正拼写或添加 `@method` / `@property` PHPDoc
      - 不可达代码：移除或修正逻辑
    - 每次修复后运行测试确认无行为变更（R4 AC2）
    - 无法合理修复的 false positive 加入 baseline（GK-CR Q1=A：优先 baseline 集中管理）
    - 如需 baseline，生成 `phpstan-baseline.neon` 并在 `phpstan.neon` 中引用
    - 仅在 baseline 无法覆盖的场景使用 inline `@phpstan-ignore`，附带 justification comment
    - _Ref: R4 AC1, R4 AC2, R4 AC3, R4 AC4_
  - [ ] 3.3 Checkpoint: 运行 `vendor/bin/phpstan analyse` 确认零错误，运行 `phpunit --testsuite all` 确认全量通过，commit
    - _Ref: R3 AC6, R4 AC1_

- [ ] 4. 文档统一更新（R8、R9、R10、R11）
  - [ ] 4.1 更新 `PROJECT.md`
    - 技术栈表格：PHP ≥ 8.5、Symfony MicroKernel（Symfony 7.x 组件）、Twig 3.x、Guzzle 7.x、PHPUnit 13.x、oasis/utils ^3.0、oasis/logging ^3.0
    - 核心入口：引用 `MicroKernel`（`src/MicroKernel.php`），移除 `SilexKernel` 引用
    - 构建与测试命令：添加 PHPStan 分析命令（`vendor/bin/phpstan analyse`）
    - 测试 Suite 表格：反映 `phpunit.xml` 中所有 suite
    - 项目描述：Silex → Symfony MicroKernel
    - 移除所有 Silex、Pimple、已替换技术的引用
    - _Ref: R8 AC1, R8 AC2, R8 AC3, R8 AC4, R8 AC5, R8 AC6_
  - [ ] 4.2 更新 `README.md`
    - PHP 版本要求：`>=8.5`
    - 框架描述：Symfony MicroKernel
    - 移除 Silex 相关引用
    - 依赖版本信息与 `composer.json` 一致
    - _Ref: R9 AC1, R9 AC2, R9 AC3, R9 AC4_
  - [ ] 4.3 Review 并更新 `docs/state/`
    - `architecture.md`：确认模块结构、类层次、公共 API 签名与代码一致
    - 移除 Silex / Pimple / Symfony 4.x 引用
    - 技术栈描述与 `composer.json` 一致
    - 修正与当前代码不一致的描述（过时的类名、方法签名、流程描述）
    - _Ref: R10 AC1, R10 AC2, R10 AC3, R10 AC4, R10 AC5_
  - [ ] 4.4 Review 并更新 `docs/manual/`
    - 逐文件 review：`getting-started.md`、`bootstrap-configuration.md`、`routing.md`、`security.md`、`cors.md`、`README.md`
    - 确保代码示例、配置说明与当前 MicroKernel + Symfony 7.x 一致
    - 移除 Silex / Pimple 引用
    - _Ref: R11 AC1, R11 AC2, R11 AC3, R11 AC4, R11 AC5_
  - [ ] 4.5 Checkpoint: 通过 grep 搜索 "Silex"、"Pimple"、"SilexKernel"、"Symfony 4" 确认文档无遗漏，commit

- [ ] 5. PBT 验证（R12）
  - [ ] 5.1 编写 Property 1 测试：ArrayDataProvider round-trip invariant
    - 新建或更新 `ut/PBT/ArrayDataProviderPropertyTest.php`
    - **Property 1: ArrayDataProvider round-trip invariant**
    - 使用 Eris 1.x 生成随机关联数组（key 为 string，value 为 string/int/float/bool/array/null）
    - 构造 `ArrayDataProvider`，遍历原始数组每个 key，验证 `has()` 返回 true，`get()` / `getOptional()` 返回原始值
    - 标注格式：`// Feature: php85-phase5-validation-stabilization, Property 1: ArrayDataProvider round-trip invariant`
    - 最少 100 次迭代
    - **Validates: R12 AC1, R12 AC2**
  - [ ] 5.2 编写 Property 2 测试：ArrayDataProvider non-existent key error condition
    - 在 `ut/PBT/ArrayDataProviderPropertyTest.php` 中添加
    - **Property 2: ArrayDataProvider non-existent key error condition**
    - 使用 Eris 1.x 生成随机关联数组和一个不在数组中的随机 key
    - 构造 `ArrayDataProvider`，验证 `has(nonExistentKey)` 返回 false，`get(nonExistentKey)` 抛出异常
    - 标注格式：`// Feature: php85-phase5-validation-stabilization, Property 2: ArrayDataProvider non-existent key error condition`
    - 最少 100 次迭代
    - **Validates: R12 AC3**
  - [ ] 5.3 Checkpoint: 运行 `phpunit --testsuite pbt` 确认 PBT 通过，commit

- [ ] 6. 公共 API 兼容性验证（R13）
  - [ ] 6.1 验证 Routing 兼容性
    - 确认 2.5 的路由注册方式（route config 数组）在 3.x MicroKernel 下正常工作
    - 检查路由解析、参数提取、HTTP method 匹配
    - 如发现行为差异，记录为 breaking change
    - _Ref: R13 AC1_
  - [ ] 6.2 验证 Controller 兼容性
    - 确认 2.5 的 controller 写法（参数注入、返回值类型）在 3.x 下兼容
    - 检查 Request 注入、路由参数注入、Response 返回
    - _Ref: R13 AC2_
  - [ ] 6.3 验证 View/Renderer 兼容性
    - 确认 2.5 的 view handler（JSON、HTML、Twig 渲染）行为一致
    - 检查 Content-Type、响应体格式、模板变量传递
    - _Ref: R13 AC3_
  - [ ] 6.4 验证 Security 兼容性
    - 确认 2.5 的 security 配置（firewall、access rule、authenticator）正常工作
    - 检查认证流程、拦截行为、角色检查
    - _Ref: R13 AC4_
  - [ ] 6.5 验证 CORS 兼容性
    - 确认 2.5 的 CORS 配置行为一致
    - 检查 preflight 响应、allowed origins/methods/headers
    - _Ref: R13 AC5_
  - [ ] 6.6 验证 Bootstrap Config 兼容性
    - 确认 2.5 的 bootstrap config 数组在 3.x MicroKernel 下正常初始化
    - 检查配置 key 兼容性、默认值行为
    - _Ref: R13 AC6_
  - [ ] 6.7 验证 Error Handling 兼容性
    - 确认 2.5 的异常处理（ExceptionWrapper、JsonErrorHandler）行为一致
    - 检查 HTTP status code、响应体结构、异常映射
    - _Ref: R13 AC7_
  - [ ] 6.8 记录 Breaking Changes
    - 如验证过程中发现任何行为差异，以结构化表格记录：`| 模块 | 2.5 行为 | 3.x 行为 | 影响 | 迁移方式 |`
    - 记录的 breaking changes 作为 PRP-008 迁移指南的输入
    - _Ref: R13 AC8_
  - [ ] 6.9 Checkpoint: 公共 API 兼容性验证完成，结果记录在手工测试资源目录中，commit

- [ ] 7. 最终验证（R5、R6、R7）
  - [ ] 7.1 全量测试验证
    - 运行 `phpunit --testsuite all` 确认全量通过
    - 运行 `phpunit --testsuite pbt` 确认 PBT 通过
    - 确认 `phpunit.xml` 中定义的所有 suite 均通过
    - _Ref: R5 AC1, R5 AC2, R5 AC3_
  - [ ] 7.2 零 Deprecation Notice 验证
    - 运行 `phpunit --testsuite all`，确认输出中无来自 `src/` 和 `ut/` 的 deprecation notice
    - 确认代码中无 PHP 8.x deprecated syntax 或 API 使用
    - 如有第三方依赖产生的 deprecation notice，记录但不要求修复
    - _Ref: R6 AC1, R6 AC2, R6 AC3_
  - [ ] 7.3 PHPStan 通过验证
    - 运行 `vendor/bin/phpstan analyse` 确认零错误（或仅有 baseline 中的已知 false positive）
    - 确认分析结果可复现
    - _Ref: R7 AC1, R7 AC2_
  - [ ] 7.4 Checkpoint: 最终验证三件套全部通过（全量测试 + 零 deprecation + PHPStan），commit

- [ ] 8. 手工测试
  - [ ] 8.1 验证 oasis/utils ^3.0 升级完整性
    - 确认 `composer.json` 中 `oasis/utils` 版本约束为 `^3.0`
    - 确认 `src/` 和 `ut/` 中无 oasis/utils ^2.0 已废弃 API 的残留调用
    - _Ref: R1 AC2, R1 AC6_
  - [ ] 8.2 验证 oasis/logging ^3.0 升级完整性
    - 确认 `composer.json` 中 `oasis/logging` 版本约束为 `^3.0`
    - 确认 `ut/bootstrap.php` 中 `LocalFileHandler` 调用与 ^3.0 API 一致
    - _Ref: R2 AC2, R2 AC4_
  - [ ] 8.3 验证 PHPStan 配置正确性
    - 确认 `phpstan.neon` 存在且配置正确（level 8、paths: src/）
    - 确认 `composer.json` require-dev 中包含 `phpstan/phpstan`
    - 如有 baseline，确认 `phpstan-baseline.neon` 存在且在 `phpstan.neon` 中引用
    - _Ref: R3 AC1, R3 AC2, R3 AC3, R3 AC4_
  - [ ] 8.4 验证文档更新完整性
    - 通过 grep 搜索 "Silex"、"Pimple"、"SilexKernel"、"Symfony 4" 确认 PROJECT.md、README.md、docs/state/、docs/manual/ 中无遗漏
    - 确认 PROJECT.md 技术栈版本与 `composer.json` 一致
    - 确认 docs/state/architecture.md 模块结构与代码一致
    - _Ref: R8 AC5, R9 AC3, R10 AC2, R10 AC3, R11 AC3_
  - [ ] 8.5 Checkpoint: 手工测试全部通过，commit

- [ ] 9. Code Review
  - [ ] 9.1 委托给 code-reviewer agent 执行
  - [ ] 9.2 Checkpoint: Code review 通过，处理所有 review 意见，commit

## Socratic Review

**Q1: tasks 是否完整覆盖了 design 中的所有工作块？有无遗漏？**
A: Design 列出 6 大工作块：(1) 内部依赖 ^3.0 升级（R1、R2）→ Task 1、Task 2；(2) PHPStan 引入与错误修复（R3、R4）→ Task 3；(3) 文档全面 review 与更新（R8–R11）→ Task 4；(4) PBT 验证（R12）→ Task 5；(5) 公共 API 兼容性验证（R13）→ Task 6；(6) 全量验证（R5–R7）→ Task 7。全部覆盖，无遗漏。

**Q2: Design CR 决策是否已体现在 task 编排中？**
A: Q1=A（粗粒度 task）→ Task 1 完成整个 oasis/utils ^3.0 升级，Task 3 完成整个 PHPStan 引入+修复；Q2=B（文档统一更新）→ Task 4 在代码变更全部完成后统一更新所有文档；Q3=C（PBT 作为最终验证一部分）→ Task 5 在代码变更和文档更新完成后执行。三个决策均已体现。

**Q3: task 之间的依赖顺序是否正确？**
A: Task 1（oasis/utils ^3.0）→ Task 2（oasis/logging ^3.0）→ Task 3（PHPStan）→ Task 4（文档更新）→ Task 5（PBT）→ Task 6（公共 API 兼容性）→ Task 7（最终验证）→ Task 8（手工测试）→ Task 9（Code Review）。依赖关系：Task 2 依赖 Task 1（两个依赖可能有交叉影响）；Task 3 依赖 Task 1+2（PHPStan 分析需要代码编译通过）；Task 4 依赖 Task 1–3（文档需反映代码变更）；Task 5 依赖 Task 1（PBT 验证 oasis/utils ^3.0 API）；Task 7 依赖 Task 1–6（最终验证需所有变更完成）。顺序正确。

**Q4: Requirements R1–R13 是否全部被 task 引用？**
A: R1 → Task 1（1.1–1.4）；R2 → Task 2（2.1–2.3）；R3 → Task 3（3.1）；R4 → Task 3（3.2）；R5 → Task 7（7.1）；R6 → Task 7（7.2）；R7 → Task 7（7.3）；R8 → Task 4（4.1）；R9 → Task 4（4.2）；R10 → Task 4（4.3）；R11 → Task 4（4.4）；R12 → Task 5（5.1–5.2）；R13 → Task 6（6.1–6.8）。全部覆盖。

**Q5: PBT 的 2 个 property 是否全部有对应 task？**
A: Property 1（round-trip invariant）→ Task 5.1；Property 2（non-existent key error condition）→ Task 5.2。2 个 property 全部覆盖。

**Q6: checkpoint 的设置是否覆盖了关键阶段？**
A: 每个 top-level task 末尾都有 checkpoint：Task 1（oasis/utils 升级后全量测试）→ Task 2（oasis/logging 升级后全量测试）→ Task 3（PHPStan 零错误 + 全量测试）→ Task 4（文档 grep 验证）→ Task 5（PBT 通过）→ Task 6（兼容性验证记录）→ Task 7（最终验证三件套）→ Task 8（手工测试）→ Task 9（Code Review）。覆盖了依赖升级、静态分析、文档更新、PBT、兼容性验证、最终验证、手工验证、Code Review 等关键阶段。

**Q7: 手工测试是否覆盖了 requirements 中的关键验证场景？**
A: 手工测试覆盖了：oasis/utils ^3.0 升级完整性（8.1）、oasis/logging ^3.0 升级完整性（8.2）、PHPStan 配置正确性（8.3）、文档更新完整性（8.4）。这些是自动化测试难以覆盖的"全局一致性"验证。公共 API 兼容性验证（R13）已作为独立 Task 6 执行，本身就是手工验证性质。

**Q8: 文档更新 task 是否覆盖了 design 中列出的所有文件？**
A: Design Components §5 列出的文件：PROJECT.md → Task 4.1；README.md → Task 4.2；docs/state/architecture.md → Task 4.3；docs/manual/ 6 个文件（getting-started.md、bootstrap-configuration.md、routing.md、security.md、cors.md、README.md）→ Task 4.4。全部覆盖。

## Notes

- 按 `spec-execution.md` 规范执行各 task
- commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task 为 checkpoint + commit
- **粗粒度 task**（Design CR Q1=A）：oasis/utils ^3.0 升级为一个完整 task，PHPStan 引入+修复为一个完整 task，避免过度拆分
- **文档统一更新**（Design CR Q2=B）：代码变更全部完成后统一更新所有文档，避免反复修改
- **PBT 在最终验证前**（Design CR Q3=C）：PBT 在所有代码变更完成后执行，作为最终验证的前置步骤
- **PHPStan 仅分析 src/**（GK-CR Q4=A）：不包含 `ut/`，避免测试代码的 false positive 噪音
- **False positive 优先 baseline**（GK-CR Q1=A）：PHPStan false positive 优先加入 `phpstan-baseline.neon` 集中管理
- **公共 API 兼容性验证**（R13）为手工验证性质，发现的 breaking changes 记录为结构化表格，作为 PRP-008 迁移指南输入
- oasis/utils ^3.0 的具体 API 变更在执行 `composer require` 后才能确定，Task 1.2/1.3 的具体修改内容取决于实际编译错误和测试失败

## Gatekeep Log

**校验时间**: 2026-04-25
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [格式] Requirement 引用格式统一为 `_Ref: RX ACY_`——原文使用 `_Requirements: 1.1, 1.6_` 格式（数字编号，无 R 前缀），已全部修正为标准的 `_Ref: R1 AC1, R1 AC6_` 格式，涵盖 Task 1–8 中所有 sub-task 的引用
- [格式] PBT task（5.1、5.2）中的 `**Validates: Requirements 12.1, 12.2**` 统一为 `**Validates: R12 AC1, R12 AC2**` 格式，与其他 sub-task 的引用风格一致
- [内容] Checkpoint 5.3 移除了不必要的 requirement 引用（`_Requirements: 5.2_`）——checkpoint 类 sub-task 不要求引用 requirement
- [内容] Checkpoint 3.3 的 requirement 引用从 `R3 AC5`（level 降级条件）修正为 `R3 AC6`（工具正常运行）+ `R4 AC1`（零错误），更准确反映 checkpoint 的验证内容
- [内容] Task 3.1 的 Ref 补充遗漏的 `R3 AC5`——该 sub-task 描述中已包含 level 降级逻辑（"如果 level 8 错误量过大，与用户商量是否降到 level 5（R3 AC5）"），但 Ref 行遗漏了 AC5

### 合规检查

**机械扫描**
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号 R1–R13、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误

**结构校验**
- [x] `## Tasks` section 存在
- [x] 倒数第一个 top-level task 是 Code Review（Task 9）
- [x] 倒数第二个 top-level task 是手工测试（Task 8）
- [x] 自动化实现 task（Task 1–7）排在手工测试和 Code Review 之前

**Task 格式校验**
- [x] 所有 task 使用 `- [ ]` checkbox 语法
- [x] top-level task 有序号（1–9）
- [x] sub-task 有层级序号（1.1–9.2）
- [x] 序号连续，无跳号

**Requirement 追溯校验**
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款（`_Ref: RX ACY_` 格式）
- [x] requirements.md 中 R1–R13 的每条 requirement 至少被一个 task 引用
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在

**依赖与排序校验**
- [x] top-level task 按依赖关系排序（Task 1→2→3→4→5→6→7→8→9）
- [x] 无循环依赖
- [x] 无标注并行的 sub-task

**Graphify 跨模块依赖校验**
- [x] 已对核心模块执行 graphify 依赖查询（MicroKernel、ChainedParameterBagDataProvider、ExceptionWrapper）
- [x] task 排序与 graphify 揭示的模块依赖一致——MicroKernel 作为 god node（42 edges）的所有 oasis/utils 依赖在 Task 1 中统一处理；ExceptionWrapper 位于独立 community（community 4），不产生跨 community 连锁影响
- [x] 无遗漏的隐含跨模块依赖

**Checkpoint 校验**
- [x] checkpoint 作为每个 top-level task 的最后一个 sub-task
- [x] 每个 top-level task 末尾有 checkpoint
- [x] checkpoint 包含具体验证命令和 commit 动作

**Test-first 校验**
- [○] PBT task（5.1、5.2）为验证现有 API 行为的测试，非新增功能的 test-first 场景，不适用严格 test-first 编排

**Task 粒度校验**
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory

**手工测试 Task 校验**
- [x] 手工测试 top-level task 存在（Task 8）
- [x] 覆盖关键验证场景（依赖升级完整性、PHPStan 配置、文档更新完整性）
- [x] 场景描述具体，可执行

**Code Review Task 校验**
- [x] Code Review 是最后一个 top-level task（Task 9）
- [x] 描述为"委托给 code-reviewer agent 执行"
- [x] 未展开 review checklist 或 fix policy

**执行注意事项校验**
- [x] `## Notes` section 存在
- [x] 明确提到 `spec-execution.md`
- [x] 明确说明 commit 随 checkpoint 一起执行
- [x] 包含 spec 特有执行要点（粗粒度策略、文档统一更新时机、PBT 执行时机、PHPStan 分析范围、baseline 策略、API 变更不确定性说明）

**Socratic Review 校验**
- [x] 存在且覆盖充分（8 个 Q&A）
- [x] 覆盖 design 工作块完整性、CR 决策体现、依赖顺序、requirement 覆盖、PBT 覆盖、checkpoint 覆盖、手工测试覆盖、文档文件覆盖

**目的性审查**
- [x] Design CR 回应：Q1=A（粗粒度）、Q2=B（文档统一更新）、Q3=C（PBT 最终验证）均已在 task 编排中体现
- [x] Design 全覆盖：6 大工作块全部有对应 task
- [x] 可独立执行：每个 sub-task 描述自包含，含具体文件清单和操作步骤
- [x] 验收闭环：checkpoint（每个 task）+ 手工测试（Task 8）+ Code Review（Task 9）构成完整闭环
- [x] 执行路径无歧义：严格顺序执行，无需执行者自行判断优先级
