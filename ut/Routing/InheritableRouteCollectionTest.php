<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\InheritableRouteCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class InheritableRouteCollectionTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction — copies all routes from wrapped collection
    //----------------------------------------------------------------------

    public function testConstructionCopiesAllRoutes()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->add('route_b', new Route('/b'));
        $original->add('route_c', new Route('/c'));

        $inheritable = new InheritableRouteCollection($original);

        $this->assertCount(3, $inheritable->all());
        $this->assertNotNull($inheritable->get('route_a'));
        $this->assertNotNull($inheritable->get('route_b'));
        $this->assertNotNull($inheritable->get('route_c'));
    }

    public function testConstructionWithEmptyCollection()
    {
        $original    = new RouteCollection();
        $inheritable = new InheritableRouteCollection($original);

        $this->assertCount(0, $inheritable->all());
    }

    public function testConstructionPreservesRoutePaths()
    {
        $original = new RouteCollection();
        $original->add('home', new Route('/home'));
        $original->add('about', new Route('/about'));

        $inheritable = new InheritableRouteCollection($original);

        $this->assertSame('/home', $inheritable->get('home')->getPath());
        $this->assertSame('/about', $inheritable->get('about')->getPath());
    }

    //----------------------------------------------------------------------
    // addDefaults — adds default values for routes without specified defaults
    //----------------------------------------------------------------------

    public function testAddDefaultsSetsDefaultsOnRoutesWithoutThem()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a'));
        $original->add('route_b', new Route('/b'));

        $inheritable = new InheritableRouteCollection($original);
        $inheritable->addDefaults(['_format' => 'json', 'version' => '1']);

        $this->assertSame('json', $inheritable->get('route_a')->getDefault('_format'));
        $this->assertSame('1', $inheritable->get('route_a')->getDefault('version'));
        $this->assertSame('json', $inheritable->get('route_b')->getDefault('_format'));
        $this->assertSame('1', $inheritable->get('route_b')->getDefault('version'));
    }

    //----------------------------------------------------------------------
    // addDefaults — does not overwrite existing default values
    //----------------------------------------------------------------------

    public function testAddDefaultsDoesNotOverwriteExistingDefaults()
    {
        $original = new RouteCollection();
        $route    = new Route('/a', ['_format' => 'html', 'existing' => 'keep']);
        $original->add('route_a', $route);

        $inheritable = new InheritableRouteCollection($original);
        $inheritable->addDefaults([
            '_format' => 'json',   // should NOT overwrite 'html'
            'existing' => 'new',   // should NOT overwrite 'keep'
            'added'    => 'value', // should be added
        ]);

        $this->assertSame('html', $inheritable->get('route_a')->getDefault('_format'));
        $this->assertSame('keep', $inheritable->get('route_a')->getDefault('existing'));
        $this->assertSame('value', $inheritable->get('route_a')->getDefault('added'));
    }

    //----------------------------------------------------------------------
    // addDefaults — mixed routes (some with, some without defaults)
    //----------------------------------------------------------------------

    public function testAddDefaultsMixedRoutes()
    {
        $original = new RouteCollection();
        $original->add('with_default', new Route('/a', ['_format' => 'xml']));
        $original->add('without_default', new Route('/b'));

        $inheritable = new InheritableRouteCollection($original);
        $inheritable->addDefaults(['_format' => 'json']);

        // Route with existing default keeps it
        $this->assertSame('xml', $inheritable->get('with_default')->getDefault('_format'));
        // Route without default gets the new one
        $this->assertSame('json', $inheritable->get('without_default')->getDefault('_format'));
    }

    //----------------------------------------------------------------------
    // addDefaults — empty defaults array is a no-op
    //----------------------------------------------------------------------

    public function testAddDefaultsWithEmptyArrayIsNoOp()
    {
        $original = new RouteCollection();
        $original->add('route_a', new Route('/a', ['_format' => 'html']));

        $inheritable = new InheritableRouteCollection($original);
        $inheritable->addDefaults([]);

        $this->assertSame('html', $inheritable->get('route_a')->getDefault('_format'));
    }
}
