<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\CrossOriginResourceSharingConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class CrossOriginResourceSharingConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var CrossOriginResourceSharingConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new CrossOriginResourceSharingConfiguration();
    }

    /**
     * Helper: process a single config array through CrossOriginResourceSharingConfiguration.
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
    // pattern — required field
    //----------------------------------------------------------------------

    public function testPatternIsRequired()
    {
        $this->setExpectedException(\Exception::class);

        $this->process([]);
    }

    public function testPatternAcceptsString()
    {
        $result = $this->process(['pattern' => '^/api']);

        $this->assertSame('^/api', $result['pattern']);
    }

    //----------------------------------------------------------------------
    // origins — beforeNormalization
    //----------------------------------------------------------------------

    public function testOriginsStringAutoConvertedToArray()
    {
        $result = $this->process([
            'pattern' => '^/api',
            'origins' => 'http://example.com',
        ]);

        $this->assertSame(['http://example.com'], $result['origins']);
    }

    public function testOriginsArrayRemainsUnchanged()
    {
        $origins = ['http://example.com', 'http://other.com'];

        $result = $this->process([
            'pattern' => '^/api',
            'origins' => $origins,
        ]);

        $this->assertSame($origins, $result['origins']);
    }

    //----------------------------------------------------------------------
    // max_age — default value
    //----------------------------------------------------------------------

    public function testMaxAgeDefaultsTo86400()
    {
        $result = $this->process(['pattern' => '^/api']);

        $this->assertSame(86400, $result['max_age']);
    }

    public function testMaxAgeExplicitOverride()
    {
        $result = $this->process([
            'pattern' => '^/api',
            'max_age' => 3600,
        ]);

        $this->assertSame(3600, $result['max_age']);
    }

    //----------------------------------------------------------------------
    // credentials_allowed — default value
    //----------------------------------------------------------------------

    public function testCredentialsAllowedDefaultsToFalse()
    {
        $result = $this->process(['pattern' => '^/api']);

        $this->assertFalse($result['credentials_allowed']);
    }

    public function testCredentialsAllowedExplicitTrue()
    {
        $result = $this->process([
            'pattern'             => '^/api',
            'credentials_allowed' => true,
        ]);

        $this->assertTrue($result['credentials_allowed']);
    }

    //----------------------------------------------------------------------
    // Optional variable nodes: headers, headers_exposed
    //----------------------------------------------------------------------

    public function testHeadersOptionalNotPresentByDefault()
    {
        $result = $this->process(['pattern' => '^/api']);

        // variable nodes without defaultValue are not present when omitted
        $this->assertArrayNotHasKey('headers', $result);
    }

    public function testHeadersAcceptsArray()
    {
        $headers = ['Content-Type', 'Authorization'];

        $result = $this->process([
            'pattern' => '^/api',
            'headers' => $headers,
        ]);

        $this->assertSame($headers, $result['headers']);
    }

    public function testHeadersExposedOptionalNotPresentByDefault()
    {
        $result = $this->process(['pattern' => '^/api']);

        $this->assertArrayNotHasKey('headers_exposed', $result);
    }

    public function testHeadersExposedAcceptsArray()
    {
        $exposed = ['X-Custom-Header', 'X-Request-Id'];

        $result = $this->process([
            'pattern'         => '^/api',
            'headers_exposed' => $exposed,
        ]);

        $this->assertSame($exposed, $result['headers_exposed']);
    }
}
