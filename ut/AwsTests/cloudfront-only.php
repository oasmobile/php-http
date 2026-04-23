<?php
/**
 * AWS configuration with trust_cloudfront_ips=true but behind_elb=false.
 * Used by ElbTrustedProxyTest supplementary tests (R12 AC 5).
 *
 * Accepts external $cacheDir to isolate route cache per test.
 * When trust_cloudfront_ips=true, the cache dir should contain aws.ips
 * to avoid live HTTP requests to AWS.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

if (!isset($cacheDir)) {
    $cacheDir = __DIR__ . '/../cache';
}

$config = [
    'cache_dir'            => $cacheDir,
    'trust_cloudfront_ips' => true,
    'behind_elb'           => false,
    'routing'              => [
        'path'       => __DIR__ . "/../routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
];
/** @var SilexKernel $app */
$app                 = new SilexKernel($config, true);
$app->view_handlers  = [new JsonViewHandler()];
$app->error_handlers = [new JsonErrorHandler()];
$app->injected_args  = [new JsonViewHandler()];
$app->trusted_proxies = ['127.0.0.1', '1.2.3.4', '5.6.7.8/16'];
return $app;
