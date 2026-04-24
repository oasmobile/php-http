<?php

namespace Oasis\Mlib\Http\Test\Cookie;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SimpleCookieProviderTest extends TestCase
{
    public function testGetSubscribedEventsListensOnKernelResponse()
    {
        $events = SimpleCookieProvider::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.response', $events);
        $this->assertSame(['onResponse', 0], $events['kernel.response']);
    }

    public function testOnResponseWritesCookiesToResponseHeaders()
    {
        $cookieContainer = new ResponseCookieContainer();
        $cookie          = new Cookie('test_cookie', 'test_value');
        $cookieContainer->addCookie($cookie);

        $provider = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createMock(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $provider->onResponse($event);

        $responseCookies = $response->headers->getCookies();
        $this->assertCount(1, $responseCookies);
        $this->assertSame('test_cookie', $responseCookies[0]->getName());
        $this->assertSame('test_value', $responseCookies[0]->getValue());
    }

    public function testOnResponseWritesMultipleCookies()
    {
        $cookieContainer = new ResponseCookieContainer();
        $cookieContainer->addCookie(new Cookie('first', 'value1'));
        $cookieContainer->addCookie(new Cookie('second', 'value2'));

        $provider = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createMock(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $provider->onResponse($event);

        $responseCookies = $response->headers->getCookies();
        $this->assertCount(2, $responseCookies);

        $names = array_map(fn(Cookie $c) => $c->getName(), $responseCookies);
        $this->assertContains('first', $names);
        $this->assertContains('second', $names);
    }

    public function testOnResponseDoesNothingWhenNoCookies()
    {
        $cookieContainer = new ResponseCookieContainer();
        $provider        = new SimpleCookieProvider($cookieContainer);

        $kernel   = $this->createMock(HttpKernelInterface::class);
        $request  = Request::create('/test');
        $response = new Response('ok');
        $event    = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $provider->onResponse($event);

        $this->assertEmpty($response->headers->getCookies());
    }

    public function testConstructorCreatesDefaultCookieContainerWhenNoneProvided()
    {
        $provider  = new SimpleCookieProvider();
        $container = $provider->getCookieContainer();

        $this->assertInstanceOf(ResponseCookieContainer::class, $container);
        $this->assertEmpty($container->getCookies());
    }

    public function testGetCookieContainerReturnsSameInstance()
    {
        $cookieContainer = new ResponseCookieContainer();
        $provider        = new SimpleCookieProvider($cookieContainer);

        $this->assertSame($cookieContainer, $provider->getCookieContainer());
    }
}
