<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Internal middleware that wraps callable callbacks for the
 * MicroKernel::before() / MicroKernel::after() convenience methods.
 *
 * @internal Not intended for direct use by downstream consumers.
 */
final class CallbackMiddleware implements MiddlewareInterface
{
    /**
     * @param (callable(Request, MicroKernel): (Response|null))|null      $beforeCallback
     * @param (callable(Request, Response, MicroKernel): void)|null       $afterCallback
     * @param int|false    $beforePriority
     * @param int|false    $afterPriority
     * @param bool         $masterRequestOnly
     * @param MicroKernel  $kernel  Reference to the kernel for after-callback invocation
     */
    public function __construct(
        private readonly mixed $beforeCallback,
        private readonly mixed $afterCallback,
        private readonly int|false $beforePriority,
        private readonly int|false $afterPriority,
        private readonly bool $masterRequestOnly,
        private readonly MicroKernel $kernel,
    ) {
    }

    public function onlyForMasterRequest(): bool
    {
        return $this->masterRequestOnly;
    }

    public function before(Request $request, MicroKernel $kernel): Response|null
    {
        if ($this->beforeCallback === null) {
            return null;
        }

        return ($this->beforeCallback)($request, $kernel);
    }

    public function after(Request $request, Response $response): void
    {
        if ($this->afterCallback !== null) {
            ($this->afterCallback)($request, $response, $this->kernel);
        }
    }

    public function getBeforePriority(): int|false
    {
        return $this->beforePriority;
    }

    public function getAfterPriority(): int|false
    {
        return $this->afterPriority;
    }
}
