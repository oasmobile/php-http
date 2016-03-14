<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAuthenticationPolicy;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\Security\TestAccessRule;
use Oasis\Mlib\Http\Ut\Security\TestApiUserPreAuthenticator;
use Oasis\Mlib\Http\Ut\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationFirewall;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Ut\TestPreAuthAuthenticationListener;
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
$secPolicy->setPreAuthenticator(new TestApiUserPreAuthenticator());

$testFirewall = new TestAuthenticationFirewall();

$provider = new SimpleSecurityProvider();
$provider->addAuthenticationPolicy('mauth', $secPolicy);
$provider->addFirewall(
    "admin",
    [
        "pattern" => "^/secured/admin",
        "http"    => true,
        "users"   => $users,
    ]
);
$provider->addFirewall(
    "form.admin",
    [
        "pattern" => "^/secured/fadmin",
        "form"    => [
            "login_path" => "/secured/flogin",
            "check_path" => "/secured/fadmin/check",
        ],
        "users"   => $users,
    ]
);
$provider->addFirewall("minhao.admin", $testFirewall);
$provider->addAccessRule(new TestAccessRule());

$app->service_providers = [
    [
        $provider,
        [
            //'security.access_rules' => [
            //    ['^/secured/madmin', 'ROLE_ADMIN', 'http'],
            //],
        ],
    ],
    new SessionServiceProvider(),
];

return $app;
