<?php
declare(strict_types=1);

/**
 * Property-Based Tests for SecurityTrait Merge Logic.
 *
 * Feature: hotfix/3.7.0
 * Task: 5.2 — SecurityTrait 合并逻辑属性测试
 *
 * **Validates: Requirements Expected Behavior 5, 6, 7, 9, 11**
 *
 * 验证 mergeSecurityConfigs() 的合并逻辑正确性：
 * - 对所有随机 security config 片段，合并输出正确
 * - 对所有随机注册顺序，access_rules 保持插入顺序
 * - 对所有含重复 firewall 名的随机 config，抛出异常（除非 overwrite）
 * - 对所有含重复 policy 名的随机 config，抛出异常（除非 overwrite）
 * - 对所有含重复 role_hierarchy 角色的随机 config，抛出异常（除非 overwrite）
 * - 对所有 $allowOverwrite = true 的随机 config，firewalls/policies/roles 采用 last-write-wins
 */

namespace Oasis\Mlib\Http\Test\PBT\Security;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;

class SecurityMergePropertyTest extends TestCase
{
    use TestTrait;

    // ═══════════════════════════════════════════════════════════════════
    // Property: Correct Merge Output for Random Config Fragments
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all random security config fragments, mergeSecurityConfigs()
     * produces correct merged output — all firewalls, policies, role_hierarchy
     * entries appear in the result, and access_rules are appended.
     *
     * **Validates: Requirements Expected Behavior 5, 6, 7, 9, 11**
     */
    public function testMergeProducesCorrectOutput(): void
    {
        $this->forAll(
            // Number of firewalls (1–4)
            Generators::choose(1, 4),
            // Number of policies (1–4)
            Generators::choose(1, 4),
            // Number of access rules (1–5)
            Generators::choose(1, 5),
            // Number of role hierarchy entries (1–3)
            Generators::choose(1, 3)
        )->then(function (int $fwCount, int $policyCount, int $ruleCount, int $roleCount) {
            $kernel = $this->createUnbootedKernel();

            // Inject unique firewalls
            $expectedFirewalls = [];
            for ($i = 0; $i < $fwCount; $i++) {
                $name = "fw_merge_{$i}";
                $config = ['pattern' => "^/merge/{$i}", 'policies' => ['p' => true]];
                $kernel->addFirewall($name, $config);
                $expectedFirewalls[$name] = $config;
            }

            // Inject unique policies
            $expectedPolicies = [];
            for ($i = 0; $i < $policyCount; $i++) {
                $name = "policy_merge_{$i}";
                $config = ['type' => 'pre_auth', 'idx' => $i];
                $kernel->addPolicy($name, $config);
                $expectedPolicies[$name] = $config;
            }

            // Inject access rules
            $expectedRules = [];
            for ($i = 0; $i < $ruleCount; $i++) {
                $rule = ['pattern' => "^/rule/{$i}", 'roles' => ['ROLE_USER']];
                $kernel->addAccessRule($rule);
                $expectedRules[] = $rule;
            }

            // Inject unique role hierarchy entries
            $expectedRoles = [];
            for ($i = 0; $i < $roleCount; $i++) {
                $role = "ROLE_MERGE_{$i}";
                $children = ['ROLE_USER'];
                $kernel->addRoleHierarchy($role, $children);
                $expectedRoles[$role] = $children;
            }

            // Query merged config
            $merged = $kernel->getSecurityConfig();

            // Verify all firewalls present
            $this->assertArrayHasKey('firewalls', $merged);
            foreach ($expectedFirewalls as $name => $config) {
                $this->assertArrayHasKey($name, $merged['firewalls'], "Firewall '{$name}' should be in merged config");
                $this->assertSame($config, $merged['firewalls'][$name]);
            }

            // Verify all policies present
            $this->assertArrayHasKey('policies', $merged);
            foreach ($expectedPolicies as $name => $config) {
                $this->assertArrayHasKey($name, $merged['policies'], "Policy '{$name}' should be in merged config");
                $this->assertSame($config, $merged['policies'][$name]);
            }

            // Verify all access rules present and in order
            $this->assertArrayHasKey('access_rules', $merged);
            $this->assertSame($expectedRules, $merged['access_rules']);

            // Verify all role hierarchy entries present
            $this->assertArrayHasKey('role_hierarchy', $merged);
            foreach ($expectedRoles as $role => $children) {
                $this->assertArrayHasKey($role, $merged['role_hierarchy'], "Role '{$role}' should be in merged config");
                $this->assertSame($children, $merged['role_hierarchy'][$role]);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property: access_rules Preserve Insertion Order
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all random registration orders, access_rules maintain
     * insertion order in the merged output.
     *
     * **Validates: Requirements Expected Behavior 6**
     */
    public function testAccessRulesPreserveInsertionOrder(): void
    {
        $this->forAll(
            // Number of access rules (2–8)
            Generators::choose(2, 8)
        )->then(function (int $ruleCount) {
            $kernel = $this->createUnbootedKernel();

            $expectedOrder = [];
            for ($i = 0; $i < $ruleCount; $i++) {
                $rule = ['pattern' => "^/order/{$i}", 'roles' => ["ROLE_LEVEL_{$i}"]];
                $kernel->addAccessRule($rule);
                $expectedOrder[] = $rule;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertArrayHasKey('access_rules', $merged);
            $this->assertCount($ruleCount, $merged['access_rules']);

            // Verify exact order preservation
            for ($i = 0; $i < $ruleCount; $i++) {
                $this->assertSame(
                    $expectedOrder[$i],
                    $merged['access_rules'][$i],
                    "Access rule at position {$i} should match insertion order"
                );
            }
        });
    }

    /**
     * Property: access_rules from Constructor_Config come before Pending_Queue rules,
     * and within Pending_Queue, rules maintain registration order.
     *
     * **Validates: Requirements Expected Behavior 6**
     */
    public function testAccessRulesConstructorBeforePending(): void
    {
        $this->forAll(
            // Number of constructor rules (1–3)
            Generators::choose(1, 3),
            // Number of pending rules (1–4)
            Generators::choose(1, 4)
        )->then(function (int $ctorCount, int $pendingCount) {
            // Constructor config with access_rules
            $ctorRules = [];
            for ($i = 0; $i < $ctorCount; $i++) {
                $ctorRules[] = ['pattern' => "^/ctor/{$i}", 'roles' => ['ROLE_CTOR']];
            }

            $kernel = $this->createUnbootedKernel(['access_rules' => $ctorRules]);

            // Add pending rules
            $pendingRules = [];
            for ($i = 0; $i < $pendingCount; $i++) {
                $rule = ['pattern' => "^/pending/{$i}", 'roles' => ['ROLE_PENDING']];
                $kernel->addAccessRule($rule);
                $pendingRules[] = $rule;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertArrayHasKey('access_rules', $merged);
            $allRules = $merged['access_rules'];
            $this->assertCount($ctorCount + $pendingCount, $allRules);

            // Constructor rules come first
            for ($i = 0; $i < $ctorCount; $i++) {
                $this->assertSame($ctorRules[$i], $allRules[$i], "Constructor rule at position {$i} should come first");
            }

            // Pending rules come after
            for ($i = 0; $i < $pendingCount; $i++) {
                $this->assertSame(
                    $pendingRules[$i],
                    $allRules[$ctorCount + $i],
                    "Pending rule at position {$i} should come after constructor rules"
                );
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property: Duplicate Firewall Names Throw Exception (unless overwrite)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all random configs containing duplicate firewall names,
     * an exception is thrown (unless $allowOverwrite = true).
     *
     * **Validates: Requirements Expected Behavior 5**
     */
    public function testDuplicateFirewallThrowsException(): void
    {
        $this->forAll(
            Generators::elements('api', 'admin', 'web', 'main', 'internal')
        )->then(function (string $firewallName) {
            $kernel = $this->createUnbootedKernel();

            // First registration succeeds
            $kernel->addFirewall($firewallName, ['pattern' => '^/first', 'policies' => ['p' => true]]);

            // Second registration with same name but different config throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate firewall: '{$firewallName}'");
            $kernel->addFirewall($firewallName, ['pattern' => '^/second', 'policies' => ['q' => true]]);
        });
    }

    /**
     * Property: Duplicate firewall from Constructor_Config also throws.
     *
     * **Validates: Requirements Expected Behavior 5**
     */
    public function testDuplicateFirewallFromConstructorThrows(): void
    {
        $this->forAll(
            Generators::elements('api', 'admin', 'web', 'main')
        )->then(function (string $firewallName) {
            $ctorConfig = [
                'firewalls' => [
                    $firewallName => ['pattern' => '^/ctor', 'policies' => ['p' => true]],
                ],
            ];

            $kernel = $this->createUnbootedKernel($ctorConfig);

            // Attempting to add same-name firewall with different config throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate firewall: '{$firewallName}'");
            $kernel->addFirewall($firewallName, ['pattern' => '^/pending', 'policies' => ['q' => true]]);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property: Duplicate Policy Names Throw Exception (unless overwrite)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all random configs containing duplicate policy names,
     * an exception is thrown (unless $allowOverwrite = true).
     *
     * **Validates: Requirements Expected Behavior 9**
     */
    public function testDuplicatePolicyThrowsException(): void
    {
        $this->forAll(
            Generators::elements('token_auth', 'jwt_auth', 'basic_auth', 'oauth2', 'api_key')
        )->then(function (string $policyName) {
            $kernel = $this->createUnbootedKernel();

            // First registration succeeds
            $kernel->addPolicy($policyName, ['type' => 'pre_auth', 'version' => 1]);

            // Second registration with same name but different config throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate policy: '{$policyName}'");
            $kernel->addPolicy($policyName, ['type' => 'pre_auth', 'version' => 2]);
        });
    }

    /**
     * Property: Duplicate policy from Constructor_Config also throws.
     *
     * **Validates: Requirements Expected Behavior 9**
     */
    public function testDuplicatePolicyFromConstructorThrows(): void
    {
        $this->forAll(
            Generators::elements('token_auth', 'jwt_auth', 'basic_auth', 'oauth2')
        )->then(function (string $policyName) {
            $ctorConfig = [
                'policies' => [
                    $policyName => ['type' => 'pre_auth', 'version' => 1],
                ],
            ];

            $kernel = $this->createUnbootedKernel($ctorConfig);

            // Attempting to add same-name policy with different config throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate policy: '{$policyName}'");
            $kernel->addPolicy($policyName, ['type' => 'pre_auth', 'version' => 2]);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property: Duplicate role_hierarchy Roles Throw Exception (unless overwrite)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all random configs containing duplicate role_hierarchy roles,
     * an exception is thrown (unless $allowOverwrite = true).
     *
     * **Validates: Requirements Expected Behavior 7, 11**
     */
    public function testDuplicateRoleHierarchyThrowsException(): void
    {
        $this->forAll(
            Generators::elements('ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_EDITOR', 'ROLE_MODERATOR')
        )->then(function (string $role) {
            $kernel = $this->createUnbootedKernel();

            // First registration succeeds
            $kernel->addRoleHierarchy($role, ['ROLE_USER']);

            // Second registration with same role but different children throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate role in role_hierarchy: '{$role}'");
            $kernel->addRoleHierarchy($role, ['ROLE_STAFF', 'ROLE_USER']);
        });
    }

    /**
     * Property: Duplicate role from Constructor_Config also throws.
     *
     * **Validates: Requirements Expected Behavior 7, 11**
     */
    public function testDuplicateRoleFromConstructorThrows(): void
    {
        $this->forAll(
            Generators::elements('ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_EDITOR')
        )->then(function (string $role) {
            $ctorConfig = [
                'role_hierarchy' => [
                    $role => ['ROLE_USER'],
                ],
            ];

            $kernel = $this->createUnbootedKernel($ctorConfig);

            // Attempting to add same role with different children throws
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage("Duplicate role in role_hierarchy: '{$role}'");
            $kernel->addRoleHierarchy($role, ['ROLE_STAFF']);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property: $allowOverwrite = true → Last-Write-Wins
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property: For all $allowOverwrite = true configs, firewalls adopt
     * last-write-wins semantics — the later registration overwrites the earlier one.
     *
     * **Validates: Requirements Expected Behavior 5**
     */
    public function testOverwriteFirewallLastWriteWins(): void
    {
        $this->forAll(
            Generators::elements('api', 'admin', 'web', 'main'),
            Generators::choose(2, 5)
        )->then(function (string $firewallName, int $writeCount) {
            $kernel = $this->createUnbootedKernel();

            $lastConfig = null;
            for ($i = 0; $i < $writeCount; $i++) {
                $config = ['pattern' => "^/version/{$i}", 'policies' => ['p' => true]];
                $kernel->addFirewall($firewallName, $config, true); // allowOverwrite = true
                $lastConfig = $config;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertArrayHasKey('firewalls', $merged);
            $this->assertArrayHasKey($firewallName, $merged['firewalls']);
            $this->assertSame(
                $lastConfig,
                $merged['firewalls'][$firewallName],
                "Firewall '{$firewallName}' should have last-write-wins value"
            );
        });
    }

    /**
     * Property: For all $allowOverwrite = true configs, policies adopt
     * last-write-wins semantics.
     *
     * **Validates: Requirements Expected Behavior 9**
     */
    public function testOverwritePolicyLastWriteWins(): void
    {
        $this->forAll(
            Generators::elements('token_auth', 'jwt_auth', 'basic_auth', 'oauth2'),
            Generators::choose(2, 5)
        )->then(function (string $policyName, int $writeCount) {
            $kernel = $this->createUnbootedKernel();

            $lastConfig = null;
            for ($i = 0; $i < $writeCount; $i++) {
                $config = ['type' => 'pre_auth', 'version' => $i];
                $kernel->addPolicy($policyName, $config, true); // allowOverwrite = true
                $lastConfig = $config;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertArrayHasKey('policies', $merged);
            $this->assertArrayHasKey($policyName, $merged['policies']);
            $this->assertSame(
                $lastConfig,
                $merged['policies'][$policyName],
                "Policy '{$policyName}' should have last-write-wins value"
            );
        });
    }

    /**
     * Property: For all $allowOverwrite = true configs, role_hierarchy adopts
     * last-write-wins semantics.
     *
     * **Validates: Requirements Expected Behavior 7, 11**
     */
    public function testOverwriteRoleHierarchyLastWriteWins(): void
    {
        $this->forAll(
            Generators::elements('ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_EDITOR'),
            Generators::choose(2, 5)
        )->then(function (string $role, int $writeCount) {
            $kernel = $this->createUnbootedKernel();

            $lastChildren = null;
            for ($i = 0; $i < $writeCount; $i++) {
                $children = ["ROLE_CHILD_{$i}"];
                $kernel->addRoleHierarchy($role, $children, true); // allowOverwrite = true
                $lastChildren = $children;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertArrayHasKey('role_hierarchy', $merged);
            $this->assertArrayHasKey($role, $merged['role_hierarchy']);
            $this->assertSame(
                $lastChildren,
                $merged['role_hierarchy'][$role],
                "Role '{$role}' should have last-write-wins children"
            );
        });
    }

    /**
     * Property: $allowOverwrite = true via addSecurityConfig batch API also
     * adopts last-write-wins for firewalls, policies, and role_hierarchy.
     *
     * **Validates: Requirements Expected Behavior 5, 9, 11**
     */
    public function testBatchOverwriteLastWriteWins(): void
    {
        $this->forAll(
            Generators::choose(2, 4)
        )->then(function (int $writeCount) {
            $kernel = $this->createUnbootedKernel();

            $lastFirewall = null;
            $lastPolicy = null;
            $lastRole = null;

            for ($i = 0; $i < $writeCount; $i++) {
                $fwConfig = ['pattern' => "^/batch/{$i}", 'policies' => ['p' => true]];
                $policyConfig = ['type' => 'pre_auth', 'batch_version' => $i];
                $roleChildren = ["ROLE_BATCH_CHILD_{$i}"];

                $kernel->addSecurityConfig([
                    'firewalls'      => ['batch_fw' => $fwConfig],
                    'policies'       => ['batch_policy' => $policyConfig],
                    'role_hierarchy' => ['ROLE_BATCH' => $roleChildren],
                ], true); // allowOverwrite = true

                $lastFirewall = $fwConfig;
                $lastPolicy = $policyConfig;
                $lastRole = $roleChildren;
            }

            $merged = $kernel->getSecurityConfig();

            $this->assertSame($lastFirewall, $merged['firewalls']['batch_fw']);
            $this->assertSame($lastPolicy, $merged['policies']['batch_policy']);
            $this->assertSame($lastRole, $merged['role_hierarchy']['ROLE_BATCH']);
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create an unbooted MicroKernel instance for testing.
     *
     * @param array|null $securityConfig Optional security config for constructor
     */
    private function createUnbootedKernel(?array $securityConfig = null): MicroKernel
    {
        $httpConfig = [];
        if ($securityConfig !== null) {
            $httpConfig['security'] = $securityConfig;
        }

        return new MicroKernel($httpConfig, true);
    }
}
