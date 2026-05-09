<?php
declare(strict_types=1);

/**
 * Bug Condition Exploration Test — Security Config Injection API Missing.
 *
 * Feature: hotfix/3.7.0
 * Property 1: Bug Condition — Security Config Injection API Missing
 *
 * **Validates: Requirements Expected Behavior 1, 2, 3, 4**
 *
 * 此测试编码了期望行为：MicroKernel 应提供 pre-boot security config 注入 API。
 * 在未修复代码上，这些方法不存在，测试将因 Fatal Error（Call to undefined method）而失败。
 * 修复实现后，测试通过即验证期望行为已满足。
 *
 * 预期结果（未修复代码）：测试失败——证明 bug 存在。
 */

namespace Oasis\Mlib\Http\Test\PBT\Security;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;

class SecurityInjectionBugConditionTest extends TestCase
{
    use TestTrait;

    // ─── Bug Condition: addSecurityConfig() 方法不存在 ──────────────

    /**
     * Property 1: Bug Condition — addSecurityConfig() API Missing
     *
     * 在 MicroKernel 上调用 addSecurityConfig() 应被接受并存储到 Pending_Queue。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 1**
     */
    public function testAddSecurityConfigMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->forAll(
            Generators::associative([
                'pattern' => Generators::constant('^/api'),
                'policies' => Generators::constant(['token_auth' => true]),
            ])
        )->then(function (array $firewallConfig) use ($kernel) {
            // This call should succeed on fixed code.
            // On unfixed code, it triggers: Fatal Error: Call to undefined method
            $kernel->addSecurityConfig([
                'firewalls' => ['api' => $firewallConfig],
            ]);

            $this->assertTrue(
                method_exists($kernel, 'addSecurityConfig'),
                'MicroKernel should have addSecurityConfig() method'
            );
        });
    }

    // ─── Bug Condition: addFirewall() 方法不存在 ────────────────────

    /**
     * Property 1: Bug Condition — addFirewall() API Missing
     *
     * 在 MicroKernel 上调用 addFirewall() 应被接受并存储到 Pending_Queue。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 2**
     */
    public function testAddFirewallMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->forAll(
            Generators::elements('api', 'admin', 'web', 'internal')
        )->then(function (string $firewallName) use ($kernel) {
            // This call should succeed on fixed code.
            // On unfixed code, it triggers: Fatal Error: Call to undefined method
            $kernel->addFirewall($firewallName, [
                'pattern' => '^/' . $firewallName,
                'policies' => ['token_auth' => true],
            ]);

            $this->assertTrue(
                method_exists($kernel, 'addFirewall'),
                'MicroKernel should have addFirewall() method'
            );
        });
    }

    // ─── Bug Condition: getSecurityConfig() 方法不存在 ──────────────

    /**
     * Property 1: Bug Condition — getSecurityConfig() API Missing
     *
     * 在 MicroKernel 上调用 getSecurityConfig() 应返回当前累积的 security config。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 4 (read-only query)**
     */
    public function testGetSecurityConfigMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        // This call should succeed on fixed code.
        // On unfixed code, it triggers: Fatal Error: Call to undefined method
        $config = $kernel->getSecurityConfig();

        $this->assertIsArray(
            $config,
            'getSecurityConfig() should return an array'
        );
    }

    // ─── Bug Condition: addAccessRule() 方法不存在 ──────────────────

    /**
     * Property 1: Bug Condition — addAccessRule() API Missing
     *
     * 在 MicroKernel 上调用 addAccessRule() 应被接受并存储到 Pending_Queue。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 2**
     */
    public function testAddAccessRuleMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->forAll(
            Generators::elements('^/admin', '^/api', '^/secured', '^/internal')
        )->then(function (string $pattern) use ($kernel) {
            // This call should succeed on fixed code.
            // On unfixed code, it triggers: Fatal Error: Call to undefined method
            $kernel->addAccessRule([
                'pattern' => $pattern,
                'roles' => ['ROLE_ADMIN'],
            ]);

            $this->assertTrue(
                method_exists($kernel, 'addAccessRule'),
                'MicroKernel should have addAccessRule() method'
            );
        });
    }

    // ─── Bug Condition: addPolicy() 方法不存在 ─────────────────────

    /**
     * Property 1: Bug Condition — addPolicy() API Missing
     *
     * 在 MicroKernel 上调用 addPolicy() 应被接受并存储到 Pending_Queue。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 2**
     */
    public function testAddPolicyMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->forAll(
            Generators::elements('token_auth', 'jwt_auth', 'basic_auth', 'oauth2')
        )->then(function (string $policyName) use ($kernel) {
            // This call should succeed on fixed code.
            // On unfixed code, it triggers: Fatal Error: Call to undefined method
            $kernel->addPolicy($policyName, ['type' => 'pre_auth']);

            $this->assertTrue(
                method_exists($kernel, 'addPolicy'),
                'MicroKernel should have addPolicy() method'
            );
        });
    }

    // ─── Bug Condition: addRoleHierarchy() 方法不存在 ──────────────

    /**
     * Property 1: Bug Condition — addRoleHierarchy() API Missing
     *
     * 在 MicroKernel 上调用 addRoleHierarchy() 应被接受并存储到 Pending_Queue。
     * 未修复代码上此方法不存在，将触发 Fatal Error。
     *
     * **Validates: Requirements Expected Behavior 2**
     */
    public function testAddRoleHierarchyMethodExists(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->forAll(
            Generators::elements('ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_EDITOR', 'ROLE_MODERATOR')
        )->then(function (string $role) use ($kernel) {
            // This call should succeed on fixed code.
            // On unfixed code, it triggers: Fatal Error: Call to undefined method
            $kernel->addRoleHierarchy($role, ['ROLE_USER']);

            $this->assertTrue(
                method_exists($kernel, 'addRoleHierarchy'),
                'MicroKernel should have addRoleHierarchy() method'
            );
        });
    }

    // ─── Bug Condition: ServiceProvider 无法注入 security config ────

    /**
     * Property 1: Bug Condition — ServiceProvider Cannot Inject Security Config
     *
     * 即使 Constructor_Config 为空，ServiceProvider 也无法注入 security config。
     * 在未修复代码上，没有注入 API 可用，带 allowed-roles 的路由将返回 403。
     *
     * **Validates: Requirements Expected Behavior 1, 3**
     */
    public function testServiceProviderCannotInjectSecurityConfig(): void
    {
        // Create kernel with empty security config (no constructor-based security)
        $kernel = $this->createUnbootedKernel([]);

        // Verify that the injection API methods do not exist on unfixed code
        // On fixed code, all these methods should exist
        $requiredMethods = [
            'addSecurityConfig',
            'addFirewall',
            'addAccessRule',
            'addPolicy',
            'addRoleHierarchy',
            'getSecurityConfig',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($kernel, $method),
                "MicroKernel should have {$method}() method for ServiceProvider security injection"
            );
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────

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
