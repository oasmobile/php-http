<?php
/**
 * Twig test bootstrap — MicroKernel with twig config via Bootstrap_Config.
 * This file tests the configuration-driven initialization path.
 */
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\TwigHelper;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir'      => sys_get_temp_dir() . '/oasis-http-ut',
    'routing'        => [
        'path'       => __DIR__ . '/../routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'twig'           => [
        'template_dir' => __DIR__ . '/templates',
        'cache_dir'    => '/tmp/twig_cache',
        'asset_base'   => 'http://163.com/img',
        'globals'      => [
            'helper' => new TwigHelper(),
        ],
    ],
    'view_handlers'  => new JsonViewHandler(),
    'error_handlers' => new JsonErrorHandler(),
];

$app = new MicroKernel($config, true);

return $app;
