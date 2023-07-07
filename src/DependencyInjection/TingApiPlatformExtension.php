<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\DependencyInjection;

use CCMBenchmark\Ting\ApiPlatform\Extension\QueryCollectionExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryItemExtension;
use CCMBenchmark\Ting\ApiPlatform\Filter\AbstractFilter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class TingApiPlatformExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(QueryCollectionExtension::class)
            ->addTag('ting.api_platform.query_extension.collection');
        $container->registerForAutoconfiguration(QueryItemExtension::class)
            ->addTag('ting.api_platform.query_extension.item');
        $container->registerForAutoconfiguration(AbstractFilter::class);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('ting.php');
        //$container->setParameter('ting.api_platform.search_filters_descriptions', include $container->getParameter('kernel.cache_dir') . '/search_filters_descriptions.php');
    }
}
