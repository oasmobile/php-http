<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 11:53
 */
use Oasis\Mlib\Http\ServiceProviders\Twig\SimpleTwigServiceProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Ut\TwigHelper;

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.security.php";

$app->register(
    new SimpleTwigServiceProvider(
        [
            "template_dir" => __DIR__ . "/templates",
            "cache_dir"    => "/tmp/twig_cache",
            "globals"      => [
                "helper" => new TwigHelper(),
            ],
        ]
    )
);

return $app;
