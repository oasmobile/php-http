<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-03
 * Time: 11:57
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

interface SimplePreAuthenticateUserProviderInterface extends UserProviderInterface
{
    /**
     * @param mixed $credentials the credentials extracted from request
     *
     * @return UserInterface
     *
     * @throws AuthenticationException throws authentication exception if authentication by credentials failed
     */
    public function authenticateAndGetUser($credentials);
}
