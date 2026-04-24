<?php

namespace Oasis\Mlib\Http\Test\Twig;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests for Twig integration via SimpleTwigServiceProvider.
 */
class TwigServiceProviderTest extends WebTestCase
{
    use RouteCacheCleaner;

    protected function setUp(): void
    {
        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $cacheDir = static::createTempCacheDir();
        return require __DIR__ . "/app.twig.php";
    }

    /**
     * @runInSeparateProcess
     */
    public function testBasicTemplate()
    {
        $client = $this->createClient();
        $client->request('GET', '/twig/2');
        $crawler = $client->getCrawler();
        $this->assertStringContainsString("WOW", $crawler->filter("body")->text());
        $this->assertStringContainsString("haha", $crawler->filter("body")->text());

        // escape testing
        $this->assertStringContainsString("yyzzMljlkfda", $crawler->filter("div#div_foo")->text());
        $this->assertStringContainsString("X<BR/>U", $crawler->filter("div#div_foo")->text());
        $this->assertStringContainsString("X&lt;BR/&gt;U", $crawler->filter("div#div_foo")->html());

        // macro testing
        $this->assertEquals("alice@9", $crawler->filter("div#div_side > input")->first()->attr('value'));

        // include testing
        $this->assertStringContainsString('THIS IS FOOTER', $crawler->filter('div#div_footer')->text());

        // global var testing
        $this->assertStringContainsString('great nba game', $crawler->filter('div#div_footer')->text());
    }

    // --- Supplementary tests ---

    /**
     * Globals: the 'http' global is always registered (pointing to the kernel).
     * Verify it is a MicroKernel instance.
     *
     * @runInSeparateProcess
     */
    public function testGlobalHttpIsRegistered()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $twig    = $app->getTwig();
        $globals = $twig->getGlobals();
        $this->assertArrayHasKey('http', $globals);
        $this->assertInstanceOf(MicroKernel::class, $globals['http']);
    }

    /**
     * Globals: custom global variables from configuration are accessible in Twig.
     * The 'helper' global is a TwigHelper instance.
     *
     * @runInSeparateProcess
     */
    public function testGlobalCustomVariableRegistered()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $twig    = $app->getTwig();
        $globals = $twig->getGlobals();
        $this->assertArrayHasKey('helper', $globals);
        $this->assertInstanceOf('Oasis\Mlib\Http\Test\Helpers\TwigHelper', $globals['helper']);
    }

    /**
     * Globals: when globals config is empty array, only the default 'http' global is present.
     *
     * @runInSeparateProcess
     */
    public function testGlobalEmptyConfig()
    {
        // Create a kernel with empty globals
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                'cache_dir'    => '/tmp/twig_cache',
                'asset_base'   => 'http://163.com/img',
                'globals'      => [],
            ],
        ];
        $app = new MicroKernel($config, true);
        $app->boot();
        $twig    = $app->getTwig();
        $globals = $twig->getGlobals();
        $this->assertArrayHasKey('http', $globals);
        // 'helper' should not be present since globals is empty
        $this->assertArrayNotHasKey('helper', $globals);
    }

    /**
     * Globals: scalar global variables are correctly registered.
     *
     * @runInSeparateProcess
     */
    public function testGlobalScalarVariables()
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                'cache_dir'    => '/tmp/twig_cache',
                'globals'      => [
                    'site_name' => 'TestSite',
                    'version'   => 42,
                    'debug'     => true,
                ],
            ],
        ];
        $app = new MicroKernel($config, true);
        $app->boot();
        $twig    = $app->getTwig();
        $globals = $twig->getGlobals();
        $this->assertEquals('TestSite', $globals['site_name']);
        $this->assertEquals(42, $globals['version']);
        $this->assertEquals(true, $globals['debug']);
    }

    /**
     * asset_base: the asset() Twig function prepends asset_base to the file path.
     * Template a.twig calls {{ asset('/pics/cool.jpg', "3.0") }}.
     *
     * @runInSeparateProcess
     */
    public function testAssetBaseInTemplate()
    {
        $client = $this->createClient();
        $client->request('GET', '/twig/2');
        $crawler = $client->getCrawler();
        $html = $crawler->filter('div#div_foo')->html();
        $expected = 'http://163.com/img/pics/cool.jpg?v=3.0';
        $this->assertStringContainsString($expected, $html);
    }

    /**
     * asset_base: the asset() function with empty version omits the query string.
     *
     * @runInSeparateProcess
     */
    public function testAssetFunctionWithoutVersion()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $twig = $app->getTwig();
        // Render inline template to test asset function without version
        $result = $twig->createTemplate('{{ asset("/style.css") }}')->render([]);
        $this->assertEquals('http://163.com/img/style.css', $result);
    }

    /**
     * asset_base: the asset() function with a version appends ?v=<version>.
     *
     * @runInSeparateProcess
     */
    public function testAssetFunctionWithVersion()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $twig = $app->getTwig();
        $result = $twig->createTemplate('{{ asset("/style.css", "2.1") }}')->render([]);
        $this->assertEquals('http://163.com/img/style.css?v=2.1', $result);
    }

    /**
     * asset_base: when asset_base is empty string (default), asset paths are relative.
     *
     * @runInSeparateProcess
     */
    public function testAssetBaseEmptyDefault()
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            'twig'      => [
                'template_dir' => __DIR__ . '/templates',
                // no asset_base — defaults to empty string
            ],
        ];
        $app = new MicroKernel($config, true);
        $app->boot();
        $twig   = $app->getTwig();
        $result = $twig->createTemplate('{{ asset("/style.css") }}')->render([]);
        $this->assertEquals('/style.css', $result);
    }

    /**
     * cache_dir absent: when cache_dir is not configured (defaults to null),
     * Twig should still work without caching.
     *
     * @runInSeparateProcess
     */
    public function testNoCacheDirTemplateRendering()
    {
        $app = require __DIR__ . "/app.twig-no-cache.php";

        $this->app = $app;
        $client    = $this->createClient();
        $client->request('GET', '/twig/2');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('WOW', $response->getContent());
    }

    /**
     * cache_dir absent: Twig environment should not have cache enabled.
     *
     * @runInSeparateProcess
     */
    public function testNoCacheDirTwigHasNoCache()
    {
        $app = require __DIR__ . "/app.twig-no-cache.php";
        $app->boot();

        $twig = $app->getTwig();
        // When cache_dir is null, Twig should not have a cache directory set
        $this->assertFalse($twig->getCache());
    }

    /**
     * cache_dir present: Twig environment should have cache enabled.
     *
     * @runInSeparateProcess
     */
    public function testCacheDirSetInTwig()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $twig = $app->getTwig();
        $this->assertEquals('/tmp/twig_cache', $twig->getCache());
    }

    /**
     * getTwig() returns null when twig config is absent.
     *
     * @runInSeparateProcess
     */
    public function testGetTwigReturnsNullWhenNotConfigured()
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ],
            // no 'twig' key
        ];
        $app = new MicroKernel($config, true);
        $app->boot();
        $this->assertNull($app->getTwig());
    }

    /**
     * getTwig() returns a Twig\Environment when twig config is present.
     *
     * @runInSeparateProcess
     */
    public function testGetTwigReturnsTwigEnvironment()
    {
        /** @var MicroKernel $app */
        $app = $this->createApplication();
        $app->boot();
        $this->assertInstanceOf(\Twig\Environment::class, $app->getTwig());
    }
}
