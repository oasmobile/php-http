<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Silex\Provider\SecurityServiceProvider;

$app                    = require __DIR__ . "/app.php";
$app->service_providers = [
    new SecurityServiceProvider(),
];

return $app;
