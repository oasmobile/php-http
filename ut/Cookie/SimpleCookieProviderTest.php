<?php

namespace Oasis\Mlib\Http\Test\Cookie;

use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use Oasis\Mlib\Http\SilexKernel;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleCookieProviderTest extends TestCase
{
    public function testBootThrowsLogicExceptionForNonSilexKernel()
    {
        $provider = new SimpleCookieProvider();
        $app = new Application();

        $this->expectException(\LogicException::class);
        $provider->boot($app);
    }

    public function testBootRegistersAfterMiddlewareThatWritesCookiesToResponse()
    {
        $provider = new SimpleCookieProvider();

        $kernel = new SilexKernel([], true);
        $provider->register($kernel);
        $provider->boot($kernel);

        // Access the internal cookie container via reflection
        $ref = new \ReflectionProperty(SimpleCookieProvider::class, 'cookieContainer');
        $ref->setAccessible(true);
        $cookieContainer = $ref->getValue($provider);

        // Add a cookie to the container
        $cookie = new Cookie('test_cookie', 'test_value');
        $cookieContainer->addCookie($cookie);

        // Set up a simple route and handle a request to trigger the after middleware
        $kernel->get('/test', function () {
            return new Response('ok');
        });

        $request = Request::create('/test');
        $response = $kernel->handle($request);

        // Verify the cookie was written to the response headers
        $responseCookies = $response->headers->getCookies();
        $this->assertCount(1, $responseCookies);
        $this->assertSame('test_cookie', $responseCookies[0]->getName());
        $this->assertSame('test_value', $responseCookies[0]->getValue());
    }
}
