<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TestApiUserPreAuthenticator extends AbstractSimplePreAuthenticator
{
    public function getCredentialsFromRequest(Request $request)
    {
        $apiKey = $request->query->get('sig');

        if (!$apiKey) {
            throw new BadCredentialsException("sig not found!");
        }

        return $apiKey;
    }

    /**
     * Phase 3 stub — not functional in Phase 1.
     */
    public function createToken(Request $request, $providerKey)
    {
        throw new \LogicException("Security authenticator system not yet implemented — Phase 3 (PRP-005)");
    }

    /**
     * Phase 3 stub — not functional in Phase 1.
     */
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        throw new \LogicException("Security authenticator system not yet implemented — Phase 3 (PRP-005)");
    }

    /**
     * Phase 3 stub — not functional in Phase 1.
     */
    public function supportsToken(TokenInterface $token, $providerKey)
    {
        throw new \LogicException("Security authenticator system not yet implemented — Phase 3 (PRP-005)");
    }
}
