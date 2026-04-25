<?php
/**
 * Property-Based Tests for AbstractPreAuthenticator.
 *
 * Feature: php85-phase3-security-refactor
 *
 * Property 1: Supports ↔ Credentials 一致性
 * Property 2: Authenticate round-trip
 * Property 3: Authenticate error condition
 * Property 4: CreateToken invariant
 *
 * 使用 concrete test subclass 验证模板方法行为。
 *
 * Ref: Requirements 1.2, 1.3, 1.4, 1.5, 14.1, 14.2, 14.3, 14.4, 14.5
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractPreAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Concrete test implementation of AbstractPreAuthenticator.
 *
 * Credentials are extracted from the 'sig' query parameter.
 * Valid credentials map to a fixed set of users; invalid credentials
 * cause a UserNotFoundException.
 */
class StubPreAuthenticator extends AbstractPreAuthenticator
{
    /** @var array<string, array{identifier: string, roles: string[]}> */
    private array $validCredentials;

    /**
     * @param array<string, array{identifier: string, roles: string[]}> $validCredentials
     */
    public function __construct(array $validCredentials)
    {
        $this->validCredentials = $validCredentials;
    }

    protected function getCredentialsFromRequest(Request $request): mixed
    {
        $sig = $request->query->get('sig');

        return $sig ?: null;
    }

    protected function authenticateAndGetUser(mixed $credentials): UserInterface
    {
        if (!isset($this->validCredentials[$credentials])) {
            throw new UserNotFoundException("Unknown credential: {$credentials}");
        }

        $data = $this->validCredentials[$credentials];

        return new StubUser($data['identifier'], $data['roles']);
    }
}

/**
 * Minimal UserInterface implementation for PBT.
 */
class StubUser implements UserInterface
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

class AuthenticatorPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 1: Supports ↔ Credentials 一致性 ──────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 1: Supports ↔ Credentials 一致性
     * For any request, supports() returns true iff getCredentialsFromRequest() !== null.
     *
     * Ref: Requirements 1.2, 14.1, 14.2
     */
    public function testSupportsEqualsCredentialsNotNull(): void
    {
        $authenticator = new StubPreAuthenticator([
            'valid-key' => ['identifier' => 'user1', 'roles' => ['ROLE_USER']],
        ]);

        // Use names() instead of string() to avoid null bytes and special
        // characters that break query-parameter round-trip through Request::create().
        $this->forAll(
            Generators::oneOf(
                Generators::suchThat(fn(string $s) => $s !== '', Generators::names()),
                Generators::constant('')
            )
        )->then(function (string $sig) use ($authenticator) {
            $query = $sig !== '' ? ['sig' => $sig] : [];
            $request = Request::create('/', 'GET', $query);

            $supports = $authenticator->supports($request);
            $hasCredentials = $sig !== '';

            $this->assertSame(
                $hasCredentials,
                (bool) $supports,
                sprintf(
                    'supports() should return %s when sig=%s',
                    $hasCredentials ? 'true' : 'false',
                    var_export($sig, true)
                )
            );
        });
    }

    // ─── Property 2: Authenticate round-trip ────────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 2: Authenticate round-trip
     * For any valid credential, authenticate() returns a Passport whose user matches
     * the user returned by authenticateAndGetUser().
     *
     * Ref: Requirements 1.3, 14.3
     */
    public function testAuthenticateRoundTrip(): void
    {
        // Use names() instead of string() to generate URL-safe credential keys
        // and usernames. Generators::string() can produce null bytes and special
        // characters that break query-parameter round-trip through Request::create().
        // Wrap with suchThat to filter out empty strings (names() returns '' at size 0).
        $nonEmptyName = Generators::suchThat(fn(string $s) => $s !== '', Generators::names());
        $this->forAll(
            $nonEmptyName,
            $nonEmptyName,
            Generators::choose(0, 3)
        )->then(function (string $credentialKey, string $username, int $roleCount) {
            $roles = [];
            for ($i = 0; $i < $roleCount; $i++) {
                $roles[] = 'ROLE_' . strtoupper(bin2hex(random_bytes(3)));
            }
            // Ensure at least one role (Symfony requires it)
            if (empty($roles)) {
                $roles = ['ROLE_USER'];
            }

            $authenticator = new StubPreAuthenticator([
                $credentialKey => ['identifier' => $username, 'roles' => $roles],
            ]);

            $request = Request::create('/', 'GET', ['sig' => $credentialKey]);
            $passport = $authenticator->authenticate($request);

            $this->assertInstanceOf(SelfValidatingPassport::class, $passport);

            $user = $passport->getUser();
            $this->assertSame($username, $user->getUserIdentifier());
            $this->assertSame($roles, $user->getRoles());
        });
    }

    // ─── Property 3: Authenticate error condition ───────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 3: Authenticate error condition
     * For any credential that does not map to a valid user, authenticate() throws
     * AuthenticationException.
     *
     * Ref: Requirements 1.4, 14.4
     */
    public function testAuthenticateThrowsOnInvalidCredentials(): void
    {
        // Authenticator with no valid credentials at all
        $authenticator = new StubPreAuthenticator([]);

        $this->forAll(
            Generators::suchThat(fn(string $s) => $s !== '', Generators::names())
        )->then(function (string $invalidKey) use ($authenticator) {
            $request = Request::create('/', 'GET', ['sig' => $invalidKey]);

            $this->expectException(AuthenticationException::class);
            $authenticator->authenticate($request);
        });
    }

    // ─── Property 4: CreateToken invariant ──────────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 4: CreateToken invariant
     * For any successful authentication, createToken() returns a token where
     * token.getUser() === passport.getUser() and token.getRoleNames() contains
     * all user roles.
     *
     * Ref: Requirements 1.5, 14.5
     */
    public function testCreateTokenInvariant(): void
    {
        // Use names() instead of string() — same rationale as testAuthenticateRoundTrip.
        $nonEmptyName = Generators::suchThat(fn(string $s) => $s !== '', Generators::names());
        $this->forAll(
            $nonEmptyName,
            $nonEmptyName,
            $nonEmptyName,
            Generators::choose(1, 4)
        )->then(function (string $credentialKey, string $username, string $firewallName, int $roleCount) {
            $roles = [];
            for ($i = 0; $i < $roleCount; $i++) {
                $roles[] = 'ROLE_' . strtoupper(bin2hex(random_bytes(3)));
            }

            $authenticator = new StubPreAuthenticator([
                $credentialKey => ['identifier' => $username, 'roles' => $roles],
            ]);

            $request = Request::create('/', 'GET', ['sig' => $credentialKey]);
            $passport = $authenticator->authenticate($request);
            $token = $authenticator->createToken($passport, $firewallName);

            // token.getUser() === passport.getUser()
            $this->assertSame($passport->getUser(), $token->getUser());

            // token is a PostAuthenticationToken
            $this->assertInstanceOf(PostAuthenticationToken::class, $token);

            // token.getRoleNames() contains all user roles
            $tokenRoles = $token->getRoleNames();
            foreach ($roles as $role) {
                $this->assertContains(
                    $role,
                    $tokenRoles,
                    "Token should contain role {$role}"
                );
            }
        });
    }
}
