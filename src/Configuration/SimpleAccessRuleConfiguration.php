<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 17:45
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SimpleAccessRuleConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $rule    = $builder->root('rule');
        {
            $rule->children()->variableNode('pattern')->isRequired();
            $rule->children()->variableNode('roles')->isRequired()->beforeNormalization()->ifString()->then(
                function ($v) {
                    return [$v];
                }
            )->end();
            $rule->children()->enumNode('channel')->values([null, 'http', 'https'])->defaultValue(null);

        }

        return $builder;
    }
}
