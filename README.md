# **oasis/http** 

**oasis/http** is a composer component that provides a simple yet useful
framework for building web applications. This component is an extension
to the widely used [Silex micro-framework] [Silex]. And as [Silex]
states, **oasis/http** is also a micro-framework standing on the
shoulder of giants: [Silex] , [Symfony] and [Pimple] .

### Installation

Install the latest version with command below:

```bash
$ composer require **oasis/http**
```

### Web Server Configuration

All examples in this documentation rely on a well-configured web server.

Please refere to [Silex Web Server Configuration] for sample web server
configurations.

### Basic Usage

The first step of using **oasis/http** is to instantiate an
`Oasis\Mlib\Http\SilexKernel` object.

```php
<?php

use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Response;

$config = [];
$isDebug = true;
$kernel = new SilexKernel($config, $isDebug);

$kernel->get('/', function() {
    return new Response("Hello world!");
});

$kernel->run();

```

The `$config` is an array of configuration values. An empty default
value will work fine. However, you can easily instantiate a
well-configured kernel by providing a detail
[bootstrap configuration] (#bootstrap-configuration)

### Silex

Due to the fact that the core class of **oasis/http**,
`Oasis\Mlib\Http\SilexKernel`, is an extension of [Silex]. It is
advisable to prepare your self with some knowledge of [Silex] before you
start using **oasis/http**.

### Bootstrap Configuration

The bootstrap configuration can be categorized into the following parts:

- [routing](#routing)
- [security](#security)
- [cors](#cross-origin-resource-sharing)
- [twig](#rendering-templates)
- [middlewares](#middlewares)
- [providers](#providers)
- [view_handlers](#view-handler)
- [error_handlers](#error-handler)
- [injected_args](#injected-arguments)

##### Routing

By default, [Silex] supports adding routes on-the-fly:

```php
<?php

use Oasis\Mlib\Http\SilexKernel;

/** @var SilexKernel $kernel */
$kernel->get('/', function() {
    // return Response
});
$kernel->match('/patch', function() {
    // return Response
})->method('PATCH');

```

However, the biggest disadvantage of the above routing mechanism is the
lack of caching ability. As a result, **oasis/http** has utilized the
[symfony/routing] component to support cacheable routing.

To achieve this, we will need to configure the bootstrap using the
`routing` parameter, and at the same time supply a routes yaml file.

```php
<?php

$config = [
    "routing" => [
        "path" => "routes.yml",
        "namespaces" => [
            "Oasis\\Mlib\\TestControllers\\"
        ],
    ],
];

// initialize SilexKernel with $config

```

The `routes.yml` file looks like:

```yaml
home:
    path: /
    defaults:
        _controller: TestController::homeAction

```

The `_controller` attribute determines the function to call when certain
path is matched. It consists of a classname and a method name separated
by a "::" sign.

**NOTE**: classname in `_controller` should be fully qualified (i.e.
includes full namespace prefix) unless you specify a default namespace
prefix. The default namesapces are defined under `routing` parameter's
`namespaces` parameter.

Because the `routes.yml` file is fully symfony compatible, we can use
the advanced routing features as well. Please refer to
[advanced routing configuration] (docs/AdvancedRoutingConfiguration.md)
for more.

##### Security

When a web application is deployed, it is often the case that we would
like to protect certain resources (paths, hosts, etc.) behind security
rules. In **oasis/http**, we use `security` bootstrap parameter to
enforce security check on incoming requests.

Let's first have a glance of a simple security configuration:

```php
<?php

$config = [
    "security" => [
        "firewalls" => [
            "http.auth" => [
                "pattern" => "^/admin/",
                "policies" => [
                    "http" => true
                ],
                "users" => [
                    // raw password is foo
                    'admin' => array('ROLE_ADMIN', '$2y$10$3i9/lVd8UOFIJ6PAMFt8gu3/r5g0qeCJvoSlLCsvMTythye19F77a'),
                ],
            ],
        ],
    ],
];

// initialize SilexKernel with $config

```

The example above defines a firewall named `http.auth` which protects
resources with path starting like "/admin/". With the `http` policy set
to true, all incoming request must have HTTP Basic authentication. In
the end, a user named admin with password 'foo' is the only user
provided for this firewall.

Please refer to
[detailed security configuration](docs/AdvancedSecurityConfiguration.md)
for more advanced security schemes.

##### Cross Origin Resource Sharing

When your resources are accessed from domains other than the hosting
one, modern browsers will perform a same-origin restriction check. By
default, the request will silently fail. Generally speaking this is a
safe behavior in most cases. However, there are occasions that you just
want to allow this access. You may host fonts to be included by
different css. You may provide APIs accessible by other javascript
applications. And this is why we need to configure cross origin resource
sharing (CORS) rules for our application.

In **oasis/http**, this has been made incredibly simple for you:

```php
<?php

$config = [
    "security" => [
        "cors" => [
            'pattern' => '^/cors/.*',
            'origins' => ["my.second.domain.tld"],
        ],
    ],
];

// initialize SilexKernel with $config

```

Using the configuration above, any request for path starting with
"/cors/" will be rejected unless it originates from
`my.second.domain.tld`.

Many more rules can be configured for CORS. Please refer
[here](docs/AdvancedCorsConfiguration.md) for a more detailed guide.

##### Rendering Templates

MVC probably is one of the most well-known design patterns in software
development. In web application, the view layer is normally implemented
using some template engine. **oasis/http** uses [Twig] as the primary
template engine. This is a consistent decision in line with [Silex] and
[Symfony].

We will not discuss how to write a [Twig] template in this
documentation. But below is an example of how to enable twig support:

```php
<?php

/** @var Symfony\Component\Security\Core\User\User $user */
$config = [
    "twig" => [
        "template_dir" => "path to your twig templates",
        "asset_base" => "http://my.domain.tld/assets/",
        "globals" => [
            "user" => $user,
        ], // global varialbes accessible in twig
    ],
];

// initialize SilexKernel with $config

```

##### Middlewares

[Silex] allows you to run code, that changes the default [Silex]
behavior, at different stages during the handling of a request through
*[Middleware]*.

In addition to the [Silex] way of adding middleware on-the-fly, you may
also implement the `Oasis\Mlib\Http\Middlewares\MiddlewareInterface` and
install the middleware during bootstrap phase:

```php
<?php
/** @var Oasis\Mlib\Http\Middlewares\MiddlewareInterface $mid */
$config = [
    "middlewares" => [
        $mid,
    ],
];

// initialize SilexKernel with $config

```

##### Providers

[Service provider] (http://silex.sensiolabs.org/doc/master/providers.html#service-providers)
is the mechanism that [Silex] allow the developer to reuse parts of an
application into another one.

Service providers can either be installed on-the-fly using the
`register` method provided by [Silex]:

```php
<?php

use Oasis\Mlib\Http\SilexKernel;
use Silex\Provider\HttpCacheServiceProvider;

/** @var SilexKernel $kernel */
$kernel->register(new HttpCacheServiceProvider());

```

or be installed using bootstrap configuration parameter `providers`:

```php
<?php

use Silex\Provider\HttpCacheServiceProvider;

$config = [
    "providers" => [
        new HttpCacheServiceProvider(),
    ],
];

// initialize SilexKernel with $config

```

##### View Handler

A *view handler* is a callable object (i.e. implementing the
`__invoke()` magic method), that is called when route controller does
not return a valid `Symfony\Component\HttpFoundation\Response` object.

An example of the view handler is as below:

```php
<?php

use Symfony\Component\HttpFoundation\JsonResponse;

class MyViewHandler
{
    function __invoke($rawResult, Symfony\Component\HttpFoundation\Request $request)
    {
        return new JsonResponse($rawResult);
    }
}

```

The __invoke function takes a raw result and returns a
`Symfony\Component\HttpFoundation\Response` object, which is the valid
response type for [Silex].

> **NOTE**: if the view handler does not return an object of
> `Symfony\Component\HttpFoundation\Response` or its descendant class,
> the returned value will be passed into the next view handler if
> provided. This cycle will stop if there is no more view handler, or if
> a valid `Response` object is returned.  

To install a view handler, we can either register it through `view()`
method of `SilexKernel`:

```php
<?php

use Oasis\Mlib\Http\SilexKernel;

/** @var SilexKernel $kernel*/
/** @var callable $viewHandler*/
$kernel->view($viewHandler);

```

or using `view_handlers` bootstrap configuration parameter:

```php
<?php

/** @var callable $viewHandler*/
$config = [
    "view_handlers" => [
        $viewHandler,
    ],
];

// initialize SilexKernel with $config

```

##### Error Handler

Like *view handler*, an *error handler* is a callable object, that is
called when any exception is thrown during request processing. This does
not only limit to route controller execution, but also includes the
before and after middleware phases.

An error handler should take an `\Exception` object and an HTTP code as
its arguments. The return value could be anything. And if the returned
value is not of `Symfony\Component\HttpFoundation\Response` type, it
will go through the *[view handlers](#view-handler)* as well.

Example:

```php

class MyErrorHandler
{
    function __invoke(\Exception $e, $code)
    {
        return [
            'message' => $e->getMessage(),
            'code' => $code,
        ];
    }
}

```

To install an error handler, we can either register it through `error()`
method of `SilexKernel`:

```php
<?php

use Oasis\Mlib\Http\SilexKernel;

/** @var SilexKernel $kernel*/
/** @var callable $errorHandler*/
$kernel->error($errorHandler);

```

or using `error_handlers` bootstrap configuration parameter:

```php
<?php

/** @var callable $errorHandler*/
$config = [
    "error_handlers" => [
        $errorHandler,
    ],
];

// initialize SilexKernel with $config

```

##### Injected Arguments

When we implement route controller, we always need access to different
kind of objects, such as a DB connection, a Cache instance, the Request
object, or sometimes the SilexKernel itself.

**oasis/http** maintains a list of injectable arguments, and performs a
type check before a controller is invoked: it will go through each
type-hinted argument of the controller, and check if there is an object
in the injectable list that is an instance of that type. If so, this
argument will be passed into the controller. If no injectable argument
is found, a `\RuntimeException` will be thrown.

By default, **oasis/http** will inject the following objects:

- `Symfony\Component\HttpFoundation\Request` object: the current request
  being processed
- `Oasis\Mlib\Http\SilexKernel` object: the kernel object itself

To have access to other variables, we can inject them as candidates for
controller arguments by using `injected_args` bootstrap configuration
parameter:

```php
<?php

use Symfony\Component\HttpFoundation\Request;

/** @var \Memcached $memcached */
$config = [
    "injected_args" => [
        'cache' => $memcached,
    ],
];

// initialize SilexKernel with $config

class MyController
{
    public function testAction(\Memcached $cache, Request $request)
    {
    }
}

```

In the example above, `testAction()` will be called with an `\Memcached`
object and the current request

> **NOTE**: the order of arguments in the controller doesn't matter,
> only their types matter

[Silex]: http://silex.sensiolabs.org/ "Silex Micro-Framework"
[Symfony]: https://symfony.com/components "Symfony Components"
[Pimple]: http://pimple.sensiolabs.org/ "Simple PHP DI Container"
[Twig]: http://twig.sensiolabs.org/ "Twig template engine"
[Middleware]: http://silex.sensiolabs.org/doc/master/middlewares.html
[Silex Web Server Configuration]: http://silex.sensiolabs.org/doc/master/web_servers.html
[symfony/routing]: http://symfony.com/doc/current/components/routing/introduction.html "Symfony Routing Component"

