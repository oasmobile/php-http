<?php
/**
 * Created by PhpStorm.
 *
 * This file returns a SilexKernel configured using configuration, which is sutiable for Yaml DI file
 *
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationPolicy;
use Silex\Provider\SessionServiceProvider;

$users = [
    "admin"  => [
        "ROLE_ADMIN",
        "Eti36Ru/pWG6WfoIPiDFUBxUuyvgMA4L8+LLuGbGyqV9ATuT9brCWPchBqX5vFTF+DgntacecW+sSGD+GZts2A==",
    ],
    "admin2" => [
        "ROLE_ADMIN",
        "5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==",
    ],
];

$preUsers = new TestApiUserProvider();

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.php";

$secPolicy = new TestAuthenticationPolicy();

//$testFirewall = new TestAuthenticationFirewall();
$testFirewall = new SimpleFirewall(
    [
        "pattern"  => "^/secured/madmin",
        "policies" => [
            "mauth" => true,
        ],
        "users"    => new TestApiUserProvider(),

    ]
);

$provider = new SimpleSecurityProvider(
    [
        'policies'     => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls'    => [
            'minhao.admin' => [
                "pattern"  => "^/secured/madmin",
                "policies" => [
                    "mauth" => true,
                ],
                "users"    => new TestApiUserProvider(),
            ],
            "admin"        => [
                "pattern"  => "^/secured/admin",
                "policies" => [
                    "http" => true,
                ],
                "users"    => $users,
            ],
            "form.admin"   => [
                "pattern"  => "^/secured/fadmin",
                "policies" => [
                    "form" => [
                        "login_path" => "/secured/flogin",
                        "check_path" => "/secured/fadmin/check",
                    ],
                ],
                "users"    => $users,
            ],
        ],
        'access_rules' => [
            [
                'pattern' => '^/secured/madmin/admin',
                'roles'   => 'ROLE_ADMIN',
            ],
            [
                'pattern' => '^/secured/madmin/parent',
                'roles'   => ['ROLE_PARENT'],
            ],
            [
                'pattern' => '^/secured/madmin/child',
                'roles'   => 'ROLE_CHILD',
            ],
            [
                'pattern' => '^/secured/madmin',
                'roles'   => 'ROLE_USER',
            ],
        ],
    ]
);

$provider->addRoleHierarchy('ROLE_GOOD', 'ROLE_USER');
$provider->addRoleHierarchy('ROLE_CHILD', 'ROLE_USER');
$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_CHILD');
$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_USER');

$app->service_providers = [
    $provider,
    new SessionServiceProvider(),
];

return $app;