<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Cookie;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Regression tests for Cookie module fixes discovered during v3.3.0 behavior audit.
 *
 * Fix: SimpleCookieProvider::onResponse() now only writes cookies on main request,
 * matching Silex after() default behavior ($masterRequestOnly = true).
 */
class CookieFixRegressionTest extends TestCase
{
    /**
     * Verify cookies ARE written on main request response.
     *
     * This is the normal case — ensures the fix didn't break main request behavior.
     */
    public function testCookiesWrittenOnMainRequest(): void
    {
        $cookieContainer = new ResponseCookieContainer();
        $cookieContainer->addCookie(new Cookie('test', 'value'));

        $provider = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createStub(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $provider->onResponse($event);

        $this->assertCount(1, $response->headers->getCookies());
        $this->assertSame('test', $response->headers->getCookies()[0]->getName());
    }

    /**
     * Verify cookies are NOT written on sub-request response.
     *
     * In v2.5.0, Silex after() used $masterRequestOnly = true by default,
     * meaning cookie writing was skipped for sub-requests. v3.x was missing
     * this check, causing cookies to be written on sub-request responses.
     */
    public function testCookiesNotWrittenOnSubRequest(): void
    {
        $cookieContainer = new ResponseCookieContainer();
        $cookieContainer->addCookie(new Cookie('test', 'value'));

        $provider = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createStub(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $provider->onResponse($event);

        $this->assertEmpty(
            $response->headers->getCookies(),
            'Cookies should NOT be written on sub-request response (Silex after() $masterRequestOnly=true behavior)',
        );
    }

    /**
     * Verify multiple cookies are all written on main request.
     *
     * Ensures the main-request guard doesn't interfere with multi-cookie writing.
     */
    public function testMultipleCookiesWrittenOnMainRequest(): void
    {
        $cookieContainer = new ResponseCookieContainer();
        $cookieContainer->addCookie(new Cookie('a', '1'));
        $cookieContainer->addCookie(new Cookie('b', '2'));

        $provider = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createStub(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $provider->onResponse($event);

        $this->assertCount(2, $response->headers->getCookies());
    }
}
