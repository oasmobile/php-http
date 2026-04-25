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
    public function import(mixed $resource, ?string $type = null, bool $ignoreErrors = false, ?string $sourceResource = null, string|array|null $exclude = null): mixed
    {
        return new InheritableRouteCollection(parent::import($resource, $type, $ignoreErrors, $sourceResource, $exclude));
    }
}
