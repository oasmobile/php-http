<?php

namespace Oasis\Mlib\Http\Test\Twig;

use Silex\WebTestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 11:53
 */
class TwigServiceProviderTest extends WebTestCase
{
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
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
        $this->assertContains("WOW", $crawler->filter("body")->text());
        $this->assertContains("haha", $crawler->filter("body")->text());
        
        // escape testing
        $this->assertContains("yyzzMljlkfda", $crawler->filter("div#div_foo")->text());
        $this->assertContains("X<BR/>U", $crawler->filter("div#div_foo")->text());
        $this->assertContains("X&lt;BR/&gt;U", $crawler->filter("div#div_foo")->html());
        
        // macro testing
        $this->assertEquals("alice@9", $crawler->filter("div#div_side > input")->first()->attr('value'));
        
        // include testing
        $this->assertContains('THIS IS FOOTER', $crawler->filter('div#div_footer')->text());
        
        // global var testing
        $this->assertContains('great nba game', $crawler->filter('div#div_footer')->text());
    }
    
    // --- Supplementary tests for R12 AC 4 ---
    
    /**
     * Globals: the 'http' global is always registered (pointing to the app container).
     * Verify it is a SilexKernel instance.
     *
     * @runInSeparateProcess
     */
    public function testGlobalHttpIsRegistered()
    {
        $app = $this->createApplication();
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig    = $app['twig'];
        $globals = $twig->getGlobals();
        $this->assertArrayHasKey('http', $globals);
        $this->assertInstanceOf('Oasis\Mlib\Http\SilexKernel', $globals['http']);
    }
    
    /**
     * Globals: custom global variables from configuration are accessible in Twig.
     * The 'helper' global is a TwigHelper instance.
     *
     * @runInSeparateProcess
     */
    public function testGlobalCustomVariableRegistered()
    {
        $app = $this->createApplication();
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig    = $app['twig'];
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
        $app = $this->createApplication();
        // Override globals config to empty
        $app['twig.config'] = array_merge($app['twig.config'], ['globals' => []]);
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig    = $app['twig'];
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
        $app = $this->createApplication();
        $app['twig.config'] = array_merge($app['twig.config'], [
            'globals' => [
                'site_name' => 'TestSite',
                'version'   => 42,
                'debug'     => true,
            ],
        ]);
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig    = $app['twig'];
        $globals = $twig->getGlobals();
        $this->assertEquals('TestSite', $globals['site_name']);
        $this->assertEquals(42, $globals['version']);
        $this->assertEquals(true, $globals['debug']);
    }
    
    /**
     * asset_base: the asset() Twig function prepends asset_base to the file path.
     * Template a.twig calls {{ asset('/pics/cool.jpg', "3.0") }}.
     * The expected output depends on the app's asset_base configuration.
     *
     * @runInSeparateProcess
     */
    public function testAssetBaseInTemplate()
    {
        $app = $this->createApplication();
        $assetBase = isset($app['twig.config']['asset_base']) ? $app['twig.config']['asset_base'] : '';
        
        $client = $this->createClient();
        $client->request('GET', '/twig/2');
        $crawler = $client->getCrawler();
        $html = $crawler->filter('div#div_foo')->html();
        $expected = $assetBase . '/pics/cool.jpg?v=3.0';
        $this->assertContains($expected, $html);
    }
    
    /**
     * asset_base: the asset() function with empty version omits the query string.
     * Uses Twig template rendering to avoid Closure serialization issues.
     *
     * @runInSeparateProcess
     */
    public function testAssetFunctionWithoutVersion()
    {
        $app = $this->createApplication();
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig = $app['twig'];
        // Render inline template to test asset function without version
        $result = $twig->createTemplate('{{ asset("/style.css") }}')->render([]);
        $assetBase = $app['twig.config.asset_base'];
        $this->assertEquals($assetBase . '/style.css', $result);
    }
    
    /**
     * asset_base: the asset() function with a version appends ?v=<version>.
     * Uses Twig template rendering to avoid Closure serialization issues.
     *
     * @runInSeparateProcess
     */
    public function testAssetFunctionWithVersion()
    {
        $app = $this->createApplication();
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig = $app['twig'];
        $result = $twig->createTemplate('{{ asset("/style.css", "2.1") }}')->render([]);
        $assetBase = $app['twig.config.asset_base'];
        $this->assertEquals($assetBase . '/style.css?v=2.1', $result);
    }
    
    /**
     * asset_base: when asset_base is empty string (default), asset paths are relative.
     *
     * @runInSeparateProcess
     */
    public function testAssetBaseEmptyDefault()
    {
        $app = $this->createApplication();
        // Override to remove asset_base (will default to empty string via TwigConfiguration)
        $config = $app['twig.config'];
        unset($config['asset_base']);
        $app['twig.config'] = $config;
        $app->boot();
        /** @var \Twig_Environment $twig */
        $twig   = $app['twig'];
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
        $app['session.test'] = true;
        
        $client = $this->createClient();
        // Override the app for this test
        $this->app = $app;
        $client    = $this->createClient();
        $client->request('GET', '/twig/2');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('WOW', $response->getContent());
    }
    
    /**
     * cache_dir absent: twig.options should not contain 'cache' key when cache_dir is null.
     *
     * @runInSeparateProcess
     */
    public function testNoCacheDirTwigOptions()
    {
        $app = require __DIR__ . "/app.twig-no-cache.php";
        $app['session.test'] = true;
        $app->boot();
        
        // When cache_dir is null, boot() should not set cache in twig.options
        $options = $app['twig.options'];
        $this->assertArrayNotHasKey('cache', $options);
    }
    
    /**
     * cache_dir present: twig.options should contain 'cache' key with the configured path.
     *
     * @runInSeparateProcess
     */
    public function testCacheDirSetInTwigOptions()
    {
        $app = $this->createApplication();
        $app->boot();
        $options = $app['twig.options'];
        $this->assertArrayHasKey('cache', $options);
        $this->assertEquals('/tmp/twig_cache', $options['cache']);
    }
    
    /**
     * template_dir: twig.path is set to the configured template_dir.
     *
     * @runInSeparateProcess
     */
    public function testTemplateDirSetInTwigPath()
    {
        $app = $this->createApplication();
        $app->boot();
        $this->assertEquals($app['twig.config']['template_dir'], $app['twig.path']);
    }
    
    /**
     * getConfigDataProvider() before register() throws LogicException.
     */
    public function testGetConfigDataProviderBeforeRegisterThrows()
    {
        $provider = new \Oasis\Mlib\Http\ServiceProviders\Twig\SimpleTwigServiceProvider();
        $this->setExpectedException('LogicException');
        $provider->getConfigDataProvider();
    }
}
