<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 17:34
 */

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use Silex\WebTestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FallbackViewHandlerTest extends WebTestCase
{
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $config              = [
            'routing' => [
                'path'       => __DIR__ . "/fallback-test.routes.yml",
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Ut\\Controllers\\',
                ],
            ],
        ];
        $app                 = new SilexKernel($config, true);
        $app->view_handlers  = [
            new FallbackViewHandler($app, new RouteBasedResponseRendererResolver()),
        ];
        $app->error_handlers = [
            new ExceptionWrapper(),
        ];
        
        return $app;
    }
    
    public function testPanelOk()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            'panel/ok'
        );
        $response = $client->getResponse();
        $this->assertEquals("Hello world!", $response->getContent());
    }
    
    public function testPanelError()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            'panel/error'
        );
        $response = $client->getResponse();
        $this->assertTrue(preg_match("/RuntimeException/", $response->getContent()) > 0);
        $this->assertTrue(preg_match("/code.*:.*500/", $response->getContent()) > 0);
    }
    
    public function testApiOk()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            'api/ok'
        );
        $response = $client->getResponse();
        $this->assertEquals(json_encode(["result" => "Hello world!"]), $response->getContent());
    }
    
    public function testApiError()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            'api/error'
        );
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals(500, $json['code']);
        $this->assertEquals('RuntimeException', $json['exception']['type']);
        $this->assertEquals('Oops!', $json['exception']['message']);
    }
}
