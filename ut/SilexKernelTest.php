<?php

use Oasis\Mlib\Http\SilexKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:01
 */
class SilexKernelTest extends TestCase
{
    public function testCreationWithOkConfig()
    {
        require __DIR__ . '/app.php';
    }
    
    public function testProductionMode()
    {
        $config = [
        
        ];
        $kernel = new SilexKernel($config, false);
        $kernel['resolver'];
    }
    
    public function testCreationWithWrongConfiguration()
    {
        $config = [
            'routing2' => [
                'path'       => __DIR__ . "/routes.yml",
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test',
                ],
            ],
        ];
        
        $this->expectException(InvalidConfigurationException::class);
        
        new SilexKernel($config, true);
    }
    
    public function testSlowRequest()
    {
        $config                        = [
        ];
        $slowCalled                    = false;
        $app                           = new SilexKernel($config, true);
        $app['slow_request_threshold'] = 300;
        $app['slow_request_handler']   = $app->protect(
            function (Request $request, $start, $sent, $end) use (&$slowCalled) {
                $this->assertEquals('/abc', $request->getPathInfo());
                $this->assertLessThan($end, $start);
                $this->assertLessThan($sent, $start);
                $this->assertLessThan($end, $sent);
                $slowCalled = true;
            }
        );
        $app->get(
            '/abc',
            function () {
                usleep(400000);
                
                return new Response('');
            }
        );
        $app->run(Request::create("/abc"));
        $this->assertTrue($slowCalled);
    }
}
