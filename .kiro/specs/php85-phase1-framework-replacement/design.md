# Design Document

> PHP 8.5 Upgrade — Phase 1: Framework Replacement — `.kiro/specs/php85-phase1-framework-replacement/`

---

## Overview

本 design 描述如何将 `oasis/http` 从 Silex 2.x + Pimple + Symfony 4.x 迁移到 Symfony MicroKernel + Symfony DI + Symfony 7.x + Twig 3.x。核心思路是：

1. **MicroKernel** 继承 Symfony `Kernel` + `MicroKernelTrait`，在 `configureContainer()` 中将 Bootstrap_Config 转化为 Symfony DI service 定义
2. **EventSubscriber 替代 Silex middleware API**：before/after/view/error/CORS/cookie 全部迁移到 `EventSubscriberInterface`
3. **Routing 保持独立**：`CacheableRouter` 等路由类仅移除 Silex/Pimple 依赖，核心逻辑不变
4. **Security 最小适配**：移除 Silex `SecurityServiceProvider` 继承，保留接口定义，authenticator 重写留给 Phase 3
5. **Twig 完整升级**：`twig/twig` ^3.0 + `symfony/twig-bridge` ^7.2，移除 `twig/extensions`

---

## Impact Analysis

基于知识图谱分析（`graphify-out/GRAPH_REPORT.md`），`SilexKernel` 是系统的核心 cross-community bridge（betweenness 0.096，19 edges），连接 Middleware & Controllers、CORS、Security、Error Handling 等多个社区。替换 `SilexKernel` 的影响面覆盖几乎所有模块。

**直接影响的源文件**（需要修改或重写）：

| 文件 | 影响类型 | 关联 Requirement |
|------|----------|-----------------|
| `src/SilexKernel.php` | 删除，替换为 `src/MicroKernel.php` | R2, R3, R16 |
| `src/Middlewares/MiddlewareInterface.php` | 修改签名（移除 Silex 依赖） | R4 |
| `src/Middlewares/AbstractMiddleware.php` | 修改（移除 Silex 依赖） | R4 |
| `src/ServiceProviders/Routing/CacheableRouterProvider.php` | 重写为 DI 注册 | R3, R5 |
| `src/ServiceProviders/Routing/CacheableRouter.php` | 修改（SilexKernel → MicroKernel） | R5 |
| `src/ServiceProviders/Cors/CrossOriginResourceSharingProvider.php` | 重写为 EventSubscriber | R6 |
| `src/ServiceProviders/Cookie/SimpleCookieProvider.php` | 重写为 EventSubscriber | R9 |
| `src/ServiceProviders/Twig/SimpleTwigServiceProvider.php` | 重写为 DI 注册 + Twig 3.x | R10 |
| `src/ServiceProviders/Security/SimpleSecurityProvider.php` | 重写（移除 Silex 继承） | R11 |
| `src/ServiceProviders/Security/AuthenticationPolicyInterface.php` | 修改签名（Pimple → MicroKernel） | R11 |
| `src/Views/FallbackViewHandler.php` | 修改（SilexKernel → MicroKernel） | R7 |
| `src/Views/ResponseRendererInterface.php` | 修改（SilexKernel → MicroKernel） | R7 |
| `src/Views/DefaultHtmlRenderer.php` | 修改（SilexKernel → MicroKernel + Twig 3.x） | R7, R10 |
| `src/Views/JsonApiRenderer.php` | 修改（SilexKernel → MicroKernel） | R7 |
| `src/ExtendedExceptionListnerWrapper.php` | 重写（移除 Silex 依赖） | R8 |
| `src/ExtendedArgumentValueResolver.php` | 适配 Symfony 7.x | R12 |
| `composer.json` | 依赖替换 | R1 |

**不受影响的源文件**：

| 文件 | 原因 |
|------|------|
| `src/Configuration/*.php` | 仅依赖 Symfony Config 组件，无 Silex 依赖 |
| `src/ErrorHandlers/ExceptionWrapper.php` | 无 Silex 依赖 |
| `src/ErrorHandlers/JsonErrorHandler.php` | 无 Silex 依赖 |
| `src/ErrorHandlers/WrappedExceptionInfo.php` | 无 Silex 依赖 |
| `src/Exceptions/UniquenessViolationHttpException.php` | 无 Silex 依赖 |
| `src/ChainedParameterBagDataProvider.php` | 无 Silex 依赖 |
| `src/Views/AbstractSmartViewHandler.php` | 无 Silex 依赖 |
| `src/Views/JsonViewHandler.php` | 无 Silex 依赖 |
| `src/Views/PrefilightResponse.php` | 无 Silex 依赖 |
| `src/Views/RouteBasedResponseRendererResolver.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Cors/CrossOriginResourceSharingStrategy.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Cookie/ResponseCookieContainer.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Security/NullEntryPoint.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Security/AccessRuleInterface.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Security/FirewallInterface.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Security/SimpleAccessRule.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Security/SimpleFirewall.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Routing/GroupUrlMatcher.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Routing/GroupUrlGenerator.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Routing/CacheableRouterUrlMatcherWrapper.php` | 无 Silex 依赖 |
| `src/ServiceProviders/Routing/InheritableRouteCollection.php` | 无 Silex 依赖 |

**测试文件影响**：几乎所有测试文件和 bootstrap 文件都引用 `SilexKernel`，需要全面更新。

**遗漏的受影响源文件**：

| 文件 | 影响类型 | 关联 Requirement |
|------|----------|-----------------|
| `src/ServiceProviders/Security/AbstractSimplePreAuthenticationPolicy.php` | 修改签名（Pimple `Container` → `MicroKernel`） | R11 |

**State 文档影响**：

- `docs/state/architecture.md`：核心类 section 需更新（`SilexKernel` → `MicroKernel`，继承关系从 `Silex\Application` 改为 Symfony `Kernel` + `MicroKernelTrait`）；模块结构 section 需更新（`SilexKernel.php` → `MicroKernel.php`，新增 `EventSubscribers/` 目录）；Bootstrap Config 结构 section 中 `providers` key 的说明需更新（`ServiceProviderInterface` → `CompilerPassInterface` / `ExtensionInterface`）；请求处理流程 section 的实现细节描述需同步更新

**配置项变更**：

- Bootstrap_Config `providers` key 的语义变更：从接受 `Pimple\ServiceProviderInterface` 实例改为接受 Symfony `CompilerPassInterface` / `ExtensionInterface` 实例（breaking change，下游消费者需适配）
- 其他 Bootstrap_Config key 的语义和结构不变

**数据模型变更**：不涉及

**外部系统交互变化**：不涉及（CloudFront IP 获取逻辑保持不变，仍使用 Guzzle 6.x）

---

## Technical Design

### D1: composer.json 依赖替换（R1）

**移除**：
- `silex/silex` ^2.3
- `silex/providers` ^2.3
- `twig/extensions` ^1.3

**升级**：
- `symfony/http-foundation` ^4.0 → ^7.2
- `symfony/routing` ~4.2.0 → ^7.2
- `symfony/config` ^4.0 → ^7.2
- `symfony/yaml` ^4.0 → ^7.2
- `symfony/expression-language` ^4.0 → ^7.2
- `symfony/security` ^4.0 → `symfony/security-core` ^7.2 + `symfony/security-http` ^7.2
- `symfony/twig-bridge` ^4.0 → ^7.2
- `symfony/css-selector` ^4.0 → ^7.2
- `symfony/browser-kit` ^4.0 → ^7.2
- `twig/twig` ^1.24 → ^3.0

**新增**：
- `symfony/http-kernel` ^7.2
- `symfony/dependency-injection` ^7.2
- `symfony/event-dispatcher` ^7.2
- `symfony/framework-bundle` ^7.2（MicroKernel 需要）
- `giorgiosironi/eris` ^1.0（require-dev）

**保持不变**：
- `guzzlehttp/guzzle` ^6.3（Phase 2）
- `oasis/logging` ^2.0
- `oasis/utils` ^2.0
- `phpunit/phpunit` ^13.0

**注意**：`symfony/security-bundle` 是否需要取决于 Minimal_Security_Adaptation 的实际需求。如果 Phase 1 不使用 Symfony Security Bundle 的 firewall 系统（authenticator 重写在 Phase 3），可能只需要 `symfony/security-core` + `symfony/security-http`。Design 阶段倾向于只引入 `security-core` + `security-http`，避免 Security Bundle 的强约束。

### D2: MicroKernel 核心设计（R2, R3, R16）

```php
namespace Oasis\Mlib\Http;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MicroKernel extends Kernel implements AuthorizationCheckerInterface
{
    use MicroKernelTrait;
    use ConfigurationValidationTrait;

    // Priority constants — 精确数值是行为契约（CR Q3: A）
    const BEFORE_PRIORITY_EARLIEST       = 512;
    const BEFORE_PRIORITY_ROUTING        = 32;
    const BEFORE_PRIORITY_CORS_PREFLIGHT = 20;
    const BEFORE_PRIORITY_FIREWALL       = 8;
    const BEFORE_PRIORITY_LATEST         = -512;
    const AFTER_PRIORITY_EARLIEST        = 512;
    const AFTER_PRIORITY_LATEST          = -512;

    protected ArrayDataProvider $httpDataProvider;
    protected bool $isDebug;
    protected ?string $cacheDir = null;
    protected array $controllerInjectedArgs = [];
    protected array $extraParameters = [];
    protected array $middlewares = [];
    protected array $viewHandlers = [];
    protected array $errorHandlers = [];

    public function __construct(array $httpConfig, bool $isDebug)
    {
        $this->httpDataProvider = $this->processConfiguration($httpConfig, new HttpConfiguration());
        $this->isDebug = $isDebug;
        $this->cacheDir = $this->httpDataProvider->getOptional('cache_dir');

        parent::__construct($isDebug ? 'dev' : 'prod', $isDebug);

        // 解析 Bootstrap_Config 各 key
        $this->parseBootstrapConfig();
    }
}
```

**关键设计决策**：

1. **构造函数**：接受 `(array $httpConfig, bool $isDebug)`，与 `SilexKernel` 的 `(array $httpConfig, $isDebug)` 基本一致（新增 `bool` 类型提示）。内部调用 `parent::__construct($environment, $debug)` 满足 Symfony Kernel 要求。

2. **Bootstrap_Config 解析**：在 `parseBootstrapConfig()` 中处理 `trusted_proxies`、`trusted_header_set`、`middlewares`、`view_handlers`、`error_handlers`、`injected_args`、`providers` 等 key，将它们存储为实例属性，在 `configureContainer()` 和 `boot()` 阶段注册到 DI 容器和 EventDispatcher。

3. **`configureContainer()`**：MicroKernel 的容器配置入口，在此注册所有 service definition（routing、CORS、cookie、twig、security 等）。

4. **`run()`、`handle()`、`isGranted()` 等公共 API**：保持与 `SilexKernel` 相同的行为语义。

5. **`__set()` 魔术方法**：不保留（CR Q&A 已确认）。


### D3: Middleware 机制迁移（R4）

**MiddlewareInterface 新签名**：

```php
namespace Oasis\Mlib\Http\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    public function onlyForMasterRequest(): bool;
    public function before(Request $request, MicroKernel $kernel);
    public function after(Request $request, Response $response);
    public function getBeforePriority(): int|false;
    public function getAfterPriority(): int|false;
}
```

**AbstractMiddleware 新实现**：

```php
abstract class AbstractMiddleware implements MiddlewareInterface
{
    public function onlyForMasterRequest(): bool { return true; }
    public function getAfterPriority(): int|false { return MicroKernel::AFTER_PRIORITY_LATEST; }
    public function getBeforePriority(): int|false { return MicroKernel::BEFORE_PRIORITY_EARLIEST; }
}
```

**注册机制**：`MicroKernel::addMiddleware()` 在 EventDispatcher 上注册闭包 listener：

```php
public function addMiddleware(MiddlewareInterface $middleware): void
{
    $this->middlewares[] = $middleware;
}

// 在 boot() 阶段注册到 EventDispatcher
protected function registerMiddlewares(): void
{
    $dispatcher = $this->getContainer()->get('event_dispatcher');
    foreach ($this->middlewares as $middleware) {
        if (false !== ($priority = $middleware->getBeforePriority())) {
            $dispatcher->addListener(
                KernelEvents::REQUEST,
                function (RequestEvent $event) use ($middleware) {
                    if ($middleware->onlyForMasterRequest()
                        && $event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
                        return;
                    }
                    $ret = $middleware->before($event->getRequest(), $this);
                    if ($ret instanceof Response) {
                        $event->setResponse($ret);
                    }
                },
                $priority
            );
        }
        if (false !== ($priority = $middleware->getAfterPriority())) {
            $dispatcher->addListener(
                KernelEvents::RESPONSE,
                function (ResponseEvent $event) use ($middleware) {
                    if ($middleware->onlyForMasterRequest()
                        && $event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
                        return;
                    }
                    $middleware->after($event->getRequest(), $event->getResponse());
                },
                $priority
            );
        }
    }
}
```

### D4: CORS EventSubscriber（R6）

`CrossOriginResourceSharingProvider` 重写为 `CrossOriginResourceSharingSubscriber`：

```php
namespace Oasis\Mlib\Http\ServiceProviders\Cors;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CrossOriginResourceSharingSubscriber implements EventSubscriberInterface
{
    // 保留现有的 strategies、activeStrategy、preFlightResponse 属性和所有处理方法

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onPreRouting', 33],   // BEFORE_PRIORITY_ROUTING + 1
                ['onPostRouting', 20],  // BEFORE_PRIORITY_CORS_PREFLIGHT
            ],
            KernelEvents::RESPONSE => [
                ['onResponse', -512],   // AFTER_PRIORITY_LATEST
            ],
            KernelEvents::EXCEPTION => [
                ['onException', 512],   // AFTER_PRIORITY_EARLIEST
            ],
        ];
    }

    // onPreRouting(), onPostRouting() 签名不变
    // onResponse() 签名改为 (ResponseEvent $event)
    // onException() 替代 onMethodNotAllowedHttp()，签名改为 (ExceptionEvent $event)
}
```

**关键变更**：
- `onMethodNotAllowedHttp()` 的 Silex error handler 签名 `($e, $request, $code, $event)` 改为标准 `ExceptionEvent` 处理
- `onResponse()` 从 `(Request, Response)` 改为 `(ResponseEvent $event)`，内部通过 `$event->getRequest()` / `$event->getResponse()` 获取
- `onPostRouting()` 返回 `PrefilightResponse` 时，需要通过 `RequestEvent::setResponse()` 设置

### D5: View Handler 链 EventSubscriber（R7）

新建 `ViewHandlerSubscriber`：

```php
namespace Oasis\Mlib\Http\EventSubscribers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ViewHandlerSubscriber implements EventSubscriberInterface
{
    private array $handlers;

    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::VIEW => ['onView', 0]];
    }

    public function onView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        $request = $event->getRequest();

        foreach ($this->handlers as $handler) {
            $response = $handler($result, $request);
            if ($response instanceof Response) {
                $event->setResponse($response);
                return;
            }
        }
    }
}
```

### D6: Error Handler 链注册（R8）

Error handler 的注册不使用 EventSubscriber 模式（原因见 Socratic Review），而是在 `MicroKernel` 中通过 `addListener()` 逐个注册到 `KernelEvents::EXCEPTION`。`ExtendedExceptionListnerWrapper` 不再作为独立类存在，其核心行为（handler 返回 null 时不强制生成 response）内联到注册闭包中：

```php
protected function registerErrorHandlers(): void
{
    $dispatcher = $this->getContainer()->get('event_dispatcher');
    foreach ($this->errorHandlers as $index => $handler) {
        $priority = -8; // 默认 priority，与 SilexKernel::error() 一致
        $dispatcher->addListener(
            KernelEvents::EXCEPTION,
            function (ExceptionEvent $event) use ($handler) {
                $exception = $event->getThrowable();
                $request = $event->getRequest();
                $code = $exception instanceof HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : 500;

                $response = $handler($exception, $request, $code);

                if ($response instanceof Response) {
                    $event->setResponse($response);
                } elseif ($response === null && $event->getResponse() === null) {
                    // 保留 ExtendedExceptionListnerWrapper 的行为：
                    // handler 返回 null 且 event 无 response 时，不设置 response，让异常继续传播
                    return;
                }
            },
            $priority
        );
    }
}
```


### D7: Cookie EventSubscriber（R9）

```php
namespace Oasis\Mlib\Http\ServiceProviders\Cookie;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CookieSubscriber implements EventSubscriberInterface
{
    private ResponseCookieContainer $cookieContainer;

    public function __construct(ResponseCookieContainer $cookieContainer)
    {
        $this->cookieContainer = $cookieContainer;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', 0]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        foreach ($this->cookieContainer->getCookies() as $cookie) {
            $event->getResponse()->headers->setCookie($cookie);
        }
    }
}
```

在 `MicroKernel::boot()` 中注册：创建 `ResponseCookieContainer` 实例，注册为 injected arg，创建 `CookieSubscriber` 并添加到 EventDispatcher。

### D8: 路由系统迁移（R5）

**CacheableRouterProvider** 重写为 DI 注册方式。核心变更：

1. 移除 `Pimple\ServiceProviderInterface` 实现
2. 提供 `register(MicroKernel $kernel)` 方法，在 `MicroKernel::boot()` 中调用
3. 路由 service（`request_matcher`、`url_generator`、`router`）通过 MicroKernel 的 getter 方法暴露，而非 Pimple 数组式访问

**CacheableRouter** 变更：
- 构造函数 `SilexKernel $kernel` → 接受一个 `ParameterProviderInterface`（或直接接受 `MicroKernel`）
- `getRouteCollection()` 的 `%param%` 替换逻辑不变

**RedirectableUrlMatcher** 替换：
- `Silex\Provider\Routing\RedirectableUrlMatcher` → `Symfony\Component\Routing\Matcher\RedirectableUrlMatcher`（Symfony 7.x 内置）

**InheritableYamlFileLoader** 适配：
- Symfony Routing 7.x 的 `YamlFileLoader::import()` 签名可能有变化，需要检查并适配

### D9: Twig Provider 迁移（R10）

**SimpleTwigServiceProvider** 重写：

1. 移除 `Silex\Provider\TwigServiceProvider` 继承
2. 在 `MicroKernel::boot()` 中直接创建 `\Twig\Environment` 实例
3. Twig 1.x API 替换：
   - `Twig_Environment` → `\Twig\Environment`
   - `Twig_SimpleFunction` → `\Twig\TwigFunction`
   - `Twig_Error_Loader` → `\Twig\Error\LoaderError`
4. `twig/extensions` 移除后，检查是否有使用其功能的代码需要适配

**DefaultHtmlRenderer** 中的 Twig 引用更新：
- `\Twig_Error_Loader` → `\Twig\Error\LoaderError`
- `$twig->render()` 调用保持不变（Twig 3.x 兼容）

### D10: Security Provider 最小适配（R11）

**SimpleSecurityProvider** 重写：

1. 移除 `Silex\Provider\SecurityServiceProvider` 继承
2. 保留 `addFirewall()`、`addAccessRule()`、`addAuthenticationPolicy()`、`addRoleHierarchy()` 等配置方法
3. `register()` 方法改为接受 `MicroKernel`，将 security 配置存储到 kernel 属性
4. `subscribe()` 方法暂时保留签名但标记为 Phase 3 重写目标
5. `installAuthenticationFactory()` 中的 Pimple `$app[$id]` 访问全部移除，Phase 3 重写

**AuthenticationPolicyInterface** 变更：
- `getAuthenticationProvider(Container $app, ...)` → `getAuthenticationProvider(MicroKernel $kernel, ...)`
- `getAuthenticationListener(Container $app, ...)` → `getAuthenticationListener(MicroKernel $kernel, ...)`
- `getEntryPoint(Container $app, ...)` → `getEntryPoint(MicroKernel $kernel, ...)`
- 返回类型中引用的 `ListenerInterface`（`Symfony\Component\Security\Http\Firewall\ListenerInterface`）在 Symfony 5.4 已移除，Phase 3 重写时需替换

**AbstractSimplePreAuthenticationPolicy** 变更：
- 方法签名中的 `Container $app` → `MicroKernel $kernel`
- 内部使用的 `SimpleAuthenticationProvider` 和 `SimplePreAuthenticationListener` 在 Symfony 6.0 已移除；最小适配阶段保留方法签名但实现体标记为 Phase 3 重写（可 throw `\LogicException('Phase 3 重写')` 或保留空实现使其可编译）

**AbstractSimplePreAuthenticator** 变更：
- 实现的 `SimplePreAuthenticatorInterface`（`Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface`）在 Symfony 6.0 已移除
- 最小适配策略：将 `AbstractSimplePreAuthenticationPolicy` 和 `AbstractSimplePreAuthenticator` 整体改为 abstract stub，移除对已删除接口的 `implements` / `extends`，所有依赖已移除 API 的方法改为 abstract——强制下游在 Phase 3 提供实现

### D11: ExtendedArgumentValueResolver 适配（R12）

Symfony 7.x 中 `ArgumentValueResolverInterface` 已被 `ValueResolverInterface` 替代：

```php
// Symfony 7.x
namespace Symfony\Component\HttpKernel\Controller;

interface ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable;
}
```

`supports()` 方法被移除，`resolve()` 应返回空数组表示不支持。

```php
class ExtendedArgumentValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $classname = $argument->getType();
        if (!$classname || !class_exists($classname)) {
            return [];
        }
        if (array_key_exists($classname, $this->mappingParameters)) {
            return [$this->mappingParameters[$classname]];
        }
        foreach ($this->mappingParameters as $value) {
            if ($value instanceof $classname) {
                return [$value];
            }
        }
        return [];
    }
}
```

### D12: Symfony 4.x → 7.x API 映射表（R13）

| Symfony 4.x | Symfony 7.x | 影响文件 |
|-------------|-------------|---------|
| `FilterResponseEvent` | `ResponseEvent` | MicroKernel, CORS, Cookie |
| `GetResponseEvent` | `RequestEvent` | MicroKernel, middleware |
| `GetResponseForExceptionEvent` | `ExceptionEvent` | Error handlers, CORS |
| `HttpKernelInterface::MASTER_REQUEST` | `HttpKernelInterface::MAIN_REQUEST` | MicroKernel, middleware |
| `Silex\CallbackResolver` | 直接调用 callable | MicroKernel |
| `Silex\ExceptionListenerWrapper` | 自定义 error handler 注册 | ExtendedExceptionListnerWrapper |
| `Silex\Application::EARLY_EVENT` (512) | 自定义常量 `MicroKernel::BEFORE_PRIORITY_EARLIEST` (512) | AbstractMiddleware |
| `Silex\Application::LATE_EVENT` (-512) | 自定义常量 `MicroKernel::BEFORE_PRIORITY_LATEST` (-512) | AbstractMiddleware |
| `Twig_Environment` | `\Twig\Environment` | DefaultHtmlRenderer, SimpleTwigServiceProvider |
| `Twig_SimpleFunction` | `\Twig\TwigFunction` | SimpleTwigServiceProvider |
| `Twig_Error_Loader` | `\Twig\Error\LoaderError` | DefaultHtmlRenderer |
| `RequestMatcher` constructor | 检查 Symfony 7.x 是否有变化 | CrossOriginResourceSharingStrategy |
| `SimplePreAuthenticatorInterface` | 已移除（Symfony 6.0） | AbstractSimplePreAuthenticator |
| `ListenerInterface` (`Security\Http\Firewall`) | 已移除（Symfony 5.4） | AuthenticationPolicyInterface |
| `SimpleAuthenticationProvider` | 已移除（Symfony 6.0） | AbstractSimplePreAuthenticationPolicy |
| `SimplePreAuthenticationListener` | 已移除（Symfony 6.0） | AbstractSimplePreAuthenticationPolicy |
| `Silex\Provider\ServiceControllerServiceProvider` | `framework-bundle` 内置 controller-as-service 支持 | SilexKernel::boot() |


### D13: 测试适配策略（R14）

**测试 bootstrap 文件更新**：

所有 `ut/*.php` 和 `ut/*/app.*.php` 文件中的 `SilexKernel` 引用改为 `MicroKernel`。`new SilexKernel($config, $debug)` → `new MicroKernel($config, $debug)`。

**测试类更新**：

- `SilexKernelTest` → 可选择重命名为 `MicroKernelTest`，或保持文件名但更新内部引用
- `SilexKernelWebTest` → 同上
- `FallbackViewHandlerTest` → 更新 `SilexKernel` mock 为 `MicroKernel` mock
- 所有使用 `$app->service_providers = [...]` 的测试 → 改为通过 Bootstrap_Config 的 `providers` key 传入 CompilerPass/Extension，或直接在 config 中配置

**WebTestCase 适配**：

Silex 的 `WebTestCase` 不再可用。需要使用 Symfony 的 `WebTestCase` 或自定义 test client 机制。`SilexKernelWebTest` 和 CORS 测试中的 `createClient()` 需要适配。

### D14: Eris PBT 设计（R15）

**目录结构**：`ut/PBT/`

**测试文件**：
- `ut/PBT/RoutingPropertyTest.php` — 路由解析 property test
- `ut/PBT/MiddlewareChainPropertyTest.php` — 中间件链 property test
- `ut/PBT/RequestDispatchPropertyTest.php` — 请求分发 property test

**Eris 集成方式**：

```php
use Eris\Generator;
use Eris\TestTrait;

class RoutingPropertyTest extends TestCase
{
    use TestTrait;

    public function testAnyValidRouteResolvesToController(): void
    {
        // 从 routes.yml 读取所有已定义路由
        // 使用 Eris 生成随机的有效路径
        // 验证 router->match() 返回正确的 _controller
    }

    public function testUndefinedRouteThrowsException(): void
    {
        $this->forAll(Generator\string())
            ->when(fn($path) => !$this->isDefinedRoute($path))
            ->then(function ($path) {
                $this->expectException(ResourceNotFoundException::class);
                $this->router->match($path);
            });
    }
}
```

**phpunit.xml 新增 suite**：

```xml
<testsuite name="pbt">
    <directory>ut/PBT</directory>
</testsuite>
```

### D15: Provider 迁移策略（R3 AC4, CR Q4: B）

Bootstrap_Config 的 `providers` key 改为接受 Symfony `CompilerPass` 实例数组：

```php
// MicroKernel 中处理 providers config
if ($providersConfig = $this->httpDataProvider->getOptional('providers', ...)) {
    foreach ($providersConfig as $provider) {
        if ($provider instanceof CompilerPassInterface) {
            // 在 configureContainer() 中注册
            $containerBuilder->addCompilerPass($provider);
        } elseif ($provider instanceof ExtensionInterface) {
            $containerBuilder->registerExtension($provider);
        } else {
            throw new InvalidConfigurationException(
                'providers must be an array of CompilerPassInterface or ExtensionInterface'
            );
        }
    }
}
```

---

## Correctness Properties

### CP1: 路由解析幂等性
- **Property**: 对于任何已定义路由 path P，`router->match(P)` 的结果在多次调用间保持一致
- **验证**: PBT — 随机选择已定义路由，多次调用 match()，断言结果相同

### CP2: Middleware 优先级排序
- **Property**: 对于任何 middleware 集合 M，执行顺序严格按 priority 降序排列
- **验证**: PBT — 生成随机 priority 的 middleware 集合，记录执行顺序，断言降序

### CP3: Before middleware 短路
- **Property**: 如果 before middleware 返回 Response，后续 middleware 和 controller 不执行
- **验证**: PBT — 随机选择一个 middleware 返回 Response，验证后续 middleware 的 before() 未被调用

### CP4: 请求分发完整性
- **Property**: 对于任何有效请求，`handle()` 返回的 Response 状态码在 100–599 范围内
- **验证**: PBT — 生成随机请求，断言 Response 状态码范围

### CP5: View Handler 链传递
- **Property**: 如果所有 view handler 返回 null，则无 Response 被设置；如果某个 handler 返回 Response，链停止
- **验证**: PBT — 生成随机 handler 链（部分返回 null，部分返回 Response），验证链行为

### CP6: Bootstrap_Config 完整性
- **Property**: 对于 SilexKernel 支持的所有 Bootstrap_Config key，MicroKernel 也支持且行为一致
- **验证**: Example-based test — 逐个 key 验证

---

## Socratic Review

**Q: MicroKernel 继承 Symfony Kernel + MicroKernelTrait 是否过重？**
A: MicroKernelTrait 提供了 `configureContainer()` 和 `configureRoutes()` 两个入口，正好满足 Bootstrap_Config 驱动初始化的需求。相比自己实现 DI 容器管理，MicroKernelTrait 更可靠且与 Symfony 生态兼容。`framework-bundle` 的引入确实增加了依赖，但它提供了 EventDispatcher、Router、ArgumentResolver 等基础设施的自动配置，减少了手动 wiring 的工作量。

**Q: Error handler 的注册为什么不用 EventSubscriber 的 getSubscribedEvents()？**
A: 因为每个 error handler 可能有不同的 priority（当前 SilexKernel::error() 默认 priority 是 -8，但用户可以指定不同值）。`getSubscribedEvents()` 是静态方法，无法动态设置 priority。所以改为在 MicroKernel 中通过 `addListener()` 逐个注册。

**Q: CORS Subscriber 的 onResponse 签名变更是否会影响现有行为？**
A: 不会。现有 `onResponse(Request $request, Response $response)` 的参数通过 Silex 的 after middleware 机制传入。迁移后改为 `onResponse(ResponseEvent $event)`，内部通过 `$event->getRequest()` 和 `$event->getResponse()` 获取相同的对象。处理逻辑完全不变。

**Q: Twig 升级到 3.x 是否会影响现有模板？**
A: 可能会。Twig 3.x 移除了一些 Twig 1.x 的 deprecated 功能（如 `{% spaceless %}` tag 改为 `{% apply spaceless %}`）。但本项目的模板文件在 `ut/` 测试目录中，scope 有限。如果模板使用了 deprecated 语法，需要在测试适配阶段修复。

**Q: Security 组件的"最小可编译适配"如何处理 Symfony 6.0+ 已移除的接口？**
A: `SimplePreAuthenticatorInterface`、`ListenerInterface`、`SimpleAuthenticationProvider`、`SimplePreAuthenticationListener` 等在 Symfony 5.4–6.0 之间被移除。最小适配策略是：移除对这些已删除接口的 `implements` / `extends` 引用，保留方法签名使类可编译，但方法实现体标记为 Phase 3 重写目标。这确保了 Phase 1 的目标（可编译）而不提前进入 Phase 3 的 scope（authenticator 系统重写）。

**Q: `ServiceControllerServiceProvider` 的替换方案是什么？**
A: 当前 `SilexKernel::boot()` 中注册了 `Silex\Provider\ServiceControllerServiceProvider`，它提供 controller-as-service 支持。迁移后，`symfony/framework-bundle` 内置了 controller-as-service 功能（通过 `controller.service_arguments` tag 和 `AbstractController`），无需额外注册。

**Q: Design 是否覆盖了所有 Requirements 的 AC？**
A: 是。R1→D1, R2→D2, R3→D2+D15, R4→D3, R5→D8, R6→D4, R7→D5, R8→D6, R9→D7, R10→D9, R11→D10, R12→D11, R13→D12, R14→D13, R15→D14, R16→D2（Bootstrap_Config 解析）。

---

## Gatekeep Log

**校验时间**: 2025-07-17
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [内容] Impact Analysis：补充 state 文档影响（`docs/state/architecture.md` 需更新的具体 section）、配置项变更（`providers` key 语义变更）、数据模型变更（不涉及）、外部系统交互（不涉及）
- [内容] Impact Analysis：补充遗漏的受影响文件 `AbstractSimplePreAuthenticationPolicy.php`（有 Pimple `Container` 依赖）
- [内容] D6：移除误导性的 `ErrorHandlerSubscriber` 类定义（实现 `EventSubscriberInterface` 但 `getSubscribedEvents()` 返回空数组），改为直接描述 MicroKernel 中的 `addListener()` 注册方式
- [内容] D10：补充 Symfony 6.0+ 已移除接口的处理策略（`SimplePreAuthenticatorInterface`、`ListenerInterface`、`SimpleAuthenticationProvider`、`SimplePreAuthenticationListener`）
- [内容] D12 API 映射表：补充 Security 相关已移除 API（`SimplePreAuthenticatorInterface`、`ListenerInterface`、`SimpleAuthenticationProvider`、`SimplePreAuthenticationListener`）和 `ServiceControllerServiceProvider` 的替换
- [内容] Socratic Review：补充两条 Q&A——(1) Security 已移除接口的最小适配策略；(2) `ServiceControllerServiceProvider` 的替换方案
- [格式] D2 构造函数描述：修正"与 SilexKernel 签名一致"为准确描述（新增 `bool` 类型提示）
- [格式] Correctness Properties 与 Socratic Review 之间补充 `---` 分隔线

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（R1–R16 编号、术语引用与 requirements.md 一致）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] 技术方案主体存在（D1–D15），承接了 requirements 中的需求
- [x] 接口签名 / 数据模型有明确定义（代码块形式）
- [x] 各 section 之间使用 `---` 分隔
- [x] Impact Analysis 存在，覆盖源文件影响、state 文档影响、配置项变更、数据模型变更、外部系统交互
- [x] Impact Analysis 利用了 GRAPH_REPORT.md 的模块依赖关系和 community 结构
- [x] 每条 requirement（R1–R16）在 design 中都有对应的技术方案
- [x] design 中的方案不超出 requirements 的范围
- [x] 技术选型有明确理由（MicroKernel、EventSubscriber、addListener 等）
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致
- [x] Socratic Review 覆盖充分（7 条 Q&A，覆盖技术选型、行为兼容、已移除接口、替换方案、requirements 覆盖）
- [x] Requirements CR 决策已体现（Q1→D1/D9 Twig 3.x、Q2→D2 getTwig()、Q3→D2 priority 常量、Q4→D15 CompilerPass/Extension）
- [x] 技术选型明确，无"待定"或含糊选型
- [x] 接口定义可执行（参数类型、返回类型明确）
- [x] 可 task 化（各 D section 独立，模块间关系清晰）

### Clarification Round

**状态**: 已回答

**Q1:** 实现顺序偏好：
- A) 自底向上
- B) 功能切片
- C) 请求流程优先
- D) 其他

**A:** C — 先跑通基本请求链路（D1+D2+D3+D8），再补全其他子系统。

**Q2:** Security 最小适配中已移除接口的处理策略：
- A) throw LogicException
- B) 空实现
- C) abstract stub

**A:** C — 将 `AbstractSimplePreAuthenticationPolicy` 和 `AbstractSimplePreAuthenticator` 整体改为 abstract stub，所有依赖已移除 API 的方法改为 abstract，强制下游在 Phase 3 提供实现。

**Q3:** 测试文件是否重命名？
- A) 重命名
- B) 保持文件名不变
- C) 重命名 + alias

**A:** B — 保持文件名不变（`SilexKernelTest.php`、`SilexKernelWebTest.php`），仅更新内部引用。

**Q4:** PBT 粒度和 mock 策略：
- A) 集成级
- B) 单元级
- C) 混合

**A:** A — PBT 测试直接启动 MicroKernel 实例（集成级），使用真实的 routing/middleware/view handler 配置。

**补充决策：类名变更**

用户决定新 Kernel 类名为 `MicroKernel`（`Oasis\Mlib\Http\MicroKernel`），而非 `OasisKernel`。理由：package name 已包含 oasis，`MicroKernel` 强调轻量级特性。requirements.md 和 design.md 中所有 `OasisKernel` 引用已全局替换为 `MicroKernel`。
