<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:54
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CacheableRouterConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('routing');
        $routing = $builder->getRootNode();
        {
            $routing->children()->scalarNode('path');
            $routing->children()->scalarNode('cache_dir')->defaultValue(null);
            $routing->children()->variableNode('namespaces')->beforeNormalization()->ifString()->then(
                function ($v) {
                    return [$v];
                }
            );
        }

        return $builder;
    }
}
