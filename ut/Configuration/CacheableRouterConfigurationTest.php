<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class CacheableRouterConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var CacheableRouterConfiguration */
    private $configuration;

    protected function setUp(): void
    {
        $this->processor     = new Processor();
        $this->configuration = new CacheableRouterConfiguration();
    }

    /**
     * Helper: process a single config array through CacheableRouterConfiguration.
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
    // path — optional scalar
    //----------------------------------------------------------------------

    public function testPathOptionalNotPresentByDefault()
    {
        $result = $this->process([]);

        $this->assertArrayNotHasKey('path', $result);
    }

    public function testPathAcceptsString()
    {
        $result = $this->process(['path' => '/config/routes.yml']);

        $this->assertSame('/config/routes.yml', $result['path']);
    }

    //----------------------------------------------------------------------
    // cache_dir — defaults to null
    //----------------------------------------------------------------------

    public function testCacheDirDefaultsToNull()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('cache_dir', $result);
        $this->assertNull($result['cache_dir']);
    }

    public function testCacheDirExplicitOverride()
    {
        $result = $this->process(['cache_dir' => '/tmp/router-cache']);

        $this->assertSame('/tmp/router-cache', $result['cache_dir']);
    }

    //----------------------------------------------------------------------
    // namespaces — beforeNormalization
    //----------------------------------------------------------------------

    public function testNamespacesStringAutoConvertedToArray()
    {
        $result = $this->process(['namespaces' => 'App\\Controller']);

        $this->assertSame(['App\\Controller'], $result['namespaces']);
    }

    public function testNamespacesArrayRemainsUnchanged()
    {
        $namespaces = ['App\\Controller', 'App\\Api'];

        $result = $this->process(['namespaces' => $namespaces]);

        $this->assertSame($namespaces, $result['namespaces']);
    }

    public function testNamespacesNotPresentByDefault()
    {
        $result = $this->process([]);

        $this->assertArrayNotHasKey('namespaces', $result);
    }
}
