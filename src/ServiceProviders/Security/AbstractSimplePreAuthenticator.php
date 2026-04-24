<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Abstract stub for pre-authenticator.
 *
 * In Symfony 6.0+, SimplePreAuthenticatorInterface has been removed.
 * This class no longer implements that interface. All methods that previously
 * depended on the removed API are declared abstract, forcing downstream to
 * provide implementations in Phase 3 (PRP-005).
 *
 * The concrete methods (createToken, authenticateToken, supportsToken) that
 * relied on removed Symfony APIs are now abstract stubs.
 */
abstract class AbstractSimplePreAuthenticator
{
    /**
     * Must be implemented by downstream in Phase 3.
     * Previously created a PreAuthenticatedToken from request credentials.
     *
     * @param Request $request
     * @param string  $providerKey
     *
     * @return TokenInterface
     */
    abstract public function createToken(Request $request, $providerKey);

    /**
     * Must be implemented by downstream in Phase 3.
     * Previously authenticated a token using SimplePreAuthenticateUserProviderInterface.
     *
     * @param TokenInterface        $token
     * @param UserProviderInterface $userProvider
     * @param string                $providerKey
     *
     * @return TokenInterface
     */
    abstract public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey);

    /**
     * Must be implemented by downstream in Phase 3.
     * Previously checked if the token was a PreAuthenticatedToken matching the provider key.
     *
     * @param TokenInterface $token
     * @param string         $providerKey
     *
     * @return bool
     */
    abstract public function supportsToken(TokenInterface $token, $providerKey);

    /**
     * Parse the given request, and extract the credential information from the request
     *
     * @param Request $request
     *
     * @return mixed
     */
    abstract public function getCredentialsFromRequest(Request $request);
}
