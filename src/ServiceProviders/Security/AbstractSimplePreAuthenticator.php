<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

abstract class AbstractSimplePreAuthenticator implements SimplePreAuthenticatorInterface
{

    public function createToken(Request $request, $providerKey)
    {
        $credentials = $this->getCredentialsFromRequest($request);
        $username    = $this->getUsernameFromRequest($request);

        return new PreAuthenticatedToken($username, $credentials, $providerKey);
    }

    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        $user = $this->loadUserByToken($token, $userProvider);

        $roles = $user->getRoles();

        return new PreAuthenticatedToken(
            $user,
            $token->getCredentials(),
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

    /**
     * Parse the given request, and extract the username from the request.
     *
     * If username cannot be parsed from request, "anon." should be returned.
     *
     * NOTE: this method should only parse the request, and should NOT load username from any resouce except the request
     *
     * @param Request $request
     *
     * @return string
     */
    public function getUsernameFromRequest(/** @noinspection PhpUnusedParameterInspection */
        Request $request)
    {
        return "anon.";
    }

    /**
     *
     * Load a user identified by the given token, from the given user provider
     *
     * @param TokenInterface        $token
     * @param UserProviderInterface $userProvider
     *
     * @return UserInterface
     *
     * @throws UsernameNotFoundException if a user cannot be loaded by the specified token, this exception is thrown
     */
    abstract public function loadUserByToken(TokenInterface $token, UserProviderInterface $userProvider);

    /**
     * Parse the given request, and extract the credential information from the request
     *
     * @param Request $request
     *
     * @return mixed
     */
    abstract public function getCredentialsFromRequest(Request $request);
}
