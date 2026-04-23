<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\TwigConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class TwigConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var TwigConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new TwigConfiguration();
    }

    /**
     * Helper: process a single config array through TwigConfiguration.
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
    // template_dir — optional scalar
    //----------------------------------------------------------------------

    public function testTemplateDirOptionalNotPresentByDefault()
    {
        $result = $this->process([]);

        // scalar node without defaultValue is not present when omitted
        $this->assertArrayNotHasKey('template_dir', $result);
    }

    public function testTemplateDirAcceptsString()
    {
        $result = $this->process(['template_dir' => '/path/to/templates']);

        $this->assertSame('/path/to/templates', $result['template_dir']);
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
        $result = $this->process(['cache_dir' => '/tmp/twig-cache']);

        $this->assertSame('/tmp/twig-cache', $result['cache_dir']);
    }

    //----------------------------------------------------------------------
    // asset_base — defaults to empty string
    //----------------------------------------------------------------------

    public function testAssetBaseDefaultsToEmptyString()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('asset_base', $result);
        $this->assertSame('', $result['asset_base']);
    }

    public function testAssetBaseExplicitOverride()
    {
        $result = $this->process(['asset_base' => 'https://cdn.example.com']);

        $this->assertSame('https://cdn.example.com', $result['asset_base']);
    }

    //----------------------------------------------------------------------
    // globals — defaults to empty array
    //----------------------------------------------------------------------

    public function testGlobalsDefaultsToEmptyArray()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('globals', $result);
        $this->assertSame([], $result['globals']);
    }

    public function testGlobalsAcceptsArray()
    {
        $globals = ['app_name' => 'MyApp', 'version' => '1.0'];

        $result = $this->process(['globals' => $globals]);

        $this->assertSame($globals, $result['globals']);
    }
}
