<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 10:26
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class TwigConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('twig');
        $twig    = $builder->getRootNode();
        {
            $twig->children()->scalarNode('template_dir');
            $twig->children()->scalarNode('cache_dir')->defaultValue(null);
            $twig->children()->scalarNode('asset_base')->defaultValue('');
            $twig->children()->variableNode('globals')->defaultValue([]);
            $twig->children()->booleanNode('strict_variables')->defaultTrue();
            $twig->children()
                ->enumNode('auto_reload')
                ->values([true, false, null])
                ->defaultNull()
                ->end();
        }

        return $builder;
    }
}
