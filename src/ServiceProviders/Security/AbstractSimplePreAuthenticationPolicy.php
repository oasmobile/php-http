<?php

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Abstract pre-authentication policy.
 *
 * Implements AuthenticationPolicyInterface for the pre-auth authentication type.
 * Subclasses must provide a concrete getAuthenticator() that returns an
 * AuthenticatorInterface instance (typically an AbstractPreAuthenticator subclass).
 */
abstract class AbstractSimplePreAuthenticationPolicy implements AuthenticationPolicyInterface
{
    public function getAuthenticationType(): string
    {
        return self::AUTH_TYPE_PRE_AUTH;
    }

    abstract public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface;

    public function getAuthenticatorConfig(): array
    {
        return [];
    }

    public function getEntryPoint(MicroKernel $kernel, string $name, array $options): AuthenticationEntryPointInterface
    {
        return new NullEntryPoint();
    }
}
