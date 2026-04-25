<?php
/**
 * Property-Based Tests for Constructor Promotion equivalence.
 *
 * Feature: php85-phase4-language-adaptation
 *
 * Property 6: SimpleAccessRule configuration round-trip
 * Property 7: SimpleFirewall configuration round-trip
 * Property 8: UniquenessViolationHttpException construction round-trip
 *
 * 使用 Eris 生成随机配置参数，验证构造后 getter 返回等价于输入配置的值。
 * 基于当前代码验证 property 成立，作为后续 constructor promotion 重构的回归保护。
 *
 * Ref: Requirements 17.1, 17.2, 17.3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\Exceptions\UniquenessViolationHttpException;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use PHPUnit\Framework\TestCase;

class ConstructorPromotionPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 6: SimpleAccessRule configuration round-trip ───────

    /**
     * Feature: php85-phase4-language-adaptation, Property 6: SimpleAccessRule configuration round-trip
     * For any valid access rule configuration (pattern × roles × channel),
     * constructing a SimpleAccessRule and calling getters returns values
     * equivalent to the input configuration.
     *
     * Ref: Requirements 17.1
     */
    public function testSimpleAccessRuleRoundTrip(): void
    {
        $this->forAll(
            // pattern: non-empty string
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            ),
            // number of roles (0–5)
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

            $this->assertSame(
                $pattern,
                $rule->getPattern(),
                'getPattern() should return the original pattern'
            );
            $this->assertSame(
                $roles,
                $rule->getRequiredRoles(),
                'getRequiredRoles() should return the original roles array'
            );
            $this->assertSame(
                $channel,
                $rule->getRequiredChannel(),
                'getRequiredChannel() should return the original channel'
            );
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 6 (string normalization):
     * When roles is provided as a single string, it is normalized to a single-element array.
     *
     * Ref: Requirements 17.1
     */
    public function testSimpleAccessRuleRolesStringNormalization(): void
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

    // ─── Property 7: SimpleFirewall configuration round-trip ────────

    /**
     * Feature: php85-phase4-language-adaptation, Property 7: SimpleFirewall configuration round-trip
     * For any valid firewall configuration, constructing a SimpleFirewall and calling
     * getters returns values equivalent to the input configuration.
     *
     * Ref: Requirements 17.2
     */
    public function testSimpleFirewallRoundTrip(): void
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
            $misc = ['custom_key' => 'custom_value'];

            $firewall = new SimpleFirewall([
                'pattern'   => $pattern,
                'policies'  => $policies,
                'users'     => $users,
                'stateless' => $stateless,
                'misc'      => $misc,
            ]);

            $this->assertSame(
                $pattern,
                $firewall->getPattern(),
                'getPattern() should return the original pattern'
            );
            $this->assertSame(
                $policies,
                $firewall->getPolicies(),
                'getPolicies() should return the original policies'
            );
            $this->assertSame(
                $stateless,
                $firewall->isStateless(),
                'isStateless() should return the original stateless flag'
            );
            $this->assertSame(
                $users,
                $firewall->getUserProvider(),
                'getUserProvider() should return the original users config'
            );
            $this->assertSame(
                $misc,
                $firewall->getOtherSettings(),
                'getOtherSettings() should return the original misc config'
            );
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 7 (default values):
     * When optional fields are omitted, SimpleFirewall uses defaults.
     *
     * Ref: Requirements 17.2
     */
    public function testSimpleFirewallDefaultValues(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 100,
                Generators::string()
            )
        )->then(function (string $pattern) {
            $firewall = new SimpleFirewall([
                'pattern'  => $pattern,
                'policies' => ['test_policy' => true],
                'users'    => ['memory' => ['admin' => ['password' => '1234', 'roles' => ['ROLE_ADMIN']]]],
                // 'stateless' and 'misc' omitted — should use defaults
            ]);

            // stateless defaults to false (per SimpleFirewallConfiguration)
            $this->assertFalse(
                $firewall->isStateless(),
                'isStateless() should default to false when not specified'
            );

            // misc defaults to empty array
            $this->assertSame(
                [],
                $firewall->getOtherSettings(),
                'getOtherSettings() should default to empty array when not specified'
            );
        });
    }

    // ─── Property 8: UniquenessViolationHttpException construction round-trip ─

    /**
     * Feature: php85-phase4-language-adaptation, Property 8: UniquenessViolationHttpException construction round-trip
     * For any valid combination of message, previous exception, and code,
     * getStatusCode() is always 400, getMessage()/getPrevious()/getCode() match input.
     *
     * Ref: Requirements 17.3
     */
    public function testUniquenessViolationHttpExceptionRoundTrip(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => strlen($s) <= 200,
                Generators::string()
            ),
            Generators::choose(-1000, 1000)
        )->then(function (string $message, int $code) {
            $exception = new UniquenessViolationHttpException($message, null, $code);

            // getStatusCode() is always 400
            $this->assertSame(
                400,
                $exception->getStatusCode(),
                'getStatusCode() should always return 400'
            );

            // getMessage() matches input (empty string when null, but we always pass string here)
            $this->assertSame(
                $message,
                $exception->getMessage(),
                'getMessage() should return the input message'
            );

            // getPrevious() is null when not provided
            $this->assertNull(
                $exception->getPrevious(),
                'getPrevious() should be null when not provided'
            );

            // getCode() matches input
            $this->assertSame(
                $code,
                $exception->getCode(),
                'getCode() should return the input code'
            );
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 8 (with previous exception):
     * When a previous exception is provided, getPrevious() returns it.
     *
     * Ref: Requirements 17.3
     */
    public function testUniquenessViolationHttpExceptionWithPrevious(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn(string $s) => strlen($s) <= 200,
                Generators::string()
            ),
            Generators::choose(-1000, 1000)
        )->then(function (string $message, int $code) {
            $previous = new \RuntimeException('previous error');
            $exception = new UniquenessViolationHttpException($message, $previous, $code);

            $this->assertSame(
                400,
                $exception->getStatusCode(),
                'getStatusCode() should always return 400'
            );
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
            $this->assertSame($code, $exception->getCode());
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 8 (empty message):
     * When message is empty string, getMessage() returns empty string.
     * Note: Symfony 7.x HttpException requires string $message (not null).
     *
     * Ref: Requirements 17.3
     */
    public function testUniquenessViolationHttpExceptionEmptyMessage(): void
    {
        $this->forAll(
            Generators::choose(-1000, 1000)
        )->then(function (int $code) {
            $exception = new UniquenessViolationHttpException('', null, $code);

            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertSame($code, $exception->getCode());
        });
    }
}
