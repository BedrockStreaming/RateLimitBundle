<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bedrock_rate_limit');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('limit_by_route')
                    ->defaultValue(false)
                ->end()
                ->integerNode('limit')
                    ->defaultValue(1000)
                    ->min(0)
                ->end()
                ->integerNode('period')
                    ->defaultValue(60)
                    ->min(0)
                ->end()
                ->booleanNode('display_headers')
                    ->defaultValue(false)
                ->end()
                ->arrayNode('routes')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('limit')->end()
                            ->integerNode('period')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
