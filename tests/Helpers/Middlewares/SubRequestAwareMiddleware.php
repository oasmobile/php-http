<?php
declare(strict_types=1);

/**
 * Middleware with configurable master-request-only filtering.
 *
 * Used by MiddlewareScenarioTest to verify that onlyForMasterRequest()
 * controls whether the middleware executes for sub-requests.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubRequestAwareMiddleware extends AbstractMiddleware
{
    private bool $masterOnly;

    /** @var int Number of times before() was called */
    private int $beforeCallCount = 0;

    public function __construct(bool $masterOnly = true)
    {
        $this->masterOnly = $masterOnly;
    }

    public function onlyForMasterRequest(): bool
    {
        return $this->masterOnly;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        $this->beforeCallCount++;
        $request->attributes->set('sub_request_middleware_called', true);

        return null;
    }

    public function after(Request $request, Response $response): void
    {
        // no-op
    }

    public function getBeforeCallCount(): int
    {
        return $this->beforeCallCount;
    }

    public function reset(): void
    {
        $this->beforeCallCount = 0;
    }
}
