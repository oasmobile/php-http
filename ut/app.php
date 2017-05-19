<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config              = [
    'routing' => [
        'path'       => __DIR__ . "/routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Ut\\Controllers\\',
        ],
    ],
];
$app                 = new SilexKernel($config, true);
$app->view_handlers  = [
    new JsonViewHandler(),
];
$app->error_handlers = [
    new JsonErrorHandler(),
];
//$app->addControllerInjectedArg(new JsonViewHandler());
$app->injected_args   = [new JsonViewHandler()];
$app->trusted_proxies = [
    '127.0.0.1',
    '1.2.3.4',
    '5.6.7.8/16',
];
return $app;
