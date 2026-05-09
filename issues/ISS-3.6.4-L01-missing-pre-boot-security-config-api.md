# ISS-3.6.4-L01 Missing Pre-boot Security Config API

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `open` |
| Found In | `v3.6.4` |
| Fixed In | |
| Related Test | |

---

## Description

`MicroKernel` 缺少 boot 前注入 security config 的公开 API。

当前 security config 只能通过构造函数 `$httpConfig['security']` 传入，在 `boot()` 阶段由 `registerSecurity()` 从 `httpDataProvider` 中读取。没有提供构造后、boot 前追加 security config 的方法。

这与路由注入的情况形成对比：`RoutingTrait` 提供了 `addRoute()` / `addRoutes()` 作为 boot 前的公开 API，`registerRouting()` 在 boot 时统一消费 `$this->pendingRoutes`。Security 缺少对应的 pending/merge 机制。

---

## Steps to Reproduce

1. 创建一个 ServiceProvider（如 CoreSDK），在 `register($kernel)` 中需要注入 firewalls/policies
2. 构造 `MicroKernel` 后调用 `$sdk->register($kernel)`
3. ServiceProvider 无法将自己的 security config 注入 kernel，因为没有入口

---

## Expected Behavior

提供 boot 前可调用的 API（如 `addSecurityConfig(array $config)` 或 `mergeSecurityConfig(array $config)`），允许 ServiceProvider 在 `register()` 阶段追加 firewalls、policies、role_hierarchy 等安全配置。`registerSecurity()` 在 boot 时合并所有已注册的 security config 后统一初始化。

---

## Actual Behavior

消费方必须在构造 kernel 之前调用 ServiceProvider 的方法获取 security config，再手动传给 kernel 构造函数，然后再调 `register()`。这破坏了 ServiceProvider 模式"注册即生效"的语义。

下游用户按文档只调用 `register()` 后，所有带 `allowed-roles` 的路由返回 403——因为没有 firewall listener，`tokenStorage` 中永远没有 token。

当前 workaround：

```php
$sdk = new CoreSDK($options);
$kernel = new MicroKernel(['security' => $sdk->buildSecurityConfig()], false);
$sdk->register($kernel);
```

---

## Analysis

与 `addRoute()` / `addRoutes()` 属于同一类问题：boot 前缺少公开的配置注入 API。

路由已通过 `RoutingTrait` 中的 `$pendingRoutes` + `addRoute()` 解决。Security 需要类似的模式：

- 在 `MicroKernel`（或新的 trait）中增加 `$pendingSecurityConfigs` 数组
- 提供 `addSecurityConfig(array $config): void` 公开方法，boot 前可调用，boot 后抛 `LogicException`
- `registerSecurity()` 在 boot 时将构造函数传入的 security config 与所有 pending configs 做 deep merge 后统一初始化

---

## History

- `2026-05-09T00:00Z` `v3.6.4` [发现] 下游用户反馈 ServiceProvider 无法在 register() 阶段注入 security config
