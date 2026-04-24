<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config              = [
    'cache_dir'            => isset($cacheDir) ? $cacheDir : __DIR__ . '/cache',
    'routing'              => [
        'path'       => __DIR__ . "/routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'view_handlers'  => [
        new JsonViewHandler(),
    ],
    'error_handlers' => [
        new JsonErrorHandler(),
    ],
    'injected_args'  => [new JsonViewHandler()],
    'trusted_proxies' => [
        '127.0.0.1',
        '1.2.3.4',
        '5.6.7.8/16',
    ],
];
$app                 = new MicroKernel($config, true);

return $app;
