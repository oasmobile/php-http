<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:19 PM
 */

namespace AwsTests;

use Silex\WebTestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ElbTrustedProxyTest extends WebTestCase
{
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        return require __DIR__ . "/elb.php";
    }
    
    public function testHttpsForwardByElb()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/',
            [],
            [],
            [
                'REMOTE_ADDR'            => '3.4.5.6',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true);
        $this->assertEquals('443', $json['port']);
        $this->assertEquals(true, $json['https']);
    }
    
    public function testHttpForwardByElb()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/',
            [],
            [],
            [
                'HTTPS'       => 'on',
                'REMOTE_ADDR' => '3.4.5.6',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true);
        $this->assertEquals('443', $json['port']);
        $this->assertEquals(true, $json['https']);
        $client->request(
            'GET',
            '/aws/',
            [],
            [],
            [
                'HTTPS'                  => 'on',
                'REMOTE_ADDR'            => '3.4.5.6',
                'HTTP_X_FORWARDED_PROTO' => 'http',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true);
        $this->assertEquals('80', $json['port']);
        $this->assertEquals(false, $json['https']);
    }
}
