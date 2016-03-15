<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:06
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TestApiUserProvider implements UserProviderInterface
{
    
    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        minfo("Loading username: %s", $username);

        switch ($username) {
            default:
                throw new UsernameNotFoundException("Username $username not found for test api");
        }
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
    public function refreshUser(UserInterface $user)
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
    public function supportsClass($class)
    {
        return TestApiUser::class;
    }

    /**
     * @param $apiKey
     *
     * @return TestApiUser
     */
    public function loadUserForApiKey($apiKey)
    {
        switch ($apiKey) {
            case 'abcd':
                return new TestApiUser('admin', ['ROLE_GOOD', 'ROLE_ADMIN']);
                break;
            case 'parent':
                return new TestApiUser('parent', ['ROLE_PARENT']);
                break;
            case 'child':
                return new TestApiUser('child', ['ROLE_CHILD']);
                break;
            default:
                throw new UsernameNotFoundException("apikey $apiKey doesn't match any user!");
        }
    }
}
