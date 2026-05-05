<?php
declare(strict_types=1);

/**
 * Controller for Routing scenario tests.
 *
 * Provides simple actions that return JSON responses for verifying
 * route matching, parameter replacement, and programmatic injection.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ScenarioRoutingController
{
    public function home(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'     => 'home',
            'controller' => 'ScenarioRoutingController',
        ]);
    }

    public function about(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'     => 'about',
            'controller' => 'ScenarioRoutingController',
        ]);
    }

    public function paramAction(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'     => 'param',
            'controller' => 'ScenarioRoutingController',
        ]);
    }

    public function injected(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'     => 'injected',
            'controller' => 'ScenarioRoutingController',
        ]);
    }

    public function mixed(Request $request): JsonResponse
    {
        return new JsonResponse([
            'action'     => 'mixed',
            'controller' => 'ScenarioRoutingController',
            'source'     => 'programmatic',
        ]);
    }
}
