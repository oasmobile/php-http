<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:09
 */
use Oasis\Mlib\Http\ServiceProviders\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\CrossOriginResourceSharingStrategy;

$app                    = require __DIR__ . "/app.php";
$app->service_providers = [
    new CrossOriginResourceSharingProvider(
        [
            new CrossOriginResourceSharingStrategy(
                [
                    'path'    => '/cors/*',
                    'origins' => ['localhost', 'baidu.com', "cors.oasis.mlib.com"],
                    'headers' => ['CUSTOM_HEADER', 'custom_header2', 'CUSTOM_HEADER3', 'CUSTOM_HEADER4'],
                ]
            ),
            new CrossOriginResourceSharingStrategy(
                [
                    'path'                => '*',
                    'origins'             => '*',
                    'credentials_allowed' => true,
                    'headers_exposed'     => ['name', 'job', 'content-types'],
                ]
            ),
        ]
    ),
];

return $app;