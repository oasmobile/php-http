<?php
/**
 * Twig configuration without cache_dir — for testing the no-cache scenario.
 * Used by TwigServiceProviderTest supplementary tests (R12 AC 4).
 */
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\TwigHelper;

/** @var SilexKernel $app */
$app = require __DIR__ . "/../Security/app.security.php";

$app['twig.config'] = [
    "template_dir" => __DIR__ . "/templates",
    "globals"      => [
        "helper" => new TwigHelper(),
    ],
];

return $app;
