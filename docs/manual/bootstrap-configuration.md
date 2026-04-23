# Bootstrap Configuration

`SilexKernel` 的 bootstrap 配置项总览与基本用法。

---

## 配置项一览

| Key | 说明 | 详细文档 |
|-----|------|----------|
| `routing` | 可缓存路由（YAML 文件 + 命名空间） | [Routing](routing.md) |
| `security` | 安全（firewalls / access_rules / policies / role_hierarchy） | [Security](security.md) |
| `cors` | CORS 策略 | [CORS](cors.md) |
| `twig` | Twig 模板引擎 | 本文 |
| `middlewares` | Before / After 中间件 | 本文 |
| `providers` | Silex Service Provider | 本文 |
| `view_handlers` | View Handler 链 | 本文 |
| `error_handlers` | Error Handler 链 | 本文 |
| `injected_args` | 控制器参数注入 | 本文 |
| `trusted_proxies` | 可信代理 IP | 本文 |

---

## Routing

通过 `routing` 配置启用基于 YAML 的可缓存路由：

```php
$config = [
    "routing" => [
        "path" => "routes.yml",
        "namespaces" => [
            "Oasis\\Mlib\\TestControllers\\"
        ],
    ],
];
```

`routes.yml` 示例：

```yaml
home:
    path: /
    defaults:
        _controller: TestController::homeAction
```

`_controller` 中的类名需要完全限定，除非在 `namespaces` 中配置了默认前缀。

高级路由功能（placeholder、requirements、resource importing、caching）参见 [Routing](routing.md)。

---

## Twig

启用 Twig 模板支持：

```php
$config = [
    "twig" => [
        "template_dir" => "path/to/templates",
        "asset_base" => "http://my.domain.tld/assets/",
        "globals" => [
            "user" => $user,
        ],
    ],
];
```

---

## Middlewares

实现 `Oasis\Mlib\Http\Middlewares\MiddlewareInterface` 后通过 bootstrap 安装：

```php
$config = [
    "middlewares" => [$mid],
];
```

---

## Providers

安装 Silex Service Provider：

```php
$config = [
    "providers" => [
        new HttpCacheServiceProvider(),
    ],
];
```

也可通过 `$kernel->register()` 动态注册。

---

## View Handlers

当控制器返回值不是 `Response` 对象时，进入 View Handler 链：

```php
class MyViewHandler
{
    function __invoke($rawResult, Request $request)
    {
        return new JsonResponse($rawResult);
    }
}

$config = [
    "view_handlers" => [new MyViewHandler()],
];
```

如果 View Handler 返回的仍不是 `Response`，会传递给下一个 handler。

---

## Error Handlers

异常处理链，接收 `\Exception` 和 HTTP code：

```php
class MyErrorHandler
{
    function __invoke(\Exception $e, $code)
    {
        return ['message' => $e->getMessage(), 'code' => $code];
    }
}

$config = [
    "error_handlers" => [new MyErrorHandler()],
];
```

返回 `null` 时传递给下一个 handler；非 `null` 返回值如果不是 `Response`，会进入 View Handler 链。

---

## Injected Arguments

控制器参数自动注入。默认注入 `Request` 和 `SilexKernel`，可通过 `injected_args` 添加更多：

```php
$config = [
    "injected_args" => [
        'cache' => $memcached,
    ],
];

class MyController
{
    public function testAction(\Memcached $cache, Request $request)
    {
        // $cache 和 $request 均自动注入，参数顺序无关，按类型匹配
    }
}
```

---

## Trusted Proxies

指定可信代理 IP 数组，支持 CIDR 表示法。

### AWS ELB

设置 `behind_elb` 为 `true`，自动将 `REMOTE_ADDR` 加入可信代理。

### AWS CloudFront

设置 `trust_cloudfront_ips` 为 `true`，自动拉取 AWS CloudFront IP 范围并加入可信代理（支持缓存）。
