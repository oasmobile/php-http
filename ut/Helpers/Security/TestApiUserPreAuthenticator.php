<?php

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractPreAuthenticator;
use Oasis\Mlib\Http\ServiceProviders\Security\SimplePreAuthenticateUserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test pre-authenticator that extracts credentials from the 'sig' query parameter.
 *
 * Extends the new AbstractPreAuthenticator (Symfony 7.x authenticator system).
 * Delegates user lookup to an injected SimplePreAuthenticateUserProviderInterface.
 */
class TestApiUserPreAuthenticator extends AbstractPreAuthenticator
{
    public function __construct(
        private readonly SimplePreAuthenticateUserProviderInterface $userProvider
    ) {
    }

    protected function getCredentialsFromRequest(Request $request): mixed
    {
        $apiKey = $request->query->get('sig');

        return $apiKey ?: null; // null 表示不支持该请求
    }

    protected function authenticateAndGetUser(mixed $credentials): UserInterface
    {
        return $this->userProvider->authenticateAndGetUser($credentials);
    }
}
