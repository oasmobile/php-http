<?php
use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Controllers\ExceptionTestController;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-19
 * Time: 11:54
 */
class HttpExceptionTest extends WebTestCase
{
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $config              = [
        ];
        $app                 = new SilexKernel($config, true);
        $app->view_handlers  = [
            new FallbackViewHandler($app, new RouteBasedResponseRendererResolver()),
        ];
        $app->error_handlers = [
            new ExceptionWrapper(),
        ];
        $app->get('/uniq', [new ExceptionTestController(), "throwUniquenessViolationExceptionAction"])
            ->getRoute()
            ->addDefaults(
                [
                    'format' => 'api',
                ]
            );
        
        return $app;
    }
    
    public function testUnqiuessViolationException()
    {
        $client = $this->createClient();
        $client->request(
            'get',
            '/uniq'
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('UniquenessViolationHttpException', $json['exception']['type']);
        $this->assertEquals('something exists!', $json['exception']['message']);
    }
    
}
