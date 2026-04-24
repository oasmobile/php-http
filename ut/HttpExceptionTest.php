<?php
use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
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
    use RouteCacheCleaner;

    protected function setUp(): void
    {
        $this->cleanRouteCache(__DIR__ . '/cache');
        parent::setUp();
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $lazyViewHandler = null;
        $appRef = null;

        $viewHandlerCallable = function ($result, $request) use (&$lazyViewHandler, &$appRef) {
            if ($lazyViewHandler === null) {
                $lazyViewHandler = new FallbackViewHandler($appRef, new RouteBasedResponseRendererResolver());
            }
            return $lazyViewHandler($result, $request);
        };

        $config = [
            'cache_dir' => __DIR__ . '/cache',
            'routing'   => [
                'path'       => __DIR__ . "/exception-test.routes.yml",
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'view_handlers'  => [$viewHandlerCallable],
            'error_handlers' => [new ExceptionWrapper()],
        ];

        $app = new MicroKernel($config, true);
        $appRef = $app;

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
