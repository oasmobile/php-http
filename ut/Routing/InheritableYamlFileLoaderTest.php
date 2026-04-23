<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\InheritableRouteCollection;
use Oasis\Mlib\Http\ServiceProviders\Routing\InheritableYamlFileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;

class InheritableYamlFileLoaderTest extends TestCase
{
    //----------------------------------------------------------------------
    // import — returns InheritableRouteCollection
    //----------------------------------------------------------------------

    public function testImportReturnsInheritableRouteCollection()
    {
        $fixtureDir = __DIR__ . '/fixtures';
        $locator    = new FileLocator([$fixtureDir]);
        $loader     = new InheritableYamlFileLoader($locator);

        $result = $loader->import('simple.routes.yml');

        $this->assertInstanceOf(InheritableRouteCollection::class, $result);
    }

    public function testImportedCollectionContainsRoutes()
    {
        $fixtureDir = __DIR__ . '/fixtures';
        $locator    = new FileLocator([$fixtureDir]);
        $loader     = new InheritableYamlFileLoader($locator);

        /** @var InheritableRouteCollection $collection */
        $collection = $loader->import('simple.routes.yml');

        $routes = $collection->all();
        $this->assertArrayHasKey('simple.home', $routes);
        $this->assertArrayHasKey('simple.about', $routes);
        $this->assertSame('/', $routes['simple.home']->getPath());
        $this->assertSame('/about', $routes['simple.about']->getPath());
    }
}
