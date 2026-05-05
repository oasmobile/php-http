# Coverage Improvement

> 来源：release/3.0 覆盖率采集结果 → hotfix/3.6.1 提升

---

## 当前状态（v3.6.1）

- Lines: 95.05%（1305/1373）
- Methods: 88.57%（186/210）
- Classes: 82.98%（39/47）

目标 95% 已达成。

---

## 历史对比

| 版本 | Lines | Methods | Classes |
|------|-------|---------|---------|
| v3.0 | 89.21% (1058/1186) | 81.77% (148/181) | 75.56% (34/45) |
| v3.6.1 | 95.05% (1305/1373) | 88.57% (186/210) | 82.98% (39/47) |

---

## 剩余不可覆盖行分析

| 位置 | 行数 | 原因 |
|------|------|------|
| `MicroKernel::run()` slow request `mwarning` 路径 | ~6 | 已覆盖 custom handler 路径，default 路径仅调用 `mwarning()` |
| `MicroKernel::getProjectDir()` `getcwd() === false` | 1 | PHP 运行时不可能触发 |
| `CallbackMiddleware::after()` null callback 路径 | 1 | before-only middleware 的 after 不会被 dispatcher 调用 |
| 防御性 `throw` 分支 | ~5 | 正常运行不可达 |
| `SimpleSecurityProvider` 内部分支 | ~17 | 需要构造特定 security 配置组合 |
| CORS provider 防御性路径 | ~9 | 需要构造特定 CORS 边界条件 |

---

## 改进建议（如需进一步提升）

1. 补充 `SimpleSecurityProvider` 的 access rule listener 注册路径测试（+17 行）
2. 补充 CORS provider 的 edge case（无 origin header、无匹配 pattern 等）（+9 行）
