<?php

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Symfony\Component\HttpKernel\HttpKernelInterface;

require_once "SecurityServiceProviderTest.php";

/**
 * Tests SecurityServiceProvider using config-based setup (app.security2.php).
 *
 * Inherits all test methods from SecurityServiceProviderTest but uses a
 * different bootstrap file that passes security config via MicroKernel constructor.
 */
class SecurityServiceProviderConfigurationTest extends SecurityServiceProviderTest
{
    use RouteCacheCleaner;

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $cacheDir = static::createTempCacheDir();
        $app = require __DIR__ . "/app.security2.php";

        return $app;
    }
}
