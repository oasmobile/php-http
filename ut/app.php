<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\JsonErrorViewHandler;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config              = [
    'routing' => [
        'path'       => __DIR__ . "/routes.yml",
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Ut',
        ],
    ],
];
$app                 = new SilexKernel($config, true);
$app->view_handlers  = [
    new JsonViewHandler(),
];
$app->error_handlers = [
    new JsonErrorViewHandler(),
];

return $app;
