# **oasis/http**

**oasis/http** is a composer component that provides a simple yet useful
framework for building web applications. This component is built on top
of the [Symfony] MicroKernel, leveraging [Symfony] components for
routing, security, dependency injection, and more.

### Requirements

- PHP >= 8.5

### Installation

Install the latest version with command below:

```bash
$ composer require oasis/http
```

### Web Server Configuration

All examples in this documentation rely on a well-configured web server.

Please refer to [Symfony Web Server Configuration][Symfony Web Server]
for sample web server configurations.

### Basic Usage

The first step of using **oasis/http** is to instantiate an
`Oasis\Mlib\Http\MicroKernel` object.

```php
<?php

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

$config = [
    'routing' => [
        'path' => 'routes.yml',
        'namespaces' => ['App\\Controllers\\'],
    ],
];
$isDebug = true;
$kernel = new MicroKernel($config, $isDebug);

$kernel->run();
```

The `$config` is an array of configuration values. An empty default
value will work fine. However, you can easily instantiate a
well-configured kernel by providing a detailed
[bootstrap configuration](#bootstrap-configuration).

### Bootstrap Configuration

The bootstrap configuration can be categorized into the following parts:

- [routing](#routing)
- [security](#security)
- [cors](#cross-origin-resource-sharing)
- [twig](#rendering-templates)
- [middlewares](#middlewares)
- [view_handlers](#view-handler)
- [error_handlers](#error-handler)
- [injected_args](#injected-arguments)
- [trusted_proxies](#trusted-proxies)

##### Routing

**oasis/http** utilizes the [symfony/routing] component to support
cacheable routing via YAML configuration files.

To achieve this, we will need to configure the bootstrap using the
`routing` parameter, and at the same time supply a routes YAML file.

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

// initialize MicroKernel with $config
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
prefix. The default namespaces are defined under `routing` parameter's
`namespaces` parameter.

Because the `routes.yml` file is fully Symfony compatible, we can use
the advanced routing features as well. Please refer to
[advanced routing configuration](docs/manual/routing.md)
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

// initialize MicroKernel with $config
```

The example above defines a firewall named `http.auth` which protects
resources with path starting like "/admin/". With the `http` policy set
to true, all incoming request must have HTTP Basic authentication. In
the end, a user named admin with password 'foo' is the only user
provided for this firewall.

Please refer to
[detailed security configuration](docs/manual/security.md)
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
    "cors" => [
        [
            'pattern' => '^/cors/.*',
            'origins' => ["my.second.domain.tld"],
        ],
    ],
];

// initialize MicroKernel with $config
```

Using the configuration above, any request for path starting with
"/cors/" will be rejected unless it originates from
`my.second.domain.tld`.

Many more rules can be configured for CORS. Please refer
[here](docs/manual/cors.md) for a more detailed guide.

##### Rendering Templates

MVC probably is one of the most well-known design patterns in software
development. In web application, the view layer is normally implemented
using some template engine. **oasis/http** uses [Twig] as the primary
template engine.

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
        ], // global variables accessible in twig
    ],
];

// initialize MicroKernel with $config
```

##### Middlewares

**oasis/http** allows you to run code, that changes the default
behavior, at different stages during the handling of a request through
*Middleware*.

You may implement the `Oasis\Mlib\Http\Middlewares\MiddlewareInterface`
and install the middleware during bootstrap phase:

```php
<?php
/** @var Oasis\Mlib\Http\Middlewares\MiddlewareInterface $mid */
$config = [
    "middlewares" => [
        $mid,
    ],
];

// initialize MicroKernel with $config
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
`Symfony\Component\HttpFoundation\Response` object.

> **NOTE**: if the view handler does not return an object of
> `Symfony\Component\HttpFoundation\Response` or its descendant class,
> the returned value will be passed into the next view handler if
> provided. This cycle will stop if there is no more view handler, or if
> a valid `Response` object is returned.

To install a view handler, use `view_handlers` bootstrap configuration
parameter:

```php
<?php

/** @var callable $viewHandler*/
$config = [
    "view_handlers" => [
        $viewHandler,
    ],
];

// initialize MicroKernel with $config
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

To install an error handler, use `error_handlers` bootstrap
configuration parameter:

```php
<?php

/** @var callable $errorHandler*/
$config = [
    "error_handlers" => [
        $errorHandler,
    ],
];

// initialize MicroKernel with $config
```

> **NOTE**: If the error handler returns `null`, the same `\Exception`
> object and code will be passed into the next error handler. However,
> any non `null` return value will cause the error handling phase to
> end, and the returned value will either be sent back to client (in
> case its a valid `Symfony\Component\HttpFoundation\Response` object),
> or be passed into the [view handling phase](#view-handler).

##### Injected Arguments

When we implement route controller, we always need access to different
kind of objects, such as a DB connection, a Cache instance, the Request
object, or sometimes the MicroKernel itself.

**oasis/http** maintains a list of injectable arguments, and performs a
type check before a controller is invoked: it will go through each
type-hinted argument of the controller, and check if there is an object
in the injectable list that is an instance of that type. If so, this
argument will be passed into the controller. If no injectable argument
is found, a `\RuntimeException` will be thrown.

By default, **oasis/http** will inject the following objects:

- `Symfony\Component\HttpFoundation\Request` object: the current request
  being processed
- `Oasis\Mlib\Http\MicroKernel` object: the kernel object itself

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

// initialize MicroKernel with $config

class MyController
{
    public function testAction(\Memcached $cache, Request $request)
    {
    }
}
```

In the example above, `testAction()` will be called with an `\Memcached`
object and the current request.

> **NOTE**: the order of arguments in the controller doesn't matter,
> only their types matter

##### Trusted Proxies

When trying to get the IP address of a request, we always need to filter
certain addresses acting as trusted proxies. These proxies forward the
real sender's IP in the HTTP header X-Forwarded-For in a reverse order
(i.e. nearest address in the end). We can use the `trusted_proxies`
setting to specify what addresses should be considered trusted.

The format of `trusted_proxies` is an array of IP addresses. In
addition, you can use CIDR notations in place of IP addresses if you
would like to trust a subnet of IP addresses.

###### AWS ELB Trusted Proxies

In case your server is behind an AWS ELB, you should trust the
REMOTE_ADDR variable as a trusted proxy, as this is the ELB IP.

To make things easier, there is a shortcut setting called `behind_elb`,
which defaults to `false`. If this setting is set to `true`, the direct
IP set in REMOTE_ADDR will be considered trusted, and ignored when
getting IP address from request.

###### AWS Cloudfront Trusted Proxies

There is a setting named `trust_cloudfront_ips`, which defaults to
`false`. If this parameter is set to `true`, all AWS CloudFront IPs will
also be considered as trusted proxies. As a result, `getClientIp()` on a
request will return the first IP address reaching AWS CloudFront.

[Symfony]: https://symfony.com/ "Symfony Framework"
[Twig]: https://twig.symfony.com/ "Twig template engine"
[Symfony Web Server]: https://symfony.com/doc/current/setup/web_server_configuration.html
[symfony/routing]: https://symfony.com/doc/current/components/routing.html "Symfony Routing Component"
