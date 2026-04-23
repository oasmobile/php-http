<?php
/**
 * Integration test controller.
 *
 * Provides simple actions for integration tests covering security, cookie, and middleware flows.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class IntegrationController
{
    // --- Security routes (R10) ---

    public function securedAdmin(SilexKernel $app)
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
            'user'   => $app->getUser()->getUsername(),
            'admin'  => $app->isGranted('ROLE_ADMIN'),
        ];
    }

    public function securedUser(SilexKernel $app)
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
            'user'   => $app->getUser()->getUsername(),
        ];
    }

    public function securedParent(SilexKernel $app)
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
            'user'   => $app->getUser()->getUsername(),
            'parent' => $app->isGranted('ROLE_PARENT'),
        ];
    }

    public function securedChild(SilexKernel $app)
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
            'user'   => $app->getUser()->getUsername(),
            'child'  => $app->isGranted('ROLE_CHILD'),
        ];
    }

    public function publicAction()
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
        ];
    }

    // --- Cookie routes (R11 AC 1) ---

    public function cookieSet(ResponseCookieContainer $cookies)
    {
        $cookies->addCookie(new Cookie('integration_name', 'integration_value'));

        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
        ];
    }

    public function cookieCheck(Request $request)
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
            'name'   => $request->cookies->get('integration_name'),
        ];
    }

    // --- Middleware routes (R11 AC 2) ---

    public function middlewareTest()
    {
        return [
            'called' => __CLASS__ . '::' . __FUNCTION__ . '()',
        ];
    }
}
