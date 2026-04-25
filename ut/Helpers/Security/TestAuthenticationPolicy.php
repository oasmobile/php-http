<?php

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Test authentication policy for pre-auth.
 *
 * Extends AbstractSimplePreAuthenticationPolicy and provides a
 * TestApiUserPreAuthenticator backed by TestApiUserProvider.
 */
class TestAuthenticationPolicy extends AbstractSimplePreAuthenticationPolicy
{
    public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface
    {
        $userProvider = new TestApiUserProvider();

        return new TestApiUserPreAuthenticator($userProvider);
    }
}
