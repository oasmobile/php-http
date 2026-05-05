<?php
declare(strict_types=1);

/**
 * Controller for Cookie scenario tests.
 *
 * Provides actions that interact with ResponseCookieContainer to verify
 * cookie writing behavior through the full request pipeline.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;

class ScenarioCookieController
{
    /**
     * Add a single cookie via ResponseCookieContainer.
     */
    public function addCookie(ResponseCookieContainer $cookieContainer): JsonResponse
    {
        $cookieContainer->addCookie(new Cookie('scenario_cookie', 'scenario_value'));

        return new JsonResponse(['action' => 'addCookie', 'added' => true]);
    }

    /**
     * Add multiple cookies via ResponseCookieContainer.
     */
    public function addMultipleCookies(ResponseCookieContainer $cookieContainer): JsonResponse
    {
        $cookieContainer->addCookie(new Cookie('first_cookie', 'first_value'));
        $cookieContainer->addCookie(new Cookie('second_cookie', 'second_value'));
        $cookieContainer->addCookie(new Cookie('third_cookie', 'third_value'));

        return new JsonResponse(['action' => 'addMultipleCookies', 'count' => 3]);
    }

    /**
     * Verify that ResponseCookieContainer is injectable.
     * Simply returns success if the container was resolved.
     */
    public function verifyInjection(ResponseCookieContainer $cookieContainer): JsonResponse
    {
        return new JsonResponse([
            'action'    => 'verifyInjection',
            'injected'  => true,
            'class'     => get_class($cookieContainer),
        ]);
    }
}
