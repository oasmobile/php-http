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
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\HttpFoundation\RequestMatcher;

require_once __DIR__ . '/bootstrap.php';

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

$config = [
    'cache_dir'      => sys_get_temp_dir() . "/oasis-http-ut",
    'routing'        => [
        'path'       => __DIR__ . "/routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Ut\\Controllers\\',
        ],
    ],
    'security'       => [
        'policies'       => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls'      => [
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
        'access_rules'   => [
            [
                'pattern' => '^/secured/madmin/admin',
                'roles'   => 'ROLE_ADMIN',
            ],
            [
                'pattern' => new RequestMatcher('^/secured/madmin/parent', "bai(du|da)\\.com"),
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
        'role_hierarchy' => [
            'ROLE_GOOD'   => 'ROLE_USER',
            'ROLE_CHILD'  => ['ROLE_USER'],
            'ROLE_PARENT' => ['ROLE_CHILD', 'ROLE_USER'],
        ],
    ],
    'view_handlers'  => new JsonViewHandler(),
    'error_handlers' => new JsonErrorHandler(),
    'providers'      => new SessionServiceProvider(),
];

$app = new SilexKernel($config, true);
//
//$provider->addRoleHierarchy('ROLE_GOOD', 'ROLE_USER');
//$provider->addRoleHierarchy('ROLE_CHILD', 'ROLE_USER');
//$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_CHILD');
//$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_USER');

return $app;
