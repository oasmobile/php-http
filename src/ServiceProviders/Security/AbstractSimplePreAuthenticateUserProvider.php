<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-03
 * Time: 12:05
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractSimplePreAuthenticateUserProvider implements SimplePreAuthenticateUserProviderInterface
{
    public function __construct(
        private readonly string $supportedUserClassname
    ) {
    }

    /**
     * Loads the user for the given identifier.
     *
     * This method must throw UserNotFoundException if the user is not found.
     *
     * @param string $identifier The user identifier (e.g. username or email)
     *
     * @return UserInterface
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \LogicException("You should not load a user by identifier, try authenticateAndGetUser()");
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return $class === $this->supportedUserClassname || is_subclass_of($class, $this->supportedUserClassname, true);
    }
}
