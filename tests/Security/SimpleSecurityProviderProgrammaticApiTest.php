<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\Test\Helpers\KernelLifecycleTestTrait;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Tests for SimpleSecurityProvider's programmatic API methods
 * (addFirewall, addAccessRule, addAuthenticationPolicy, addRoleHierarchy)
 * and the merge logic in register().
 */
class SimpleSecurityProviderProgrammaticApiTest extends TestCase
{
    use KernelLifecycleTestTrait;

    protected function setUp(): void
    {
        $this->setUpKernelLifecycle();
    }

    protected function tearDown(): void
    {
        $this->tearDownKernelLifecycle();
    }

    /**
     * Test that programmatic additions (addFirewall, addAccessRule, addAuthenticationPolicy,
     * addRoleHierarchy) are merged into the config during register().
     */
    public function testProgrammaticAdditionsAreMergedDuringRegister(): void
    {
        $provider = new SimpleSecurityProvider();

        $policy = new TestAuthenticationPolicy();
        $provider->addAuthenticationPolicy('mauth', $policy);

        $firewall = new SimpleFirewall([
            'pattern' => '^/api',
            'policies' => ['mauth' => true],
            'users' => new TestApiUserProvider(),
        ]);
        $provider->addFirewall('api_fw', $firewall);

        $provider->addAccessRule(['pattern' => '^/api', 'roles' => 'ROLE_API']);

        $provider->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);

        // Create and boot a kernel, then register the provider
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('api_test', new Route('/api/test', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/api/test')); // boot

        $provider->register($kernel);

        // Verify the config data provider is available after registration
        $configDp = $provider->getConfigDataProvider();
        $this->assertNotNull($configDp);

        // Verify firewalls were merged
        $firewalls = $provider->getFirewalls();
        $this->assertArrayHasKey('api_fw', $firewalls);

        // Verify access rules were merged
        $accessRules = $provider->getAccessRules();
        $this->assertNotEmpty($accessRules);

        // Verify role hierarchy was merged
        $roleHierarchy = $provider->getRoleHierarchy();
        $this->assertArrayHasKey('ROLE_ADMIN', $roleHierarchy);
        $this->assertContains('ROLE_USER', $roleHierarchy['ROLE_ADMIN']);
    }

    /**
     * Test addRoleHierarchy merges children for the same role.
     */
    public function testAddRoleHierarchyMergesChildren(): void
    {
        $provider = new SimpleSecurityProvider();
        $provider->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);
        $provider->addRoleHierarchy('ROLE_ADMIN', ['ROLE_MANAGER']);

        $policy = new TestAuthenticationPolicy();
        $provider->addAuthenticationPolicy('mauth', $policy);
        $provider->addFirewall('main', new SimpleFirewall([
            'pattern' => '^/',
            'policies' => ['mauth' => true],
            'users' => new TestApiUserProvider(),
        ]));

        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('test', new Route('/test', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/test')); // boot

        $provider->register($kernel);

        $roleHierarchy = $provider->getRoleHierarchy();
        $this->assertArrayHasKey('ROLE_ADMIN', $roleHierarchy);
        $this->assertContains('ROLE_USER', $roleHierarchy['ROLE_ADMIN']);
        $this->assertContains('ROLE_MANAGER', $roleHierarchy['ROLE_ADMIN']);
    }

    /**
     * Test that programmatic additions merge with config-based settings.
     */
    public function testProgrammaticAdditionsMergeWithConfigBased(): void
    {
        $provider = new SimpleSecurityProvider();

        // Add programmatic firewall
        $provider->addFirewall('programmatic_fw', new SimpleFirewall([
            'pattern' => '^/programmatic',
            'policies' => ['mauth' => true],
            'users' => new TestApiUserProvider(),
        ]));

        // Add programmatic policy
        $provider->addAuthenticationPolicy('mauth', new TestAuthenticationPolicy());

        // Add programmatic access rule
        $provider->addAccessRule(['pattern' => '^/programmatic', 'roles' => 'ROLE_USER']);

        // Add programmatic role hierarchy
        $provider->addRoleHierarchy('ROLE_SUPER', ['ROLE_ADMIN']);

        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('test', new Route('/test', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/test')); // boot

        // Register with existing config-based settings
        $provider->register($kernel, [
            'firewalls' => [
                'config_fw' => [
                    'pattern' => '^/config',
                    'policies' => ['mauth' => true],
                    'users' => new TestApiUserProvider(),
                ],
            ],
            'access_rules' => [
                ['pattern' => '^/config', 'roles' => 'ROLE_ADMIN'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        // Verify both config-based and programmatic firewalls exist
        $firewalls = $provider->getFirewalls();
        $this->assertArrayHasKey('config_fw', $firewalls);
        $this->assertArrayHasKey('programmatic_fw', $firewalls);

        // Verify both access rules exist
        $accessRules = $provider->getAccessRules();
        $this->assertCount(2, $accessRules);

        // Verify both role hierarchies exist
        $roleHierarchy = $provider->getRoleHierarchy();
        $this->assertArrayHasKey('ROLE_ADMIN', $roleHierarchy);
        $this->assertArrayHasKey('ROLE_SUPER', $roleHierarchy);
    }

    /**
     * Test getConfigDataProvider throws before registration.
     */
    public function testGetConfigDataProviderThrowsBeforeRegistration(): void
    {
        $provider = new SimpleSecurityProvider();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot get config data provider before registration');
        $provider->getConfigDataProvider();
    }
}
