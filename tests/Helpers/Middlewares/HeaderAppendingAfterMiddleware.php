<?php
declare(strict_types=1);

/**
 * After middleware that appends a custom header to the response.
 *
 * Used by MiddlewareScenarioTest to verify after middleware executes
 * after the controller and can modify the response.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HeaderAppendingAfterMiddleware extends AbstractMiddleware
{
    private string $headerName;
    private string $headerValue;
    private int $afterPriority;

    public function __construct(
        string $headerName = 'X-After-Middleware',
        string $headerValue = 'applied',
        int $afterPriority = MicroKernel::AFTER_PRIORITY_EARLIEST,
    ) {
        $this->headerName    = $headerName;
        $this->headerValue   = $headerValue;
        $this->afterPriority = $afterPriority;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        return null; // no before phase
    }

    public function after(Request $request, Response $response): void
    {
        $response->headers->set($this->headerName, $this->headerValue);
    }

    public function getBeforePriority(): int|false
    {
        return false; // no before phase
    }

    public function getAfterPriority(): int|false
    {
        return $this->afterPriority;
    }
}
