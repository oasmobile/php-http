<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 15:11
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class CrossOriginResourceSharingConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $cors    = $builder->root('cors');
        {
            $cors->children()->scalarNode('path')->isRequired();
            $cors->children()->variableNode('origins')->beforeNormalization()->ifString()->then(
                function ($v) {
                    return [$v];
                }
            );
            $cors->children()->variableNode('headers');
            $cors->children()->variableNode('headers_exposed');
            $cors->children()->integerNode('max_age')->defaultValue(86400);
            $cors->children()->booleanNode('credentials_allowed')->defaultValue(false);
        }

        return $builder;
    }
}
