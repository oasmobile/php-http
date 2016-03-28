<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 16:53
 */

use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Request;

/** @noinspection PhpIncludeInspection */
require_once __DIR__ . "/bootstrap.php";

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.security2.php";

$app->run(Request::create("/secured/admin"));

/** @var SilexKernel $app */
$app = require __DIR__ . "/app.security.php";

$app->run(Request::create("/secured/admin"));
