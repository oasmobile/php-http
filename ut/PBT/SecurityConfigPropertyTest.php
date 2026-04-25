<?php
/**
 * Property-Based Tests for SecurityConfiguration and SimpleSecurityProvider.
 *
 * Feature: php85-phase3-security-refactor
 *
 * Property 13: Security 配置注册 invariant
 * Property 14: 配置合并顺序 confluence
 * Property 15: Role hierarchy string 归一化 round-trip
 * Property 16: RefreshUser identity
 *
 * Ref: Requirements 2.4, 15.1, 15.2, 15.3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Concrete test implementation of AbstractSimplePreAuthenticateUserProvider
 * for Property 16 (RefreshUser identity).
 */
class StubUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    public function __construct()
    {
        parent::__construct(StubRefreshUser::class);
    }

    public function authenticateAndGetUser($credentials): UserInterface
    {
        return new StubRefreshUser($credentials, ['ROLE_USER']);
    }
}

/**
 * Minimal UserInterface for RefreshUser property test.
 */
class StubRefreshUser implements UserInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly array $roles,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function eraseCredentials(): void
    {
    }
}

class SecurityConfigPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 13: Security 配置注册 invariant ───────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 13: Security 配置注册 invariant
     * For any valid security configuration containing policies, firewalls,
     * access_rules, and role_hierarchy, register() completes without exception.
     *
     * Ref: Requirements 15.1
     */
    public function testSecurityConfigRegisterInvariant(): void
    {
        $this->forAll(
            // number of access rules (0–3)
            Generators::choose(0, 3),
            // number of role hierarchy entries (0–3)
            Generators::choose(0, 3)
        )->then(function (int $ruleCount, int $hierarchyCount) {
            $provider = new SimpleSecurityProvider();

            // Use a stub policy instead of TestAuthenticationPolicy
            // (TestAuthenticationPolicy will be rewritten in Task 6)
            $policy = $this->createStub(AuthenticationPolicyInterface::class);
            $policy->method('getAuthenticationType')->willReturn('pre_auth');
            $provider->addAuthenticationPolicy('test_pre_auth', $policy);

            // Add a firewall
            $provider->addFirewall('main', [
                'pattern'       => '^/secured',
                'policies'      => ['test_pre_auth' => true],
                'users'         => new TestApiUserProvider(),
                'stateless'     => true,
            ]);

            // Add random access rules
            for ($i = 0; $i < $ruleCount; $i++) {
                $provider->addAccessRule(
                    new TestAccessRule(
                        '^/secured/area' . $i,
                        ['ROLE_USER'],
                        null
                    )
                );
            }

            // Add random role hierarchy entries
            for ($i = 0; $i < $hierarchyCount; $i++) {
                $parent = 'ROLE_PARENT_' . $i;
                $children = ['ROLE_CHILD_' . $i . '_A', 'ROLE_CHILD_' . $i . '_B'];
                $provider->addRoleHierarchy($parent, $children);
            }

            // register() should not throw
            $kernel = $this->createMinimalKernelMock();
            $provider->register($kernel);

            // Verify we can access config data after registration
            $this->assertNotNull($provider->getConfigDataProvider());
        });
    }

    // ─── Property 14: 配置合并顺序 confluence ───────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 14: 配置合并顺序 confluence
     * Programmatic additions are appended after config-based settings.
     *
     * Ref: Requirements 15.2
     */
    public function testConfigMergeOrderConfluence(): void
    {
        $this->forAll(
            // number of config-based access rules (1–3)
            Generators::choose(1, 3),
            // number of programmatic access rules (1–3)
            Generators::choose(1, 3)
        )->then(function (int $configRuleCount, int $programmaticRuleCount) {
            $provider = new SimpleSecurityProvider();

            // Programmatic additions (added before register)
            $programmaticPatterns = [];
            for ($i = 0; $i < $programmaticRuleCount; $i++) {
                $pattern = '^/programmatic/' . $i;
                $programmaticPatterns[] = $pattern;
                $provider->addAccessRule(
                    new TestAccessRule($pattern, ['ROLE_USER'], null)
                );
            }

            // Config-based settings (passed to register)
            $configRules = [];
            $configPatterns = [];
            for ($i = 0; $i < $configRuleCount; $i++) {
                $pattern = '^/config/' . $i;
                $configPatterns[] = $pattern;
                $configRules[] = [
                    'pattern' => $pattern,
                    'roles'   => ['ROLE_USER'],
                ];
            }

            $securityConfig = [
                'access_rules' => $configRules,
            ];

            $kernel = $this->createMinimalKernelMock();
            $provider->register($kernel, $securityConfig);

            $accessRules = $provider->getAccessRules();

            // Config-based rules come first, programmatic rules come after
            $allPatterns = array_map(fn($rule) => $rule[0], $accessRules);

            // Verify config patterns appear before programmatic patterns
            $configPositions = [];
            $programmaticPositions = [];
            foreach ($allPatterns as $idx => $pattern) {
                if (in_array($pattern, $configPatterns, true)) {
                    $configPositions[] = $idx;
                }
                if (in_array($pattern, $programmaticPatterns, true)) {
                    $programmaticPositions[] = $idx;
                }
            }

            if (!empty($configPositions) && !empty($programmaticPositions)) {
                $this->assertLessThan(
                    min($programmaticPositions),
                    max($configPositions),
                    'Config-based rules should appear before programmatic additions'
                );
            }
        });
    }

    // ─── Property 15: Role hierarchy string 归一化 round-trip ───────

    /**
     * Feature: php85-phase3-security-refactor, Property 15: Role hierarchy string 归一化 round-trip
     * String values in role_hierarchy config are automatically converted to single-element arrays.
     *
     * Ref: Requirements 15.3
     */
    public function testRoleHierarchyStringNormalization(): void
    {
        $this->forAll(
            // number of hierarchy entries (1–5)
            Generators::choose(1, 5)
        )->then(function (int $entryCount) {
            $provider = new SimpleSecurityProvider();

            $expectedHierarchy = [];
            $configHierarchy = [];
            for ($i = 0; $i < $entryCount; $i++) {
                $parent = 'ROLE_PARENT_' . strtoupper(bin2hex(random_bytes(2)));
                $child = 'ROLE_CHILD_' . strtoupper(bin2hex(random_bytes(2)));
                // Pass as string (not array) — should be normalized
                $configHierarchy[$parent] = $child;
                $expectedHierarchy[$parent] = [$child];
            }

            $securityConfig = [
                'role_hierarchy' => $configHierarchy,
            ];

            $kernel = $this->createMinimalKernelMock();
            $provider->register($kernel, $securityConfig);

            $hierarchy = $provider->getRoleHierarchy();

            foreach ($expectedHierarchy as $parent => $expectedChildren) {
                $this->assertArrayHasKey($parent, $hierarchy, "Hierarchy should contain parent {$parent}");
                $this->assertIsArray(
                    $hierarchy[$parent],
                    "Children for {$parent} should be an array (string normalized)"
                );
                $this->assertSame(
                    $expectedChildren,
                    $hierarchy[$parent],
                    "String child should be normalized to single-element array for {$parent}"
                );
            }
        });
    }

    // ─── Property 16: RefreshUser identity ──────────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 16: RefreshUser identity
     * For any UserInterface instance, refreshUser() returns the same object.
     *
     * Ref: Requirements 2.4
     */
    public function testRefreshUserIdentity(): void
    {
        $userProvider = new StubUserProvider();

        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 50,
                Generators::string()
            ),
            Generators::choose(1, 4)
        )->then(function (string $identifier, int $roleCount) use ($userProvider) {
            $roles = [];
            for ($i = 0; $i < $roleCount; $i++) {
                $roles[] = 'ROLE_' . strtoupper(bin2hex(random_bytes(3)));
            }

            $user = new StubRefreshUser($identifier, $roles);
            $refreshed = $userProvider->refreshUser($user);

            // refreshUser() returns the same object
            $this->assertSame(
                $user,
                $refreshed,
                'refreshUser() should return the exact same user object'
            );
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Create a minimal MicroKernel mock for register().
     */
    private function createMinimalKernelMock(): \Oasis\Mlib\Http\MicroKernel
    {
        $dispatcher = $this->createStub(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('get')->willReturn($dispatcher);

        $kernel = $this->createStub(\Oasis\Mlib\Http\MicroKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        return $kernel;
    }
}
