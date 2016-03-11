<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\SimpleAuthenticationPolicy;
use Oasis\Mlib\Http\ServiceProviders\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
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
/** @var SilexKernel $app */
$app = require __DIR__ . "/app.php";

$secPolicy = new SimpleAuthenticationPolicy();
$secPolicy->setAnonymousAllowed(false);
$secPolicy->setEntryPointFactory(
    function ($app, $name) {
        return new BasicAuthenticationEntryPoint($name);
    }
);
$secPolicy->setListnerFactory(
    function () {
        return new TestPreAuthAuthenticationListener();
    }
);
$secPolicy->setAuthenticationType(SimpleAuthenticationPolicy::AUTH_TYPE_HTTP);

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
                "preauth.admin" => [
                    "pattern"  => "^/secured/padmin",
                    "pre_auth" => true,
                    "users"    => $users,
                ],
                "minhao.admin"  => [
                    "pattern" => "^/secured/madmin",
                    "mauth"   => true,
                    "users"   => $users,
                ],
            ],
            'security.authentication_listener.pre_auth._proto' => $app->protect(
                function () use ($app) {
                    return $app->share(
                        function () {
                            return new TestPreAuthAuthenticationListener();
                        }
                    );
                }
            ),
        ],
    ],
    new SessionServiceProvider(),
];

return $app;
