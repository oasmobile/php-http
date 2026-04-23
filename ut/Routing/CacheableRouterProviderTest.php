<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\SilexKernel;
use PHPUnit\Framework\TestCase;

class CacheableRouterProviderTest extends TestCase
{
    //----------------------------------------------------------------------
    // getConfigDataProvider — throws LogicException before register()
    //----------------------------------------------------------------------

    public function testGetConfigDataProviderThrowsLogicExceptionBeforeRegister()
    {
        $provider = new CacheableRouterProvider();

        $this->setExpectedException(\LogicException::class);
        $provider->getConfigDataProvider();
    }

    //----------------------------------------------------------------------
    // register — registers expected services
    //----------------------------------------------------------------------

    public function testRegisterRegistersExpectedServices()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new SilexKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $provider = new CacheableRouterProvider();
        $provider->register($kernel);

        // After register, these service keys should be defined
        $this->assertTrue(isset($kernel['routing.config.data_provider']));
        $this->assertTrue(isset($kernel['routing.config.namespaces']));
        $this->assertTrue(isset($kernel['routing.config.cache_dir']));
        $this->assertTrue(isset($kernel['router']));

        // request_matcher and url_generator are extended (they exist from Silex base)
        $this->assertTrue(isset($kernel['request_matcher']));
        $this->assertTrue(isset($kernel['url_generator']));
    }

    //----------------------------------------------------------------------
    // getConfigDataProvider — works after register()
    //----------------------------------------------------------------------

    public function testGetConfigDataProviderWorksAfterRegister()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new SilexKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $provider = new CacheableRouterProvider();
        $provider->register($kernel);

        $dataProvider = $provider->getConfigDataProvider();
        $this->assertNotNull($dataProvider);
    }
}
