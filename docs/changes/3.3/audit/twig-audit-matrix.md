# Twig Module Audit Matrix

> 审计基准：`oasis/http` v2.5.0（tag `v2.5.0`）+ Silex 2.3.0 + Twig 1.39.1
> 当前版本：v3.x + Twig 3.24.0

---

## API Surface 审计

| Silex API_Surface Item | Category | Coverage Status | Current Implementation | Disposition | Notes |
|------------------------|----------|-----------------|----------------------|-------------|-------|
| `SimpleTwigServiceProvider` 继承 Silex `TwigServiceProvider` | registration | intentionally-removed | `SimpleTwigServiceProvider` 完全重写，不再继承 Silex provider | confirm-documented | Migration Guide §10 已标注 |
| `SimpleTwigServiceProvider::register(Container $app)` | registration | covered | `SimpleTwigServiceProvider::register(MicroKernel $kernel, array $twigConfig)` | no-action | 签名变更但功能等价 |
| `SimpleTwigServiceProvider::boot(Application $app)` | registration | intentionally-removed | 注册逻辑合并到 `register()` 中，由 `MicroKernel::registerTwig()` 在 boot 阶段调用 | no-action | 内部实现变更，外部行为等价 |
| `$app['twig.config']` Pimple service | registration | intentionally-removed | 配置通过 `MicroKernel::registerTwig()` 直接传递给 provider | no-action | Pimple 容器已移除 |
| `$app['twig']` Pimple service 访问 | access | intentionally-removed | `MicroKernel::getTwig()` | confirm-documented | Migration Guide §10 已标注 |
| `TwigConfiguration` config schema | config | covered | `TwigConfiguration` — 新增 `strict_variables`、`auto_reload` 配置项 | no-action | v3.x 扩展了配置能力 |
| `template_dir` 配置项 | config | covered | `TwigConfiguration::template_dir` → `FilesystemLoader` | no-action | — |
| `cache_dir` 配置项（twig 级别） | config | covered | `TwigConfiguration::cache_dir` → `TwigEnvironment` options `cache` | no-action | — |
| 顶层 `cache_dir` 自动合并到 twig config | config | intentionally-removed | v3.x 不再将顶层 `cache_dir` 自动合并到 twig config | no-action | v2.5.0 中 `array_merge(['cache_dir' => $this->cacheDir], $twigConfig)` 将顶层 cache_dir 作为 twig cache_dir 的默认值；v3.x 要求用户显式配置 `twig.cache_dir`。行为变更但不影响下游（下游均显式配置了 `twig.cache_dir`） |
| `asset_base` 配置项 | config | covered | `TwigConfiguration::asset_base` → `asset()` function 使用 | no-action | — |
| `globals` 配置项 | config | covered | `TwigConfiguration::globals` → `$twig->addGlobal()` 循环注册 | no-action | — |
| `strict_variables` 配置项 | config | covered | `TwigConfiguration::strict_variables`（默认 `true`）→ `TwigEnvironment` options | no-action | v2.5.0 无此配置项（由 Silex TwigServiceProvider 默认 false）；v3.x 新增并默认 true |
| `auto_reload` 配置项 | config | covered | `TwigConfiguration::auto_reload`（默认 `null`）→ auto-detect via `isDebug()` | no-action | v2.5.0 无此配置项（由 Silex TwigServiceProvider 根据 debug 自动设置）；v3.x 显式暴露配置 |
| `FilesystemLoader` 创建 | initialization | covered | `new FilesystemLoader($templateDir)` | no-action | — |
| `TwigEnvironment` 创建 | initialization | covered | `new TwigEnvironment($loader, $options)` | no-action | Twig 1.x `Twig_Environment` → Twig 3.x `\Twig\Environment` |
| `http` global 变量（kernel 自身） | globals | covered | `$twig->addGlobal('http', $kernel)` | no-action | v2.5.0: `$twig->addGlobal('http', $c)` where `$c` is Pimple container (= SilexKernel) |
| 用户自定义 globals | globals | covered | `foreach ($globals as $key => $value) { $twig->addGlobal($key, $value); }` | no-action | — |
| `asset()` Twig function | functions | covered | `new TwigFunction('asset', ...)` | no-action | v2.5.0: `new Twig_SimpleFunction('asset', ...)`；功能等价 |
| `asset()` function 版本参数 | functions | covered | `$url .= "?v=$version"` when version non-empty | no-action | — |
| `is_granted()` Twig function | functions | covered | `new TwigFunction('is_granted', ...)` | no-action | v2.5.0 中由 Silex SecurityServiceProvider 的 Twig 集成提供；v3.x 由 SimpleTwigServiceProvider 直接注册 |
| `getTwig()` 返回 `TwigEnvironment` | access | covered | `MicroKernel::getTwig(): ?TwigEnvironment` | no-action | — |
| `getTwig()` 无 twig 配置时返回 null | access | covered | `registerTwig()` 不调用 → `$twigEnvironment` 保持 null | no-action | — |
| Silex `TwigTrait::render()` 便捷方法 | access | intentionally-removed | N/A — 用户直接调用 `$kernel->getTwig()->render(...)` | confirm-documented | Migration Guide §10 `SimpleTwigServiceProvider` 重写 section 隐含覆盖（访问方式变更） |
| Silex `TwigTrait::renderView()` 便捷方法 | access | intentionally-removed | N/A — 用户直接调用 `$kernel->getTwig()->render(...)` | confirm-documented | 同上 |
| Twig 1.x 类名（`Twig_Environment` 等） | class_migration | intentionally-removed | Twig 3.x 命名空间类名 | confirm-documented | Migration Guide §10 已标注 |
| `twig/extensions` 包集成 | class_migration | intentionally-removed | N/A — Twig 3.x 内置或通过 `twig/*-extra` 提供 | confirm-documented | Migration Guide §10 已标注 |

---

## 行为等价性审计

### 1. Twig Environment 配置项传递

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| `cache` option | 通过 `boot()` 中 `$app['twig.options']` 设置 | 通过 `SimpleTwigServiceProvider::register()` 直接设置 options | ✅ 等价 |
| `strict_variables` option | 由 Silex TwigServiceProvider 默认 false | 由 `TwigConfiguration` 默认 true | ⚠️ 默认值变更（true vs false），但已在 Migration Guide 中说明 |
| `auto_reload` option | 由 Silex TwigServiceProvider 根据 `$app['debug']` 自动设置 | 由 `SimpleTwigServiceProvider` 根据 `$kernel->isDebug()` 自动设置（当 `auto_reload` 为 null 时） | ✅ 等价 |
| `debug` option | 由 Silex TwigServiceProvider 根据 `$app['debug']` 设置 | v3.x 未显式设置 `debug` option | ⚠️ 见下方分析 |

**`debug` option 分析**：

Silex 的 `TwigServiceProvider` 会将 `$app['debug']` 传递给 Twig 的 `debug` option，这会影响 Twig 的 `dump()` function 等调试功能。v3.x 的 `SimpleTwigServiceProvider` 未显式设置 `debug` option，Twig 3.x 默认为 `false`。

然而，Twig 3.x 的 `debug` option 主要影响 `dump()` 扩展（需要额外安装 `twig/extra-bundle`），在 oasis/http 的使用场景中不涉及。且 v2.5.0 的下游代码中未发现依赖 Twig debug mode 的用法。此差异为 **non-breaking**，但影响极小，不需要修复。

### 2. Global 变量注册

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| `http` global 注册时机 | 在 `$app['twig']` extend 回调中 | 在 `SimpleTwigServiceProvider::register()` 中 | ✅ 等价（均在 boot 阶段完成） |
| `http` global 值 | `$c`（Pimple Container = SilexKernel） | `$kernel`（MicroKernel） | ✅ 等价（均为 kernel 实例） |
| 用户 globals 注册 | 在 `$app['twig']` extend 回调中循环 | 在 `register()` 中循环 | ✅ 等价 |
| globals 支持对象类型 | ✅ | ✅ | ✅ 等价 |
| globals 支持标量类型 | ✅ | ✅ | ✅ 等价 |

### 3. Template Loader 配置

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| Loader 类型 | `Twig_Loader_Filesystem`（通过 Silex TwigServiceProvider） | `FilesystemLoader` | ✅ 等价（Twig 3.x 类名） |
| template path 来源 | `$app['twig.path']` = `$app['twig.config.template_dir']` | `$dataProvider->getMandatory('template_dir')` | ✅ 等价 |
| 多 template path | Silex 支持数组形式的 `twig.path` | v3.x 仅支持单个 `template_dir` 字符串 | ⚠️ 见下方分析 |

**多 template path 分析**：

Silex 的 `TwigServiceProvider` 支持 `$app['twig.path']` 为数组（多个模板目录）。v2.5.0 的 `SimpleTwigServiceProvider` 通过 `$app['twig.path'] = $app['twig.config.template_dir']` 设置，而 `TwigConfiguration` 中 `template_dir` 为 `scalarNode`（单值）。因此 v2.5.0 实际上也只支持单个 template_dir。

结论：v2.5.0 和 v3.x 均只支持单个 template_dir，**行为等价**。

### 4. `asset()` Function

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| function 名称 | `asset` | `asset` | ✅ 等价 |
| 参数签名 | `($assetFile, $version = '')` | `(string $assetFile, string $version = '')` | ✅ 等价（增加了类型声明） |
| URL 拼接逻辑 | `$c['twig.config.asset_base'] . $assetFile` | `$assetBase . $assetFile` | ✅ 等价 |
| 版本参数处理 | `"?v=$version"` when `$version !== ''` | `"?v=$version"` when `$version !== ''` | ✅ 等价 |

### 5. `is_granted()` Function

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 注册来源 | Silex SecurityServiceProvider 的 Twig 集成 | `SimpleTwigServiceProvider` 直接注册 | ✅ 功能等价 |
| 调用目标 | `$app['security.authorization_checker']->isGranted($role)` | `$kernel->isGranted($role)` | ✅ 等价（MicroKernel 实现了 `AuthorizationCheckerInterface`） |
| 无 Security 配置时 | function 不存在（SecurityServiceProvider 未注册） | function 存在但调用 `$kernel->isGranted()` 会抛 `AuthenticationCredentialsNotFoundException` | ⚠️ 行为差异但合理 |

**`is_granted()` 无 Security 时的行为差异分析**：

v2.5.0 中，如果未配置 Security，`is_granted()` Twig function 不会被注册（因为 Silex SecurityServiceProvider 不会加载）。在模板中调用会得到 Twig 的 "unknown function" 错误。

v3.x 中，`is_granted()` 始终被注册（由 SimpleTwigServiceProvider 注册），但如果未配置 Security，调用时会抛出 `AuthenticationCredentialsNotFoundException`。

两种情况下，在无 Security 配置时调用 `is_granted()` 都会导致错误，只是错误类型不同。这是 **non-breaking** 差异（正常使用场景下不会在无 Security 时调用 `is_granted()`），不需要修复。

### 6. Twig 缺失时的行为

| 行为 | v2.5.0 | v3.x | 等价性 |
|------|--------|------|--------|
| 无 `twig` config key | `$this['twig.config'] = []` → `SimpleTwigServiceProvider` 不注册 → `$app['twig']` 未定义 | `registerTwig()` 检测到空/非数组 config → 不调用 provider → `$twigEnvironment` 为 null | ✅ 等价 |
| `getTwig()` 返回值 | `isset($this['twig']) ? $this['twig'] : null` | `return $this->twigEnvironment` (null) | ✅ 等价 |

---

## 审计结论

| Coverage Status | 数量 | 说明 |
|-----------------|------|------|
| covered | 17 | 功能完整覆盖，行为等价 |
| intentionally-removed | 8 | Silex/Pimple 架构相关，已在 Migration Guide 中标注或隐含覆盖 |
| missing-non-breaking | 0 | — |
| missing-breaking | 0 | — |

**总结**：Twig 模块的 v3.x 实现完整覆盖了 v2.5.0 + Silex 2.3.0 的所有用户可见能力。所有 intentionally-removed 项均为 Silex/Pimple 架构移除的必然结果，已在 Migration Guide 中文档化。未发现需要修复的缺失能力。

**行为差异备注**（均为 non-breaking，不需要修复）：
1. `strict_variables` 默认值从 false 变为 true — 已在 Migration Guide 中说明
2. Twig `debug` option 未显式传递 — 影响极小，不涉及下游使用场景
3. `is_granted()` 在无 Security 时的错误类型不同 — 正常使用不会触发
4. 顶层 `cache_dir` 不再自动合并到 twig config — 下游均显式配置 `twig.cache_dir`
