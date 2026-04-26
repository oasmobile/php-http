<?php
/**
 * AWS configuration with behind_elb=true but trust_cloudfront_ips=false.
 * Used by ElbTrustedProxyTest supplementary tests (R12 AC 5).
 *
 * Accepts external $cacheDir to isolate route cache per test.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

if (!isset($cacheDir)) {
    $cacheDir = __DIR__ . '/../cache';
}

$config = [
    'cache_dir'            => $cacheDir,
    'trust_cloudfront_ips' => false,
    'behind_elb'           => true,
    'routing'              => [
        'path'       => __DIR__ . "/../routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
    'injected_args'  => [new JsonViewHandler()],
    'trusted_proxies' => ['127.0.0.1', '1.2.3.4', '5.6.7.8/16'],
];
/** @var MicroKernel $app */
$app = new MicroKernel($config, true);
return $app;
