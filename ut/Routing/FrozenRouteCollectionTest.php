<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\FrozenRouteCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class FrozenRouteCollectionTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction — copies all routes from wrapped collection
    //----------------------------------------------------------------------

    public function testConstructionCopiesAllRoutes()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a', ['_controller' => 'ControllerA::action']));
        $original->add('route_b', new Route('/b', ['_controller' => 'ControllerB::action']));
        $original->add('route_c', new Route('/c', ['_controller' => 'ControllerC::action']));

        $frozen = new FrozenRouteCollection($original);

        $this->assertCount(3, $frozen->all());
        $this->assertNotNull($frozen->get('route_a'));
        $this->assertNotNull($frozen->get('route_b'));
        $this->assertNotNull($frozen->get('route_c'));
    }

    public function testConstructionPreservesRoutePathsAndDefaults()
    {
        $original = new RouteCollection();
        $original->add('home', new Route('/home', ['_controller' => 'HomeController::index']));

        $frozen = new FrozenRouteCollection($original);

        $this->assertSame('/home', $frozen->get('home')->getPath());
        $this->assertSame('HomeController::index', $frozen->get('home')->getDefault('_controller'));
    }

    public function testConstructionWithEmptyCollection()
    {
        $original = new RouteCollection();
        $frozen   = new FrozenRouteCollection($original);

        $this->assertCount(0, $frozen->all());
    }

    //----------------------------------------------------------------------
    // Write operations — all throw LogicException
    //----------------------------------------------------------------------

    public function testAddThrowsLogicException()
    {
        $frozen = new FrozenRouteCollection(new RouteCollection());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route collection is frozen after boot');

        $frozen->add('new_route', new Route('/new'));
    }

    public function testAddCollectionThrowsLogicException()
    {
        $frozen = new FrozenRouteCollection(new RouteCollection());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route collection is frozen after boot');

        $extra = new RouteCollection();
        $extra->add('extra', new Route('/extra'));
        $frozen->addCollection($extra);
    }

    public function testRemoveThrowsLogicException()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));

        $frozen = new FrozenRouteCollection($original);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route collection is frozen after boot');

        $frozen->remove('route_a');
    }

    public function testAddResourceThrowsLogicException()
    {
        $frozen = new FrozenRouteCollection(new RouteCollection());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route collection is frozen after boot');

        $frozen->addResource(new FileResource(__FILE__));
    }

    //----------------------------------------------------------------------
    // Read-only operations — work normally
    //----------------------------------------------------------------------

    public function testGetReturnsKnownRoute()
    {
        $original = new RouteCollection();
        $route    = new Route('/a', ['_controller' => 'ControllerA::action']);
        $original->add('route_a', $route);

        $frozen = new FrozenRouteCollection($original);

        $result = $frozen->get('route_a');
        $this->assertNotNull($result);
        $this->assertSame('/a', $result->getPath());
        $this->assertSame('ControllerA::action', $result->getDefault('_controller'));
    }

    public function testGetReturnsNullForUnknownRoute()
    {
        $frozen = new FrozenRouteCollection(new RouteCollection());

        $this->assertNull($frozen->get('nonexistent'));
    }

    public function testAllReturnsAllRoutes()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->add('route_b', new Route('/b'));

        $frozen = new FrozenRouteCollection($original);

        $all = $frozen->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('route_a', $all);
        $this->assertArrayHasKey('route_b', $all);
    }

    public function testCountReturnsCorrectNumber()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->add('route_b', new Route('/b'));
        $original->add('route_c', new Route('/c'));

        $frozen = new FrozenRouteCollection($original);

        $this->assertCount(3, $frozen);
    }

    public function testCountReturnsZeroForEmptyCollection()
    {
        $frozen = new FrozenRouteCollection(new RouteCollection());

        $this->assertCount(0, $frozen);
    }

    public function testGetIteratorIsTraversableAndContentCorrect()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->add('route_b', new Route('/b'));

        $frozen = new FrozenRouteCollection($original);

        $names = [];
        foreach ($frozen as $name => $route) {
            $names[] = $name;
            $this->assertInstanceOf(Route::class, $route);
        }

        $this->assertSame(['route_a', 'route_b'], $names);
    }

    public function testGetResourcesReturnsResources()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->addResource(new FileResource(__FILE__));

        $frozen = new FrozenRouteCollection($original);

        $resources = $frozen->getResources();
        $this->assertNotEmpty($resources);
    }
}
