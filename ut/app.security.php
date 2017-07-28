<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\Security\TestAccessRule;
use Oasis\Mlib\Http\Ut\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationPolicy;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\HttpFoundation\RequestMatcher;

$users = [
    "admin"  => [
        "ROLE_ADMIN",
        
        // this is for BCrypt encoder, which is default for silex 2
        '$2y$10$EY4SlT0KGCg4066H23gBYuKorAu0b/oSvrlMj4yaGHo50QQsXTOU2',
        
        // this is for MessageDigestPasswordEncoder, which is default for silex 1.3
        //"Eti36Ru/pWG6WfoIPiDFUBxUuyvgMA4L8+LLuGbGyqV9ATuT9brCWPchBqX5vFTF+DgntacecW+sSGD+GZts2A==",
    ],
    //"admin2" => [
    //    "ROLE_ADMIN",
    //    "5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==",
    //],
];

$preUsers = new TestApiUserProvider();

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.php";

$secPolicy = new TestAuthenticationPolicy();

$testFirewall = new SimpleFirewall(
    [
        "pattern"  => "^/secured/madmin",
        "policies" => [
            "mauth" => true,
        ],
        "users"    => new TestApiUserProvider(),
    
    ]
);

$provider = new SimpleSecurityProvider();
$provider->addAuthenticationPolicy('mauth', $secPolicy);
$provider->addFirewall(
    "admin",
    [
        "pattern"  => "^/secured/admin",
        "policies" => ["http" => true],
        "users"    => $users,
    ]
);
$provider->addFirewall(
    "form.admin",
    [
        "pattern"  => "^/secured/fadmin",
        "policies" => [
            "form" => [
                "login_path" => "/secured/flogin",
                "check_path" => "/secured/fadmin/check",
            ],
        ],
        "users"    => $users,
    ]
);
$provider->addFirewall("minhao.admin", $testFirewall);
$provider->addAccessRule(new TestAccessRule('^/secured/madmin/admin', 'ROLE_ADMIN'));
$provider->addAccessRule(
    new TestAccessRule(
        new RequestMatcher('^/secured/madmin/parent', "bai(du|da)\\.com"), ['ROLE_PARENT']
    )
);
$provider->addAccessRule(
    new TestAccessRule(
        new RequestMatcher('^/secured/madmin/child'),
        'ROLE_CHILD'
    )
);
$provider->addAccessRule(new TestAccessRule('^/secured/madmin', 'ROLE_USER'));

$provider->addRoleHierarchy('ROLE_GOOD', 'ROLE_USER');
$provider->addRoleHierarchy('ROLE_CHILD', 'ROLE_USER');
$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_CHILD');
$provider->addRoleHierarchy('ROLE_PARENT', 'ROLE_USER');

$app->service_providers = [
    $provider,
    new SessionServiceProvider(),
];

return $app;
