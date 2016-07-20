# Advanced Routing Configuration

When using routes yaml file to define application routing, there are
many more advanced techniques which could make your life a lot easier if
mastered. We will go through all of them in this document one by one:

- [Attributes](#attributes)
- [Placeholder](#placeholder)
- [Requirements](#requirements)
- [Importing Resources](#importing-resources)
- [Caching and Debuging](#caching-and-debugging)

### Attributes

Before we start, we should first understand the concept of `attributes`.
Attributes are a group of values associated to named keys. In [Silex], all
routing information will be stored into `attributes`. This includes route name,
controller, placeholder parameter, and anything else defined in `defaults`
section of a route.

To access the `attributes`, we can simply try this:

```php
<?php

/** @var Symfony\Component\HttpFoundation\Request $request */

$route      = $request->attributes->get('_route'); // route name
$controller = $request->attributes->get('_controller'); // controller
$id         = $request->attributes->get('id'); // placeholder
if ($request->attributes->has('user')) {
    // ...
}
// ...

```

### Placeholder

Placeholder is probably a must feature in any kind of smart routing.
There is no exception in **[oasis/http]** either.

To create a placeholder, simply surround the desired placeholder name
with curly braces, and then put the placeholder into the `path` or
`host` part of the configuration:

```yaml

product.detail:
    path: "/product/{id}"
    host: "{shopname}.domain.tld"
    defaults:
        _controller: "ProductController::showDetailAction"

```

In the example above, we have defined 2 placeholders: `id` and
`shopname`. Providing the controller is defined like this:

```php
<?php

use Symfony\Component\HttpFoundation\Response;

class ProductController
{
    public function showDetailAction($shopname, $id)
    {
        return new Response(sprintf(
            "You are visiting [%s] for product #%d",
            $shopname,
            $id
        ));
    }
}

```

And if we visit the following url:

`http://my-candy-shop.domain.tld/product/120`

The response will be:

```

You are visiting [my-candy-shop] for prduct 120

```

You may also find all placeholder parameters in the `attributes` member
variable of `Symfony\Component\HttpFoundation\Request` object

```php
<?php

/** @var Symfony\Component\HttpFoundation\Request $request */
$id = $request->attributes->get('id');

```

### Requirements

A raw placeholder acts like a wildcard matcher, and sometimes we need a
stricter approach to match placeholders. This introduces the
`requirements` parameter:

The `requirements` parameter is a top-level parameter of a route (i.e.
directly under the route name, sibling to `path`, `host`, `defaults`,
etc.). The values of `requirements` are key-value arrays, where the keys
are placeholder names, and values are regular expressions used to match
against placeholder value.

For example, you may have two types of paths: "/product/list" which
lists products, and "/product/{id}" which shows detail of a single
product. We can write our routes.yml like below:

```yaml

product.detail:
    path: "/product/{id}"
    host: "{shopname}.domain.tld"
    requirements:
        id: \d+
    defaults:
        _controller: "ProductController::showDetailAction"

product.list:
    path: "/product/list"
    host: "{shopname}.domain.tld"
    defaults:
        _controller: "ProductController::listAction"

```

Without the `requirements` configuration, the request "/product/list"
will never enter the route "product.list" because it will be matched and
swallowed by the route "product.detail".

> **NOTE**: when no requirement is given, the default matcher for
> placehodler is `[^/]+`. As a result, if you would like to match a
> placeholder which might contain "/", using `requirements` would be a
> much better choice.
>
> Below is an example of matching product name with any kind of
> characters:

```yaml

product.name:
    path: "/product/{name}"
    host: "{shopname}.domain.tld"
    requirements:
        id: .+
    defaults:
        _controller: "ProductController::showDetailByNameAction"

```

### Importing Resources

The ability to manage more than one grouped routing files, is another
useful feature of **oasis/http**. Instead of having one large and messy
routes file, we can separate our routes by categories, and mount
different files under different path prefixes. Let's see some example:

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

user.detail:
    path: "/{id}"
    requirements:
        id: .+
    defaults:
        _controller: "UserController::showDetailAction"

component.user.cart:
    prefix: /{id}/cart
    resource: "routes/user.cart.yml"
    requirements:
        id: .+
    defaults:
        component: user.cart
```

```yaml
# routes/user.cart.yml
user.cart.index:
    path: "/"
    defaults:
        _controller: "UserCartController::listAction"

user.cart.checkout:
    path: "/checkout"
    defaults:
        _controller: "UserCartController::checkoutAction"
```

From the example above:

- All routes imported in "routes/user.yml" will have their paths prefixed by "/user"
- Any placeholder defined in importing route (i.e. component.user), will be available in all routes in the imported resource
- Resource importing is done recursively, so that prefixes are prepended recursively too (i.e. "/user/2/cart/checkout" is a perfectly working path, while "/2/cart/" or "/checkout" is not)
- Attributes imported can override what is defined outside, e.g. in route "user.cart.index", the "component" attribute has the value "user.cart" which is defined in "routes/user.yml", rather than the value "user" which is defined in "routes.yml"

### Caching and Debugging

Unlike [Silex], **[oasis/http]** offers the ability to cache routes defined in yaml files. In debugging, we may also wonder how the routes are matched (or not matched). So, what actully happens during the routing phase?

At first, the kernel will try to find out if a cached url matcher exists. This matcher normally resides at the root of the caching directory and is named like "ProjectUrlMatcher_698cad18956c48dea950b166a7a64ddf.php". In this file, a class with the same name, extending `Symfony\Component\Routing\Matcher\UrlMatcher`, is defined.

In the url matcher, there is an overriden method, called `match()`, and this is where all the magic take place in. To debug, we can set a breakpoint at the beginning of this function, and follow how a route is found, or why a 404/405 exception is thrown.

> **NOTE**: although it is declared that the cached url matcher will invalidate and be regenerated whenever routing yamls or related PHP codes change, some times, we may notice unexpected behavior like changes do not reflect in execution. It is worth a try to remove this url matcher file, or maybe everything under the cache directory, so that program will start from a cleaner environment.

[Silex]: http://silex.sensiolabs.org/ "Silex Micro-Framework"
[oasis/http]: ../README.md
