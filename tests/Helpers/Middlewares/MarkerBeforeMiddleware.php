<?php
declare(strict_types=1);

/**
 * Before middleware that sets a marker on the request attributes.
 *
 * Used by MiddlewareScenarioTest to verify before middleware executes
 * before the controller (the controller echoes the marker back).
 */

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkerBeforeMiddleware extends AbstractMiddleware
{
    private string $marker;
    private int $beforePriority;

    /** @var list<string> Shared execution log for ordering verification */
    private array $executionLog = [];

    public function __construct(string $marker, int $beforePriority = MicroKernel::BEFORE_PRIORITY_EARLIEST)
    {
        $this->marker         = $marker;
        $this->beforePriority = $beforePriority;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        $request->attributes->set('middleware_marker', $this->marker);
        $this->executionLog[] = $this->marker;

        return null;
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

    /**
     * Inject a shared log array so multiple middleware instances can record
     * their execution order into the same list.
     *
     * @param list<string> &$sharedLog Reference to a shared array
     */
    public function useSharedLog(array &$sharedLog): void
    {
        $this->executionLog = &$sharedLog;
    }

    /** @return list<string> */
    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }
}
