<?php
/**
 * Twig test bootstrap — MicroKernel with twig config (without cache_dir).
 */
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\TwigHelper;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir'      => isset($cacheDir) ? $cacheDir : __DIR__ . '/../cache',
    'routing'        => [
        'path'       => __DIR__ . '/../routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'twig'           => [
        'template_dir' => __DIR__ . '/templates',
        'globals'      => [
            'helper' => new TwigHelper(),
        ],
    ],
    'view_handlers'  => [
        new JsonViewHandler(),
    ],
    'error_handlers' => [
        new JsonErrorHandler(),
    ],
];

$app = new MicroKernel($config, true);

return $app;
