<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TestApiUserPreAuthenticator extends AbstractSimplePreAuthenticator
{
    /**
     * @param TokenInterface        $token
     * @param UserProviderInterface $userProvider
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if a user cannot be loaded by the specified token, this exception is thrown
     */
    public function loadUserByToken(TokenInterface $token, UserProviderInterface $userProvider)
    {
        if (!$userProvider instanceof TestApiUserProvider) {
            throw new \InvalidArgumentException("User provider of wrong type, type = ", get_class($userProvider));
        }

        $apiKey = $token->getCredentials();
        $user   = $userProvider->loadUserForApiKey($apiKey);

        return $user;
    }

    public function getCredentialsFromRequest(Request $request)
    {
        $apiKey = $request->query->get('sig');

        if (!$apiKey) {
            throw new BadCredentialsException("sig not found!");
        }

        return $apiKey;
    }
}
