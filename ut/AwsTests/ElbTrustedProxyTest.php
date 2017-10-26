<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:19 PM
 */

namespace AwsTests;

use GuzzleHttp\Client;
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
    
    public function testCloudfrontTrustedIps()
    {
        $guzzle   = new Client();
        $response = $guzzle->request('GET', 'https://ip-ranges.amazonaws.com/ip-ranges.json');
        $awsIps   = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('prefixes', $awsIps);
        foreach ($awsIps['prefixes'] as $info) {
            if (\array_key_exists('ip_prefix', $info) && $info['service'] == "CLOUDFRONT") {
                list($cfIp,) = \explode('/', $info['ip_prefix']);
                $client = $this->createClient();
                $client->request(
                    'GET',
                    '/aws/ip',
                    [],
                    [],
                    [
                        'REMOTE_ADDR'          => '1.2.2.2',
                        'HTTP_X_FORWARDED_FOR' => '9.8.7.6, $cfIp',
                    ]
                );
                $response = $client->getResponse();
                $json     = \GuzzleHttp\json_decode($response->getContent(), true);
                $this->assertEquals('9.8.7.6', $json['ip']);
                //break;
            }
        }
        
    }
    
    public function testBehindElb()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '1.2.2.2',
                'HTTP_X_FORWARDED_FOR' => '9.7.8.9',
            ]
        );
        $response = $client->getResponse();
        $json     = \GuzzleHttp\json_decode($response->getContent(), true);
        $this->assertEquals('9.7.8.9', $json['ip']);
        
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
