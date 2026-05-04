<?php
declare(strict_types=1);

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

    protected function setUp(): void
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
        $this->expectException(\Exception::class);

        $this->process([
            'policies' => ['basic'],
            'users'    => ['admin' => []],
        ]);
    }

    public function testPoliciesIsRequired()
    {
        $this->expectException(\Exception::class);

        $this->process([
            'pattern' => '^/',
            'users'   => ['admin' => []],
        ]);
    }

    public function testUsersIsRequired()
    {
        $this->expectException(\Exception::class);

        $this->process([
            'pattern'  => '^/',
            'policies' => ['basic'],
        ]);
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
            'misc'      => ['entry_point' => 'null'],
        ];

        $result = $this->process($config);

        $this->assertSame('^/api', $result['pattern']);
        $this->assertSame(['token', 'basic'], $result['policies']);
        $this->assertSame(['entry_point' => 'null'], $result['misc']);
    }
}
