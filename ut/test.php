<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 16:53
 */
use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;

/** @noinspection PhpIncludeInspection */
require_once __DIR__ . "/bootstrap.php";

$a = new WrappedExceptionInfo(new RuntimeException('ld'), 400);
var_dump((array)$a);

