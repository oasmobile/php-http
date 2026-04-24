# Architecture

`oasis/http` 的系统架构与模块划分。

---

## 核心类

`SilexKernel`（`src/SilexKernel.php`）继承 `Silex\Application`，实现 `AuthorizationCheckerInterface`。

通过 bootstrap config 数组驱动初始化，config 经 Symfony Config 组件校验后分发给各 Service Provider。

---

## 模块结构

```
src/
├── SilexKernel.php                  # 核心入口
├── Configuration/                   # Symfony Config 定义（校验 bootstrap 数组）
├── ServiceProviders/
│   ├── Routing/                     # 可缓存路由（YAML → Symfony Routing）
│   ├── Security/                    # 安全：Firewall + Policy + AccessRule
│   ├── Cors/                        # CORS 策略与 preflight 处理
│   ├── Twig/                        # Twig 模板集成
│   └── Cookie/                      # Response Cookie 管理
├── Middlewares/                     # Before / After 中间件抽象
├── Views/                           # View Handler 与 Response Renderer
├── ErrorHandlers/                   # 异常包装与 JSON 错误处理
├── Exceptions/                      # 自定义 HTTP 异常
├── ExtendedArgumentValueResolver.php  # 控制器参数自动注入
└── ExtendedExceptionListnerWrapper.php # 异常监听器扩展
```

---

## Bootstrap Config 结构

`SilexKernel` 构造函数接受一个关联数组，支持以下顶层 key：

| Key | 说明 |
|-----|------|
| `routing` | 路由配置（YAML 路径 + 命名空间） |
| `security` | 安全配置（firewalls / access_rules / role_hierarchy / policies） |
| `cors` | CORS 策略数组 |
| `twig` | Twig 模板配置 |
| `twig.strict_variables` | boolean，默认 `true`。启用 Twig 严格变量模式，引用未定义变量时抛出异常 |
| `twig.auto_reload` | boolean/null，默认 `null`。模板自动重载：`true` 强制开启，`false` 强制关闭，`null` 根据 debug 模式自动判定 |
| `middlewares` | `MiddlewareInterface` 实例数组 |
| `providers` | `ServiceProviderInterface` 实例数组 |
| `view_handlers` | callable 数组，处理非 Response 返回值 |
| `error_handlers` | callable 数组，处理异常 |
| `injected_args` | 控制器参数自动注入候选对象 |
| `trusted_proxies` | 可信代理 IP 数组 |
| `trusted_header_set` | 可信 header 集合 |
| `behind_elb` | bool，是否在 AWS ELB 后 |
| `trust_cloudfront_ips` | bool，是否信任 CloudFront IP |
| `cache_dir` | 缓存目录路径 |

---

## 请求处理流程

1. `SilexKernel::run()` 创建 Request
2. `handle()` 处理 ELB / CloudFront 可信代理
3. Symfony EventDispatcher 按优先级触发 `KernelEvents::REQUEST`：
   - Routing（priority 32）
   - CORS preflight（priority 20）
   - Firewall（priority 8）
   - 用户 before middleware
4. 路由匹配 → 控制器执行（参数通过 `ExtendedArgumentValueResolver` 注入）
5. 返回值非 Response 时进入 View Handler 链
6. 异常时进入 Error Handler 链
7. `KernelEvents::RESPONSE` 触发 after middleware
8. Response 发送，慢请求检测

---

## 安全模型

- **Policy**：认证策略，实现 `AuthenticationPolicyInterface`，内置支持 http / form / anonymous 等
- **Firewall**：按 pattern 匹配请求，使用指定 policy 提取 SecurityToken
- **AccessRule**：按 pattern + roles 做授权检查，未授权抛 403
- **Role Hierarchy**：角色继承关系

认证失败不阻断请求（token 为 null），授权由 AccessRule 负责拦截。

---

## CORS 模型

- 每个 CORS 策略由 `CrossOriginResourceSharingStrategy` 定义（pattern / origins / headers / max_age / credentials）
- Preflight 请求在 before middleware 阶段（priority 20）直接返回 `PrefilightResponse`
- 支持自定义策略（继承 `CrossOriginResourceSharingStrategy`）
