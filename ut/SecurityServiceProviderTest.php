<?php
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 12:22
 */
class SecurityServiceProviderTest extends WebTestCase
{
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $app = require __DIR__ . "/app.security.php";

        $app['session.test'] = true;

        return $app;
    }

    public function testBasicAuth()
    {
        $this->markTestSkipped();
        $client = $this->createClient(
            [
                'PHP_AUTH_USER' => "admin",
                "PHP_AUTH_PW"   => "12345",
            ]
        );
        $client->request('GET', '/secured/admin');
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $client = $this->createClient(
            [
                'PHP_AUTH_USER' => "admin",
                "PHP_AUTH_PW"   => "1234",
            ]
        );
        $client->request('GET', '/secured/admin');
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testFormAuth()
    {
        $this->markTestSkipped();
        $client = $this->createClient();
        $client->request('GET', '/secured/fadmin/test');
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Location'));
        $this->assertStringEndsWith('/secured/flogin', $response->headers->get('Location'));

        //$client = $this->createClient(
        //    [
        //        'PHP_AUTH_USER' => "admin",
        //        "PHP_AUTH_PW"   => "1234",
        //    ]
        //);
        $client->request(
            'POST',
            '/secured/fadmin/check',
            [
                "_username" => 'admin',
                "_password" => '12345',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Location'));
        $this->assertStringEndsWith('/secured/flogin', $response->headers->get('Location'));

        $client->request(
            'POST',
            '/secured/fadmin/check',
            [
                "_username" => 'admin',
                "_password" => '1234',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Location'));
        $this->assertStringEndsWith('/secured/flogin', $response->headers->get('Location'));
    }

    public function testPreAuth()
    {
        $client = $this->createClient();
        $client->request('GET', '/secured/madmin');
        $response = $client->getResponse();

    }
}
