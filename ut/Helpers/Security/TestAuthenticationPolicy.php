<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 15:58
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticationPolicy;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;

class TestAuthenticationPolicy extends AbstractSimplePreAuthenticationPolicy
{
    /**
     * Phase 3 stub: returns the pre-authenticator instance.
     * Previously used by SimpleAuthenticationProvider (removed in Symfony 6.0).
     *
     * @return TestApiUserPreAuthenticator
     */
    public function getPreAuthenticator()
    {
        return new TestApiUserPreAuthenticator();
    }

    /**
     * @inheritDoc
     * Phase 3 stub — not functional in Phase 1.
     */
    public function getAuthenticationProvider(MicroKernel $kernel, $firewallName, $options): string|AuthenticationProviderInterface
    {
        throw new \LogicException("Security authenticator system not yet implemented — Phase 3 (PRP-005)");
    }

    /**
     * @inheritDoc
     * Phase 3 stub — not functional in Phase 1.
     */
    public function getAuthenticationListener(MicroKernel $kernel, $firewallName, $options): mixed
    {
        throw new \LogicException("Security authenticator system not yet implemented — Phase 3 (PRP-005)");
    }
}
