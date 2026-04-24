<?php

namespace Oasis\Mlib\Http\Test\Cors;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:08
 */
class CrossOriginResourceSharingTest extends WebTestCase
{
    use RouteCacheCleaner;

    protected function setUp(): void
    {
        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        return require __DIR__ . '/app.cors.php';
    }
    
    public function testPreflightOnExistingRoute()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'localhost',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        $this->assertEmpty($response->getContent(), $response->getContent());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));
        $this->assertEquals(86400, $response->headers->get(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));
    }
    
    public function testPreflightOnNotFoundRoute()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/404',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'localhost',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
    
    public function testPrefilightOnAllowedOrigin()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/home',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
    }
    
    public function testPrefilightOnNotAllowedOrigin()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/home',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => '163.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
    }
    
    public function testPrefilightOnLimitedAllowedMethod()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertStringContainsString('PUT', $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
    }
    
    public function testPrefilightOnNotAllowedMethod()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'DELETE',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
    }
    
    public function testPrefilightOnAllowedHeader()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
                "HTTP_"
                . CrossOriginResourceSharingProvider::HEADER_REQUEST_HEADERS        => 'CUSTOM_HEADER,custom_Header2, custom_header3 ,custom_header4',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
    }
    
    public function testPrefilightOnNotAllowedHeader()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN  => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD  => 'PUT',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_HEADERS => 'CUSTOM_HEADER, NO_SUCH_HEADER',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
    }
    
    public function testPrefilightOnCredentials()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'POST',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertEquals(
            'true',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS)
        );
        $this->assertEquals(
            'baidu.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN)
        );
        $this->assertStringNotContainsString('*', $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
    }
    
    public function testNormalRequestAfterPreflight()
    {
        $client = $this->createClient();
        $client->request(
            'PUT',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
    }
    
    public function testExposedHeadersAfterPreflight()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
        $exposedHeaders = strtolower(
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS)
        );
        $this->assertStringContainsString('name', $exposedHeaders);
        $this->assertStringContainsString('job', $exposedHeaders);
    }
    
    // ========================================================================
    // Supplementary tests for uncovered branches (R12, AC 2)
    // ========================================================================
    
    /**
     * Normal request WITHOUT Origin header should skip all CORS headers.
     * Covers: onPreRouting() early return when no Origin header,
     *         onResponse() normal-request early return when no Origin header.
     */
    public function testNormalRequestWithoutOriginHeader()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/cors/home',
            [],
            [],
            []
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
    }
    
    /**
     * Normal request with a DISALLOWED origin should skip CORS headers.
     * Covers: onResponse() normal-request path — isOriginAllowed() returns false.
     * Strategy 1 (pattern /cors/.*) only allows localhost, baidu.com, cors.oasis.mlib.com.
     */
    public function testNormalRequestWithDisallowedOrigin()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/cors/home',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => '163.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
    }
    
    /**
     * Normal request with credentials_allowed=true (strategy 2, wildcard pattern).
     * Route "/" matches strategy 2 (pattern "*", credentials_allowed=true).
     * Covers: onResponse() normal-request path — credentials branch.
     */
    public function testNormalRequestWithCredentialsAllowed()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertEquals(
            'true',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS)
        );
        // When credentials_allowed=true, origin is echoed (not wildcard)
        $this->assertEquals(
            'baidu.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN)
        );
    }
    
    /**
     * Normal request with non-wildcard origin, credentials_allowed=false (strategy 1).
     * Route "/cors/put" matches strategy 1 (specific origins, credentials_allowed=false).
     * Covers: onResponse() normal-request path — non-wildcard, non-credentials branch
     *         (sets Allow-Origin=origin and Vary=Origin).
     */
    public function testNormalRequestNonWildcardOriginNoCredentials()
    {
        $client = $this->createClient();
        $client->request(
            'PUT',
            '/cors/put',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertEquals(
            'baidu.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN)
        );
        // Non-wildcard origin should set Vary: Origin
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
        // credentials_allowed=false, so no Allow-Credentials header
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
    }
    
    /**
     * Multi-strategy priority: first matching strategy wins.
     * Route "/cors/home" matches strategy 1 (pattern /cors/.*) first,
     * so credentials_allowed should be false (strategy 1 default),
     * even though strategy 2 (pattern *) has credentials_allowed=true.
     * Covers: onPreRouting() — first matching strategy breaks the loop.
     */
    public function testMultiStrategyPriorityFirstMatchWins()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/cors/home',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'baidu.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        // Strategy 1 has credentials_allowed=false (default), so no Allow-Credentials
        $this->assertFalse(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
            'First matching strategy (credentials_allowed=false) should take priority over second strategy (credentials_allowed=true)'
        );
        // Strategy 1 has specific origins (not wildcard), so Vary: Origin should be set
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
        // Strategy 1 has no headers_exposed, so no Expose-Headers
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
    }
    
    /**
     * Normal request with credentials_allowed=true also includes exposed headers.
     * Route "/" matches strategy 2 (credentials_allowed=true, headers_exposed=['name','job','content-types']).
     * Covers: onResponse() normal-request path — both credentials and exposed headers branches.
     */
    public function testNormalRequestCredentialsAndExposedHeaders()
    {
        $client = $this->createClient();
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'localhost',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        // Credentials
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertEquals(
            'true',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS)
        );
        $this->assertEquals(
            'localhost',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN)
        );
        // Exposed headers
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
        $exposedHeaders = strtolower(
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS)
        );
        $this->assertStringContainsString('name', $exposedHeaders);
        $this->assertStringContainsString('job', $exposedHeaders);
        $this->assertStringContainsString('content-types', $exposedHeaders);
    }
    
    /**
     * Preflight request WITHOUT Origin header should not set CORS headers.
     * Covers: onPreRouting() early return — activeStrategy stays null.
     */
    public function testPreflightWithoutOriginHeader()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/cors/home',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ]
        );
        $response = $client->getResponse();
        // Without Origin, no CORS processing; route exists but OPTIONS may not be allowed
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
    }
}
