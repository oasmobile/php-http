<?php
namespace Oasis\Mlib\Http\Test\Cors;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 17:08
 */
class CrossOriginResourceSharingAdvancedTest extends WebTestCase
{
    use RouteCacheCleaner;

    protected function setUp()
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
        return require __DIR__ . '/app.cors-advanced.php';
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testPreflightWhenAccessIsDeniedRoute()
    {
        $origin   = 'http://baidu.com';
        $myHeader = 'custom_header';

        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/secured/madmin/admin',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN  => $origin,
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD  => 'GET',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_HEADERS => $myHeader,
            ]
        );
        $response = $client->getResponse();
        $this->assertEmpty($response->getContent(), $response->getContent());
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertEquals($origin, $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        $this->assertContains(
            $myHeader,
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS)
        );
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));
        $this->assertEquals(86400, $response->headers->get(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));
    }
    
    // ========================================================================
    // Supplementary tests for uncovered branches (R12, AC 2)
    // ========================================================================
    
    /**
     * Normal (non-preflight) request to a secured CORS route with allowed origin.
     * The advanced app has only one strategy: pattern /secured/madmin/.*, specific origins,
     * credentials_allowed=false (default), no headers_exposed.
     * Covers: onResponse() normal-request path with non-wildcard origin, no credentials.
     *
     * @runInSeparateProcess
     */
    public function testNormalRequestToSecuredCorsRoute()
    {
        $origin = 'http://baidu.com';
        
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => $origin,
            ]
        );
        $response = $client->getResponse();
        // The route is secured, so we may get a 403, but CORS headers should still be set
        // because CORS processing happens in the after middleware regardless of auth result
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertEquals(
            $origin,
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN)
        );
        // Non-wildcard origin, credentials_allowed=false → Vary: Origin
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
        // No credentials in this strategy
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        // Normal request should not have Allow-Methods or Allow-Headers
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        // No headers_exposed in this strategy
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
    }
    
    /**
     * Normal request to a secured CORS route WITHOUT Origin header.
     * Covers: onPreRouting() early return when no Origin → activeStrategy stays null,
     *         onResponse() skips all CORS processing.
     *
     * @runInSeparateProcess
     */
    public function testNormalRequestWithoutOriginToSecuredRoute()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            [],
            [],
            []
        );
        $response = $client->getResponse();
        // No Origin header → no CORS headers at all
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
    }
    
    /**
     * Preflight request with disallowed origin on the advanced (secured) app.
     * Covers: onResponse() preflight path — isOriginAllowed() returns false.
     *
     * @runInSeparateProcess
     */
    public function testPreflightWithDisallowedOriginOnSecuredRoute()
    {
        $client = $this->createClient();
        $client->request(
            'OPTIONS',
            '/secured/madmin/admin',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => '163.com',
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'GET',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
    }
    
    /**
     * Normal request with disallowed origin on the advanced (secured) app.
     * Covers: onResponse() normal-request path — isOriginAllowed() returns false for disallowed origin.
     *
     * @runInSeparateProcess
     */
    public function testNormalRequestWithDisallowedOriginOnSecuredRoute()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            [],
            [],
            [
                "HTTP_" . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => '163.com',
            ]
        );
        $response = $client->getResponse();
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
    }
}
