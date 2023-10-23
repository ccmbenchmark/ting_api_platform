<?php

namespace CCMBenchmark\Ting\ApiPlatform\DependencyInjection;

use CCMBenchmark\Ting\ApiPlatform\Warmer\FilterWarmer;
use CCMBenchmark\Ting\ApiPlatform\DependencyInjection\FilterDescriptionGetter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FilterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $warmer = $container->findDefinition(FilterWarmer::class);

        $filterServices = $container->findTaggedServiceIds('api_platform.filter');
        array_walk($filterServices, fn(array &$tagParams, string $serviceId) => $tagParams = new Reference($serviceId));

        $warmer
            ->setArgument('$filterServices', $filterServices)
        ;
        foreach ($filterServices as $serviceId => $reference) {
            $definition = $container->getDefinition($serviceId);

            $id = 'ting.api_platform.filter_description_getter.' . $serviceId;
            $container->setDefinition(
                $id,
                new Definition(FilterDescriptionGetter::class, ['%kernel.cache_dir%/ting_api_platform/search_filters_descriptions_' . $serviceId . '.php'])
            );
            $definition->addMethodCall('setFilterDescriptionGetter', [new Reference($id)]);
        }
    }
}
