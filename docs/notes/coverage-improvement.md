# Coverage Improvement

> 来源：release/3.0 覆盖率采集结果

---

## 背景

Release 3.0 自动化验证阶段首次采集了代码覆盖率（PCOV），整体结果：

- Lines: 89.21%（1058/1186）
- Methods: 81.77%（148/181）
- Classes: 75.56%（34/45）

---

## 未覆盖缺口分析

| 类 | 行覆盖率 | 未覆盖行数 | 未覆盖内容 |
|---|---|---|---|
| `MicroKernel` | 80.92% (318/393) | 75 | boot 阶段条件分支（缓存路由、Twig 未启用、特定 error handler 配置等）、部分 register 逻辑 |
| `CrossOriginResourceSharingProvider` | 91.00% (91/100) | 9 | CORS provider 中的防御性代码路径 |
| `CrossOriginResourceSharingStrategy` | 85.00% (34/40) | 6 | CORS 策略中的 edge case 分支 |
| `SimpleSecurityProvider` | 96.18% (126/131) | 5 | Security provider 中的少量未触发路径 |
| `CacheableRouterProvider` | 94.55% (52/55) | 3 | 路由 provider 中的缓存相关分支 |
| `SimpleAccessRule` | 70.00% (7/10) | 3 | Access rule 的部分方法未调用 |
| `AbstractPreAuthenticator` | 80.00% (8/10) | 2 | 认证器中的异常路径 |
| `AbstractSimplePreAuthenticateUserProvider` | 50.00% (2/4) | 2 | 用户 provider 的 2 个方法未调用（总共只有 4 行代码） |
| `ChainedParameterBagDataProvider` | 93.33% (14/15) | 1 | 1 个方法未调用 |
| `FallbackViewHandler` | 87.50% (7/8) | 1 | fallback 路径未触发 |

`MicroKernel` 占总未覆盖行数的 59%（75/128），是提升覆盖率的主要目标。

---

## 改进方向

- 为 `MicroKernel` 补充不同启动配置组合的集成测试（无 Twig、无 CORS、缓存路由启用/禁用等），这是投入产出比最高的方向
- 为 CORS 和 Security 组件补充 edge case 测试
- 考虑将 `MicroKernel` 中的配置逻辑进一步拆分为独立的可测试单元，降低集成测试的构造成本
- 目标：行覆盖率从 89% 提升到 95%+，主要通过覆盖 `MicroKernel` 的 75 行缺口实现
