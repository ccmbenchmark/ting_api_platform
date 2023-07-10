<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\DependencyInjection;

use CCMBenchmark\Ting\ApiPlatform\Extension\QueryCollectionExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryItemExtension;
use CCMBenchmark\Ting\ApiPlatform\Filter\AbstractFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\Filter;
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
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('ting.php');
        $container
            ->registerForAutoconfiguration(AbstractFilter::class)
            ->addTag('ting.api_platform.filter')
        ;

    }
}
