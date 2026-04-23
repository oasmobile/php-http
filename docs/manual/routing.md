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

## Caching and Debugging

`oasis/http` 支持路由缓存。缓存的 URL Matcher 文件位于 `cache_dir` 根目录，命名类似 `ProjectUrlMatcher_<hash>.php`。

调试时可在该文件的 `match()` 方法设断点，跟踪路由匹配过程。

如果修改路由后行为未更新，尝试清除缓存目录。
