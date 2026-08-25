<?php

declare(strict_types=1);

namespace Spyrit\EssentialBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('spyrit_essential');
        $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
