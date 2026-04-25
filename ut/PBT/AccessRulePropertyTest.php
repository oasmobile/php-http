<?php
/**
 * Property-Based Tests for SimpleAccessRule.
 *
 * Feature: php85-phase3-security-refactor
 *
 * Property 5: Access rule 配置 round-trip
 * Property 6: Access rule invariant
 *
 * 使用 Eris 生成随机 pattern、roles、channel 组合验证配置正确性。
 *
 * Ref: Requirements 11.1, 11.2, 11.3, 11.4, 11.5
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;
use PHPUnit\Framework\TestCase;

class AccessRulePropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 5: Access rule 配置 round-trip ────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 5: Access rule 配置 round-trip
     * For any valid pattern, roles, and channel, SimpleAccessRule round-trip preserves values.
     *
     * Ref: Requirements 11.1, 11.2, 11.3
     */
    public function testAccessRuleRoundTrip(): void
    {
        $this->forAll(
            // pattern: non-empty string (regex-like)
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            ),
            // roles: array of 0–5 role strings
            Generators::choose(0, 5),
            // channel: null, 'http', or 'https'
            Generators::elements([null, 'http', 'https'])
        )->then(function (string $pattern, int $roleCount, ?string $channel) {
            $roles = [];
            for ($i = 0; $i < $roleCount; $i++) {
                $roles[] = 'ROLE_' . strtoupper(bin2hex(random_bytes(3)));
            }

            $rule = new SimpleAccessRule([
                'pattern' => $pattern,
                'roles'   => $roles,
                'channel' => $channel,
            ]);

            // round-trip: getPattern() returns original pattern
            $this->assertSame(
                $pattern,
                $rule->getPattern(),
                'getPattern() should return the original pattern'
            );

            // round-trip: getRequiredRoles() returns original roles
            $this->assertSame(
                $roles,
                $rule->getRequiredRoles(),
                'getRequiredRoles() should return the original roles array'
            );

            // round-trip: getRequiredChannel() returns original channel
            $this->assertSame(
                $channel,
                $rule->getRequiredChannel(),
                'getRequiredChannel() should return the original channel'
            );
        });
    }

    /**
     * Feature: php85-phase3-security-refactor, Property 5 (string normalization):
     * When roles is provided as a single string, it is normalized to a single-element array.
     *
     * Ref: Requirements 11.2
     */
    public function testAccessRuleRolesStringNormalization(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 50,
                Generators::string()
            )
        )->then(function (string $roleString) {
            $rule = new SimpleAccessRule([
                'pattern' => '/test',
                'roles'   => $roleString,
                'channel' => null,
            ]);

            $result = $rule->getRequiredRoles();
            $this->assertIsArray($result, 'getRequiredRoles() should always return an array');
            $this->assertCount(1, $result, 'String role should be normalized to single-element array');
            $this->assertSame($roleString, $result[0], 'Normalized array should contain the original string');
        });
    }

    // ─── Property 6: Access rule invariant ──────────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 6: Access rule invariant
     * For any valid configuration, getPattern() is non-empty and getRequiredRoles() is an array.
     *
     * Ref: Requirements 11.4, 11.5
     */
    public function testAccessRuleInvariant(): void
    {
        $this->forAll(
            // pattern: non-empty string
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            ),
            // roles: either a string or an array
            Generators::oneOf(
                Generators::suchThat(
                    fn(string $s) => $s !== '' && strlen($s) <= 50,
                    Generators::string()
                ),
                Generators::constant(['ROLE_USER']),
                Generators::constant(['ROLE_ADMIN', 'ROLE_USER']),
                Generators::constant([])
            ),
            Generators::elements([null, 'http', 'https'])
        )->then(function (string $pattern, string|array $roles, ?string $channel) {
            $rule = new SimpleAccessRule([
                'pattern' => $pattern,
                'roles'   => $roles,
                'channel' => $channel,
            ]);

            // invariant: getPattern() is non-empty (use !== '' instead of assertNotEmpty
            // because PHP's empty() treats "0" as empty)
            $this->assertNotSame(
                '',
                $rule->getPattern(),
                'getPattern() should return a non-empty value'
            );

            // invariant: getRequiredRoles() is an array
            $this->assertIsArray(
                $rule->getRequiredRoles(),
                'getRequiredRoles() should always return an array'
            );
        });
    }
}
