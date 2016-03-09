<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 10:57
 */
use Composer\Autoload\ClassLoader;
use Oasis\Mlib\Logging\LocalFileHandler;

//require_once __DIR__ . "/../vendor/autoload.php";

/** @var ClassLoader $loader */
$loader = require_once  __DIR__ . "/../vendor/autoload.php";
$loader->addPsr4('Oasis\\Mlib\\Http\\Ut\\', __DIR__);

error_reporting(E_ALL ^ ~E_NOTICE);

(new LocalFileHandler('/tmp'))->install();
