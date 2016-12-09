<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 11:53
 */
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\TwigHelper;

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.security.php";

$app['twig.config'] = [
    "template_dir" => __DIR__ . "/templates",
    "cache_dir"    => "/tmp/twig_cache",
    "asset_base"   => "http://163.com/img",
    "globals"      => [
        "helper" => new TwigHelper(),
    ],
];

return $app;
