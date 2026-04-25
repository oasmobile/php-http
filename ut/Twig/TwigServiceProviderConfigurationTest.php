<?php

namespace Oasis\Mlib\Http\Test\Twig;

use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Twig integration using the configuration-driven bootstrap (app.twig2.php).
 */
class TwigServiceProviderConfigurationTest extends TwigServiceProviderTest
{

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $cacheDir = static::createTempCacheDir();
        return require __DIR__ . "/app.twig2.php";
    }
}
