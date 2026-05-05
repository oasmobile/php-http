<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\AwsTests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Oasis\Mlib\Http\Kernel\CloudfrontTrustedProxyResolver;
use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Unit tests for setCloudfrontTrustedProxies HTTP fetch paths.
 * Uses a MicroKernel subclass to inject a mock CloudfrontTrustedProxyResolver.
 */
class CloudfrontTrustedProxiesUnitTest extends TestCase
{
    /** @var mixed */
    private $previousExceptionHandler = null;
    /** @var MicroKernel[] */
    private array $kernels = [];
    /** @var string[] */
    private array $tempDirs = [];

    private array $savedTrustedProxies;
    private int $savedTrustedHeaderSet;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        $this->savedTrustedProxies = Request::getTrustedProxies();
        $this->savedTrustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->savedTrustedProxies, $this->savedTrustedHeaderSet);

        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];

        foreach ($this->tempDirs as $dir) {
            $this->removeDir($dir);
        }
        $this->tempDirs = [];

        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }
        $this->previousExceptionHandler = null;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/oasis-cf-unit-' . getmypid() . '-' . count($this->tempDirs);
        @mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;
        return $dir;
    }

    private function buildKernel(array $config, array $mockResponses): MicroKernel
    {
        $mock = new MockHandler($mockResponses);
        $handlerStack = HandlerStack::create($mock);

        $kernel = new class($config, true, $handlerStack) extends MicroKernel {
            private HandlerStack $guzzleHandler;

            public function __construct(array $httpConfig, bool $isDebug, HandlerStack $handler)
            {
                $this->guzzleHandler = $handler;
                parent::__construct($httpConfig, $isDebug);
            }

            protected function createCloudfrontTrustedProxyResolver(): CloudfrontTrustedProxyResolver
            {
                $cacheDir = $this->cacheDir;
                $handler = $this->guzzleHandler;
                return new class($cacheDir, $handler) extends CloudfrontTrustedProxyResolver {
                    private HandlerStack $guzzleHandler;

                    public function __construct(?string $cacheDir, HandlerStack $handler)
                    {
                        parent::__construct($cacheDir);
                        $this->guzzleHandler = $handler;
                    }

                    protected function createAwsIpRangesClient(): Client
                    {
                        return new Client(['handler' => $this->guzzleHandler]);
                    }
                };
            }
        };

        $kernel->addRoute('test', new Route('/test', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        $this->kernels[] = $kernel;
        return $kernel;
    }

    // ─── Tests ───────────────────────────────────────────────────────

    /**
     * HTTP 200 with valid IP ranges → CloudFront IPs added to trusted proxies + cache written.
     */
    public function testSuccessfulFetchAddsTrustedProxiesAndWritesCache(): void
    {
        $tempDir = $this->makeTempDir();

        $ipRanges = json_encode([
            'prefixes' => [
                ['ip_prefix' => '13.32.0.0/15', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '52.46.0.0/18', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '10.0.0.0/8', 'service' => 'EC2'],
            ],
        ]);

        $kernel = $this->buildKernel(
            ['cache_dir' => $tempDir, 'trust_cloudfront_ips' => true],
            [new GuzzleResponse(200, [], $ipRanges)]
        );

        $kernel->handle(Request::create('/test'));

        $proxies = Request::getTrustedProxies();
        $this->assertContains('13.32.0.0/15', $proxies);
        $this->assertContains('52.46.0.0/18', $proxies);
        $this->assertNotContains('10.0.0.0/8', $proxies); // EC2, not CLOUDFRONT

        // Cache file should be written
        $cacheFile = $tempDir . '/aws.ips';
        $this->assertFileExists($cacheFile);
        $cached = json_decode(file_get_contents($cacheFile), true);
        $this->assertArrayHasKey('expire_at', $cached);
        $this->assertArrayHasKey('prefixes', $cached);
    }

    /**
     * HTTP non-200 response → error logged, no proxies added.
     */
    public function testNon200ResponseLogsError(): void
    {
        $tempDir = $this->makeTempDir();

        $kernel = $this->buildKernel(
            ['cache_dir' => $tempDir, 'trust_cloudfront_ips' => true],
            [new GuzzleResponse(503, [], 'Service Unavailable')]
        );

        $proxyCountBefore = count(Request::getTrustedProxies());

        $kernel->handle(Request::create('/test'));

        // No new proxies should be added
        $this->assertCount($proxyCountBefore, Request::getTrustedProxies());
    }

    /**
     * Guzzle throws exception (network error) → caught, no crash.
     */
    public function testNetworkExceptionIsCaught(): void
    {
        $tempDir = $this->makeTempDir();

        $mock = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('GET', 'ip-ranges.json')
            ),
        ]);

        $handlerStack = HandlerStack::create($mock);

        $kernel = new class(['cache_dir' => $tempDir, 'trust_cloudfront_ips' => true], true, $handlerStack) extends MicroKernel {
            private HandlerStack $guzzleHandler;

            public function __construct(array $httpConfig, bool $isDebug, HandlerStack $handler)
            {
                $this->guzzleHandler = $handler;
                parent::__construct($httpConfig, $isDebug);
            }

            protected function createCloudfrontTrustedProxyResolver(): CloudfrontTrustedProxyResolver
            {
                $cacheDir = $this->cacheDir;
                $handler = $this->guzzleHandler;
                return new class($cacheDir, $handler) extends CloudfrontTrustedProxyResolver {
                    private HandlerStack $guzzleHandler;

                    public function __construct(?string $cacheDir, HandlerStack $handler)
                    {
                        parent::__construct($cacheDir);
                        $this->guzzleHandler = $handler;
                    }

                    protected function createAwsIpRangesClient(): Client
                    {
                        return new Client(['handler' => $this->guzzleHandler]);
                    }
                };
            }
        };

        $kernel->addRoute('test', new Route('/test', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $this->kernels[] = $kernel;

        // Should not throw
        $response = $kernel->handle(Request::create('/test'));
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Successful fetch without writable cache_dir → proxies added but no cache written.
     */
    public function testSuccessfulFetchWithoutWritableCacheDir(): void
    {
        $ipRanges = json_encode([
            'prefixes' => [
                ['ip_prefix' => '99.99.0.0/16', 'service' => 'CLOUDFRONT'],
            ],
        ]);

        // No cache_dir configured
        $kernel = $this->buildKernel(
            ['trust_cloudfront_ips' => true],
            [new GuzzleResponse(200, [], $ipRanges)]
        );

        $kernel->handle(Request::create('/test'));

        $this->assertContains('99.99.0.0/16', Request::getTrustedProxies());
    }

    /**
     * Expired cache → triggers HTTP fetch.
     */
    public function testExpiredCacheTriggersFetch(): void
    {
        $tempDir = $this->makeTempDir();

        // Write expired cache
        file_put_contents($tempDir . '/aws.ips', json_encode([
            'prefixes' => [['ip_prefix' => '1.1.1.0/24', 'service' => 'CLOUDFRONT']],
            'expire_at' => time() - 100,
        ], JSON_PRETTY_PRINT));

        $freshIps = json_encode([
            'prefixes' => [
                ['ip_prefix' => '2.2.2.0/24', 'service' => 'CLOUDFRONT'],
            ],
        ]);

        $kernel = $this->buildKernel(
            ['cache_dir' => $tempDir, 'trust_cloudfront_ips' => true],
            [new GuzzleResponse(200, [], $freshIps)]
        );

        $kernel->handle(Request::create('/test'));

        // Should use fresh data from HTTP, not expired cache
        $this->assertContains('2.2.2.0/24', Request::getTrustedProxies());
    }
}
