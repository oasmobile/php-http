<?php
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:03
 */
class SilexKernelWebTest extends WebTestCase
{
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        return require __DIR__ . '/app.php';
    }

    public function testHomeRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::home()', $json['called']);
    }

    public function testNotFoundRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/404'); // non existing route
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertTrue(isset($json['code']), $response->getContent());
    }

    public function testHostBasedRoutes()
    {
        $client = $this->createClient(['HTTP_HOST' => 'localhost']);
        $client->request('GET', '/domain');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::domainLocalhost()', $json['called']);

        $client = $this->createClient(['HTTP_HOST' => 'baidu.com']);
        $client->request('GET', '/domain');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::domainBaidu()', $json['called']);
    }

    public function testSubRoutes()
    {
        $client = $this->createClient();
        $client->request('GET', '/sub/');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\SubTestController::home()', $json['called']);
    }

    public function testDomainMatching()
    {
        $client = $this->createClient(['HTTP_HOST' => "naruto.baidu.com"]);
        $client->request('GET', '/param/domain');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::paramDomain()', $json['called']);
        $this->assertEquals('naruto', $json['game']);

    }

    public function testParameterMatching()
    {
        $client = $this->createClient(['HTTP_HOST' => "naruto.baidu.com"]);
        $client->request('GET', '/param/id/29');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::paramId()', $json['called']);
        $this->assertEquals('29', $json['id']);

        $client = $this->createClient(['HTTP_HOST' => "naruto.baidu.com"]);
        $client->request('GET', '/param/id/moi');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::paramSlug()', $json['called']);
        $this->assertEquals('moi', $json['slug']);

        $client = $this->createClient(['HTTP_HOST' => "naruto.baidu.com"]);
        $client->request('GET', '/param/id/moi/hei');
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Ut\\TestController::paramSlug()', $json['called']);
        $this->assertEquals('moi/hei', $json['slug']);

    }
}