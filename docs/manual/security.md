# Security

`oasis/http` 安全配置说明，涵盖 policies、firewalls、access rules、role hierarchy 和自定义 policy。

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
$kernel->getUser();               // UserInterface | null
```
