# PHP 8.5 大版本升级 Pre-Upgrade 调研

> 来源：日常观察 — 项目长期维护规划

## 升级目标

- 目标版本：PHP 8.5（预计 2025 年 11 月 GA）
- 最低支持版本：PHP 8.4
- 当前版本要求：`>=7.0.0`
- 跨度：PHP 7.0 → 8.4/8.5，跨越 5 个大版本

## 当前依赖兼容性分析

### 已 Abandoned 的包（阻塞级）

| 包 | 当前版本约束 | 状态 | PHP 8.4/8.5 兼容性 | 说明 |
|----|-------------|------|-------------------|------|
| `silex/silex` | `^2.3` | abandoned（2018 年归档） | 不兼容 | 项目核心框架，基于 Pimple + Symfony 4 组件。无后续维护，不会适配 PHP 8.x 的 breaking changes。必须替换。官方建议迁移到 Symfony Flex 或 Laravel |
| `twig/extensions` | `^1.3` | abandoned | 不兼容 | 功能已合并到 Twig 3.x 核心或独立的 `twig/extra-bundle`、`twig/intl-extra` 等包 |
| `silex/providers` | `^2.3` | 随 Silex 一同 abandoned | 不兼容 | 提供 Silex 的 service provider 集成，无独立维护 |

### Symfony 组件（需大版本升级）

| 包 | 当前版本约束 | PHP 8.4/8.5 兼容性 | 升级路径 |
|----|-------------|-------------------|----------|
| `symfony/http-foundation` | `^4.0` | Symfony 4.x 不支持 PHP 8.4+ | 需升级到 `^7.2`（支持 PHP 8.2+）或 `^7.3`（2025 年 5 月发布） |
| `symfony/routing` | `~4.2.0` | 不兼容 | 同上，升级到 `^7.2` |
| `symfony/config` | `^4.0` | 不兼容 | 同上 |
| `symfony/yaml` | `^4.0` | 不兼容 | 同上 |
| `symfony/expression-language` | `^4.0` | 不兼容 | 同上 |
| `symfony/security` | `^4.0` | 不兼容 | 同上。注意 Symfony 5.x 起 Security 组件经历了重大重构（新 authenticator 系统），API 变化显著 |
| `symfony/twig-bridge` | `^4.0` | 不兼容 | 同上 |
| `symfony/css-selector`（dev） | `^4.0` | 不兼容 | 同上 |
| `symfony/browser-kit`（dev） | `^4.0` | 不兼容 | 同上 |

Symfony 版本支持情况：
- Symfony 4.x：已 EOL（2023 年 11 月）
- Symfony 5.x：已 EOL（2024 年 11 月）
- Symfony 6.x：需 PHP 8.1+，LTS 到 2027 年 11 月
- Symfony 7.x：需 PHP 8.2+，当前活跃版本

### 其他第三方依赖

| 包 | 当前版本约束 | PHP 8.4/8.5 兼容性 | 升级路径 |
|----|-------------|-------------------|----------|
| `twig/twig` | `^1.24` | Twig 1.x 不兼容 PHP 8.x | 需升级到 `^3.0`（支持 PHP 8.x）。Twig 2.x 已 EOL。1→3 有大量 breaking changes（模板语法、扩展 API） |
| `guzzlehttp/guzzle` | `^6.3` | Guzzle 6.x 部分兼容 PHP 8.x 但不支持 8.4+ | 需升级到 `^7.0`（支持 PHP 7.2+）。Guzzle 7 基于 PSR-18，API 变化较小 |
| `oasis/logging` | `^1.1` | 未知 | 需检查其依赖链是否兼容 PHP 8.4+，可能需要同步升级 |
| `oasis/utils` | `^1.6` | 未知 | 同上 |
| `phpunit/phpunit`（dev） | `^5.2` | 不兼容 | PHPUnit 5.x 不支持 PHP 8.x。需升级到 PHPUnit 10.x（PHP 8.1+）或 11.x（PHP 8.2+） |

### 兼容性总结

- 全部 15 个运行时依赖均不兼容 PHP 8.4/8.5
- 全部 3 个开发依赖均不兼容 PHP 8.4/8.5
- 3 个包已 abandoned，无法通过简单升级解决

## 语言层面 Breaking Changes（PHP 7.0 → 8.4/8.5）

### PHP 7.x → 8.0 关键变化

- **命名参数**：函数签名中的参数名成为公共 API 的一部分
- **Union types**、**match 表达式**、**nullsafe operator**（新特性，非 breaking）
- **字符串与数字比较行为变更**：`0 == "foo"` 从 `true` 变为 `false`，影响松散比较逻辑
- **内部函数参数类型检查严格化**：传入错误类型从 warning 变为 TypeError
- **`@` 错误抑制符不再抑制 fatal errors**
- **`each()` 函数移除**
- **`create_function()` 移除**
- **Reflection API 变更**：多个方法签名变化
- **`array_key_exists()` 不再适用于对象**

### PHP 8.0 → 8.1 关键变化

- **Enums** 引入
- **Fibers** 引入
- **`readonly` 属性**
- **交集类型**
- **内部类方法返回类型声明**：继承内部类时需匹配返回类型
- **`$GLOBALS` 使用限制**
- **`Serializable` 接口弃用**

### PHP 8.1 → 8.2 关键变化

- **`readonly` 类**
- **动态属性弃用**（`#[AllowDynamicProperties]` 可临时保留）：对依赖动态属性的旧代码影响大
- **`utf8_encode()` / `utf8_decode()` 弃用**
- **`${var}` 字符串插值弃用**（仅 `{$var}` 保留）
- **隐式 nullable 参数类型弃用**

### PHP 8.2 → 8.3 关键变化

- **类常量类型声明**
- **`json_validate()` 函数**
- **`#[Override]` 属性**
- **`Readonly` 属性深拷贝改进**
- **弃用函数的持续移除**

### PHP 8.3 → 8.4 关键变化

- **属性钩子（Property Hooks）**：`get` / `set` 钩子
- **不对称可见性**：属性读写可设不同可见性
- **`new` 无括号调用**：`new Foo()->method()`
- **隐式 nullable 参数类型正式移除**（8.2 弃用 → 8.4 移除）：需检查所有 `function foo(Type $param = null)` 改为 `?Type $param = null`
- **多个弃用函数移除**

### PHP 8.4 → 8.5（预览，基于 RFC 进展）

- **管道操作符 `|>`**（已通过 RFC）
- **`Closures::compose()`**（提案中）
- **`array_first()` / `array_last()`**（提案中）
- **进一步的弃用清理**
- 注意：PHP 8.5 尚未 GA，最终特性列表可能变化

### 对本项目的主要影响

- **隐式 nullable 参数**：PHP 8.4 移除，需全面排查 `function foo(Type $param = null)` 模式
- **动态属性弃用**：Silex/Pimple 大量使用动态属性模式，PHP 8.2+ 会产生 deprecation notice
- **内部函数严格类型检查**：PHP 7.x 代码中可能存在隐式类型转换
- **字符串/数字比较行为变更**：需排查松散比较

## 依赖升级路径建议

### 核心问题：Silex 替换

Silex 是项目的核心框架（`SilexKernel` 继承 `Silex\Application`），其 abandoned 状态意味着无法通过简单升级解决。可选方案：

1. **迁移到 Symfony 框架**：最自然的路径，因为 Silex 本身基于 Symfony 组件。可使用 Symfony 的 MicroKernelTrait 实现类似的微框架体验
2. **迁移到 Slim Framework**：轻量级替代，但需要重写大量集成代码
3. **自建微框架层**：直接基于 Symfony 组件组装，去掉 Silex/Pimple 中间层。工作量大但可控

### 建议的依赖目标版本

| 包 | 目标版本 | 备注 |
|----|---------|------|
| PHP | `>=8.5` | |
| Symfony 组件 | `^7.2` | 统一升级到 7.x |
| `twig/twig` | `^3.0` | |
| `guzzlehttp/guzzle` | `^7.0` | |
| `phpunit/phpunit` | `^13.0` | |
| `silex/silex` | 移除 | 替换为 Symfony MicroKernel 或等价方案 |
| `silex/providers` | 移除 | 功能由 Symfony Bundle 或自定义 provider 替代 |
| `twig/extensions` | 移除 | 替换为 `twig/extra-bundle` 或内联实现 |

## 风险与阻塞点

### 阻塞级

1. **Silex 替换**：项目核心入口 `SilexKernel` 继承 `Silex\Application`，替换涉及 DI 容器（Pimple → Symfony DI）、路由注册、中间件机制、service provider 模式等全面重构
2. **Symfony Security 重构**：4.x → 7.x 的 Security 组件经历了 authenticator 系统重写，项目中的 `SimplePreAuthenticator` 等自定义安全组件需要完全重写
3. **`oasis/logging` 和 `oasis/utils` 兼容性**：作为内部包，如果不兼容 PHP 8.4+ 则需要同步升级，可能引入额外工作量

### 高风险

4. **Twig 1.x → 3.x**：模板语法和扩展 API 有 breaking changes，需检查所有模板文件和自定义 Twig 扩展
5. **PHPUnit 5.x → 11.x**：测试 API 大幅变化（`setUp`/`tearDown` 返回类型、assertion 方法签名、mock API 等），所有测试文件需要适配
6. **动态属性弃用**：需排查项目代码中是否依赖动态属性模式

### 中等风险

7. **PHP 语言层面 breaking changes 累积**：跨 5 个大版本，隐式行为变更多，需要充分的测试覆盖来发现问题
8. **下游消费者兼容性**：如果有其他项目依赖 `oasis/http`，PHP 最低版本提升会影响它们

## 建议的升级顺序

1. **Phase 0 — 前置准备**：升级 `oasis/logging`、`oasis/utils` 到 PHP 8.4 兼容版本；升级 PHPUnit 到 11.x 并适配测试
2. **Phase 1 — 框架替换**：用 Symfony MicroKernel（或等价方案）替换 Silex，同时升级全部 Symfony 组件到 7.x
3. **Phase 2 — 模板与 HTTP 客户端**：升级 Twig 到 3.x，Guzzle 到 7.x
4. **Phase 3 — 安全组件重构**：适配 Symfony Security 7.x 的新 authenticator 系统
5. **Phase 4 — PHP 语言适配**：修复隐式 nullable 参数、动态属性、类型严格化等语言层面问题
6. **Phase 5 — 验证与稳定**：全量测试、静态分析（PHPStan/Psalm level 提升）、CI 矩阵覆盖 PHP 8.4 + 8.5

每个 Phase 建议作为独立的 proposal 立项，避免单次变更过大。
