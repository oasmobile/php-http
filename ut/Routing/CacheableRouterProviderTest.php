<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlGenerator;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;

class CacheableRouterProviderTest extends TestCase
{
    /** @var string|null */
    private $savedErrorHandler = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore error/exception handlers that Symfony Kernel may have set
        restore_exception_handler();
        parent::tearDown();
    }

    //----------------------------------------------------------------------
    // getConfigDataProvider — throws LogicException before register()
    //----------------------------------------------------------------------

    public function testGetConfigDataProviderThrowsLogicExceptionBeforeRegister()
    {
        $provider = new CacheableRouterProvider();

        $this->expectException(\LogicException::class);
        $provider->getConfigDataProvider();
    }

    //----------------------------------------------------------------------
    // register + getConfigDataProvider — works after register()
    //----------------------------------------------------------------------

    public function testGetConfigDataProviderWorksAfterRegister()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        // boot() triggers routing registration internally
        $kernel->boot();

        // Verify routing config data provider is accessible via kernel
        $this->assertNotNull($kernel->getRoutingConfigDataProvider());
    }

    //----------------------------------------------------------------------
    // register — routing services are available after boot
    //----------------------------------------------------------------------

    public function testRoutingServicesAvailableAfterBoot()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        // After boot with routing config, request matcher and url generator should be available
        $this->assertInstanceOf(GroupUrlMatcher::class, $kernel->getRequestMatcher());
        $this->assertInstanceOf(GroupUrlGenerator::class, $kernel->getUrlGenerator());
        $this->assertNotNull($kernel->getRouter());
    }

    //----------------------------------------------------------------------
    // register — buildRequestMatcher returns GroupUrlMatcher
    //----------------------------------------------------------------------

    public function testBuildRequestMatcherReturnsGroupUrlMatcher()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        $matcher = $kernel->getRequestMatcher();
        $this->assertInstanceOf(GroupUrlMatcher::class, $matcher);
    }

    //----------------------------------------------------------------------
    // register — buildUrlGenerator returns GroupUrlGenerator
    //----------------------------------------------------------------------

    public function testBuildUrlGeneratorReturnsGroupUrlGenerator()
    {
        $routesDir = __DIR__ . '/fixtures';

        $kernel = new MicroKernel(
            [
                'routing' => [
                    'path'      => $routesDir . '/simple.routes.yml',
                    'cache_dir' => 'false',
                ],
            ],
            true
        );

        $kernel->boot();

        $generator = $kernel->getUrlGenerator();
        $this->assertInstanceOf(GroupUrlGenerator::class, $generator);
    }
}
