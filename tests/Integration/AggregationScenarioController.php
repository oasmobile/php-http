<?php
declare(strict_types=1);

/**
 * Controller for MicroKernel aggregation scenario tests.
 *
 * Provides actions that exercise the full pipeline (routing + security +
 * CORS + middleware + view handler + error handler) and other aggregation
 * layer behaviors.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;

class AggregationScenarioController
{
    /**
     * Full pipeline action — returns authentication and middleware state.
     * Used to verify the complete pipeline traversal.
     */
    public function fullPipeline(MicroKernel $app, Request $request): array
    {
        return [
            'controller_called' => true,
            'action'            => 'fullPipeline',
            'user'              => $app->getUser()?->getUserIdentifier(),
            'is_authenticated'  => $app->isGranted('IS_AUTHENTICATED_FULLY'),
            'middleware_marker'  => $request->attributes->get('middleware_marker', null),
        ];
    }

    /**
     * Minimal action — just returns success.
     * Used to verify basic request-response cycle with minimal config.
     */
    public function minimal(): array
    {
        return [
            'controller_called' => true,
            'action'            => 'minimal',
        ];
    }

    /**
     * Injected arg action — receives a custom injected object and returns its value.
     * Used to verify addControllerInjectedArg() works.
     */
    public function injectedArg(AggregationTestService $service): array
    {
        return [
            'controller_called' => true,
            'action'            => 'injectedArg',
            'service_value'     => $service->getValue(),
        ];
    }

    /**
     * Slow action — sleeps to simulate a slow request.
     * Used to verify slow request detection behavior.
     */
    public function slow(): array
    {
        // Sleep 60ms to exceed a low threshold set in the test
        usleep(60_000);

        return [
            'controller_called' => true,
            'action'            => 'slow',
        ];
    }
}
