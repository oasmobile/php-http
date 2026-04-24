<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingStrategy;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir' => __DIR__ . '/../cache',
    'routing'   => [
        'path'       => __DIR__ . "/../routes.yml",
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
    'cors' => [
        [
            'pattern' => '/cors/.*',
            'origins' => ['localhost', 'baidu.com', "cors.oasis.mlib.com"],
            'headers' => ['CUSTOM_HEADER', 'custom_header2', 'CUSTOM_HEADER3', 'CUSTOM_HEADER4'],
        ],
        new CrossOriginResourceSharingStrategy(
            [
                'pattern'             => '*',
                'origins'             => '*',
                'credentials_allowed' => true,
                'headers_exposed'     => ['name', 'job', 'content-types'],
            ]
        ),
    ],
];

$app = new MicroKernel($config, true);

return $app;
