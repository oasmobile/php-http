# Security

`oasis/http` 安全配置说明，涵盖 policies、firewalls、access rules、role hierarchy、自定义 policy 和编程式注入 API。

---

## 基本示例

```php
$config = [
    "security" => [
        "firewalls" => [
            "http.auth" => [
                "pattern" => "^/admin/",
                "policies" => ["http" => true],
                "users" => [
                    'admin' => ['ROLE_ADMIN', '$2y$10$3i9/lVd8UOFIJ6PAMFt8gu3/r5g0qeCJvoSlLCsvMTythye19F77a'],
                ],
            ],
        ],
    ],
];
```

---

## 完整配置结构

```php
$config = [
    'security' => [
        'policies' => [...],
        'firewalls' => [...],
        'access_rules' => [...],
        'role_hierarchy' => [...],
    ],
];
```

---

## Policies

自定义认证策略，key 为策略名，value 为 `AuthenticationPolicyInterface` 实例。

内置策略名（不可覆盖）：`logout`、`pre_auth`、`form`、`http`、`remember_me`、`anonymous`。

---

## Firewalls

按 pattern 匹配请求，使用指定 policy 提取 SecurityToken。

| 字段 | 类型 | 说明 |
|------|------|------|
| `pattern` | string / RequestMatcher | 匹配请求的模式 |
| `policies` | array | 策略名 → `true` 或选项数组，顺序保留 |
| `users` | array / UserProviderInterface | 用户提供者 |
| `stateless` | bool | 默认 `false`，使用 `form` 策略时应设为 `true` |
| `misc` | array | 策略可能需要的额外数据 |

认证失败时请求不会被阻断（token 为 null，roles 为空），授权拦截由 access rules 负责。

---

## Access Rules

按 pattern + roles 做授权检查。

| 字段 | 类型 | 说明 |
|------|------|------|
| `pattern` | string / RequestMatcher | 匹配请求的模式 |
| `roles` | string / array | 要求的角色，多个角色时需全部满足 |
| `channel` | null / "http" / "https" | 限制访问协议 |

未授权时抛出 `AccessDeniedHttpException`（HTTP 403）。

---

## Role Hierarchy

角色继承关系。拥有父角色时自动拥有所有子角色：

```php
'role_hierarchy' => [
    "ROLE_ADMIN" => ["ROLE_USER", "ROLE_SUPPORT"],
],
```

检查角色：

```php
$kernel->isGranted('ROLE_ADMIN');
```

---

## 认证属性检查

除角色外，`isGranted()` 还支持 Symfony 认证属性：

| 属性 | 含义 |
|------|------|
| `IS_AUTHENTICATED_FULLY` | 用户通过完整认证（非 remember-me） |
| `IS_AUTHENTICATED_REMEMBERED` | 用户通过 remember-me 或完整认证 |

```php
$kernel->isGranted('IS_AUTHENTICATED_FULLY'); // 完整认证检查
```

---

## 自定义 Security Policy

实现自定义认证策略的完整流程。

### 1. Policy 类

继承 `AbstractSimplePreAuthenticationPolicy`，实现 `getPreAuthenticator()`：

```php
class MyPolicy extends AbstractSimplePreAuthenticationPolicy
{
    public function getPreAuthenticator()
    {
        return new MyAuthenticator();
    }
}
```

### 2. Authenticator

继承 `AbstractSimplePreAuthenticator`，从 Request 中提取凭据：

```php
class MyAuthenticator extends AbstractSimplePreAuthenticator
{
    public function getCredentialsFromRequest(Request $request)
    {
        if (!$request->query->has('token')) {
            throw new BadCredentialsException("'token' string is not provided.");
        }

        return [
            "ip" => $request->getClientIp(),
            "token" => $request->query->get('token'),
        ];
    }
}
```

### 3. User Provider

继承 `AbstractSimplePreAuthenticateUserProvider`，根据凭据返回 `UserInterface`：

```php
class MyUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    public function authenticateAndGetUser($credentials)
    {
        $token = $credentials['token'];
        list($userId, $secret) = explode(".", $token);

        $roles = ($userId < 100) ? ["ROLE_ADMIN"] : ["ROLE_USER"];

        return new MyRequestSender($userId, $roles);
    }
}
```

### 4. User 类（Request Sender）

实现 `UserInterface`，pre-auth 模式下 `getPassword()` / `getSalt()` / `getUsername()` 可抛异常：

```php
class MyRequestSender implements UserInterface
{
    protected $userId;
    protected $roles;

    public function __construct($userId, $roles)
    {
        $this->userId = $userId;
        $this->roles = $roles;
    }

    public function getRoles() { return $this->roles; }
    public function getPassword() { throw new \LogicException("Not supported"); }
    public function getSalt() { throw new \LogicException("Not supported"); }
    public function getUsername() { throw new \LogicException("Not supported"); }
    public function eraseCredentials() {}
}
```

### 5. 集成使用

```php
$config = [
    'security' => [
        'policies' => ["my_policy" => new MyPolicy()],
        'firewalls' => [
            "admin_area" => [
                "pattern" => "^/admin/.*",
                "policies" => ["my_policy" => true],
                "users" => new MyUserProvider(),
                "stateless" => false,
            ],
        ],
        'access_rules' => [
            "admin_rule" => [
                "pattern" => "^/admin/.*",
                "roles" => ["ROLE_ADMIN"],
                "channel" => "https",
            ],
        ],
    ],
];
```

### 6. 认证后访问

```php
$kernel->getToken();              // TokenInterface | null
$kernel->getToken()->getRoles();  // 所有角色
$kernel->isGranted("ROLE_ADMIN"); // 角色检查
$kernel->isGranted("IS_AUTHENTICATED_FULLY"); // 认证状态检查
$kernel->getUser();               // UserInterface | null
```

---

## Programmatic Security Config Injection

除构造函数传入外，`MicroKernel` 提供编程式安全配置注入 API，在 boot 前通过代码注册 security config。典型场景是 ServiceProvider 在 `register()` 阶段注入 firewalls、access_rules、policies、role_hierarchy。

### addSecurityConfig

批量注入安全配置，接受与构造函数 `security` key 相同结构的完整或部分配置：

```php
$kernel->addSecurityConfig([
    'firewalls' => [
        'api' => [
            'pattern' => '^/api/',
            'policies' => ['my_policy' => true],
            'users' => new MyUserProvider(),
            'stateless' => true,
        ],
    ],
    'access_rules' => [
        ['pattern' => '^/api/', 'roles' => ['ROLE_API']],
    ],
]);
```

仅允许 `firewalls`、`access_rules`、`policies`、`role_hierarchy` 四个顶层 key，传入未知 key 时抛出 `InvalidArgumentException`。

### 细粒度 API

逐条注入单个配置项：

```php
// 注入单个 firewall
$kernel->addFirewall('admin', [
    'pattern' => '^/admin/',
    'policies' => ['http' => true],
    'users' => ['admin' => ['ROLE_ADMIN', '$2y$...']],
]);

// 注入单条 access rule（始终按注册顺序追加）
$kernel->addAccessRule([
    'pattern' => '^/admin/',
    'roles' => ['ROLE_ADMIN'],
]);

// 注入单个 policy
$kernel->addPolicy('my_policy', new MyPolicy());

// 注入单个角色层级
$kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER', 'ROLE_SUPPORT']);
```

### getSecurityConfig

只读查询当前累积的安全配置（Constructor_Config + 已注入的 Pending_Queue 合并视图）：

```php
$current = $kernel->getSecurityConfig();
// 条件注入：仅在尚未注册 'api' firewall 时注入
if (!isset($current['firewalls']['api'])) {
    $kernel->addFirewall('api', [...]);
}
```

### 在 ServiceProvider 中使用

```php
class MySecurityProvider implements ServiceProviderInterface
{
    public function register($kernel): void
    {
        // 批量注入
        $kernel->addSecurityConfig([
            'firewalls' => [
                'api' => [
                    'pattern' => '^/api/',
                    'policies' => ['token_auth' => true],
                    'users' => new TokenUserProvider(),
                    'stateless' => true,
                ],
            ],
            'access_rules' => [
                ['pattern' => '^/api/', 'roles' => ['ROLE_API']],
            ],
        ]);

        // 或使用细粒度 API
        $kernel->addPolicy('token_auth', new TokenAuthPolicy());
        $kernel->addRoleHierarchy('ROLE_API', ['ROLE_USER']);

        // 条件注入：查询当前状态后决定
        $config = $kernel->getSecurityConfig();
        if (!isset($config['firewalls']['admin'])) {
            $kernel->addFirewall('admin', [...]);
        }
    }
}
```

---

## Conflict Detection and $allowOverwrite

安全配置注入采用 fail-fast 冲突检测：注入时立即检查已有配置，发现同名 firewall、policy 或 role_hierarchy 时抛出 `LogicException`。

### 默认行为

| API | `$allowOverwrite` 默认值 | 说明 |
|-----|--------------------------|------|
| `addSecurityConfig()` | `false` | 同名冲突时抛异常 |
| `addFirewall()` | `false` | 同名冲突时抛异常 |
| `addPolicy()` | `false` | 同名冲突时抛异常 |
| `addRoleHierarchy()` | `false` | 同角色冲突时抛异常 |
| `addAccessRule()` | — | 无冲突概念，始终追加 |
| `addRoute()` | `true` | 同名路由静默覆盖（向后兼容） |
| `addRoutes()` | `true` | 同名路由静默覆盖（向后兼容） |

Security API 默认 `$allowOverwrite = false`（严格模式），Routing API 默认 `$allowOverwrite = true`（向后兼容）。

### 覆盖模式

传入 `$allowOverwrite = true` 时，同名条目静默覆盖（last-write-wins）：

```php
// 第一个 provider 注入
$kernel->addFirewall('api', ['pattern' => '^/api/', ...]);

// 第二个 provider 覆盖（不抛异常）
$kernel->addFirewall('api', ['pattern' => '^/api/v2/', ...], allowOverwrite: true);
```

### 冲突示例

```php
// 两个 provider 注入同名 firewall，默认抛异常
$kernel->addFirewall('api', [...]);
$kernel->addFirewall('api', [...]); // LogicException: Duplicate firewall: 'api'
```

---

## Post-Boot Freeze

boot 完成后，所有安全配置注入 API 和查询 API 被冻结，调用将抛出 `LogicException`：

- `addSecurityConfig()`：抛出 `LogicException('Cannot add security config after the kernel has been booted.')`
- `addFirewall()` / `addAccessRule()` / `addPolicy()` / `addRoleHierarchy()`：同上
- `getSecurityConfig()`：抛出 `LogicException('Cannot query security config after the kernel has been booted.')`

所有注入必须在 ServiceProvider 的 `register()` 阶段完成，boot 后不可修改。
