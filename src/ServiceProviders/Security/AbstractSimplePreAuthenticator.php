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
        if (!$userProvider instanceof SimplePreAuthenticateUserProviderInterface) {
            throw new \InvalidArgumentException(
                "User provider must implement " . SimplePreAuthenticateUserProviderInterface::class
            );
        }

        $credentials = $token->getCredentials();
        $user = $userProvider->authenticateAndGetUser($credentials);

        return $this->createAuthenticatedToken($user, $credentials, $providerKey);
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
    protected function getUsernameFromRequest(/** @noinspection PhpUnusedParameterInspection */
        Request $request)
    {
        return "anon.";
    }

    /**
     * Creates an authenticated token upon authentication success.
     *
     * Inherited class can override this method to provide their own pre-authenticated token implementation
     *
     * @param string|UserInterface $user        The user
     * @param mixed                $credentials The user credentials
     * @param string               $providerKey The provider key
     *
     * @return PreAuthenticatedToken
     */
    protected function createAuthenticatedToken($user, $credentials, $providerKey)
    {
        return new PreAuthenticatedToken($user, $credentials, $providerKey, $user->getRoles());
    }

    /**
     * Parse the given request, and extract the credential information from the request
     *
     * @param Request $request
     *
     * @return mixed
     */
    abstract public function getCredentialsFromRequest(Request $request);
}
