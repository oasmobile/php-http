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
 * @deprecated Use {@see AbstractPreAuthenticator} instead.
 *
 * Legacy pre-authenticator stub from the Symfony 4.x era.
 * In Symfony 7.x the SimplePreAuthenticatorInterface has been removed.
 * Migrate to AbstractPreAuthenticator which implements the unified
 * AuthenticatorInterface.
 *
 * The three legacy API methods (createToken, authenticateToken,
 * supportsToken) are now concrete and throw LogicException to give
 * callers a clear migration signal. getCredentialsFromRequest()
 * remains abstract so that existing subclasses continue to compile.
 */
abstract class AbstractSimplePreAuthenticator
{
    /**
     * @deprecated Use AbstractPreAuthenticator instead.
     *
     * Previously created a PreAuthenticatedToken from request credentials.
     * Now throws LogicException — migrate to AbstractPreAuthenticator.
     *
     * @param Request $request
     * @param string  $providerKey
     *
     * @return TokenInterface
     *
     * @throws \LogicException always
     */
    public function createToken(Request $request, string $providerKey): TokenInterface
    {
        throw new \LogicException(
            sprintf(
                '%s is deprecated. Migrate to %s which implements AuthenticatorInterface.',
                __METHOD__,
                AbstractPreAuthenticator::class
            )
        );
    }

    /**
     * @deprecated Use AbstractPreAuthenticator instead.
     *
     * Previously authenticated a token using SimplePreAuthenticateUserProviderInterface.
     * Now throws LogicException — migrate to AbstractPreAuthenticator.
     *
     * @param TokenInterface                                                                                $token
     * @param UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> $userProvider
     * @param string                                                                                       $providerKey
     *
     * @return TokenInterface
     *
     * @throws \LogicException always
     */
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, string $providerKey): TokenInterface
    {
        throw new \LogicException(
            sprintf(
                '%s is deprecated. Migrate to %s which implements AuthenticatorInterface.',
                __METHOD__,
                AbstractPreAuthenticator::class
            )
        );
    }

    /**
     * @deprecated Use AbstractPreAuthenticator instead.
     *
     * Previously checked if the token was a PreAuthenticatedToken matching the provider key.
     * Now throws LogicException — migrate to AbstractPreAuthenticator.
     *
     * @param TokenInterface $token
     * @param string         $providerKey
     *
     * @return bool
     *
     * @throws \LogicException always
     */
    public function supportsToken(TokenInterface $token, string $providerKey): bool
    {
        throw new \LogicException(
            sprintf(
                '%s is deprecated. Migrate to %s which implements AuthenticatorInterface.',
                __METHOD__,
                AbstractPreAuthenticator::class
            )
        );
    }

    /**
     * Parse the given request, and extract the credential information from the request.
     *
     * @param Request $request
     *
     * @return mixed
     */
    abstract public function getCredentialsFromRequest(Request $request): mixed;
}
