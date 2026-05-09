# Routing

`oasis/http` 高级路由配置说明，基于 Symfony Routing 组件。

---

## Attributes

Attributes 是与路由关联的一组键值对，包含 route name、controller、placeholder 参数等。

通过 `Request` 对象访问：

```php
$route      = $request->attributes->get('_route');
$controller = $request->attributes->get('_controller');
$id         = $request->attributes->get('id');
```

---

## Placeholder

在 `path` 或 `host` 中使用花括号定义占位符：

```yaml
product.detail:
    path: "/product/{id}"
    host: "{shopname}.domain.tld"
    defaults:
        _controller: "ProductController::showDetailAction"
```

控制器方法参数名与占位符名匹配即可自动注入：

```php
public function showDetailAction($shopname, $id)
{
    // 参数顺序无关
}
```

---

## Requirements

通过正则表达式约束占位符匹配规则：

```yaml
product.detail:
    path: "/product/{id}"
    requirements:
        id: \d+
    defaults:
        _controller: "ProductController::showDetailAction"

product.list:
    path: "/product/list"
    defaults:
        _controller: "ProductController::listAction"
```

未指定 requirements 时，占位符默认匹配 `[^/]+`。如需匹配含 `/` 的值，使用 `.+`。

---

## Importing Resources

支持将路由文件拆分并通过 `resource` + `prefix` 组合挂载：

```yaml
# routes.yml
component.user:
    prefix: /user
    host: "{shopname}.domain.tld"
    resource: "routes/user.yml"
    defaults:
        component: user
```

```yaml
# routes/user.yml
user.index:
    path: "/"
    defaults:
        _controller: "UserController::listAction"

component.user.cart:
    prefix: /{id}/cart
    resource: "routes/user.cart.yml"
    requirements:
        id: .+
    defaults:
        component: user.cart
```

特性：

- 导入的路由自动继承父级 prefix
- 父级定义的 placeholder 在子路由中可用
- 递归导入，prefix 递归拼接
- 子路由的 defaults 可覆盖父级同名属性

---

## Programmatic Route Injection

除 YAML 文件声明外，`MicroKernel` 提供编程式路由注入 API，在 boot 前通过代码注册路由。

### addRoute

注入单条路由：

```php
use Symfony\Component\Routing\Route;

$kernel->addRoute('health_check', new Route('/health', [
    '_controller' => 'HealthController::check',
]));
```

可选参数 `$allowOverwrite`（默认 `true`）控制同名路由的冲突行为：

```php
// 默认行为：同名路由静默覆盖（向后兼容）
$kernel->addRoute('health_check', new Route('/health-v2', [...]));

// 严格模式：同名路由抛出 LogicException
$kernel->addRoute('health_check', new Route('/health-v2', [...]), allowOverwrite: false);
// LogicException: Duplicate route: 'health_check'
```

### addRoutes

批量注入一组路由：

```php
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$routes = new RouteCollection();
$routes->add('api.users', new Route('/api/users', [
    '_controller' => 'UserController::list',
]));
$routes->add('api.orders', new Route('/api/orders', [
    '_controller' => 'OrderController::list',
]));

$kernel->addRoutes($routes);
```

同样支持 `$allowOverwrite` 参数（默认 `true`）：

```php
$kernel->addRoutes($routes, allowOverwrite: false); // 严格模式
```

### 匹配优先级

编程式路由优先于 YAML 路由匹配。当编程式路由与 YAML 路由定义了相同路径或相同名称时，编程式路由胜出。内部实现上，编程式路由使用独立的内存 matcher，排在 YAML 缓存 matcher 之前。

### 无 YAML 配置场景

Bootstrap config 中未配置 `routing` 时，仍可通过 `addRoute()` / `addRoutes()` 注入路由。`MicroKernel` 会自动初始化空的路由基础设施并合并编程式路由。

---

## Post-Boot Freeze

boot 完成后，路由表被冻结，所有写操作将抛出 `LogicException`：

- `addRoute()` / `addRoutes()`：MicroKernel 层拦截，抛出 `LogicException('Cannot add routes after the kernel has been booted.')`
- `getRouter()->getRouteCollection()->add()` / `addCollection()` / `remove()`：`FrozenRouteCollection` 层拦截，抛出 `LogicException('Route collection is frozen after boot. Routes cannot be modified at this point.')`

boot 后的只读操作（`get()`、`all()`、`count()`、`getIterator()`）不受影响，正常返回。

---

## Caching and Debugging

`oasis/http` 支持路由缓存。缓存的 URL Matcher 文件位于 `cache_dir` 根目录，命名类似 `ProjectUrlMatcher_<hash>.php`。

调试时可在该文件的 `match()` 方法设断点，跟踪路由匹配过程。

如果修改路由后行为未更新，尝试清除缓存目录。
