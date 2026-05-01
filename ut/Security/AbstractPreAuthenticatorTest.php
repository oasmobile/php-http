<?php
declare(strict_types=1);

/**
 * Unit tests for AbstractPreAuthenticator.
 *
 * Covers createToken() and onAuthenticationFailure() — the two uncovered methods.
 */

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractPreAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Concrete stub for testing the abstract class.
 */
class StubPreAuthenticator extends AbstractPreAuthenticator
{
    private mixed $credentials;
    private ?UserInterface $user;

    public function __construct(mixed $credentials = 'test-cred', ?UserInterface $user = null)
    {
        $this->credentials = $credentials;
        $this->user = $user;
    }

    protected function getCredentialsFromRequest(Request $request): mixed
    {
        return $this->credentials;
    }

    protected function authenticateAndGetUser(mixed $credentials): UserInterface
    {
        if ($this->user === null) {
            throw new AuthenticationException('No user configured');
        }
        return $this->user;
    }
}

class AbstractPreAuthenticatorTest extends TestCase
{
    /**
     * createToken() should return a PostAuthenticationToken with the user's roles.
     */
    public function testCreateTokenReturnsPostAuthenticationToken(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('alice');
        $user->method('getRoles')->willReturn(['ROLE_USER', 'ROLE_ADMIN']);

        $passport = new SelfValidatingPassport(
            new UserBadge('alice', fn() => $user)
        );

        $authenticator = new StubPreAuthenticator('cred', $user);
        $token = $authenticator->createToken($passport, 'main');

        $this->assertInstanceOf(PostAuthenticationToken::class, $token);
        $this->assertSame($user, $token->getUser());
        $this->assertEquals('main', $token->getFirewallName());
        $this->assertContains('ROLE_USER', $token->getRoleNames());
        $this->assertContains('ROLE_ADMIN', $token->getRoleNames());
    }

    /**
     * onAuthenticationFailure() should return null (does not interrupt request).
     */
    public function testOnAuthenticationFailureReturnsNull(): void
    {
        $authenticator = new StubPreAuthenticator();
        $request = Request::create('/');
        $exception = new AuthenticationException('fail');

        $result = $authenticator->onAuthenticationFailure($request, $exception);

        $this->assertNull($result);
    }

    /**
     * onAuthenticationSuccess() should return null (does not interrupt request).
     */
    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $authenticator = new StubPreAuthenticator();
        $request = Request::create('/');
        $token = $this->createStub(TokenInterface::class);

        $result = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($result);
    }
}
