<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class TestApiUserPreAuthenticator implements SimplePreAuthenticatorInterface
{
    
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        if (!$userProvider instanceof TestApiUserProvider) {
            throw new \InvalidArgumentException("User provider of wrong type, type = ", get_class($userProvider));
        }

        $apiKey = $token->getCredentials();
        $user   = $userProvider->loadUserForApiKey($apiKey);
        $roles  = $user->getRoles();

        return new PreAuthenticatedToken(
            $user,
            $apiKey,
            $providerKey,
            $roles
        );
    }

    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return (
            $token instanceof PreAuthenticatedToken
            && $token->getProviderKey() === $providerKey
        );
    }

    public function createToken(Request $request, $providerKey)
    {
        $apiKey = $request->query->get('sig');

        if (!$apiKey) {
            throw new BadCredentialsException("sig not found!");
        }

        return new PreAuthenticatedToken('anon.', $apiKey, $providerKey);
    }
}
