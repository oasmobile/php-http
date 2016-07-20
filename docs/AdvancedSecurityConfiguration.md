# Advanced Security Configuration

When boostrapping `security` configuratin, there are following sections that can be configured:

- [policies](#policies)
- [firewalls](#firewalls)
- [access_rules](#access-rules)
- [role_hierarchy](#roles-and-hierarchies)

A full featured yet not so complicated example:

```php
<?php

/** @var Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface $customPolicy */
/** @var Oasis\Mlib\Http\ServiceProviders\Security\SimplePreAuthenticateUserProviderInterface $customUserProvider */

$config = [
    'security' => [
        'policies' => [
            "my_policy" => $customPolicy,
        ],
        'firewalls' => [
            "admin_area" => [
                "pattern" => "^/admin/.*",
                "policies" => [
                    "my_policy" => true,
                ],
                "users" => $customUserProvider,
                "stateless" => false,
            ],
            "user_area" => [
                "pattern" => "^/user/.*",
                "policies" => [
                    "my_policy" => true,
                ],
                "users" => $customUserProvider,
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
            "user_rule" => [
                "pattern" => "^/user/.*",
                "roles" => [
                    "ROLE_USER",
                ],
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

### Policies

This setting is used to inject different kinds of customized policy. It is a key-value array, while keys are names of policies and values being policy objects implementing the `Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface`.

Policy names can then be referred to in firewall settings. By default, [Silex] has provided a few useful policies already. These names of built-in policies should not be defined here:

- logout
- pre_auth
- form
- http
- remember_me
- anonymous

We have a dedicated document about [how to write your own security policy](CustomSecurityPolicy.md).

### Firewalls

We protect certain resources behind different types of firewalls. The `firewalls` section is an array of firewall configurations. The key of the array is the name of firewall (which is just informative), and the value is an array whose values comply with the following rule:

Name        | Type          | Description
---         | ---           | ---
pattern     | string &#124; RequestMatcher  | pattern to match the request, only request that fullfils the pattern will have this fireall enabled
policies    | array         | name is the policy name, and value is either `true` or options for the policy. <br />**NOTE**: order of policies are retained during processing
users       | array &#124; UserProviderInterface    | a user provider that can return object of type `UserInterface`, or an array of users whose key is username and value is user roles and passowrd
stateless   | bool          | *Default: `false`* <br />Whether to use session to store the `SecurityToken`<br />**NOTE:** this value should be `true` when using `form` policy
misc        | array         | *Default: `[] empty array`* <br /> anything else that the firewall's policies may be interested of

The purpose of a **firewall** is to process the request, and retrieve the coresponding `SecurityToken` either by creating one or restoring one. The core concept of a token is that it stores all the user **[ROLES](#roles-and-hierarchies)**.

It should be **NOTICED** that even if a request fails all firewall authentication (i.e. no token is retrieved), the request will still continue to hit the controller. And this time, with a `null` token and an empty list of **[ROLES](#roles-and-hierarchies)**.

As a result, we would need a further mechanism to decide if certain **ROLE**s are allowed to proceed. One ready made solution is the [**access rule**](#access-rules).

### Access Rules

Under the `acess_rules` configuration section of `security`, we can define an array of access rules. The keys are just unique names to distinguish the rules, and each value is an array of rule that complies to the following scheme:

Name        | Type          | Description
---         | ---           | ---
pattern     | string &#124; RequestMatcher  | pattern to match the request, only request that fullfils the pattern will have the access rule tested
roles       | string &#124; array   | roles that has access to this resouce. If more than one roles are provided, the request sender should have all the roles listed.
channel     | enum          |  *Default: `null`* <br />Enumeration of `null`, "http" or "https". Restricts the resource to be access only by certain scheme. `null` means no restriction.

If an access rule is activated (i.e. pattern matches the request) and un-authorized (i.e. sender doesn't have all requested roles), an `Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException` is thrown. By default, this will let the kernel to issue a 403 `Response`

### Roles and hierarchies

A role is a string starting with **"ROLE_"**, and followed by only alphabetcial-numeric characters or underscore. By convention, letters in a role are all in capital case.

In practice, roles are not irrelevant to each other. There is normally a predefined hierarchies in every application. The `role_hierarchy` section of `security` boostrap configuration is used for this purpose:

The `role_hierarchy` configuration is an array, whose keys are names of roles, while the values are arrays of sub-roles that belongs to the key-role. When a request has the key role, it is considered that it also poccesses all the children roles.

To test if a request has certain role, we will need the `SilexKernel` object:

```php
<?php

/** @var Oasis\Mlib\Http\SilexKernel $kernel */
if ($kernel->isGranted('ROLE_ADMIN')) {
    // do something if request has role ROLE_ADMIN
}

```
