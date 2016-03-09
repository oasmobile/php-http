<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 17:36
 */

use Composer\Autoload\ClassLoader;
use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Silex\Provider\UrlGeneratorServiceProvider;

/** @var ClassLoader $loader */
$loader = require_once __DIR__ . "/../vendor/autoload.php";
$loader->addPsr4("Oasis\\Mlib\\Http\\Ut\\", __DIR__);
$loader->register();

$config = [
    "routing" =>
        [
            "path"       => __DIR__ . "/zxc/routes.yml",
            "namespaces" => ["Oasis\\Mlib\\Http\\Ut\\"],
        ],
];

$kernel = new SilexKernel(
    $config,
    true
);

$kernel->view(new JsonViewHandler());
$kernel->error(new JsonErrorHandler());

$kernel->run();
