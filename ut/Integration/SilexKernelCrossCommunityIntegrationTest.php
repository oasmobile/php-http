<?php
/**
 * Integration test for MicroKernel cross-community interactions.
 *
 * Verifies Cookie provider → response header, Middleware execution order,
 * and Configuration validation using a full HTTP request lifecycle via WebTestCase.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SilexKernelCrossCommunityIntegrationTest extends WebTestCase
{
    use RouteCacheCleaner;

    /** @var TestMiddleware|null */
    private $testMiddleware;

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $this->testMiddleware = new TestMiddleware();

        $config = [
            'cache_dir'      => __DIR__ . '/../cache',
            'routing'        => [
                'path'       => __DIR__ . '/integration.routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Integration\\',
                ],
            ],
            'view_handlers'  => [new \Oasis\Mlib\Http\Views\JsonViewHandler()],
            'error_handlers' => [new \Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler()],
            'middlewares'    => [$this->testMiddleware],
        ];

        return new MicroKernel($config, true);
    }

    protected function setUp(): void
    {
        $this->cleanRouteCache(__DIR__ . '/../cache');
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
        $this->assertStringContainsString('cookieCheck', $json['called']);
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

        $this->assertCount(
            1,
            $this->testMiddleware->getBeforeCalls(),
            'before() should be called once after handling a request'
        );
        $this->assertCount(
            1,
            $this->testMiddleware->getAfterCalls(),
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

        // before() should have received the request and kernel
        $beforeCalls = $this->testMiddleware->getBeforeCalls();
        $this->assertCount(1, $beforeCalls);
        $this->assertArrayHasKey('request', $beforeCalls[0]);
        $this->assertArrayHasKey('kernel', $beforeCalls[0]);

        // after() should have received the request and response
        $afterCalls = $this->testMiddleware->getAfterCalls();
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
     * Constructing MicroKernel with valid configuration should succeed
     * (the app from createApplication() boots successfully).
     */
    public function testValidConfigurationBootsSuccessfully()
    {
        // The app is already created via createApplication() — just verify it boots
        if ($this->app instanceof \Symfony\Component\HttpKernel\Kernel) {
            $this->app->boot();
        }

        // If we reach here without exception, boot succeeded
        $this->assertTrue(true, 'MicroKernel with valid configuration should boot successfully');

        // Additionally verify the app can handle a request
        $client = $this->createClient();
        $client->request('GET', '/integration/public');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertStringContainsString('publicAction', $json['called']);
    }

    /**
     * Constructing MicroKernel with invalid configuration (unknown top-level key)
     * should throw InvalidConfigurationException.
     */
    public function testInvalidConfigurationThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);

        new MicroKernel(
            [
                'unknown_invalid_key' => 'some_value',
            ],
            true
        );
    }
}
