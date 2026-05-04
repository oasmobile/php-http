<?php
declare(strict_types=1);

/**
 * Controller for Middleware scenario tests.
 *
 * Provides simple actions that return JSON responses for verifying
 * middleware execution order, short-circuit behavior, and exception handling.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ScenarioMiddlewareController
{
    /**
     * Simple action that returns a greeting.
     * Used to verify middleware executes before/after this controller.
     */
    public function hello(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'           => 'hello',
            'controller'       => 'ScenarioMiddlewareController',
            'controller_called' => true,
        ]);
    }

    /**
     * Echo action that returns request attributes for inspection.
     * Middleware can set request attributes that this controller echoes back.
     */
    public function echo(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'            => 'echo',
            'controller_called' => true,
            'middleware_marker'  => $request->attributes->get('middleware_marker', null),
        ]);
    }
}
