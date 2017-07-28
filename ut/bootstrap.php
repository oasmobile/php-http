<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 10:57
 */
use Composer\Autoload\ClassLoader;
use Monolog\Logger;
use Oasis\Mlib\Logging\ConsoleHandler;
use Oasis\Mlib\Logging\LocalFileHandler;
use Oasis\Mlib\Logging\MLogging;

///** @var ClassLoader $loader */
//$loader = require __DIR__ . "/../vendor/autoload.php";
//$loader->addPsr4('Oasis\\Mlib\\Http\\Test\\Helpers\\', __DIR__ . "/Helpers");

error_reporting(E_ALL);
//Debug::enable(E_ALL ^ ~E_NOTICE);

(new LocalFileHandler('/tmp'))->install();
//(new ConsoleHandler())->install();
//MLogging::setMinLogLevel(Logger::CRITICAL);
