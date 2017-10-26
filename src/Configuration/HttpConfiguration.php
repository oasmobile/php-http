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
            $http->children()->scalarNode('cache_dir')->defaultValue(null);
            $http->children()->booleanNode('behind_elb')->defaultValue(false);
            $http->children()->booleanNode('trust_cloudfront_ips')->defaultValue(false);
            $http->children()->variableNode('trusted_proxies');
            $http->children()->variableNode('trusted_header_set');
            $http->children()->variableNode('routing');
            $http->children()->variableNode('twig');
            $http->children()->variableNode('security');
            $http->children()->variableNode('cors');
            $http->children()->variableNode('view_handlers');
            $http->children()->variableNode('error_handlers');
            $http->children()->variableNode('injected_args');
            $http->children()->variableNode('middlewares');
            $http->children()->variableNode('providers');
        }
        
        return $builder;
    }
}
