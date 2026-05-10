# Architecture

`oasis/http` 的系统架构与模块划分。

---

## 核心类

`MicroKernel`（`src/MicroKernel.php`）继承 Symfony `HttpKernel`，实现 `AuthorizationCheckerInterface`。

通过 bootstrap config 数组驱动初始化，config 经 Symfony Config 组件校验后分发给各 Service Provider。

内部逻辑拆分为 `Kernel/` 子 namespace 下的 traits：`BootstrapTrait`（生命周期）、`RoutingTrait`（路由）、`SecurityTrait`（安全配置注入）、`MiddlewareTrait`（中间件）、`ErrorHandlerTrait`（错误处理）、`ConvenienceTrait`（便捷方法）、`ServicesTrait`（服务访问）。CloudFront IP 解析由独立类 `CloudfrontTrustedProxyResolver` 负责。

boot 前支持编程式注入：`addMiddleware()`、`addControllerInjectedArg()`、`addRoute()` / `addRoutes()`、`addSecurityConfig()` / `addFirewall()` / `addAccessRule()` / `addPolicy()` / `addRoleHierarchy()` 等方法在 boot 前暂存，boot 时消费。boot 后路由表和安全配置冻结，写操作抛出 `LogicException`。`getSecurityConfig()` 提供 boot 前只读查询（返回 Constructor_Config + Pending_Queue 合并视图）。

提供便捷方法：`render()` / `renderView()`（Twig 模板渲染）、`path()` / `url()`（URL 生成）、`before()` / `after()` / `error()`（Silex 风格回调注册）、`view()`（view handler 注册）、`abort()` / `redirect()` / `json()` / `stream()` / `sendFile()`（Response 工厂），委托内部 Twig 环境、UrlGenerator、EventDispatcher 和 Symfony HttpFoundation 实现。

---

## 模块结构

```
src/
├── MicroKernel.php                    # 核心入口（组合 Kernel/ traits）
├── Kernel/                            # MicroKernel 内部 trait 拆分
│   ├── BootstrapTrait.php             # 构造 / boot / run 生命周期
│   ├── CloudfrontTrustedProxyResolver.php # CloudFront IP 解析（独立类）
│   ├── ConvenienceTrait.php           # 便捷方法（render/path/url/abort/redirect/json/stream/sendFile）
│   ├── ErrorHandlerTrait.php          # error handler 注册与异常处理
│   ├── MiddlewareTrait.php            # before/after middleware 注册
│   ├── RoutingTrait.php               # 路由注入 / matcher / generator
│   ├── SecurityTrait.php              # 安全配置注入 / registerSecurity / 冲突检测
│   └── ServicesTrait.php              # Twig / Cookie / CORS / Token 服务访问
├── ChainedParameterBagDataProvider.php # 链式参数包数据提供者
├── Configuration/                     # Symfony Config 定义（校验 bootstrap 数组）
├── ServiceProviders/
│   ├── Routing/                       # 可缓存路由（YAML → Symfony Routing）+ FrozenRouteCollection（boot 后只读包装）
│   ├── Security/                      # 安全：Firewall + Policy + AccessRule
│   ├── Cors/                          # CORS 策略与 preflight 处理
│   ├── Twig/                          # Twig 模板集成
│   └── Cookie/                        # Response Cookie 管理
├── Middlewares/                       # Before / After 中间件抽象
├── Views/                             # View Handler 与 Response Renderer
├── EventSubscribers/                  # 事件订阅器（ViewHandlerSubscriber）
├── ErrorHandlers/                     # 异常包装与 JSON 错误处理
├── Exceptions/                        # 自定义 HTTP 异常
├── ExtendedArgumentValueResolver.php  # 控制器参数自动注入
└── ExtendedExceptionListnerWrapper.php # 异常监听器扩展
```

---

## Bootstrap Config 结构

`MicroKernel` 构造函数接受一个关联数组，支持以下顶层 key：

| Key | 说明 |
|-----|------|
| `routing` | 路由配置（YAML 路径 + 命名空间） |
| `security` | 安全配置（firewalls / access_rules / role_hierarchy / policies） |
| `cors` | CORS 策略数组 |
| `twig` | Twig 模板配置 |
| `twig.strict_variables` | boolean，默认 `true`。启用 Twig 严格变量模式，引用未定义变量时抛出异常 |
| `twig.auto_reload` | boolean/null，默认 `null`。模板自动重载：`true` 强制开启，`false` 强制关闭，`null` 根据 debug 模式自动判定 |
| `middlewares` | `MiddlewareInterface` 实例数组 |
| `providers` | `CompilerPassInterface` / `ExtensionInterface` 实例数组 |
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

1. `MicroKernel::run()` 创建 Request
2. `handle()` 处理 ELB / CloudFront 可信代理
3. Symfony EventDispatcher 按优先级触发 `KernelEvents::REQUEST`：
   - Routing（priority 32）
   - CORS preflight（priority 20）
   - Firewall 认证（priority 8）：匹配 firewall pattern → `AuthenticatorInterface::supports()` → `authenticate()` → `createToken()` → `TokenStorage::setToken()`
   - Access rule 授权（priority 7）：匹配 access rule pattern → 检查 token 角色 → 通过或 403
   - 用户 before middleware
4. 路由匹配 → 控制器执行（参数通过 `ExtendedArgumentValueResolver` 注入）
   - 双层 matcher 架构：编程式路由（内存 `UrlMatcher`，优先匹配）→ YAML 路由（`CacheableRouter` 编译缓存）
   - 通过 `GroupUrlMatcher` 串联，编程式 matcher 排在前面
5. 返回值非 Response 时进入 View Handler 链
6. 异常时进入 Error Handler 链：handler 返回 `Response` 直接使用；返回非 `Response` 对象时进入 View Handler 链，HTTP 状态码取自返回对象的 `getCode()`（若存在），否则使用原始 `$code`（`HttpExceptionInterface::getStatusCode()` 或 500）
7. `KernelEvents::RESPONSE` 触发 after middleware
8. Response 发送，慢请求检测

---

## 安全模型

`SimpleSecurityProvider` 自管理认证和授权，不依赖 Symfony SecurityBundle。

### 核心组件

- **`AuthenticationPolicyInterface`**：认证策略接口，通过 `getAuthenticator()` 返回 `AuthenticatorInterface` 实例，内置支持 pre_auth / http / form / anonymous 等类型
- **`AbstractPreAuthenticator`**：模板方法基类，实现 Symfony `AuthenticatorInterface`，子类只需实现 `getCredentialsFromRequest()` + `authenticateAndGetUser()` 两步
- **`FirewallInterface` / `SimpleFirewall`**：按 URL pattern 匹配请求，关联一组 policy
- **`AccessRuleInterface` / `SimpleAccessRule`**：按 URL pattern + roles 做授权检查，未授权抛 403
- **`NullEntryPoint`**：默认认证入口点，未认证时抛出 `AccessDeniedHttpException`
- **Role Hierarchy**：角色继承关系，通过 Symfony `RoleHierarchy` + `RoleHierarchyVoter` + `AuthenticatedVoter` + `AccessDecisionManager` 实现；`AuthenticatedVoter` 负责处理 `IS_AUTHENTICATED_FULLY` 等认证属性判断

### 认证流程

`SimpleSecurityProvider.register()` 向 EventDispatcher 注册两个 `KernelEvents::REQUEST` listener：

| Listener | 优先级 | 职责 |
|----------|--------|------|
| Firewall listener | `BEFORE_PRIORITY_FIREWALL`（8） | 认证：URL 匹配 firewall → policy 获取 authenticator → `supports()` → `authenticate()` → `createToken()` → 存入 `TokenStorage` |
| Access rule listener | `BEFORE_PRIORITY_FIREWALL - 1`（7） | 授权：URL 匹配 access rule → 检查 token 角色 → 通过或抛出 `AccessDeniedHttpException` |

Firewall listener 调用链：

1. 遍历 firewalls，第一个 URL pattern 匹配的 firewall 生效
2. 遍历该 firewall 的 policies，通过 `AuthenticationPolicyInterface::getAuthenticator()` 获取 authenticator
3. 调用 `authenticator->supports(request)`——内部委托 `getCredentialsFromRequest()`，返回 null 则跳过
4. 调用 `authenticator->authenticate(request)`——提取凭证 → `authenticateAndGetUser()` → 返回 `SelfValidatingPassport`
5. 调用 `authenticator->createToken(passport, firewallName)`——返回 `PostAuthenticationToken`
6. `tokenStorage->setToken(token)` 存储认证结果

认证失败（`AuthenticationException`）不阻断请求——catch 后 token 保持 null，由 access rule listener 决定是否拒绝。

Access rule listener 按注册顺序匹配，第一个匹配的 rule 生效：无角色要求则放行，token 为 null 或角色不足则抛出 `AccessDeniedHttpException`。

### Pre-boot 安全配置注入

`SecurityTrait`（`src/Kernel/SecurityTrait.php`）提供 boot 前编程式注入安全配置的能力，模式与 `RoutingTrait` 同构（pending queue + boot 时合并）。

**注入 API**（均在 boot 前调用，boot 后抛 `LogicException`）：

| 方法 | 说明 |
|------|------|
| `addSecurityConfig(array $config, bool $allowOverwrite = false)` | 批量注入，接受 `firewalls` / `access_rules` / `policies` / `role_hierarchy` 顶层 key，未知 key 抛 `InvalidArgumentException` |
| `addFirewall(string $name, array $config, bool $allowOverwrite = false)` | 注入单个 firewall |
| `addAccessRule(array $rule)` | 注入单条 access rule（始终追加，无冲突概念） |
| `addPolicy(string $name, mixed $config, bool $allowOverwrite = false)` | 注入单个 policy |
| `addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false)` | 注入单个角色层级映射 |
| `getSecurityConfig(): array` | 只读查询，返回 Constructor_Config + Pending_Queue 合并视图 |

**冲突检测**（fail-fast）：注入时立即检测同名 firewall / policy / role_hierarchy 冲突。`$allowOverwrite = false`（默认）时抛 `LogicException`；`$allowOverwrite = true` 时 last-write-wins 静默覆盖。`access_rules` 始终按注册顺序追加。

**合并时机**：`registerSecurity()` 在 `boot()` 中执行，将 Constructor_Config（构造函数 `$httpConfig['security']`）与 `$pendingSecurityConfigs` 队列合并后传给 `SimpleSecurityProvider::register()`。无任何安全配置时 early return。

**典型使用场景**：ServiceProvider 在 `register($kernel)` 阶段调用注入 API 追加 firewalls / access_rules / policies / role_hierarchy，boot 时统一初始化。

---

## 路由注入

`RoutingTrait`（`src/Kernel/RoutingTrait.php`）提供 boot 前编程式路由注入。

| 方法 | 说明 |
|------|------|
| `addRoute(string $name, Route $route, bool $allowOverwrite = true)` | 注入单条路由 |
| `addRoutes(RouteCollection $routes, bool $allowOverwrite = true)` | 批量注入路由集合 |

**冲突检测**（fail-fast）：`$allowOverwrite = true`（默认）时同名路由静默覆盖（向后兼容）；`$allowOverwrite = false` 时抛 `LogicException`。

boot 后调用抛 `LogicException`。

---

## CORS 模型

- 每个 CORS 策略由 `CrossOriginResourceSharingStrategy` 定义（pattern / origins / headers / max_age / credentials）
- Preflight 请求在 before middleware 阶段（priority 20）直接返回 `PrefilightResponse`
- 支持自定义策略（继承 `CrossOriginResourceSharingStrategy`）
