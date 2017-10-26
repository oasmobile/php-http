<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:23 PM
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir'            => __DIR__ . '/../cache',
    'trust_cloudfront_ips' => true,
    'behind_elb'           => true,
    'routing'              => [
        'path'       => __DIR__ . "/../routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
];
/** @var SilexKernel $app */
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
//$app->trusted_proxies = [
//    // ELB address
//    '3.4.5.6',
//];
return $app;
