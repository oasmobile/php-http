<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:06
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticateUserProvider;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class TestApiUserProvider extends AbstractSimplePreAuthenticateUserProvider
{
    public function __construct()
    {
        parent::__construct(TestApiUser::class);
    }
    
    /**
     * @param mixed $credentials the credentials extracted from request
     *
     * @return UserInterface
     *
     * @throws AuthenticationException throws authentication exception if authentication by credentials failed
     */
    public function authenticateAndGetUser($credentials)
    {
        switch ($credentials) {
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
                throw new UsernameNotFoundException("apikey $credentials doesn't match any user!");
        }
    }
}
