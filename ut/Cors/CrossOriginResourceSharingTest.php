<?php

namespace Oasis\Mlib\Http\Test\Cors;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Silex\WebTestCase;
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
        $this->assertContains('PUT', $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
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
        $this->assertNotContains('*', $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
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
        $this->assertContains('name', $exposedHeaders);
        $this->assertContains('job', $exposedHeaders);
    }
}
