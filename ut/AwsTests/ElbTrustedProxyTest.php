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
    
    /**
     * Load AWS IP ranges from local cache (written by SilexKernel when trust_cloudfront_ips = true).
     * Falls back to live HTTP request only if cache is missing.
     *
     * @return array
     */
    private function loadAwsIpRanges()
    {
        $cacheFile = __DIR__ . '/../cache/aws.ips';
        if (\file_exists($cacheFile)) {
            $content = \file_get_contents($cacheFile);
            $awsIps  = \GuzzleHttp\json_decode($content, true);
            if (isset($awsIps['prefixes'])) {
                return $awsIps;
            }
        }
        
        // Fallback: fetch from AWS (slow, ~1-2s network request)
        $guzzle   = new Client();
        $response = $guzzle->request('GET', 'https://ip-ranges.amazonaws.com/ip-ranges.json');
        
        return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
    }
    
    public function testCloudfrontTrustedIps()
    {
        $awsIps = $this->loadAwsIpRanges();
        $this->assertArrayHasKey('prefixes', $awsIps);
        
        // Collect all CloudFront IP prefixes, then sample a representative subset.
        // Testing all ~200 prefixes takes 12+ seconds; the trust logic is identical for each.
        $cloudfrontPrefixes = [];
        foreach ($awsIps['prefixes'] as $info) {
            if (\array_key_exists('ip_prefix', $info) && $info['service'] == "CLOUDFRONT") {
                $cloudfrontPrefixes[] = $info['ip_prefix'];
            }
        }
        $this->assertNotEmpty($cloudfrontPrefixes, 'No CloudFront prefixes found in AWS IP ranges');
        
        // Sample: first, last, and a few from the middle
        $sample = [];
        $sample[] = $cloudfrontPrefixes[0];
        $sample[] = $cloudfrontPrefixes[\count($cloudfrontPrefixes) - 1];
        $step = \max(1, (int)(\count($cloudfrontPrefixes) / 5));
        for ($i = $step; $i < \count($cloudfrontPrefixes); $i += $step) {
            $sample[] = $cloudfrontPrefixes[$i];
        }
        $sample = \array_unique($sample);
        
        foreach ($sample as $prefix) {
            list($cfIp,) = \explode('/', $prefix);
            $client = $this->createClient();
            $client->request(
                'GET',
                '/aws/ip',
                [],
                [],
                [
                    'REMOTE_ADDR'          => '1.2.2.2',
                    'HTTP_X_FORWARDED_FOR' => "9.8.7.6, $cfIp",
                ]
            );
            $response = $client->getResponse();
            $json     = \GuzzleHttp\json_decode($response->getContent(), true);
            $this->assertEquals('9.8.7.6', $json['ip'], "Failed for CloudFront prefix: $prefix");
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
