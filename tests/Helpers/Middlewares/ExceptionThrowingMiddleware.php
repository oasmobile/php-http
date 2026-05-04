<?php
declare(strict_types=1);

/**
 * Before middleware that throws an exception.
 *
 * Used by MiddlewareScenarioTest to verify that when a before middleware
 * throws an exception, the Error_Handler_Chain is invoked.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionThrowingMiddleware extends AbstractMiddleware
{
    private string $message;

    public function __construct(string $message = 'Middleware exception')
    {
        $this->message = $message;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        throw new \RuntimeException($this->message);
    }

    public function after(Request $request, Response $response): void
    {
        // no-op
    }
}
