<?php
declare(strict_types=1);

/**
 * Before middleware that short-circuits by returning a Response.
 *
 * Used by MiddlewareScenarioTest to verify that when a before middleware
 * returns a Response, the controller is not executed.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShortCircuitMiddleware extends AbstractMiddleware
{
    private int $statusCode;
    private int $beforePriority;

    public function __construct(int $statusCode = 200, int $beforePriority = MicroKernel::BEFORE_PRIORITY_EARLIEST)
    {
        $this->statusCode     = $statusCode;
        $this->beforePriority = $beforePriority;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        return new JsonResponse(
            ['short_circuited' => true, 'middleware' => 'ShortCircuitMiddleware'],
            $this->statusCode,
        );
    }

    public function after(Request $request, Response $response): void
    {
        // no-op
    }

    public function getBeforePriority(): int|false
    {
        return $this->beforePriority;
    }

    public function getAfterPriority(): int|false
    {
        return false; // no after phase
    }
}
