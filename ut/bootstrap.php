<?php
use Oasis\Mlib\Logging\LocalFileHandler;

require __DIR__ . "/../vendor/autoload.php";

error_reporting(E_ALL);
(new LocalFileHandler('/tmp'))->install();
