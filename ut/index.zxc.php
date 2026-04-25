<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 17:36
 */

use Composer\Autoload\ClassLoader;
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;

/** @var ClassLoader $loader */
$loader = require_once __DIR__ . "/../vendor/autoload.php";
$loader->addPsr4("Oasis\\Mlib\\Http\\Test\\Helpers\\", __DIR__);
$loader->register();

$config = [
    "routing" =>
        [
            "path"       => __DIR__ . "/zxc/routes.yml",
            "namespaces" => ["Oasis\\Mlib\\Http\\Test\\Helpers\\"],
        ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
];

$kernel = new MicroKernel(
    $config,
    true
);

$kernel->run();
