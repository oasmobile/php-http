<?php
/**
 * MicroKernel configuration for cross-community integration tests.
 *
 * Provides Cookie provider + Middleware + basic routing for testing
 * MicroKernel interactions with Cookie, Middlewares, and Configuration modules.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$testMiddleware = new TestMiddleware();

$config = [
    'cache_dir'      => __DIR__ . '/../cache',
    'routing'        => [
        'path'       => __DIR__ . '/integration.routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Integration\\',
        ],
    ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
    'middlewares'     => [$testMiddleware],
];

$app = new MicroKernel($config, true);

// Store the middleware instance so tests can access it for assertions
// We use addExtraParameters as a simple key-value store
$app->addExtraParameters(['test.middleware' => $testMiddleware]);

return $app;
