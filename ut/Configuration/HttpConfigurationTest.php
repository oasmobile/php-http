<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class HttpConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var HttpConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new HttpConfiguration();
    }

    /**
     * Helper: process a single config array through HttpConfiguration.
     *
     * @param array $config
     *
     * @return array
     */
    private function process(array $config)
    {
        return $this->processor->processConfiguration(
            $this->configuration,
            [$config]
        );
    }

    //----------------------------------------------------------------------
    // Default values
    //----------------------------------------------------------------------

    public function testCacheDirDefaultsToNull()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('cache_dir', $result);
        $this->assertNull($result['cache_dir']);
    }

    public function testBehindElbDefaultsToFalse()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('behind_elb', $result);
        $this->assertFalse($result['behind_elb']);
    }

    public function testTrustCloudfrontIpsDefaultsToFalse()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('trust_cloudfront_ips', $result);
        $this->assertFalse($result['trust_cloudfront_ips']);
    }

    //----------------------------------------------------------------------
    // Variable nodes accept arbitrary values
    //----------------------------------------------------------------------

    /**
     * @dataProvider variableNodeProvider
     */
    public function testVariableNodeAcceptsArbitraryValue($nodeName, $value)
    {
        $result = $this->process([$nodeName => $value]);

        $this->assertSame($value, $result[$nodeName]);
    }

    public function variableNodeProvider()
    {
        return [
            'routing — array'          => ['routing', ['path' => '/routes.yml']],
            'routing — string'         => ['routing', '/routes.yml'],
            'twig — array'             => ['twig', ['template_dir' => '/tpl']],
            'security — array'         => ['security', ['firewalls' => []]],
            'cors — array'             => ['cors', [['pattern' => '/api']]],
            'view_handlers — array'    => ['view_handlers', ['json' => true]],
            'error_handlers — array'   => ['error_handlers', ['handler1']],
            'injected_args — array'    => ['injected_args', ['arg1' => 'val1']],
            'middlewares — array'      => ['middlewares', ['mw1', 'mw2']],
            'providers — array'        => ['providers', ['provider1']],
            'trusted_proxies — array'  => ['trusted_proxies', ['10.0.0.0/8']],
            'trusted_header_set — int' => ['trusted_header_set', 0x1e],
        ];
    }

    //----------------------------------------------------------------------
    // Explicit scalar values override defaults
    //----------------------------------------------------------------------

    public function testExplicitCacheDirOverridesDefault()
    {
        $result = $this->process(['cache_dir' => '/tmp/cache']);

        $this->assertSame('/tmp/cache', $result['cache_dir']);
    }

    public function testExplicitBehindElbOverridesDefault()
    {
        $result = $this->process(['behind_elb' => true]);

        $this->assertTrue($result['behind_elb']);
    }

    public function testExplicitTrustCloudfrontIpsOverridesDefault()
    {
        $result = $this->process(['trust_cloudfront_ips' => true]);

        $this->assertTrue($result['trust_cloudfront_ips']);
    }

    //----------------------------------------------------------------------
    // Unknown key throws InvalidConfigurationException
    //----------------------------------------------------------------------

    public function testUnknownKeyThrowsException()
    {
        $this->setExpectedException(InvalidConfigurationException::class);

        $this->process(['unknown_key' => 'value']);
    }
}
