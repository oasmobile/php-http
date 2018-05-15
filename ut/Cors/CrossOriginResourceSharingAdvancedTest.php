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
class CrossOriginResourceSharingAdvancedTest extends WebTestCase
{
    
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
}
