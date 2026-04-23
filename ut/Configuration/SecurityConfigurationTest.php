<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\SecurityConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class SecurityConfigurationTest extends TestCase
{
    /** @var Processor */
    private $processor;

    /** @var SecurityConfiguration */
    private $configuration;

    protected function setUp()
    {
        $this->processor     = new Processor();
        $this->configuration = new SecurityConfiguration();
    }

    /**
     * Helper: process a single config array through SecurityConfiguration.
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
    // Empty configuration
    //----------------------------------------------------------------------

    public function testEmptyConfigurationPassesValidation()
    {
        $result = $this->process([]);

        $this->assertArrayHasKey('policies', $result);
        $this->assertArrayHasKey('firewalls', $result);
        $this->assertArrayHasKey('access_rules', $result);
        $this->assertArrayHasKey('role_hierarchy', $result);
    }

    //----------------------------------------------------------------------
    // Array prototypes
    //----------------------------------------------------------------------

    public function testPoliciesAcceptsArrayPrototype()
    {
        $policies = [
            'basic' => ['class' => 'BasicPolicy', 'options' => []],
            'token' => ['class' => 'TokenPolicy'],
        ];

        $result = $this->process(['policies' => $policies]);

        $this->assertSame($policies, $result['policies']);
    }

    public function testFirewallsAcceptsArrayPrototype()
    {
        $firewalls = [
            'main' => ['pattern' => '^/', 'policies' => ['basic']],
        ];

        $result = $this->process(['firewalls' => $firewalls]);

        $this->assertSame($firewalls, $result['firewalls']);
    }

    public function testAccessRulesAcceptsArrayPrototype()
    {
        $rules = [
            ['pattern' => '^/admin', 'roles' => ['ROLE_ADMIN']],
            ['pattern' => '^/api', 'roles' => ['ROLE_USER']],
        ];

        $result = $this->process(['access_rules' => $rules]);

        $this->assertSame($rules, $result['access_rules']);
    }

    //----------------------------------------------------------------------
    // role_hierarchy beforeNormalization
    //----------------------------------------------------------------------

    public function testRoleHierarchyStringAutoConvertedToArray()
    {
        $result = $this->process([
            'role_hierarchy' => [
                'ROLE_ADMIN' => 'ROLE_USER',
            ],
        ]);

        $this->assertSame(['ROLE_USER'], $result['role_hierarchy']['ROLE_ADMIN']);
    }

    public function testRoleHierarchyArrayRemainsUnchanged()
    {
        $roles = ['ROLE_USER', 'ROLE_EDITOR'];

        $result = $this->process([
            'role_hierarchy' => [
                'ROLE_ADMIN' => $roles,
            ],
        ]);

        $this->assertSame($roles, $result['role_hierarchy']['ROLE_ADMIN']);
    }

    public function testRoleHierarchyMultipleRoles()
    {
        $result = $this->process([
            'role_hierarchy' => [
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
                'ROLE_ADMIN'       => 'ROLE_USER',
            ],
        ]);

        $this->assertSame(
            ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            $result['role_hierarchy']['ROLE_SUPER_ADMIN']
        );
        $this->assertSame(
            ['ROLE_USER'],
            $result['role_hierarchy']['ROLE_ADMIN']
        );
    }
}
