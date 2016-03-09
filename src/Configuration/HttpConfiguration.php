<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 11:13
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class HttpConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $http    = $builder->root('http');
        {
            $http->children()->variableNode('routing');
            $http->children()->variableNode('view_handlers');
            $http->children()->variableNode('error_handlers');
            $http->children()->variableNode('middlewares');
            $http->children()->variableNode('providers');
        }

        return $builder;
    }
}
