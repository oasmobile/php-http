<?php
declare(strict_types=1);

/**
 * Unit tests for AbstractSimplePreAuthenticateUserProvider.
 *
 * Covers loadUserByIdentifier() and supportsClass() with subclass — the uncovered methods.
 */

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUser;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class AbstractSimplePreAuthenticateUserProviderTest extends TestCase
{
    /**
     * loadUserByIdentifier() should throw LogicException.
     */
    public function testLoadUserByIdentifierThrowsLogicException(): void
    {
        $provider = new TestApiUserProvider();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You should not load a user by identifier');

        $provider->loadUserByIdentifier('any-identifier');
    }

    /**
     * supportsClass() returns true for the exact supported class.
     */
    public function testSupportsClassReturnsTrueForExactClass(): void
    {
        $provider = new TestApiUserProvider();

        $this->assertTrue($provider->supportsClass(TestApiUser::class));
    }

    /**
     * supportsClass() returns true for a subclass of the supported class.
     */
    public function testSupportsClassReturnsTrueForSubclass(): void
    {
        $provider = new TestApiUserProvider();

        // TestApiUser implements UserInterface, but we need a real subclass of TestApiUser
        // Create an anonymous subclass
        $subclass = get_class(new class('sub', []) extends TestApiUser {});

        $this->assertTrue($provider->supportsClass($subclass));
    }

    /**
     * supportsClass() returns false for an unrelated class.
     */
    public function testSupportsClassReturnsFalseForUnrelatedClass(): void
    {
        $provider = new TestApiUserProvider();

        $this->assertFalse($provider->supportsClass(\stdClass::class));
    }

    /**
     * refreshUser() should return the same user object.
     */
    public function testRefreshUserReturnsSameUser(): void
    {
        $provider = new TestApiUserProvider();
        $user = new TestApiUser('alice', ['ROLE_USER']);

        $refreshed = $provider->refreshUser($user);

        $this->assertSame($user, $refreshed);
    }
}
