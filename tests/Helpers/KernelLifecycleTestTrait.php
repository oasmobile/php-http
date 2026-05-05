<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Helpers;

use Oasis\Mlib\Http\MicroKernel;

/**
 * Shared setUp/tearDown logic for tests that create MicroKernel instances.
 * Manages kernel shutdown and exception handler cleanup.
 */
trait KernelLifecycleTestTrait
{
    /** @var mixed */
    private $previousExceptionHandler = null;

    /** @var MicroKernel[] */
    private array $kernels = [];

    protected function setUpKernelLifecycle(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
    }

    protected function tearDownKernelLifecycle(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];

        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }
        $this->previousExceptionHandler = null;
    }

    private function track(MicroKernel $kernel): MicroKernel
    {
        $this->kernels[] = $kernel;
        return $kernel;
    }
}
