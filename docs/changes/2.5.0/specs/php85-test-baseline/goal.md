# Spec Goal: PHP 8.5 升级前测试基线补全

## 来源

- 分支: `feature/php85-test-baseline`
- 需求文档: `docs/proposals/PRP-001-php85-test-baseline.md`

## 背景摘要

`oasis/http` 计划从 PHP >=7.0 升级到 PHP >=8.5。后续 Phase 1–4 将引入 Silex → Symfony 框架替换、Twig/Guzzle 大版本升级、Security 组件重写、语言层面适配等大量 breaking change。这些变更的正确性验证完全依赖测试套件。

当前测试覆盖存在明显缺口：8 个 Configuration 类、ErrorHandlers、Views（9 个类中仅 1 个有测试）、Cookie、Routing、Middlewares 等核心模块缺少测试。基于知识图谱分析，`WrappedExceptionInfo`（god node，13 edges）、`processConfiguration()`（cross-community bridge，betweenness 0.078）等关键节点完全无测试覆盖。

测试补全必须在框架替换（Phase 1 / PRP-003）之前完成——替换后旧 API 不再可用，届时无法再针对旧行为编写测试。补全的测试将作为行为 SSOT，确保后续迁移不引入功能退化。

## 目标

- 为所有缺少测试的模块补充单元测试，按 P0/P1/P2 优先级覆盖
  - P0: ErrorHandlers（WrappedExceptionInfo、ExceptionWrapper、JsonErrorHandler）、Configuration（8 个配置类 + ConfigurationValidationTrait）
  - P1: Views（6 个类）、Routing（7 个类）、Cookie（2 个类）、Middlewares（AbstractMiddleware）、NullEntryPoint
  - P2: ExtendedArgumentValueResolver、ExtendedExceptionListnerWrapper、ChainedParameterBagDataProvider、UniquenessViolationHttpException
- 补充集成测试，覆盖图谱 hyperedges 和 cross-community bridges：
  - Bootstrap Configuration → ServiceProvider → Kernel 链路
  - Security Authentication Flow 完整链路
  - SilexKernel 跨社区集成（Cookie、Middleware、Configuration 交互）
- 全面补充现有测试的场景覆盖（SilexKernel、Cors、Security、Twig、Aws），系统性分析所有未覆盖分支
- 所有测试在当前 PHP 版本 + PHPUnit 5.x 下通过

## 不做的事情（Non-Goals）

- 不涉及依赖升级（PHPUnit、Symfony 组件等升级在 PRP-002 中处理）
- 不涉及 Silex 框架替换
- 不涉及 PHP 语言层面 breaking changes 修复
- 不修改现有业务逻辑——仅补充测试

## Clarification 记录

### Q1: 测试分组策略

`phpunit.xml` 中新测试的 suite 分组方式。

- 选项: A) 按现有 suite 结构扩展 / B) 按优先级分组 / C) 按模块目录一一对应 / D) 补充说明
- 回答: C — 每个 `src/` 子目录对应一个 suite（`error-handlers`、`configuration`、`views`、`cookie`、`routing`、`middlewares` 等）

### Q2: 集成测试的组织方式

集成测试（跨模块链路测试）的目录组织。

- 选项: A) 混入对应模块目录 / B) 独立 `ut/Integration/` 目录 / C) 扩展现有 Web Test / D) 补充说明
- 回答: B — 所有集成测试集中放在 `ut/Integration/` 下，按链路命名

### Q3: 现有测试场景补充的范围控制

对已有测试类（SilexKernel、Cors、Security、Twig、Aws）的场景补充范围。

- 选项: A) 仅补明显缺失路径 / B) 全面补充所有未覆盖分支 / C) 以迁移风险为导向 / D) 补充说明
- 回答: B — 对每个已有测试类，系统性分析被测代码的所有分支，补充所有未覆盖的场景

## 约束与决策

- **Suite 组织**：按模块目录一一对应，每个 `src/` 子目录对应一个 phpunit suite
- **集成测试目录**：独立 `ut/Integration/` 目录，按链路命名
- **场景补充范围**：全面补充，系统性覆盖所有未覆盖分支（包括配置变体、边界值、异常路径）
- **测试框架**：使用当前 PHPUnit 5.x，不升级（升级在 PRP-002）
- **行为基线原则**：测试记录当前系统的实际行为，不是期望行为
