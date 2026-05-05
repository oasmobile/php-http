<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Twig;

use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Twig\Error\RuntimeError;

/**
 * Scenario-level integration tests for the Twig module.
 *
 * Validates behavior equivalence with the Silex-era SimpleTwigServiceProvider
 * from the user's perspective: configure → boot → request → verify response.
 *
 * Low-risk module: existing tests in TwigServiceProviderTest already cover
 * many aspects. This class supplements with scenario-level perspective and
 * references existing coverage via @see annotations where applicable.
 *
 * @see TwigServiceProviderTest — existing unit/integration tests for Twig
 */
class TwigScenarioTest extends ScenarioTestCase
{
    /**
     * R12-AC1: Twig template rendering via full request pipeline.
     *
     * Configure twig with template path → boot MicroKernel → send request
     * to a controller that renders a template → verify the response body
     * contains the rendered template content.
     *
     * @see TwigServiceProviderTest::testBasicTemplate() — covers rendering via WebTestCase
     */
    public function testTwigTemplateRendering(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
            ],
        ];

        $kernel   = $this->buildKernel($config, true);
        $response = $this->handleRequest($kernel, 'GET', '/twig-scenario/render');

        $this->assertStatusCode($response, 200);
        $this->assertStringContainsString('Hello, World!', $response->getContent());
    }

    /**
     * R12-AC2: getTwig() returns null when twig config is absent.
     *
     * Configure MicroKernel without `twig` key → boot → call getTwig()
     * → verify null is returned.
     *
     * @see TwigServiceProviderTest::testGetTwigReturnsNullWhenNotConfigured()
     */
    public function testTwigAbsence(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            // no 'twig' key
        ];

        $kernel = $this->buildKernel($config, true);
        $kernel->boot();

        $this->assertNull($kernel->getTwig());
    }

    /**
     * R12-AC3: strict_variables mode causes exception on undefined variable.
     *
     * Configure `twig.strict_variables = true` → render a template referencing
     * an undefined variable → verify an exception is thrown.
     *
     * @see TwigServiceProviderTest::testStrictVariablesEnabledByDefault()
     * @see TwigServiceProviderTest::testStrictVariablesDisabledWhenConfigured()
     */
    public function testTwigStrictVariablesMode(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'twig'      => [
                'template_dir'     => __DIR__ . '/templates',
                'strict_variables' => true,
            ],
        ];

        $kernel = $this->buildKernel($config, true);
        $kernel->boot();

        $twig = $kernel->getTwig();
        $this->assertNotNull($twig);
        $this->assertTrue($twig->isStrictVariables());

        // Rendering a template with an undefined variable should throw
        $this->expectException(RuntimeError::class);
        $twig->render('scenario/undefined_var.twig', []);
    }

    /**
     * R12-AC4: auto_reload configuration is reflected in Twig environment.
     *
     * Configure `twig.auto_reload` → verify the Twig environment reflects
     * the configured auto-reload setting.
     *
     * Tests three scenarios:
     * 1. auto_reload = null (default) → follows isDebug()
     * 2. auto_reload = true (explicit) → always enabled
     * 3. auto_reload = false (explicit) → always disabled
     *
     * @see TwigServiceProviderTest::testAutoReloadEnabledInDebugMode()
     * @see TwigServiceProviderTest::testAutoReloadDisabledInNonDebugMode()
     * @see TwigServiceProviderTest::testAutoReloadExplicitOverride()
     */
    public function testTwigAutoReloadBehavior(): void
    {
        // Case 1: auto_reload not set, debug=true → auto_reload enabled
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                // auto_reload not set → defaults to null → follows isDebug()
            ],
        ];

        $kernel = $this->buildKernel($config, true);
        $kernel->boot();
        $this->assertTrue(
            $kernel->getTwig()->isAutoReload(),
            'auto_reload should be true when debug=true and auto_reload is not configured',
        );
        $kernel->shutdown();

        // Case 2: auto_reload explicitly false, debug=true → auto_reload disabled
        $config2 = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                'auto_reload'  => false,
            ],
        ];

        $kernel2 = $this->buildKernel($config2, true);
        $kernel2->boot();
        $this->assertFalse(
            $kernel2->getTwig()->isAutoReload(),
            'auto_reload=false should override debug=true',
        );
        $kernel2->shutdown();

        // Case 3: auto_reload explicitly true, debug=false → auto_reload enabled
        $config3 = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                'auto_reload'  => true,
            ],
        ];

        $kernel3 = $this->buildKernel($config3, false);
        $kernel3->boot();
        $this->assertTrue(
            $kernel3->getTwig()->isAutoReload(),
            'auto_reload=true should override debug=false',
        );
    }
}
