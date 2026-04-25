<?php
/**
 * Unit tests for AbstractSimplePreAuthenticationPolicy.
 *
 * Verifies default method implementations via a concrete test subclass.
 *
 * Ref: Requirements 4.1, 4.2, 4.3, 4.5
 */

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface;
use Oasis\Mlib\Http\ServiceProviders\Security\NullEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Concrete test subclass — only getAuthenticator() needs implementation.
 */
class ConcretePreAuthenticationPolicy extends AbstractSimplePreAuthenticationPolicy
{
    public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface
    {
        // Stub: not exercised in these tests
        throw new \LogicException('Not implemented in test stub');
    }
}

class AbstractSimplePreAuthenticationPolicyTest extends TestCase
{
    private ConcretePreAuthenticationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ConcretePreAuthenticationPolicy();
    }

    /**
     * R4 AC1: SHALL implement AuthenticationPolicyInterface.
     */
    public function testImplementsAuthenticationPolicyInterface(): void
    {
        $this->assertInstanceOf(AuthenticationPolicyInterface::class, $this->policy);
    }

    /**
     * R4 AC2: getAuthenticationType() SHALL return AUTH_TYPE_PRE_AUTH.
     */
    public function testGetAuthenticationTypeReturnsPreAuth(): void
    {
        $this->assertSame(
            AuthenticationPolicyInterface::AUTH_TYPE_PRE_AUTH,
            $this->policy->getAuthenticationType()
        );
    }

    /**
     * R4 AC5: getAuthenticatorConfig() SHALL default to empty array.
     */
    public function testGetAuthenticatorConfigReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->policy->getAuthenticatorConfig());
    }

    /**
     * R4 AC3: getEntryPoint() SHALL return NullEntryPoint instance.
     */
    public function testGetEntryPointReturnsNullEntryPoint(): void
    {
        $kernel = $this->createStub(MicroKernel::class);
        $entryPoint = $this->policy->getEntryPoint($kernel, 'test-firewall', []);

        $this->assertInstanceOf(NullEntryPoint::class, $entryPoint);
    }

    /**
     * R4 AC4: getAuthenticator() SHALL be abstract (subclass must implement).
     * Verified by reflection — the method exists and is abstract on the parent class.
     */
    public function testGetAuthenticatorIsAbstract(): void
    {
        $reflection = new \ReflectionClass(AbstractSimplePreAuthenticationPolicy::class);
        $method = $reflection->getMethod('getAuthenticator');

        $this->assertTrue($method->isAbstract(), 'getAuthenticator() should be abstract');
    }
}
