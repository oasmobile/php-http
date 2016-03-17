<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-10
 * Time: 15:03
 */

namespace Oasis\Mlib\Http\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SecurityConfiguration implements ConfigurationInterface
{
    
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder  = new TreeBuilder();
        $security = $builder->root('security');
        {
            /** @var ArrayNodeDefinition $policies */
            $policies = $security->children()->arrayNode('policies');
            {
                $policies->prototype('variable');
            }
            /** @var ArrayNodeDefinition $firewalls */
            $firewalls = $security->children()->arrayNode('firewalls');
            {
                $firewalls->prototype('variable');
            }
            /** @var ArrayNodeDefinition $accessRules */
            $accessRules = $security->children()->arrayNode('access_rules');
            {
                $accessRules->prototype('variable');
            }
            $roleHierarchy = $security->children()->arrayNode('role_hierarchy');
            {
                $roleHierarchy->prototype('variable')->beforeNormalization()->ifString()->then(
                    function ($v) {
                        return [$v];
                    }
                );
            }
        }

        return $builder;
    }
}
