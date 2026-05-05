<?php
declare(strict_types=1);

/**
 * Controller for Error Handling scenario tests.
 *
 * Provides actions that throw various exceptions to verify
 * error handler chain behavior, short-circuit, passthrough,
 * and HTTP exception status code preservation.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ScenarioErrorHandlerController
{
    /**
     * Simple action that returns a success response.
     * Used to verify normal flow when no exception occurs.
     */
    public function success(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'           => 'success',
            'controller_called' => true,
        ]);
    }

    /**
     * Action that throws a generic RuntimeException.
     * Used to verify custom error handler receives the exception.
     */
    public function throwRuntime(Request $request): never
    {
        throw new \RuntimeException('Scenario runtime error');
    }

    /**
     * Action that throws an AccessDeniedHttpException (403).
     * Used to verify HTTP exception status code preservation.
     */
    public function throwForbidden(Request $request): never
    {
        throw new AccessDeniedHttpException('Access denied for scenario test');
    }
}
