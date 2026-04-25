<?php
/**
 * Property-Based Tests for SimpleFirewall.
 *
 * Feature: php85-phase3-security-refactor
 *
 * Property 7: Firewall 配置 round-trip
 * Property 8: Firewall 解析输出 invariant
 * Property 9: Firewall 缺失必填字段 error condition
 *
 * 使用 Eris 生成随机 pattern、policies、stateless 组合验证配置正确性。
 *
 * Ref: Requirements 12.1, 12.2, 12.3, 12.4, 12.5
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\ServiceProviders\Security\FirewallInterface;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class FirewallPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 7: Firewall 配置 round-trip ───────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 7: Firewall 配置 round-trip
     * For any valid firewall configuration, SimpleFirewall round-trip preserves
     * pattern, policies, and stateless flag.
     *
     * Ref: Requirements 12.1, 12.2, 12.3
     */
    public function testFirewallRoundTrip(): void
    {
        $this->forAll(
            // pattern: non-empty string
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            ),
            // stateless: boolean
            Generators::bool(),
            // number of policies (1–4)
            Generators::choose(1, 4)
        )->then(function (string $pattern, bool $stateless, int $policyCount) {
            $policies = [];
            for ($i = 0; $i < $policyCount; $i++) {
                $policyName = 'policy_' . bin2hex(random_bytes(3));
                $policies[$policyName] = true;
            }

            $users = ['memory' => ['admin' => ['password' => '1234', 'roles' => ['ROLE_ADMIN']]]];

            $firewall = new SimpleFirewall([
                'pattern'   => $pattern,
                'policies'  => $policies,
                'users'     => $users,
                'stateless' => $stateless,
            ]);

            // round-trip: getPattern() returns original pattern
            $this->assertSame(
                $pattern,
                $firewall->getPattern(),
                'getPattern() should return the original pattern'
            );

            // round-trip: getPolicies() returns original policies
            $this->assertSame(
                $policies,
                $firewall->getPolicies(),
                'getPolicies() should return the original policies'
            );

            // round-trip: isStateless() returns original stateless flag
            $this->assertSame(
                $stateless,
                $firewall->isStateless(),
                'isStateless() should return the original stateless flag'
            );
        });
    }

    // ─── Property 8: Firewall 解析输出 invariant ────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 8: Firewall 解析输出 invariant
     * For any SimpleFirewall instance, parseFirewall() output contains
     * 'pattern', 'users', and 'stateless' keys.
     *
     * Ref: Requirements 12.4
     */
    public function testParseFirewallOutputInvariant(): void
    {
        $this->forAll(
            // pattern: non-empty string
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            ),
            Generators::bool()
        )->then(function (string $pattern, bool $stateless) {
            $policies = ['test_policy' => true];
            $users = ['memory' => ['admin' => ['password' => '1234', 'roles' => ['ROLE_ADMIN']]]];

            $firewall = new SimpleFirewall([
                'pattern'   => $pattern,
                'policies'  => $policies,
                'users'     => $users,
                'stateless' => $stateless,
            ]);

            // Use reflection to call protected parseFirewall()
            $provider = new SimpleSecurityProvider();
            $reflection = new \ReflectionMethod($provider, 'parseFirewall');
            $result = $reflection->invoke($provider, $firewall);

            // invariant: output contains required keys
            $this->assertArrayHasKey('pattern', $result, 'parseFirewall() output must contain "pattern" key');
            $this->assertArrayHasKey('users', $result, 'parseFirewall() output must contain "users" key');
            $this->assertArrayHasKey('stateless', $result, 'parseFirewall() output must contain "stateless" key');

            // verify values match
            $this->assertSame($pattern, $result['pattern']);
            $this->assertSame($users, $result['users']);
            $this->assertSame($stateless, $result['stateless']);
        });
    }

    // ─── Property 9: Firewall 缺失必填字段 error condition ──────────

    /**
     * Feature: php85-phase3-security-refactor, Property 9: Firewall 缺失必填字段 error condition
     * Missing any of pattern/policies/users causes a configuration validation exception.
     *
     * Ref: Requirements 12.5
     */
    public function testFirewallMissingPatternThrows(): void
    {
        $this->forAll(
            Generators::choose(1, 3)
        )->then(function (int $policyCount) {
            $policies = [];
            for ($i = 0; $i < $policyCount; $i++) {
                $policies['policy_' . $i] = true;
            }
            $users = ['memory' => ['admin' => ['password' => '1234', 'roles' => ['ROLE_ADMIN']]]];

            $this->expectException(InvalidConfigurationException::class);
            new SimpleFirewall([
                // 'pattern' is missing
                'policies' => $policies,
                'users'    => $users,
            ]);
        });
    }

    /**
     * Feature: php85-phase3-security-refactor, Property 9: Firewall 缺失必填字段 error condition
     * Missing 'policies' field causes a configuration validation exception.
     *
     * Ref: Requirements 12.5
     */
    public function testFirewallMissingPoliciesThrows(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            )
        )->then(function (string $pattern) {
            $users = ['memory' => ['admin' => ['password' => '1234', 'roles' => ['ROLE_ADMIN']]]];

            $this->expectException(InvalidConfigurationException::class);
            new SimpleFirewall([
                'pattern' => $pattern,
                // 'policies' is missing
                'users'   => $users,
            ]);
        });
    }

    /**
     * Feature: php85-phase3-security-refactor, Property 9: Firewall 缺失必填字段 error condition
     * Missing 'users' field causes a configuration validation exception.
     *
     * Ref: Requirements 12.5
     */
    public function testFirewallMissingUsersThrows(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            )
        )->then(function (string $pattern) {
            $policies = ['test_policy' => true];

            $this->expectException(InvalidConfigurationException::class);
            new SimpleFirewall([
                'pattern'  => $pattern,
                'policies' => $policies,
                // 'users' is missing
            ]);
        });
    }
}
