<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Test\Security\SessionServiceProvider;
use Symfony\Component\HttpFoundation\RequestMatcher;

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
$app = require __DIR__ . "/../app.php";

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
$app->service_providers = [
    new CrossOriginResourceSharingProvider(),
];
$app['cors.strategies'] = [
    //new CrossOriginResourceSharingStrategy(
    [
        'pattern' => '/secured/madmin/.*',
        'origins' => ['localhost', 'baidu.com', "cors.oasis.mlib.com"],
        'headers' => ['CUSTOM_HEADER', 'custom_header2', 'CUSTOM_HEADER3', 'CUSTOM_HEADER4'],
    ]
    //),
    ,
    //new CrossOriginResourceSharingStrategy(
    //    [
    //        'pattern'             => '*',
    //        'origins'             => '*',
    //        'credentials_allowed' => true,
    //        'headers_exposed'     => ['name', 'job', 'content-types'],
    //    ]
    //),
];
return $app;
