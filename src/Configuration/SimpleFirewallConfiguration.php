<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 14:48
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SimpleFirewallConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder  = new TreeBuilder();
        $firewall = $builder->root('firewall');
        {
            $firewall->children()->variableNode('pattern')->isRequired();
            $firewall->children()->variableNode('policies')->isRequired();
            $firewall->children()->variableNode('users')->isRequired();
            $firewall->children()->booleanNode('stateless')->defaultValue('false');
            $firewall->children()->variableNode('misc')->defaultValue([]);
        }

        return $builder;
    }
}
