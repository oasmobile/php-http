<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-06-23
 * Time: 20:26
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Routing\Loader\YamlFileLoader;

class InheritableYamlFileLoader extends YamlFileLoader
{
    //protected function parseImport(RouteCollection $collection, array $config, $path, $file)
    //{
    //    $inheritableCollection = new InheritableRouteCollection($collection);
    //
    //    parent::parseImport($inheritableCollection, $config, $path, $file);
    //
    //    $collection->addCollection($inheritableCollection);
    //}
    
    public function import($resource, $type = null, $ignoreErrors = false, $sourceResource = null)
    {
        return new InheritableRouteCollection(parent::import($resource, $type, $ignoreErrors, $sourceResource));
    }
    
}
