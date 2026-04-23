<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\SimpleFirewallConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class SimpleFirewallConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var SimpleFirewallConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new SimpleFirewallConfiguration();
    }

    /**
     * Helper: process a single config array through SimpleFirewallConfiguration.
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

    /**
     * @return array Minimal valid configuration for SimpleFirewallConfiguration.
     */
    private function minimalConfig()
    {
        return [
            'pattern'  => '^/',
            'policies' => ['basic'],
            'users'    => ['admin' => ['password' => 'secret']],
        ];
    }

    //----------------------------------------------------------------------
    // Required fields
    //----------------------------------------------------------------------

    public function testPatternIsRequired()
    {
        $this->setExpectedException(\Exception::class);

        $this->process([
            'policies' => ['basic'],
            'users'    => ['admin' => []],
        ]);
    }

    public function testPoliciesIsRequired()
    {
        $this->setExpectedException(\Exception::class);

        $this->process([
            'pattern' => '^/',
            'users'   => ['admin' => []],
        ]);
    }

    public function testUsersIsRequired()
    {
        $this->setExpectedException(\Exception::class);

        $this->process([
            'pattern'  => '^/',
            'policies' => ['basic'],
        ]);
    }

    //----------------------------------------------------------------------
    // stateless — defaults to false
    //----------------------------------------------------------------------

    /**
     * Behavior_Baseline: defaultValue('false') passes a string, so the
     * booleanNode casts it to the string 'false' rather than boolean false.
     * This records the actual behavior — do not change.
     */
    public function testStatelessDefaultsToStringFalse()
    {
        $result = $this->process($this->minimalConfig());

        $this->assertArrayHasKey('stateless', $result);
        // Actual behavior: defaultValue('false') yields string 'false', not boolean
        $this->assertSame('false', $result['stateless']);
    }

    public function testStatelessExplicitTrue()
    {
        $config             = $this->minimalConfig();
        $config['stateless'] = true;

        $result = $this->process($config);

        $this->assertTrue($result['stateless']);
    }

    //----------------------------------------------------------------------
    // misc — defaults to empty array
    //----------------------------------------------------------------------

    public function testMiscDefaultsToEmptyArray()
    {
        $result = $this->process($this->minimalConfig());

        $this->assertArrayHasKey('misc', $result);
        $this->assertSame([], $result['misc']);
    }

    public function testMiscAcceptsArbitraryValue()
    {
        $config         = $this->minimalConfig();
        $config['misc'] = ['custom_key' => 'custom_value'];

        $result = $this->process($config);

        $this->assertSame(['custom_key' => 'custom_value'], $result['misc']);
    }

    //----------------------------------------------------------------------
    // Full valid configuration
    //----------------------------------------------------------------------

    public function testFullValidConfiguration()
    {
        $config = [
            'pattern'   => '^/api',
            'policies'  => ['token', 'basic'],
            'users'     => ['admin' => ['password' => 'secret', 'roles' => ['ROLE_ADMIN']]],
            'stateless' => true,
            'misc'      => ['entry_point' => 'null'],
        ];

        $result = $this->process($config);

        $this->assertSame('^/api', $result['pattern']);
        $this->assertSame(['token', 'basic'], $result['policies']);
        $this->assertTrue($result['stateless']);
        $this->assertSame(['entry_point' => 'null'], $result['misc']);
    }
}
