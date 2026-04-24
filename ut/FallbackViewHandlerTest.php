<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 17:34
 */

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Oasis\Mlib\Http\Views\FallbackViewHandler;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FallbackViewHandlerTest extends WebTestCase
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
     * FallbackViewHandler needs the kernel instance at construction time.
     * We use a lazy callable wrapper that defers FallbackViewHandler creation
     * until the first invocation, at which point $app is fully initialized.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        // Use a lazy wrapper: the FallbackViewHandler is created on first call
        $lazyViewHandler = null;
        $appRef = null;

        $viewHandlerCallable = function ($result, $request) use (&$lazyViewHandler, &$appRef) {
            if ($lazyViewHandler === null) {
                $lazyViewHandler = new FallbackViewHandler($appRef, new RouteBasedResponseRendererResolver());
            }
            return $lazyViewHandler($result, $request);
        };

        $config = [
            'cache_dir'      => __DIR__ . '/cache',
            'routing'        => [
                'path'       => __DIR__ . "/fallback-test.routes.yml",
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
    
    public function testPanelOk()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/panel/ok'
        );
        $response = $client->getResponse();
        $this->assertEquals("Hello world!", $response->getContent());
    }
    
    public function testPanelError()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/panel/error'
        );
        $response = $client->getResponse();
        $this->assertTrue(preg_match("/RuntimeException/", $response->getContent()) > 0);
        $this->assertTrue(preg_match("/code.*:.*500/", $response->getContent()) > 0);
    }
    
    public function testApiOk()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/api/ok'
        );
        $response = $client->getResponse();
        $this->assertEquals(json_encode(["result" => "Hello world!"]), $response->getContent());
    }
    
    public function testApiError()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/api/error'
        );
        $response = $client->getResponse();
        $json     = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals(500, $json['code']);
        $this->assertEquals('RuntimeException', $json['exception']['type']);
        $this->assertEquals('Oops!', $json['exception']['message']);
    }
}
