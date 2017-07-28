<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 15:58
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Silex\Application;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class TestAuthenticationPolicy extends AbstractSimplePreAuthenticationPolicy
{
    /**
     * @return SimplePreAuthenticatorInterface
     */
    public function getPreAuthenticator()
    {
        return new TestApiUserPreAuthenticator();
    }
}
