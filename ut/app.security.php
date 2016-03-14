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
use Oasis\Mlib\Http\Ut\Security\TestApiUserPreAuthenticator;
use Oasis\Mlib\Http\Ut\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Ut\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Ut\TestPreAuthAuthenticationListener;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;

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

$provider = new SimpleSecurityProvider();
$provider->addAuthenticationPolicy('mauth', $secPolicy);

$app->service_providers = [
    [
        $provider,
        [
            'security.firewalls'                               => [
                "admin"         => [
                    "pattern" => "^/secured/admin",
                    "http"    => true,
                    "users"   => $users,
                ],
                "form.admin"    => [
                    "pattern" => "^/secured/fadmin",
                    "form"    => [
                        "login_path" => "/secured/flogin",
                        "check_path" => "/secured/fadmin/check",
                    ],
                    "users"   => $users,
                ],
                //"preauth.admin" => [
                //    "pattern"  => "^/secured/padmin",
                //    "pre_auth" => true,
                //    "users"    => $users,
                //],
                "minhao.admin"  => [
                    "pattern" => "^/secured/madmin",
                    "mauth"   => true,
                    "users"   => $preUsers,
                ],
            ],
        ],
    ],
    new SessionServiceProvider(),
];

return $app;
