<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 14:54
 */
use Oasis\Mlib\Http\SilexKernel;

require_once __DIR__ . "/bootstrap.php";

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.cors.php";

$app->run();
