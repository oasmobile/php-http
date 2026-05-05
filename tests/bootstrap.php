<?php
declare(strict_types=1);

use Oasis\Mlib\Logging\LocalFileHandler;

require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/Helpers/stringErrorHandler.php";

error_reporting(E_ALL);
(new LocalFileHandler('/tmp'))->install();
