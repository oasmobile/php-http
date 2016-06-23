<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-06-23
 * Time: 20:27
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Routing\RouteCollection;

class InheritableRouteCollection extends RouteCollection
{
    public function __construct(RouteCollection $wrapped)
    {
        $this->addCollection($wrapped);
    }

    public function addDefaults(array $defaults)
    {
        if ($defaults) {
            foreach ($this->all() as $route) {
                foreach ($defaults as $key => $val) {
                    if (!$route->hasDefault($key)) {
                        $route->setDefault($key, $val);
                    }
                }
            }
        }
    }
}
