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
}
