<?php
/**
 * SilexKernel configuration for cross-community integration tests (R11).
 *
 * Provides Cookie provider + Middleware + basic routing for testing
 * SilexKernel interactions with Cookie, Middlewares, and Configuration modules.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir' => __DIR__ . '/../cache',
    'routing'   => [
        'path'       => __DIR__ . '/integration.routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Integration\\',
        ],
    ],
];

$app = new SilexKernel($config, true);

$app->view_handlers  = [new JsonViewHandler()];
$app->error_handlers = [new JsonErrorHandler()];

// --- Middleware registration (R11 AC 2) ---
// TestMiddleware records before/after calls for assertion in tests.
// Store the middleware instance in the container so tests can access it.
$testMiddleware       = new TestMiddleware();
$app['test.middleware'] = $testMiddleware;
$app->middlewares     = [$testMiddleware];

return $app;
