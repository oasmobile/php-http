<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\SimpleAccessRuleConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class SimpleAccessRuleConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var SimpleAccessRuleConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new SimpleAccessRuleConfiguration();
    }

    /**
     * Helper: process a single config array through SimpleAccessRuleConfiguration.
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

        $this->process(['roles' => ['ROLE_USER']]);
    }

    public function testPatternAcceptsValue()
    {
        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => ['ROLE_ADMIN'],
        ]);

        $this->assertSame('^/admin', $result['pattern']);
    }

    //----------------------------------------------------------------------
    // roles — required field
    //----------------------------------------------------------------------

    public function testRolesIsRequired()
    {
        $this->setExpectedException(\Exception::class);

        $this->process(['pattern' => '^/admin']);
    }

    //----------------------------------------------------------------------
    // roles — beforeNormalization
    //----------------------------------------------------------------------

    public function testRolesStringAutoConvertedToArray()
    {
        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => 'ROLE_ADMIN',
        ]);

        $this->assertSame(['ROLE_ADMIN'], $result['roles']);
    }

    public function testRolesArrayRemainsUnchanged()
    {
        $roles = ['ROLE_ADMIN', 'ROLE_USER'];

        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => $roles,
        ]);

        $this->assertSame($roles, $result['roles']);
    }

    //----------------------------------------------------------------------
    // channel — enum with default null
    //----------------------------------------------------------------------

    public function testChannelDefaultsToNull()
    {
        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => ['ROLE_ADMIN'],
        ]);

        $this->assertArrayHasKey('channel', $result);
        $this->assertNull($result['channel']);
    }

    public function testChannelAcceptsHttp()
    {
        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => ['ROLE_ADMIN'],
            'channel' => 'http',
        ]);

        $this->assertSame('http', $result['channel']);
    }

    public function testChannelAcceptsHttps()
    {
        $result = $this->process([
            'pattern' => '^/admin',
            'roles'   => ['ROLE_ADMIN'],
            'channel' => 'https',
        ]);

        $this->assertSame('https', $result['channel']);
    }

    public function testChannelRejectsInvalidValue()
    {
        $this->setExpectedException(\Exception::class);

        $this->process([
            'pattern' => '^/admin',
            'roles'   => ['ROLE_ADMIN'],
            'channel' => 'ftp',
        ]);
    }
}
