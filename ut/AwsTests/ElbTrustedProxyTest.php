<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:19 PM
 */

namespace AwsTests;

use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ElbTrustedProxyTest extends WebTestCase
{
    /** @var string Isolated temp cache dir shared across all tests in this class */
    private static $tempCacheDir;
    
    public static function setUpBeforeClass(): void
    {
        self::$tempCacheDir = \sys_get_temp_dir() . '/oasis-http-aws-test-' . \getmypid();
        \mkdir(self::$tempCacheDir, 0777, true);
        
        // Copy aws.ips fixture so trust_cloudfront_ips tests don't hit the network
        $fixture = __DIR__ . '/../fixtures/aws.ips';
        if (\file_exists($fixture)) {
            \copy($fixture, self::$tempCacheDir . '/aws.ips');
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        if (self::$tempCacheDir && \is_dir(self::$tempCacheDir)) {
            self::removeDirectory(self::$tempCacheDir);
        }
        self::$tempCacheDir = null;
    }
    
    private static function removeDirectory(string $dir): void
    {
        foreach (\scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (\is_dir($path)) {
                self::removeDirectory($path);
            } else {
                \unlink($path);
            }
        }
        \rmdir($dir);
    }
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $cacheDir = self::$tempCacheDir;
        return require __DIR__ . "/elb.php";
    }
    
    /**
     * Load AWS IP ranges from local cache (written by MicroKernel when trust_cloudfront_ips = true).
     * Falls back to live HTTP request only if cache is missing.
     *
     * @return array
     */
    private function loadAwsIpRanges()
    {
        // Prefer the version-controlled fixture over the runtime cache
        $fixturePath = __DIR__ . '/../fixtures/aws.ips';
        if (\file_exists($fixturePath)) {
            $content = \file_get_contents($fixturePath);
            $awsIps  = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (isset($awsIps['prefixes'])) {
                return $awsIps;
            }
        }
        
        // Fallback: runtime cache written by MicroKernel
        $cacheFile = __DIR__ . '/../cache/aws.ips';
        if (\file_exists($cacheFile)) {
            $content = \file_get_contents($cacheFile);
            $awsIps  = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (isset($awsIps['prefixes'])) {
                return $awsIps;
            }
        }
        
        // Last resort: fetch from AWS (slow, ~1-2s network request)
        $guzzle   = new \GuzzleHttp\Client();
        $response = $guzzle->request('GET', 'https://ip-ranges.amazonaws.com/ip-ranges.json');
        
        return \json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
            $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
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
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
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
    
    // --- Supplementary tests for R12 AC 5 ---
    
    /**
     * behind_elb only (trust_cloudfront_ips=false):
     * REMOTE_ADDR is added to trusted proxies, so X-Forwarded-For is trusted.
     */
    public function testBehindElbOnlyIpForwarding()
    {
        $cacheDir = self::$tempCacheDir;
        $app = require __DIR__ . "/elb-only.php";
        $this->app = $app;
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('203.0.113.50', $json['ip']);
    }
    
    /**
     * behind_elb only: HTTPS forwarding via X-Forwarded-Proto is trusted.
     */
    public function testBehindElbOnlyHttpsForwarding()
    {
        $cacheDir = self::$tempCacheDir;
        $app = require __DIR__ . "/elb-only.php";
        $this->app = $app;
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/',
            [],
            [],
            [
                'REMOTE_ADDR'            => '10.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true);
        $this->assertEquals('443', $json['port']);
        $this->assertEquals(true, $json['https']);
    }
    
    /**
     * trust_cloudfront_ips only (behind_elb=false):
     * CloudFront IPs are trusted, so X-Forwarded-For through CloudFront is resolved.
     */
    public function testCloudfrontOnlyIpForwarding()
    {
        $cacheDir = self::$tempCacheDir;
        $app = require __DIR__ . "/cloudfront-only.php";
        $this->app = $app;
        
        $awsIps = $this->loadAwsIpRanges();
        $this->assertArrayHasKey('prefixes', $awsIps);
        
        // Find the first CloudFront prefix
        $cfIp = null;
        foreach ($awsIps['prefixes'] as $info) {
            if (\array_key_exists('ip_prefix', $info) && $info['service'] == "CLOUDFRONT") {
                list($cfIp,) = \explode('/', $info['ip_prefix']);
                break;
            }
        }
        $this->assertNotNull($cfIp, 'No CloudFront prefix found');
        
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => $cfIp,
                'HTTP_X_FORWARDED_FOR' => "198.51.100.10, $cfIp",
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('198.51.100.10', $json['ip']);
    }
    
    /**
     * Neither behind_elb nor trust_cloudfront_ips:
     * X-Forwarded-For from non-trusted proxy is NOT trusted.
     * Client IP should be REMOTE_ADDR (or the last untrusted hop).
     */
    public function testNoAwsFeaturesNonTrustedProxy()
    {
        $cacheDir = self::$tempCacheDir;
        $app = require __DIR__ . "/no-aws.php";
        $this->app = $app;
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '192.0.2.99',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.10',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        // 192.0.2.99 is not in trusted_proxies, so X-Forwarded-For is not trusted
        $this->assertEquals('192.0.2.99', $json['ip']);
    }
    
    /**
     * Neither behind_elb nor trust_cloudfront_ips:
     * X-Forwarded-Proto from non-trusted proxy is NOT trusted.
     */
    public function testNoAwsFeaturesHttpsNotForwarded()
    {
        $cacheDir = self::$tempCacheDir;
        $app = require __DIR__ . "/no-aws.php";
        $this->app = $app;
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/',
            [],
            [],
            [
                'REMOTE_ADDR'            => '192.0.2.99',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true);
        // Non-trusted proxy, so X-Forwarded-Proto should not be trusted
        $this->assertEquals(false, $json['https']);
    }
    
    /**
     * Both behind_elb and trust_cloudfront_ips (original elb.php config):
     * Multi-hop forwarding: client → CloudFront → ELB → app.
     * Both ELB (REMOTE_ADDR) and CloudFront IPs are trusted.
     */
    public function testBothElbAndCloudfrontMultiHop()
    {
        $awsIps = $this->loadAwsIpRanges();
        $this->assertArrayHasKey('prefixes', $awsIps);
        
        $cfIp = null;
        foreach ($awsIps['prefixes'] as $info) {
            if (\array_key_exists('ip_prefix', $info) && $info['service'] == "CLOUDFRONT") {
                list($cfIp,) = \explode('/', $info['ip_prefix']);
                break;
            }
        }
        $this->assertNotNull($cfIp, 'No CloudFront prefix found');
        
        $client = $this->createClient();
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => "203.0.113.50, $cfIp, 10.0.0.1",
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        // Both ELB (10.0.0.1 via behind_elb) and CloudFront IP are trusted
        // so the real client IP 203.0.113.50 should be resolved
        $this->assertEquals('203.0.113.50', $json['ip']);
    }
    
    /**
     * behind_elb=true: trusted proxies accumulate across requests.
     * Each request's REMOTE_ADDR is added to trusted proxies.
     */
    public function testBehindElbTrustedProxiesAccumulate()
    {
        $client = $this->createClient();
        
        // First request from ELB at 10.0.0.1
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('203.0.113.1', $json['ip']);
        
        // Second request from a different ELB at 10.0.0.2
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '10.0.0.2',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.2',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('203.0.113.2', $json['ip']);
    }
    
    /**
     * behind_elb=true with pre-configured trusted_proxies:
     * The pre-configured proxies (127.0.0.1, 1.2.3.4, 5.6.7.8/16) are preserved.
     */
    public function testBehindElbPreservesConfiguredProxies()
    {
        $client = $this->createClient();
        // Request from a pre-configured trusted proxy
        $client->request(
            'GET',
            '/aws/ip',
            [],
            [],
            [
                'REMOTE_ADDR'          => '1.2.3.4',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
            ]
        );
        $response = $client->getResponse();
        $json     = \json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        // 1.2.3.4 is in trusted_proxies, so X-Forwarded-For is trusted
        $this->assertEquals('203.0.113.99', $json['ip']);
    }
}
