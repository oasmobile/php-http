<?php
declare(strict_types=1);

/**
 * Controller for Security scenario tests.
 *
 * Provides simple actions that return JSON payloads containing
 * authentication/authorization state for assertion.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\MicroKernel;

class ScenarioSecurityController
{
    /**
     * Returns authentication info: user identifier, token class, isGranted results.
     */
    public function info(MicroKernel $app): array
    {
        $token = $app->getToken();
        $user  = $app->getUser();

        return [
            'user'              => $user?->getUserIdentifier(),
            'token_class'       => $token !== null ? $token::class : null,
            'is_authenticated'  => $app->isGranted('IS_AUTHENTICATED_FULLY'),
            'roles'             => $token?->getRoleNames() ?? [],
        ];
    }

    /**
     * Public endpoint — no security required.
     */
    public function publicAction(): array
    {
        return ['status' => 'public_ok'];
    }

    /**
     * API resource endpoint — used for access rule testing.
     */
    public function apiResource(MicroKernel $app): array
    {
        return [
            'status' => 'api_ok',
            'user'   => $app->getUser()?->getUserIdentifier(),
        ];
    }

    /**
     * Admin resource endpoint — used for role-based access testing.
     */
    public function adminResource(MicroKernel $app): array
    {
        return [
            'status' => 'admin_ok',
            'user'   => $app->getUser()?->getUserIdentifier(),
            'admin'  => $app->isGranted('ROLE_ADMIN'),
        ];
    }
}
