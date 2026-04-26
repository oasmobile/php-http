# Migration Guide: oasis/http v2.x → v3.0

`oasis/http` v3.0 完成了从 Silex 到 Symfony MicroKernel 的全面迁移，PHP 最低版本从 `>=7.0.0` 提升到 `>=8.5`。本文档面向下游消费者，按模块分章节描述所有 breaking change 及适配方法。

**适用范围**：从 `oasis/http` v2.x（基于 Silex / Symfony 4.x / PHP 7.x）升级到 v3.0（基于 Symfony MicroKernel / Symfony 7.2 / PHP 8.5）。

**严重程度标注**：

| 标记 | 含义 | 说明 |
|------|------|------|
| 🔴 | 必须改 | 代码无法编译或运行，必须适配 |
| 🟡 | 建议改 | deprecation 或行为变化，建议适配 |
| 🟢 | 可选 | 改善性变更或内部重构，无需下游操作 |

---

## 目录

- [1. PHP Version](#1-php-version)
- [2. Dependencies](#2-dependencies)
- [3. Kernel API](#3-kernel-api)
- [4. DI Container](#4-di-container)
- [5. Bootstrap Config](#5-bootstrap-config)
- [6. Routing](#6-routing)
- [7. Security](#7-security)
- [8. Middleware](#8-middleware)
- [9. Views](#9-views)
- [10. Twig](#10-twig)
- [11. CORS](#11-cors)
- [12. Cookie](#12-cookie)
- [13. PHP 语言适配](#13-php-语言适配)
- [14. 附录](#14-附录)

---

## 快速评估清单

**🔴 必须改**

| 变更 | 章节 |
|------|------|
| PHP `>=7.0.0` → `>=8.5` | [1. PHP Version](#1-php-version) |
| 移除 `silex/silex`、`silex/providers`、`twig/extensions` | [2. Dependencies](#2-dependencies) |
| Symfony `^4.0` → `^7.2` | [2. Dependencies](#2-dependencies) |
| `twig/twig` `^1.24` → `^3.0` | [2. Dependencies](#2-dependencies) |
| `guzzlehttp/guzzle` `^6.3` → `^7.0` | [2. Dependencies](#2-dependencies) |
| `oasis/logging` `^1.1` → `^3.0`、`oasis/utils` `^1.6` → `^3.0` | [2. Dependencies](#2-dependencies) |
| `SilexKernel` → `MicroKernel` | [3. Kernel API](#3-kernel-api) |
| `MicroKernel` 构造函数签名变更 | [3. Kernel API](#3-kernel-api) |
| `SilexKernel::__set()` 移除 | [3. Kernel API](#3-kernel-api) |
| Pimple DI 容器移除 | [4. DI Container](#4-di-container) |
| `$app['xxx']` 访问模式移除 | [4. DI Container](#4-di-container) |
| `Pimple\ServiceProviderInterface` → `CompilerPassInterface`/`ExtensionInterface` | [4. DI Container](#4-di-container) |
| `providers` key 语义变更 | [5. Bootstrap Config](#5-bootstrap-config) |
| `AuthenticationPolicyInterface` 重写 | [7. Security](#7-security) |
| `FirewallInterface` 重写 | [7. Security](#7-security) |
| `AccessRuleInterface` 重写 | [7. Security](#7-security) |
| `AbstractSimplePreAuthenticator` → `AbstractPreAuthenticator` | [7. Security](#7-security) |
| `AbstractSimplePreAuthenticateUserProvider` 适配 | [7. Security](#7-security) |
| `MiddlewareInterface::before()` 签名变更 | [8. Middleware](#8-middleware) |
| `AbstractMiddleware` 移除 Silex 依赖 | [8. Middleware](#8-middleware) |
| 事件优先级常量变更 | [8. Middleware](#8-middleware) |
| 旧 Symfony 事件类移除 | [8. Middleware](#8-middleware) |
| `ResponseRendererInterface` 类型参数变更 | [9. Views](#9-views) |
| Twig 类名变更（`Twig_Environment` 等） | [10. Twig](#10-twig) |
| `SimpleTwigServiceProvider` 重写 | [10. Twig](#10-twig) |

**🟡 建议改**

| 变更 | 章节 |
|------|------|
| `twig/extensions` 移除替代方案 | [10. Twig](#10-twig) |
| 隐式 nullable 参数修复 | [13. PHP 语言适配](#13-php-语言适配) |
| 动态属性废弃 | [13. PHP 语言适配](#13-php-语言适配) |

**🟢 可选 / 无需操作**

| 变更 | 章节 |
|------|------|
| 路由迁移到 Symfony Routing 7.x | [6. Routing](#6-routing) |
| CORS Provider → EventSubscriber | [11. CORS](#11-cors) |
| Cookie Provider → EventSubscriber | [12. Cookie](#12-cookie) |
| `NullEntryPoint` 适配 | [7. Security](#7-security) |
| `phpunit/phpunit` `^5.2` → `^13.0` | [14. 附录](#14-附录) |
| `phpstan/phpstan` 新增 `^2.1` | [14. 附录](#14-附录) |

---


## 1. PHP Version

### 🔴 PHP 最低版本提升

**影响**：所有下游项目必须运行 PHP 8.5 或更高版本。

**Before**:

```json
{
    "require": {
        "php": ">=7.0.0"
    }
}
```

**After**:

```json
{
    "require": {
        "php": ">=8.5"
    }
}
```

**操作**：更新 `composer.json` 中的 `"php"` 约束为 `">=8.5"`，确保运行环境为 PHP 8.5+。

---

## 2. Dependencies

### 🔴 移除 `silex/silex`

**影响**：`silex/silex` `^2.3` 已被完全移除，框架替换为 Symfony MicroKernel。

**Before**:

```json
{
    "require": {
        "silex/silex": "^2.3"
    }
}
```

**After**:

```json
{
    "require": {
        "oasis/http": "^3.0"
    }
}
```

**操作**：从 `composer.json` 中移除 `silex/silex` 依赖。`oasis/http` v3.0 已内置所有必要的 Symfony 组件。

### 🔴 移除 `silex/providers`

**影响**：`silex/providers` `^2.3` 已被移除，所有 Silex service provider 已替换为 `oasis/http` 内置实现。

**Before**:

```json
{
    "require": {
        "silex/providers": "^2.3"
    }
}
```

**After**:

```json
{}
```

**操作**：从 `composer.json` 中移除 `silex/providers` 依赖。如果下游代码直接使用了 Silex provider 类，参见 [4. DI Container](#4-di-container) 章节进行迁移。

### 🔴 移除 `twig/extensions`

**影响**：`twig/extensions` `^1.3` 已被移除。Twig 3.x 已将常用扩展（如 `Twig_Extensions_Extension_Intl`）内置到核心中。

**Before**:

```json
{
    "require": {
        "twig/extensions": "^1.3"
    }
}
```

**After**:

```json
{}
```

**操作**：从 `composer.json` 中移除 `twig/extensions`。详见 [10. Twig](#10-twig) 章节了解替代方案。

### 🔴 Symfony 组件升级

**影响**：所有 Symfony 组件从 `^4.0` 升级到 `^7.2`。

**Before**:

```json
{
    "require": {
        "symfony/http-foundation": "^4.0",
        "symfony/http-kernel": "^4.0",
        "symfony/routing": "^4.0",
        "symfony/event-dispatcher": "^4.0",
        "symfony/security-core": "^4.0",
        "symfony/security-http": "^4.0"
    }
}
```

**After**:

```json
{
    "require": {
        "symfony/http-foundation": "^7.2",
        "symfony/http-kernel": "^7.2",
        "symfony/routing": "^7.2",
        "symfony/event-dispatcher": "^7.2",
        "symfony/security-core": "^7.2",
        "symfony/security-http": "^7.2"
    }
}
```

**操作**：`oasis/http` v3.0 已声明对 Symfony `^7.2` 的依赖，下游项目无需手动管理 Symfony 版本。如果下游项目直接依赖了 Symfony 组件，需将版本约束更新为 `^7.2`。

### 🔴 `twig/twig` 升级

**影响**：`twig/twig` 从 `^1.24` 升级到 `^3.0`，类名和 API 发生重大变化。

**Before**:

```json
{
    "require": {
        "twig/twig": "^1.24"
    }
}
```

**After**:

```json
{
    "require": {
        "twig/twig": "^3.0"
    }
}
```

**操作**：更新 `twig/twig` 版本约束。类名变更详见 [10. Twig](#10-twig) 章节。

### 🔴 `guzzlehttp/guzzle` 升级

**影响**：`guzzlehttp/guzzle` 从 `^6.3` 升级到 `^7.0`。Guzzle 7 移除了部分 6.x 选项（如 `'exceptions' => false`），默认行为有变化。

**Before**:

```json
{
    "require": {
        "guzzlehttp/guzzle": "^6.3"
    }
}
```

**After**:

```json
{
    "require": {
        "guzzlehttp/guzzle": "^7.0"
    }
}
```

**操作**：更新 `guzzlehttp/guzzle` 版本约束。检查代码中是否使用了 Guzzle 6.x 特有选项（如 `'exceptions' => false`），改用 Guzzle 7.x 的 `'http_errors' => false`。

### 🔴 `oasis/logging` 和 `oasis/utils` 升级

**影响**：`oasis/logging` 从 `^1.1` 升级到 `^3.0`，`oasis/utils` 从 `^1.6` 升级到 `^3.0`。

**Before**:

```json
{
    "require": {
        "oasis/logging": "^1.1",
        "oasis/utils": "^1.6"
    }
}
```

**After**:

```json
{
    "require": {
        "oasis/logging": "^3.0",
        "oasis/utils": "^3.0"
    }
}
```

**操作**：更新版本约束。如果下游项目直接依赖了这两个包，需同步升级。

---


## 3. Kernel API

### 🔴 `SilexKernel` → `MicroKernel` 类名变更

**影响**：核心入口类从 `SilexKernel` 重命名为 `MicroKernel`，命名空间不变（`Oasis\Mlib\Http`）。

**Before**:

```php
use Oasis\Mlib\Http\SilexKernel;

$kernel = new SilexKernel($config);
```

**After**:

```php
use Oasis\Mlib\Http\MicroKernel;

$kernel = new MicroKernel($httpConfig, $isDebug);
```

**操作**：将所有 `SilexKernel` 引用替换为 `MicroKernel`，更新 `use` 语句。

### 🔴 `MicroKernel` 构造函数签名变更

**影响**：构造函数签名从单参数变为双参数，新增 `$isDebug` 参数。

**Before**:

```php
$kernel = new SilexKernel($config);
// 或
$kernel = new SilexKernel($config, $isDebug);
```

**After**:

```php
$kernel = new MicroKernel(array $httpConfig, bool $isDebug);
```

**操作**：确保构造函数调用传入两个参数：`$httpConfig`（Bootstrap Config 关联数组）和 `$isDebug`（布尔值）。

### 🔴 `SilexKernel::__set()` 魔术方法移除

**影响**：`SilexKernel` 上的 `__set()` 魔术方法已移除。此前通过 `$kernel->someProperty = $value` 动态设置属性的代码将不再工作。

**Before**:

```php
$kernel->customService = new MyService();
```

**After**:

```php
// 使用 Bootstrap Config 的 providers 机制注册服务
// 或通过 Symfony DI CompilerPass 注册
```

**操作**：移除所有对 kernel 的动态属性赋值。改用 Bootstrap Config 的 `providers` key 或 Symfony DI 机制注册服务（参见 [4. DI Container](#4-di-container)）。

### 公共 API 方法列表

以下 `MicroKernel` 公共方法保持可用（签名可能有类型声明更新）：

| 方法 | 说明 |
|------|------|
| `run(?Request $request = null): void` | 启动 HTTP 处理 |
| `handle(Request $request, int $type, bool $catch): Response` | 处理单个请求 |
| `isGranted(mixed $attributes, mixed $object = null): bool` | 授权检查 |
| `getToken(): ?TokenInterface` | 获取当前认证 token |
| `getUser(): ?UserInterface` | 获取当前认证用户 |
| `getTwig(): ?TwigEnvironment` | 获取 Twig 环境 |
| `getParameter(string $key, mixed $default = null): mixed` | 获取参数 |
| `addExtraParameters(array $extras): void` | 添加额外参数 |
| `addControllerInjectedArg(object $object): void` | 添加控制器注入参数 |
| `addMiddleware(MiddlewareInterface $middleware): void` | 添加中间件 |
| `getCacheDirectories(): array` | 获取缓存目录列表 |

---

## 4. DI Container

### 🔴 Pimple DI 容器移除

**影响**：Pimple 容器已被完全移除，替换为 Symfony DependencyInjection 组件。所有通过 Pimple 注册和获取服务的代码必须迁移。

**Before**:

```php
use Pimple\Container;

$app = new Container();
$app['my.service'] = function () {
    return new MyService();
};
```

**After**:

```php
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition('my.service', new Definition(MyService::class));
    }
}
```

**操作**：将所有 Pimple 容器操作迁移到 Symfony DI。使用 `CompilerPassInterface` 或 `ExtensionInterface` 注册服务。

### 🔴 `$app['xxx']` 访问模式移除

**影响**：Pimple 风格的数组式服务访问 `$app['xxx']` 已不可用。

**Before**:

```php
$logger = $app['logger'];
$twig = $app['twig'];
$db = $app['db'];
```

**After**:

```php
// 通过 MicroKernel 的公共 API 获取内置服务
$twig = $kernel->getTwig();

// 通过 Symfony DI 容器获取自定义服务
$myService = $container->get('my.service');
```

**操作**：移除所有 `$app['xxx']` 风格的服务访问。内置服务通过 `MicroKernel` 公共方法获取，自定义服务通过 Symfony DI 容器获取。

### 🔴 `Pimple\ServiceProviderInterface` → `CompilerPassInterface`/`ExtensionInterface`

**影响**：用户自定义的 service provider 必须从 Pimple 接口迁移到 Symfony DI 接口。

**Before**:

```php
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class MyProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app['my.service'] = function () {
            return new MyService();
        };
    }
}

// 注册
$config = [
    'providers' => [new MyProvider()],
];
```

**After**:

```php
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition('my.service', new Definition(MyService::class));
    }
}

// 注册
$config = [
    'providers' => [new MyCompilerPass()],
];
```

**操作**：将所有 `Pimple\ServiceProviderInterface` 实现替换为 `CompilerPassInterface` 或 `ExtensionInterface`。所有 Service Provider 迁移到新框架模式。Bootstrap Config 的 `providers` key 现在接受这两种类型的实例。

---


## 5. Bootstrap Config

### 🔴 `providers` key 语义变更

**影响**：`providers` key 接受的类型从 `Pimple\ServiceProviderInterface` 实例变更为 `CompilerPassInterface` / `ExtensionInterface` 实例。

**Before**:

```php
use Pimple\ServiceProviderInterface;

$config = [
    'providers' => [
        new MyPimpleProvider(),  // implements Pimple\ServiceProviderInterface
    ],
];
```

**After**:

```php
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

$config = [
    'providers' => [
        new MyCompilerPass(),   // implements CompilerPassInterface
        new MyExtension(),      // implements ExtensionInterface
    ],
];
```

**操作**：将 `providers` 数组中的所有 `Pimple\ServiceProviderInterface` 实例替换为 `CompilerPassInterface` 或 `ExtensionInterface` 实例。参见 [4. DI Container](#4-di-container) 了解迁移方法。

### Bootstrap_Config Key 参考表

| Key | 类型 | 默认值 | 是否变更 | 说明 |
|-----|------|--------|---------|------|
| `routing` | array | — | ❌ 不变 | 路由配置，行为保持不变 |
| `security` | array | — | ❌ 不变 | 安全配置结构不变，内部 policy 接口已重写 |
| `cors` | array | — | ❌ 不变 | CORS 策略配置，行为保持不变 |
| `twig` | array | — | ❌ 不变 | Twig 模板配置，行为保持不变 |
| `twig.strict_variables` | bool | `true` | ❌ 不变 | 启用 Twig 严格变量模式 |
| `twig.auto_reload` | bool/null | `null` | ❌ 不变 | 模板自动重载，`null` 根据 debug 模式自动判定 |
| `middlewares` | array | `[]` | ❌ 不变 | 元素类型 `MiddlewareInterface` 签名已变更，参见 [8. Middleware](#8-middleware) |
| `providers` | array | `[]` | ✅ **语义变更** | 从 `Pimple\ServiceProviderInterface` 改为 `CompilerPassInterface`/`ExtensionInterface` |
| `view_handlers` | array | `[]` | ❌ 不变 | callable 数组 |
| `error_handlers` | array | `[]` | ❌ 不变 | callable 数组 |
| `injected_args` | array | `[]` | ❌ 不变 | 控制器参数自动注入候选对象 |
| `trusted_proxies` | array | `[]` | ❌ 不变 | 可信代理 IP 数组 |
| `trusted_header_set` | int | — | ❌ 不变 | 可信 header 集合 |
| `behind_elb` | bool | `false` | ❌ 不变 | 是否在 AWS ELB 后 |
| `trust_cloudfront_ips` | bool | `false` | ❌ 不变 | 是否信任 CloudFront IP |
| `cache_dir` | string/null | `null` | ❌ 不变 | 缓存目录路径 |

---

## 6. Routing

### 🟢 路由迁移到 Symfony Routing 7.x

**影响**：内部路由实现已迁移到 Symfony Routing 7.x，但 Bootstrap_Config 的 `routing` key 行为保持不变。

**Before**:

```php
$config = [
    'routing' => [
        'path' => '/path/to/routes.yml',
        'namespace' => 'App\\Controller',
    ],
];
```

**After**:

```php
// 配置方式完全相同，无需修改
$config = [
    'routing' => [
        'path' => '/path/to/routes.yml',
        'namespace' => 'App\\Controller',
    ],
];
```

**操作**：无需下游操作。路由注册机制迁移到 Symfony Routing 7.x，但路由配置格式和行为保持不变。

---


## 7. Security

### 🔴 `AuthenticationPolicyInterface` 重写

**影响**：`AuthenticationPolicyInterface` 的方法签名已全面重写，适配 Symfony 7.x Security 组件。`SimpleSecurityProvider` 的 firewall / access rule 注册机制已适配新架构。

**Before**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface;
use Oasis\Mlib\Http\SilexKernel;

interface AuthenticationPolicyInterface
{
    public function getAuthenticationType(): string;
    public function getAuthenticationProvider(SilexKernel $app, string $name, array $options);
    public function getAuthenticationListener(SilexKernel $app, string $name, array $options);
    public function getEntryPoint(SilexKernel $app, string $name, array $options);
}
```

**After**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface;
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

interface AuthenticationPolicyInterface
{
    public function getAuthenticationType(): string;
    public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface;
    public function getAuthenticatorConfig(): array;
    public function getEntryPoint(MicroKernel $kernel, string $name, array $options): AuthenticationEntryPointInterface;
}
```

**操作**：重写所有 `AuthenticationPolicyInterface` 实现。旧的 `getAuthenticationProvider()` + `getAuthenticationListener()` 合并为 `getAuthenticator()`，返回 Symfony 7.x `AuthenticatorInterface` 实例。新增 `getAuthenticatorConfig()` 方法。

### 🔴 `FirewallInterface` 重写

**影响**：`FirewallInterface` 方法签名更新，使用 PHP 8.x union type 和 Symfony 7.x 类型。

**Before**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\FirewallInterface;

interface FirewallInterface
{
    public function getPattern();
    public function isStateless();
    public function getPolicies();
    public function getUserProvider();
}
```

**After**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\FirewallInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

interface FirewallInterface
{
    public function getPattern(): string|RequestMatcherInterface;
    public function isStateless(): bool;
    public function getPolicies(): array;
    public function getUserProvider(): array|UserProviderInterface;
    public function getOtherSettings(): array;
}
```

**操作**：更新所有 `FirewallInterface` 实现，添加返回类型声明，新增 `getOtherSettings()` 方法（可返回空数组）。

### 🔴 `AccessRuleInterface` 重写

**影响**：`AccessRuleInterface` 方法签名更新，使用 PHP 8.x union type。

**Before**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AccessRuleInterface;

interface AccessRuleInterface
{
    public function getPattern();
    public function getRequiredRoles();
    public function getRequiredChannel();
}
```

**After**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AccessRuleInterface;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

interface AccessRuleInterface
{
    public function getPattern(): string|RequestMatcherInterface;
    public function getRequiredRoles(): string|array;
    public function getRequiredChannel(): ?string;
}
```

**操作**：更新所有 `AccessRuleInterface` 实现，添加返回类型声明。

### 🔴 `AbstractSimplePreAuthenticator` → `AbstractPreAuthenticator`

**影响**：`AbstractSimplePreAuthenticator` 已废弃（调用其方法会抛出 `LogicException`），替换为 `AbstractPreAuthenticator`。新类采用 template method pattern，实现 Symfony 7.x `AuthenticatorInterface`。

**Before**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;

class MyAuthenticator extends AbstractSimplePreAuthenticator
{
    public function getCredentialsFromRequest(Request $request): mixed
    {
        return $request->headers->get('X-Api-Key');
    }

    // 还需实现 createToken(), authenticateToken(), supportsToken()
}
```

**After**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractPreAuthenticator;
use Symfony\Component\Security\Core\User\UserInterface;

class MyAuthenticator extends AbstractPreAuthenticator
{
    protected function getCredentialsFromRequest(Request $request): mixed
    {
        return $request->headers->get('X-Api-Key');
    }

    protected function authenticateAndGetUser(mixed $credentials): UserInterface
    {
        // 根据凭证查找并返回用户
        return $this->userProvider->findByApiKey($credentials);
    }
}
```

**操作**：将 `extends AbstractSimplePreAuthenticator` 改为 `extends AbstractPreAuthenticator`。旧的三步流程（`createToken()` → `authenticateToken()` → `supportsToken()`）简化为两步：`getCredentialsFromRequest()` + `authenticateAndGetUser()`。`supports()`、`authenticate()`、`createToken()` 等 `AuthenticatorInterface` 方法由基类自动实现。

### 🔴 `AbstractSimplePreAuthenticateUserProvider` 适配

**影响**：`AbstractSimplePreAuthenticateUserProvider` 已适配 Symfony 7.x `UserProviderInterface`，方法签名更新。

**Before**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;

class MyUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    public function loadUserByUsername($username) { /* ... */ }
}
```

**After**:

```php
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;

class MyUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // 按标识符加载用户
    }
}
```

**操作**：将 `loadUserByUsername()` 重命名为 `loadUserByIdentifier()`，添加参数和返回类型声明。

### 完整的自定义 Pre-Authentication Policy 示例

**Before**（v2.x）:

```php
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;

class ApiKeyAuthenticator extends AbstractSimplePreAuthenticator
{
    public function getCredentialsFromRequest(Request $request): mixed
    {
        return $request->headers->get('X-Api-Key');
    }

    public function createToken(Request $request, string $providerKey): TokenInterface
    {
        $credentials = $this->getCredentialsFromRequest($request);
        return new PreAuthenticatedToken('anon.', $credentials, $providerKey);
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, string $providerKey): TokenInterface
    {
        $apiKey = $token->getCredentials();
        $user = $userProvider->loadUserByUsername($apiKey);
        return new PreAuthenticatedToken($user, $apiKey, $providerKey, $user->getRoles());
    }

    public function supportsToken(TokenInterface $token, string $providerKey): bool
    {
        return $token instanceof PreAuthenticatedToken && $token->getProviderKey() === $providerKey;
    }
}

class ApiKeyPolicy implements AuthenticationPolicyInterface
{
    public function getAuthenticationType(): string { return 'pre_auth'; }
    public function getAuthenticationProvider(SilexKernel $app, string $name, array $options) { /* ... */ }
    public function getAuthenticationListener(SilexKernel $app, string $name, array $options) { /* ... */ }
    public function getEntryPoint(SilexKernel $app, string $name, array $options) { /* ... */ }
}
```

**After**（v3.0）:

```php
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractPreAuthenticator;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

class ApiKeyAuthenticator extends AbstractPreAuthenticator
{
    public function __construct(private readonly ApiKeyUserProvider $userProvider) {}

    protected function getCredentialsFromRequest(Request $request): mixed
    {
        return $request->headers->get('X-Api-Key');
    }

    protected function authenticateAndGetUser(mixed $credentials): UserInterface
    {
        return $this->userProvider->findByApiKey($credentials);
    }
}

class ApiKeyPolicy extends AbstractSimplePreAuthenticationPolicy
{
    public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface
    {
        return new ApiKeyAuthenticator(new ApiKeyUserProvider());
    }
}
```

### 🟢 `NullEntryPoint` 适配

**影响**：`NullEntryPoint` 已适配 Symfony 7.x `AuthenticationEntryPointInterface`，内部实现变更。

**Before**:

```php
// NullEntryPoint 内部使用 Silex 异常类
```

**After**:

```php
// NullEntryPoint 现在抛出 AccessDeniedHttpException
// API 保持不变：start(Request, ?AuthenticationException): Response
```

**操作**：无需下游操作。`NullEntryPoint` 的公共 API 保持不变，仅内部实现适配了新的 Security 架构。

---


## 8. Middleware

### 🔴 `MiddlewareInterface::before()` 签名变更

**影响**：中间件机制迁移到 Symfony EventDispatcher，`before()` 方法的第二个参数从 `Silex\Application` 变更为 `MicroKernel`。

**Before**:

```php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MyMiddleware implements MiddlewareInterface
{
    public function before(Request $request, Application $app): ?Response
    {
        // ...
    }
}
```

**After**:

```php
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MyMiddleware implements MiddlewareInterface
{
    public function before(Request $request, MicroKernel $kernel): Response|null
    {
        // ...
    }
}
```

**操作**：更新所有 `MiddlewareInterface` 实现的 `before()` 方法签名，将 `Silex\Application $app` 替换为 `MicroKernel $kernel`。

### 🔴 `AbstractMiddleware` 移除 Silex 依赖

**影响**：`AbstractMiddleware` 不再依赖 `Silex\Application`，改为依赖 `MicroKernel`。

**Before**:

```php
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Silex\Application;

class MyMiddleware extends AbstractMiddleware
{
    public function before(Request $request, Application $app): ?Response
    {
        // 使用 $app 访问服务
    }
}
```

**After**:

```php
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Oasis\Mlib\Http\MicroKernel;

class MyMiddleware extends AbstractMiddleware
{
    public function before(Request $request, MicroKernel $kernel): Response|null
    {
        // 使用 $kernel 的公共 API 访问服务
    }
}
```

**操作**：更新所有 `AbstractMiddleware` 子类，将 `before()` 方法中的 `Silex\Application` 参数替换为 `MicroKernel`。

### 🔴 事件优先级常量变更

**影响**：事件优先级常量从 `Application::EARLY_EVENT` / `Application::LATE_EVENT` 变更为 `MicroKernel` 上的常量。

**Before**:

```php
use Silex\Application;

$priority = Application::EARLY_EVENT;
$priority = Application::LATE_EVENT;
```

**After**:

```php
use Oasis\Mlib\Http\MicroKernel;

// Before middleware 优先级
$priority = MicroKernel::BEFORE_PRIORITY_EARLIEST;  // 512
$priority = MicroKernel::BEFORE_PRIORITY_LATEST;     // -512

// After middleware 优先级
$priority = MicroKernel::AFTER_PRIORITY_EARLIEST;    // 512
$priority = MicroKernel::AFTER_PRIORITY_LATEST;      // -512

// 内部优先级（参考）
// MicroKernel::BEFORE_PRIORITY_ROUTING        = 32
// MicroKernel::BEFORE_PRIORITY_CORS_PREFLIGHT = 20
// MicroKernel::BEFORE_PRIORITY_FIREWALL       = 8
```

**操作**：将所有 `Application::EARLY_EVENT` / `Application::LATE_EVENT` 引用替换为 `MicroKernel` 上的对应常量。

### 🔴 旧 Symfony 事件类移除

**影响**：Symfony 4.x 的事件类已在 Symfony 7.x 中移除，替换为新的事件类。

**Before**:

```php
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

// MASTER_REQUEST 常量
$type = HttpKernelInterface::MASTER_REQUEST;
```

**After**:

```php
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

// MASTER_REQUEST 已重命名为 MAIN_REQUEST
$type = HttpKernelInterface::MAIN_REQUEST;
```

**操作**：替换所有旧事件类引用：`FilterResponseEvent` → `ResponseEvent`，`GetResponseEvent` → `RequestEvent`，`GetResponseForExceptionEvent` → `ExceptionEvent`，`MASTER_REQUEST` → `MAIN_REQUEST`。

---

## 9. Views

### 🔴 View Handler / `ResponseRendererInterface` 类型参数变更

**影响**：`ResponseRendererInterface` 的方法签名中，`SilexKernel` 参数替换为 `MicroKernel`。

**Before**:

```php
use Oasis\Mlib\Http\Views\ResponseRendererInterface;
use Oasis\Mlib\Http\SilexKernel;

class MyRenderer implements ResponseRendererInterface
{
    public function renderOnSuccess(mixed $result, SilexKernel $kernel): Response
    {
        // ...
    }

    public function renderOnException(WrappedExceptionInfo $exceptionInfo, SilexKernel $kernel): Response
    {
        // ...
    }
}
```

**After**:

```php
use Oasis\Mlib\Http\Views\ResponseRendererInterface;
use Oasis\Mlib\Http\MicroKernel;

class MyRenderer implements ResponseRendererInterface
{
    public function renderOnSuccess(mixed $result, MicroKernel $kernel): Response
    {
        // ...
    }

    public function renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response
    {
        // ...
    }
}
```

**操作**：更新所有 `ResponseRendererInterface` 实现，将方法签名中的 `SilexKernel` 替换为 `MicroKernel`。

---

## 10. Twig

### 🔴 Twig 类名变更

**影响**：Twig 1.x 的下划线风格类名在 Twig 3.x 中已移除，替换为命名空间风格。

**Before**:

```php
$twig = new \Twig_Environment($loader, $options);
$function = new \Twig_SimpleFunction('name', $callable);
try {
    // ...
} catch (\Twig_Error_Loader $e) {
    // ...
}
```

**After**:

```php
use Twig\Environment;
use Twig\TwigFunction;
use Twig\Error\LoaderError;

$twig = new Environment($loader, $options);
$function = new TwigFunction('name', $callable);
try {
    // ...
} catch (LoaderError $e) {
    // ...
}
```

**操作**：替换所有 Twig 1.x 类名引用：`Twig_Environment` → `\Twig\Environment`，`Twig_SimpleFunction` → `\Twig\TwigFunction`，`Twig_Error_Loader` → `\Twig\Error\LoaderError`。

### 🟡 `twig/extensions` 移除替代方案

**影响**：`twig/extensions` 包已移除。Twig 3.x 已将常用扩展功能内置到核心中。

**Before**:

```php
use Twig_Extensions_Extension_Intl;

$twig->addExtension(new Twig_Extensions_Extension_Intl());
```

**After**:

```php
// Twig 3.x 内置了 intl 支持，通过 IntlExtension
use Twig\Extra\Intl\IntlExtension;

$twig->addExtension(new IntlExtension());
// 需要额外安装: composer require twig/intl-extra
```

**操作**：检查代码中是否使用了 `twig/extensions` 提供的扩展。大部分功能已内置到 Twig 3.x 核心或通过 `twig/extra-bundle` 提供。按需安装对应的 `twig/*-extra` 包。

### 🔴 `SimpleTwigServiceProvider` 重写

**影响**：`SimpleTwigServiceProvider` 已完全重写，不再继承 Silex 的 `TwigServiceProvider`。新实现直接创建 `\Twig\Environment` 实例。

**Before**:

```php
// 旧版通过 Silex TwigServiceProvider 注册
// Twig 通过 $app['twig'] 访问
$twig = $app['twig'];
```

**After**:

```php
// 新版通过 MicroKernel 公共 API 访问
$twig = $kernel->getTwig();
```

**操作**：将所有 `$app['twig']` 访问替换为 `$kernel->getTwig()`。`SimpleTwigServiceProvider` 的注册由 `MicroKernel` 内部自动完成，下游无需手动注册。

### `twig.strict_variables` 和 `twig.auto_reload` 行为说明

Bootstrap_Config 中的 `twig.strict_variables`（默认 `true`）和 `twig.auto_reload`（默认 `null`，根据 debug 模式自动判定）行为保持不变。无需下游操作。

---


## 11. CORS

### 🟢 CORS Provider → EventSubscriber

**影响**：CORS 处理从 Silex Service Provider 重写为 Symfony EventSubscriber。`CrossOriginResourceSharingStrategy` 的公共 API 保持不变。

**Before**:

```php
// CORS 通过 Silex provider 注册
$config = [
    'cors' => [
        ['pattern' => '/api/.*', 'origins' => ['https://example.com']],
    ],
];
```

**After**:

```php
// 配置方式完全相同，无需修改
$config = [
    'cors' => [
        ['pattern' => '/api/.*', 'origins' => ['https://example.com']],
    ],
];
```

**操作**：无需下游操作。`CrossOriginResourceSharingStrategy` API 保持不变，Bootstrap_Config 的 `cors` key 行为保持不变。

---

## 12. Cookie

### 🟢 Cookie Provider → EventSubscriber

**影响**：Cookie 处理从 Silex Service Provider 重写为 Symfony EventSubscriber。`ResponseCookieContainer` 的公共 API 保持不变。

**Before**:

```php
// Cookie 通过 Silex provider 注册
$cookieContainer = $app['cookie.container'];
$cookieContainer->addCookie(new Cookie('name', 'value'));
```

**After**:

```php
// ResponseCookieContainer API 保持不变
$cookieContainer->addCookie(new Cookie('name', 'value'));
```

**操作**：无需下游操作。`ResponseCookieContainer` 的 `addCookie()` 和 `getCookies()` 方法保持不变。注意获取 `ResponseCookieContainer` 实例的方式可能需要更新（不再通过 `$app['cookie.container']`）。

---

## 13. PHP 语言适配

### 🟡 隐式 nullable 参数修复

**影响**：PHP 8.4+ 废弃了隐式 nullable 参数（`Type $param = null`），`oasis/http` 已全面修复为显式 nullable（`?Type $param = null`）。下游项目应检查自身代码。

**Before**:

```php
function myFunction(MyClass $param = null): void
{
    // PHP 8.4+ 会产生 deprecation notice
}
```

**After**:

```php
function myFunction(?MyClass $param = null): void
{
    // 显式 nullable，无 deprecation
}
```

**操作**：检查下游代码中所有 `Type $param = null` 形式的参数声明，改为 `?Type $param = null`。

### 🟡 动态属性废弃

**影响**：PHP 8.2+ 废弃了动态属性（未声明的属性赋值），`oasis/http` 已修复动态属性使用。下游项目应检查自身代码。

**Before**:

```php
class MyClass
{
    // 未声明 $dynamicProp
}

$obj = new MyClass();
$obj->dynamicProp = 'value'; // PHP 8.2+ deprecation, PHP 9.0 将报错
```

**After**:

```php
class MyClass
{
    public string $dynamicProp = '';
    // 或使用 #[\AllowDynamicProperties] 属性（不推荐）
}
```

**操作**：检查下游代码中是否存在动态属性赋值，为所有使用的属性添加显式声明。

### 下游 PHP 8.5 兼容性检查建议

建议下游项目运行自身的 PHP 8.5 兼容性检查：

1. 使用 PHPStan 进行静态分析：`composer require --dev phpstan/phpstan:^2.1`
2. 在 PHP 8.5 环境下运行完整测试套件
3. 检查 `error_reporting(E_ALL)` 下是否有 deprecation notice

---

## 14. 附录

### 完整 API 变更速查表

| 旧 API | 新 API | Severity |
|--------|--------|----------|
| `SilexKernel` | `MicroKernel` | 🔴 |
| `SilexKernel::__set()` | 移除 | 🔴 |
| `Pimple\Container` | Symfony DI `ContainerBuilder` | 🔴 |
| `$app['xxx']` | `$kernel->getXxx()` / Symfony DI | 🔴 |
| `Pimple\ServiceProviderInterface` | `CompilerPassInterface` / `ExtensionInterface` | 🔴 |
| `Silex\Api\BootableProviderInterface` | 移除 | 🔴 |
| `AuthenticationPolicyInterface` (旧签名) | `AuthenticationPolicyInterface` (新签名) | 🔴 |
| `FirewallInterface` (旧签名) | `FirewallInterface` (新签名) | 🔴 |
| `AccessRuleInterface` (旧签名) | `AccessRuleInterface` (新签名) | 🔴 |
| `AbstractSimplePreAuthenticator` | `AbstractPreAuthenticator` | 🔴 |
| `AbstractSimplePreAuthenticateUserProvider` (旧签名) | `AbstractSimplePreAuthenticateUserProvider` (新签名) | 🔴 |
| `MiddlewareInterface::before(Request, Application)` | `MiddlewareInterface::before(Request, MicroKernel)` | 🔴 |
| `Application::EARLY_EVENT` / `LATE_EVENT` | `MicroKernel::BEFORE_PRIORITY_*` / `AFTER_PRIORITY_*` | 🔴 |
| `FilterResponseEvent` | `ResponseEvent` | 🔴 |
| `GetResponseEvent` | `RequestEvent` | 🔴 |
| `GetResponseForExceptionEvent` | `ExceptionEvent` | 🔴 |
| `HttpKernelInterface::MASTER_REQUEST` | `HttpKernelInterface::MAIN_REQUEST` | 🔴 |
| `ResponseRendererInterface` (旧 `SilexKernel` 参数) | `ResponseRendererInterface` (新 `MicroKernel` 参数) | 🔴 |
| `Twig_Environment` | `\Twig\Environment` | 🔴 |
| `Twig_SimpleFunction` | `\Twig\TwigFunction` | 🔴 |
| `Twig_Error_Loader` | `\Twig\Error\LoaderError` | 🔴 |
| `$app['twig']` | `$kernel->getTwig()` | 🔴 |
| `silex/silex` | 移除 | 🔴 |
| `silex/providers` | 移除 | 🔴 |
| `twig/extensions` | 移除（Twig 3.x 内置替代） | 🟡 |
| `Type $param = null` | `?Type $param = null` | 🟡 |
| 动态属性 | 显式属性声明 | 🟡 |
| Routing 内部实现 | Symfony Routing 7.x | 🟢 |
| CORS Provider | EventSubscriber | 🟢 |
| Cookie Provider | EventSubscriber | 🟢 |
| `NullEntryPoint` 内部实现 | 适配新 Security 架构 | 🟢 |

### 开发依赖参考

以下为开发依赖（`require-dev`）的变更，不影响下游项目的运行时代码，仅供参考：

### 🟢 `phpunit/phpunit` 升级

**影响**：`phpunit/phpunit` 从 `^5.2` 升级到 `^13.0`。仅影响测试代码。

**Before**:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^5.2"
    }
}
```

**After**:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^13.0"
    }
}
```

**操作**：如果下游项目的测试代码依赖了 PHPUnit 5.x API（如 `getMock()`、`setExpectedException()`），需适配 PHPUnit 13.x API。

### 🟢 `phpstan/phpstan` 新增

**影响**：新增 `phpstan/phpstan` `^2.1` 作为开发依赖，用于静态分析。

**Before**:

```json
{
    "require-dev": {}
}
```

**After**:

```json
{
    "require-dev": {
        "phpstan/phpstan": "^2.1"
    }
}
```

**操作**：可选。建议下游项目也引入 PHPStan 进行静态分析，提升代码质量。

### 内部验证说明

`oasis/http` v3.0 已完成以下内部验证：

- 引入 PHPStan level 8 静态分析，零错误
- 全量测试通过：510 tests, 16642 assertions
- 零 deprecation notice
- `PROJECT.md`、`README.md`、`docs/state/`、`docs/manual/` 全面更新
