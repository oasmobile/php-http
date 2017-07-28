<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:23 PM
 */
use Oasis\Mlib\Http\SilexKernel;

/** @var SilexKernel $app */
$app                     = require __DIR__ . "/../app.php";
$app->trusted_header_set = "HEADER_X_FORWARDED_AWS_ELB";
$app->trusted_proxies    = [
    '3.4.5.6',
];
return $app;
