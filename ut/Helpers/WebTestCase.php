<?php
/**
 * Replacement for Silex\WebTestCase.
 *
 * Provides the same createApplication() / createClient() API
 * that the test suite relied on under Silex, now backed by
 * Symfony HttpKernel directly.
 */

namespace Oasis\Mlib\Http\Test\Helpers;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Client as LegacyClient;

class WebTestCase extends TestCase
{
    /** @var HttpKernelInterface|null */
    protected $app;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    /**
     * Creates the application / kernel under test.
     * Subclasses MUST override this method.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        throw new \LogicException('Subclass must implement createApplication().');
    }

    protected function setUp(): void
    {
        // Save current exception handler state before creating the app
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->app = $this->createApplication();
    }

    protected function tearDown(): void
    {
        if ($this->app instanceof \Symfony\Component\HttpKernel\Kernel) {
            $this->app->shutdown();
        }
        $this->app = null;

        // Restore exception handler to the state before setUp
        // This prevents PHPUnit's "did not remove its own exception handlers" warning
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
    }

    /**
     * Creates an HTTP client that sends requests through the kernel.
     *
     * Uses Symfony's BrowserKit HttpBrowser / Client depending on
     * what is available. Falls back to the legacy Symfony\Component\HttpKernel\Client.
     *
     * @param array $server Server parameters
     * @return \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    public function createClient(array $server = [])
    {
        if ($this->app === null) {
            $this->app = $this->createApplication();
        }

        // Ensure the kernel is booted before creating the browser
        if ($this->app instanceof \Symfony\Component\HttpKernel\Kernel) {
            $this->app->boot();
        }

        return new \Symfony\Bundle\FrameworkBundle\KernelBrowser($this->app, $server);
    }
}
