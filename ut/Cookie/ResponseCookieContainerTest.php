<?php

namespace Oasis\Mlib\Http\Test\Cookie;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

class ResponseCookieContainerTest extends TestCase
{
    public function testGetCookiesReturnsEmptyArrayInitially()
    {
        $container = new ResponseCookieContainer();
        $this->assertSame([], $container->getCookies());
    }

    public function testAddCookieMakesCookieRetrievableViaGetCookies()
    {
        $container = new ResponseCookieContainer();
        $cookie = new Cookie('name', 'value');
        $container->addCookie($cookie);

        $cookies = $container->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame($cookie, $cookies[0]);
    }

    public function testMultipleAddCookieCallsAccumulateAllCookies()
    {
        $container = new ResponseCookieContainer();
        $cookie1 = new Cookie('first', 'value1');
        $cookie2 = new Cookie('second', 'value2');
        $cookie3 = new Cookie('third', 'value3');

        $container->addCookie($cookie1);
        $container->addCookie($cookie2);
        $container->addCookie($cookie3);

        $cookies = $container->getCookies();
        $this->assertCount(3, $cookies);
        $this->assertSame($cookie1, $cookies[0]);
        $this->assertSame($cookie2, $cookies[1]);
        $this->assertSame($cookie3, $cookies[2]);
    }
}
