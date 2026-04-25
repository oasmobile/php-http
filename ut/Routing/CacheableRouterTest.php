<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CacheableRouterTest extends TestCase
{
    /**
     * Create a CacheableRouter with a mock loader that returns the given RouteCollection,
     * and a mock MicroKernel with configurable getParameter() behavior.
     *
     * @param RouteCollection $collection
     * @param array           $parameters  key => value map for getParameter()
     *
     * @return CacheableRouter
     */
    private function createRouter(RouteCollection $collection, array $parameters = [])
    {
        $kernel = $this->createStub(MicroKernel::class);
        $kernel->method('getParameter')
               ->willReturnCallback(function ($key) use ($parameters) {
                   return isset($parameters[$key]) ? $parameters[$key] : null;
               });

        $loader = $this->createStub(LoaderInterface::class);
        $loader->method('load')
               ->willReturn($collection);

        // CacheableRouter extends Router; we pass resource as empty string
        // and disable caching to keep tests simple
        $router = new CacheableRouter(
            $kernel,
            $loader,
            '',  // resource
            ['cache_dir' => null]  // disable caching
        );

        return $router;
    }

    //----------------------------------------------------------------------
    // getRouteCollection — replaces %param% placeholders
    //----------------------------------------------------------------------

    public function testGetRouteCollectionReplacesParamPlaceholders()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'config_val'  => '%app.setting%',
        ]));

        $router     = $this->createRouter($collection, ['app.setting' => 'resolved_value']);
        $result     = $router->getRouteCollection();
        $route      = $result->get('test');

        $this->assertSame('resolved_value', $route->getDefault('config_val'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — parameter does not exist, placeholder preserved
    //----------------------------------------------------------------------

    public function testGetRouteCollectionPreservesPlaceholderWhenParameterNotFound()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'missing'     => '%nonexistent.param%',
        ]));

        $router = $this->createRouter($collection, []);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame('%nonexistent.param%', $route->getDefault('missing'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — %% escaping to %
    //----------------------------------------------------------------------

    public function testGetRouteCollectionEscapesDoublePercent()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'escaped'     => '100%%',
        ]));

        $router = $this->createRouter($collection, []);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame('100%', $route->getDefault('escaped'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — replaces only once (isParamReplaced flag)
    //----------------------------------------------------------------------

    public function testGetRouteCollectionReplacesOnlyOnce()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'val'         => '%key%',
        ]));

        $router = $this->createRouter($collection, ['key' => 'first_call']);

        // First call — replacement happens
        $result1 = $router->getRouteCollection();
        $this->assertSame('first_call', $result1->get('test')->getDefault('val'));

        // Second call — should return same collection without re-processing
        // Even if we could change the parameter, it won't re-replace
        $result2 = $router->getRouteCollection();
        $this->assertSame($result1, $result2);
    }

    //----------------------------------------------------------------------
    // getRouteCollection — multiple placeholders in one value
    //----------------------------------------------------------------------

    public function testGetRouteCollectionReplacesMultiplePlaceholdersInOneValue()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'combined'    => '%first%%second%',
        ]));

        $router = $this->createRouter($collection, [
            'first'  => 'hello',
            'second' => 'world',
        ]);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame('helloworld', $route->getDefault('combined'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — non-string default values are skipped
    //----------------------------------------------------------------------

    public function testGetRouteCollectionSkipsNonStringDefaults()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'int_val'     => 42,
            'bool_val'    => true,
            'null_val'    => null,
        ]));

        $router = $this->createRouter($collection, []);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame(42, $route->getDefault('int_val'));
        $this->assertTrue($route->getDefault('bool_val'));
        $this->assertNull($route->getDefault('null_val'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — mixed: some params exist, some don't
    //----------------------------------------------------------------------

    public function testGetRouteCollectionMixedExistingAndMissingParams()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'found'       => '%existing%',
            'not_found'   => '%missing%',
        ]));

        $router = $this->createRouter($collection, ['existing' => 'yes']);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame('yes', $route->getDefault('found'));
        $this->assertSame('%missing%', $route->getDefault('not_found'));
    }

    //----------------------------------------------------------------------
    // getRouteCollection — value with no placeholders is unchanged
    //----------------------------------------------------------------------

    public function testGetRouteCollectionLeavesPlainValuesUnchanged()
    {
        $collection = new RouteCollection();
        $collection->add('test', new Route('/test', [
            '_controller' => 'TestController::action',
            'plain'       => 'no_placeholders_here',
        ]));

        $router = $this->createRouter($collection, []);
        $result = $router->getRouteCollection();
        $route  = $result->get('test');

        $this->assertSame('no_placeholders_here', $route->getDefault('plain'));
    }
}
