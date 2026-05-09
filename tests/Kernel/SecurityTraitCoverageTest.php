<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Kernel;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use PHPUnit\Framework\TestCase;

/**
 * Supplementary unit tests for SecurityTrait coverage gaps.
 *
 * Covers: boot-after protection, conflict detection, unknown key rejection,
 * and merge logic branches.
 */
class SecurityTraitCoverageTest extends TestCase
{
    use RouteCacheCleaner;

    private ?MicroKernel $kernel = null;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        parent::tearDown();
    }

    // ─── Boot-after protection ───────────────────────────────────────

    public function testAddSecurityConfigAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');
        $kernel->addSecurityConfig(['firewalls' => ['api' => ['pattern' => '^/api']]]);
    }

    public function testAddFirewallAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');
        $kernel->addFirewall('api', ['pattern' => '^/api']);
    }

    public function testAddAccessRuleAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');
        $kernel->addAccessRule(['pattern' => '^/admin', 'roles' => ['ROLE_ADMIN']]);
    }

    public function testAddPolicyAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');
        $kernel->addPolicy('token_auth', ['type' => 'pre_auth']);
    }

    public function testAddRoleHierarchyAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add security config after the kernel has been booted.');
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
    }

    public function testGetSecurityConfigAfterBootThrows(): void
    {
        $kernel = $this->createBootedKernel();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot query security config after the kernel has been booted.');
        $kernel->getSecurityConfig();
    }

    // ─── Unknown key rejection ───────────────────────────────────────

    public function testAddSecurityConfigWithUnknownKeyThrows(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown security config keys: invalid_key');
        $kernel->addSecurityConfig(['invalid_key' => 'value']);
    }

    public function testAddSecurityConfigWithMultipleUnknownKeysThrows(): void
    {
        $kernel = $this->createUnbootedKernel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown security config keys:');
        $kernel->addSecurityConfig(['foo' => 1, 'bar' => 2]);
    }

    // ─── Conflict detection (fail-fast) ──────────────────────────────

    public function testDuplicateFirewallThrowsWithoutOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addFirewall('api', ['pattern' => '^/api']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Duplicate firewall: 'api'");
        $kernel->addFirewall('api', ['pattern' => '^/api/v2']);
    }

    public function testDuplicateFirewallAllowedWithOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addFirewall('api', ['pattern' => '^/api']);
        $kernel->addFirewall('api', ['pattern' => '^/api/v2'], true);

        $config = $kernel->getSecurityConfig();
        $this->assertEquals('^/api/v2', $config['firewalls']['api']['pattern']);
    }

    public function testDuplicatePolicyThrowsWithoutOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addPolicy('token_auth', ['type' => 'pre_auth']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Duplicate policy: 'token_auth'");
        $kernel->addPolicy('token_auth', ['type' => 'jwt']);
    }

    public function testDuplicatePolicyAllowedWithOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addPolicy('token_auth', ['type' => 'pre_auth']);
        $kernel->addPolicy('token_auth', ['type' => 'jwt'], true);

        $config = $kernel->getSecurityConfig();
        $this->assertEquals(['type' => 'jwt'], $config['policies']['token_auth']);
    }

    public function testDuplicateRoleHierarchyThrowsWithoutOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Duplicate role in role_hierarchy: 'ROLE_ADMIN'");
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_MANAGER']);
    }

    public function testDuplicateRoleHierarchyAllowedWithOverwrite(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_MANAGER'], true);

        $config = $kernel->getSecurityConfig();
        $this->assertEquals(['ROLE_MANAGER'], $config['role_hierarchy']['ROLE_ADMIN']);
    }

    // ─── Idempotent injection (same config = no conflict) ────────────

    public function testIdempotentFirewallInjectionDoesNotThrow(): void
    {
        $kernel = $this->createUnbootedKernel();
        $config = ['pattern' => '^/api', 'policies' => ['token_auth' => true]];
        $kernel->addFirewall('api', $config);
        $kernel->addFirewall('api', $config); // same config = idempotent

        $result = $kernel->getSecurityConfig();
        $this->assertCount(1, $result['firewalls']);
    }

    public function testIdempotentPolicyInjectionDoesNotThrow(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addPolicy('token_auth', ['type' => 'pre_auth']);
        $kernel->addPolicy('token_auth', ['type' => 'pre_auth']); // idempotent

        $result = $kernel->getSecurityConfig();
        $this->assertCount(1, $result['policies']);
    }

    public function testIdempotentRoleHierarchyInjectionDoesNotThrow(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']); // idempotent

        $result = $kernel->getSecurityConfig();
        $this->assertCount(1, $result['role_hierarchy']);
    }

    // ─── Access rules always append ──────────────────────────────────

    public function testAccessRulesAppendInOrder(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addAccessRule(['pattern' => '^/admin', 'roles' => ['ROLE_ADMIN']]);
        $kernel->addAccessRule(['pattern' => '^/api', 'roles' => ['ROLE_API']]);
        $kernel->addAccessRule(['pattern' => '^/user', 'roles' => ['ROLE_USER']]);

        $config = $kernel->getSecurityConfig();
        $this->assertCount(3, $config['access_rules']);
        $this->assertEquals('^/admin', $config['access_rules'][0]['pattern']);
        $this->assertEquals('^/api', $config['access_rules'][1]['pattern']);
        $this->assertEquals('^/user', $config['access_rules'][2]['pattern']);
    }

    // ─── Batch addSecurityConfig with conflicts ──────────────────────

    public function testBatchAddSecurityConfigConflictDetection(): void
    {
        $kernel = $this->createUnbootedKernel();
        $kernel->addSecurityConfig([
            'firewalls' => ['api' => ['pattern' => '^/api']],
            'policies' => ['token_auth' => ['type' => 'pre_auth']],
        ]);

        $this->expectException(\LogicException::class);
        $kernel->addSecurityConfig([
            'firewalls' => ['api' => ['pattern' => '^/api/v2']],
        ]);
    }

    // ─── getSecurityConfig merges constructor + pending ──────────────

    public function testGetSecurityConfigMergesConstructorAndPending(): void
    {
        $kernel = new MicroKernel([
            'security' => [
                'firewalls' => ['web' => ['pattern' => '^/']],
            ],
        ], true);

        $kernel->addFirewall('api', ['pattern' => '^/api']);

        $config = $kernel->getSecurityConfig();
        $this->assertArrayHasKey('web', $config['firewalls']);
        $this->assertArrayHasKey('api', $config['firewalls']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createUnbootedKernel(): MicroKernel
    {
        return new MicroKernel([], true);
    }

    private function createBootedKernel(): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/sec-cov-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $kernel = new MicroKernel([
            'cache_dir' => $cacheDir,
        ], true);

        $kernel->boot();

        return $kernel;
    }
}
