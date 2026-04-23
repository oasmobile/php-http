<?php
/**
 * Integration test for SilexKernel cross-community interactions (Requirement 11).
 *
 * Verifies Cookie provider → response header, Middleware execution order,
 * and Configuration validation using a full HTTP request lifecycle via WebTestCase.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Silex\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SilexKernelCrossCommunityIntegrationTest extends WebTestCase
{
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/app.integration-kernel.php';

        return $app;
    }

    protected function setUp()
    {
        // Clear route cache to avoid stale cache issues
        $cacheDir = __DIR__ . '/../cache';
        foreach (glob($cacheDir . '/Project*.php') as $file) {
            @unlink($file);
        }
        foreach (glob($cacheDir . '/Project*.php.meta') as $file) {
            @unlink($file);
        }

        parent::setUp();
    }

    // ---------------------------------------------------------------
    // AC 1: Cookie written to response via SimpleCookieProvider
    // ---------------------------------------------------------------

    /**
     * Request to /integration/cookie/set → response should contain Set-Cookie header
     * with integration_name=integration_value.
     */
    public function testCookieSetRouteWritesCookieToResponse()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/cookie/set');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        // Verify Set-Cookie header is present
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies, 'Response should contain at least one cookie');

        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'integration_name' && $cookie->getValue() === 'integration_value') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Response should contain cookie integration_name=integration_value');
    }

    /**
     * After setting cookie, subsequent request to /integration/cookie/check should
     * read the cookie back (browser-kit client handles cookies across requests).
     */
    public function testCookieSetThenCheckReadsCookieBack()
    {
        $client = $this->createClient();

        // First request: set the cookie
        $client->request('GET', '/integration/cookie/set');
        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            $client->getResponse()->getContent()
        );

        // Second request: check the cookie (browser-kit forwards cookies)
        $client->request('GET', '/integration/cookie/check');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('cookieCheck', $json['called']);
        $this->assertEquals('integration_value', $json['name']);
    }

    // ---------------------------------------------------------------
    // AC 2: Middleware execution order
    // ---------------------------------------------------------------

    /**
     * After handling a request to /integration/middleware/test, the TestMiddleware
     * should have recorded both before() and after() calls.
     */
    public function testMiddlewareRecordsBothBeforeAndAfterCalls()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/middleware/test');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        /** @var TestMiddleware $middleware */
        $middleware = $this->app['test.middleware'];

        $this->assertCount(
            1,
            $middleware->getBeforeCalls(),
            'before() should be called once after handling a request'
        );
        $this->assertCount(
            1,
            $middleware->getAfterCalls(),
            'after() should be called once after handling a request'
        );
    }

    /**
     * The middleware's before() should be called before the controller,
     * and after() should be called after. Verify by checking that both
     * recorded calls contain the expected request/response data.
     */
    public function testMiddlewareBeforeCalledBeforeControllerAndAfterCalledAfter()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/middleware/test');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        /** @var TestMiddleware $middleware */
        $middleware = $this->app['test.middleware'];

        // before() should have received the request and application
        $beforeCalls = $middleware->getBeforeCalls();
        $this->assertCount(1, $beforeCalls);
        $this->assertArrayHasKey('request', $beforeCalls[0]);
        $this->assertArrayHasKey('application', $beforeCalls[0]);

        // after() should have received the request and response
        $afterCalls = $middleware->getAfterCalls();
        $this->assertCount(1, $afterCalls);
        $this->assertArrayHasKey('request', $afterCalls[0]);
        $this->assertArrayHasKey('response', $afterCalls[0]);

        // The after() response should be the actual response returned
        $this->assertSame($response, $afterCalls[0]['response']);
    }

    // ---------------------------------------------------------------
    // AC 3: Configuration validation
    // ---------------------------------------------------------------

    /**
     * Constructing SilexKernel with valid configuration should succeed
     * (the app from app.integration-kernel.php boots successfully).
     */
    public function testValidConfigurationBootsSuccessfully()
    {
        // The app is already created via createApplication() — just verify it boots
        $this->app->boot();

        // If we reach here without exception, boot succeeded
        $this->assertTrue(true, 'SilexKernel with valid configuration should boot successfully');

        // Additionally verify the app can handle a request
        $client = $this->createClient();
        $client->request('GET', '/integration/public');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('publicAction', $json['called']);
    }

    /**
     * Constructing SilexKernel with invalid configuration (unknown top-level key)
     * should throw InvalidConfigurationException.
     */
    public function testInvalidConfigurationThrowsException()
    {
        $this->setExpectedException(InvalidConfigurationException::class);

        new SilexKernel(
            [
                'unknown_invalid_key' => 'some_value',
            ],
            true
        );
    }
}
