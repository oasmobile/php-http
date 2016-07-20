# Custom Security Policy

This article will show you how to write your own security policy and its coresponding user provider.

### 1. Start with the policy class

To begin, we should have a policy class to represent our custom policy. Fortunately, **[oasis/http]** provides a easy to use abstract class to start with.

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class MyPolicy extends AbstractSimplePreAuthenticationPolicy
{
    /**
     * @return SimplePreAuthenticatorInterface
     */
    public function getPreAuthenticator()
    {
        // TODO: return a SimplePreAuthenticatorInterface object
    }
}

```

Simply by extending the `AbstractSimplePreAuthenticationPolicy` class, we know that the only thing left to be implemented is the `getPreAuthenticator()` method which should return a `SimplePreAuthenticatorInterface` object.

### 2. The authenticator

An authenticator is an intermediate object in [symfony/security] framework. It is used to extract credential information from a `Request`. Similar to what we did with policy, we can extend a provided abstract class to create our own authenticator:

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class MyAuthenticator extends AbstractSimplePreAuthenticator
{
    /**
     * Parse the given request, and extract the credential information from the request
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function getCredentialsFromRequest(Request $request)
    {
        if (!$request->query->has('token')) {
            throw new BadCredentialsException("'token' string is not provided.");
        }
        $token = $request->query->get('token');
        $ip = $request->getClientIp();

        return [
            "ip" => $ip,
            "token" => $token,
        ];
    }
}

```

**NOTE**: the returned value is called the _credentials array_, and it will be used by user provider to generate user.

### 3. Complete the policy class

Since we have created the authenticator class, it is time to complete our policy class:

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class MyPolicy extends AbstractSimplePreAuthenticationPolicy
{
    /**
     * @return SimplePreAuthenticatorInterface
     */
    public function getPreAuthenticator()
    {
        return new MyAuthenticator();
    }
}

```

### 4. The user provider

After we have a policy class, it is time to create our user provider. Again, we extend the built-in `AbstractSimplePreAuthenticateUserProvider` class:

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

class MyUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    /**
     * @param mixed $credentials the credentials extracted from request
     *
     * @return UserInterface
     *
     * @throws AuthenticationException throws authentication exception if authentication by credentials failed
     */
    public function authenticateAndGetUser($credentials)
    {
        $ip = $credentials['ip'];
        $token = $credentials['token'];

        // TODO: retrieve UserInterface object out of token

        return $user;
    }
}

```

As you can see, a **user provider** is used to parse `$credentials` extracted by the **authenticator**, and returns coresponding `UserInterface` user.

Now, all we are left to do is to create a user class.

### 5. The user class

A user class must implement the `Symfony\Component\Security\Core\User\UserInterface`. In addition, it is a convention to call the user class "_sender_" because it naturally identifies the request sender.

An example sender class is like this:

```php
<?php

use Symfony\Component\Security\Core\User\UserInterface;

class MyRequestSender implements UserInterface
{

    protected $userId;
    /**
     * @var string[]
     */
    protected $roles;

    public function __construct($userId, $roles)
    {
        $this->userId  = $userId;
        $this->roles = $roles;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        throw new \LogicException(__FUNCTION__ . " is not supported in " . static::class);
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        throw new \LogicException(__FUNCTION__ . " is not supported in " . static::class);
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        throw new \LogicException(__FUNCTION__ . " is not supported in " . static::class);
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
    }

}

```

As you may have already observed, we have left quite a few methods either empty, or throwing (i.e. throws when called). This is intentional, because we are using the pre-auth policy type (refer to `AbstractSimplePreAuthenticationPolicy` for detail). In real life, if we use other type of policy like form, we will need to implement a different sender class.

### 6. Integrate the user class

Having created the user class, it is time to integrate the user class into the user provider.

```php
<?php

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;

class MyUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    /**
     * @param mixed $credentials the credentials extracted from request
     *
     * @return UserInterface
     *
     * @throws AuthenticationException throws authentication exception if authentication by credentials failed
     */
    public function authenticateAndGetUser($credentials)
    {
        $ip = $credentials['ip'];
        $token = $credentials['token'];
        list($userId, $secret) = explode(".", $token);

        if ($userId < 100) { // users with id less than 100 are admins
            $roles = ["ROLE_ADMIN"];
        }
        else {
            $roles = ["ROLE_USER"];
        }

        return new MyRequestSender($userId, $roles);
    }
}

```

### 7. Use the policy and user provider

At last, it is time to make use of our new policy. To start, we will have to instantiate the policy class as well as the user provider:

```php
<?php

$myPolicy = new MyPolicy();
$provider = new MyUserProvider();

```

With the policy and provider in hand, we can bootstrap the `SilexKernel` and start benefiting from our custom policy:

```php
<?php

$config = [
    'security' => [
        'policies' => [
            "my_policy" => $myPolicy,
        ],
        'firewalls' => [
            "admin_area" => [
                "pattern" => "^/admin/.*",
                "policies" => [
                    "my_policy" => true,
                ],
                "users" => $provider,
                "stateless" => false,
            ],
        ],
        'access_rules' => [
            "admin_rule" => [
                "pattern" => "^/admin/.*",
                "roles" => [
                    "ROLE_ADMIN",
                ],
                "channel" => "https",
            ],
        ],
        'role_hierarchy' => [
            "ROLE_ADMIN" => [
                "ROLE_USER",
                "ROLE_SUPPORT",
            ],
        ],
    ],
];
```

[oasis/http]: ../README.md
[symfony/security]: http://symfony.com/doc/current/components/security/introduction.html "Symfony Security Component"
