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

$hashed = password_hash('1234', PASSWORD_DEFAULT);
var_dump($hashed);

var_dump(password_verify('1234', $hashed));

