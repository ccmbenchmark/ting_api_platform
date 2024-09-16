<?php

declare(strict_types=1);

use CCMBenchmark\Ting\ApiPlatform\Extension\EagerLoadingExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\FilterEagerLoadingExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\FilterExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\OrderExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\PaginationExtension;
use CCMBenchmark\Ting\ApiPlatform\Filter\BooleanFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\DateFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\EnumFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\ExistsFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\NumericFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\OrderFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\RangeFilter;
use CCMBenchmark\Ting\ApiPlatform\Metadata\Property\TingPropertyMetadataFactory;
use CCMBenchmark\Ting\ApiPlatform\Metadata\Resource\TingLinkFactory;
use CCMBenchmark\Ting\ApiPlatform\Metadata\Resource\TingResourceMetadataCollectionFactory;
use CCMBenchmark\Ting\ApiPlatform\Serializer\Normalizer\GeometryNormalizer;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\State\ItemProvider;
use CCMBenchmark\Ting\ApiPlatform\State\LinksHandler;
use CCMBenchmark\Ting\ApiPlatform\State\PersistProcessor;
use CCMBenchmark\Ting\ApiPlatform\State\RemoveProcessor;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociationFactory;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\NoMetadataAssociationFactory;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadataFactory;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\RepositoryProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(RepositoryProvider::class)
        ->args([
            service('ting'),
            service('ting.metadatarepository'),
        ]);

    $services->set(NoMetadataAssociationFactory::class);
    $services->alias(MetadataAssociationFactory::class, NoMetadataAssociationFactory::class);

    $services->set(ClassMetadataFactory::class)
        ->args([
            service(MetadataAssociationFactory::class),
        ]);
    $services->set(ManagerRegistry::class)
        ->args([
            service(RepositoryProvider::class),
            service(ClassMetadataFactory::class),
        ]);

    $services->set('ting.api_platform.state.persist_processor', PersistProcessor::class)
        ->args([service(ManagerRegistry::class)])
        ->tag('api_platform.state_processor', ['priority' => -100, 'key' => 'ting.api_platform.state.persist_processor'])
        ->tag('api_platform.state_processor', ['priority' => -100, 'key' => PersistProcessor::class]);
    $services->set('ting.api_platform.state.remove_processor', RemoveProcessor::class)
        ->args([service(ManagerRegistry::class)])
        ->tag('api_platform.state_processor', ['priority' => -100, 'key' => 'ting.api_platform.state.remove_processor'])
        ->tag('api_platform.state_processor', ['priority' => -100, 'key' => RemoveProcessor::class]);

    $services->set('ting.api_platform.boolean_filter', BooleanFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
        ]);
    $services->alias(BooleanFilter::class, 'ting.api_platform.boolean_filter');

    $services->set('ting.api_platform.date_filter', DateFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('ting.serializerfactory'),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
        ]);
    $services->alias(DateFilter::class, 'ting.api_platform.date_filter');

    $services->set('ting.api_platform.exists_filter', ExistsFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
            param('api_platform.collection.exists_parameter_name'),
        ]);
    $services->alias(ExistsFilter::class, 'ting.api_platform.exists_filter');

    $services->set('ting.api_platform.numeric_filter', NumericFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
        ]);
    $services->alias(NumericFilter::class, 'ting.api_platform.numeric_filter');

    $services->set('ting.api_platform.order_filter', OrderFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
            param('api_platform.collection.order_parameter_name'),
            param('api_platform.collection.order_nulls_comparison'),
        ]);
    $services->alias(OrderFilter::class, 'ting.api_platform.order_filter');

    $services->set('ting.api_platform.enum_filter', EnumFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid()
        ]);
    $services->alias(EnumFilter::class, 'ting.api_platform.enum_filter');

    $services->set('ting.api_platform.range_filter', RangeFilter::class)
        ->abstract()
        ->args([
            service(ManagerRegistry::class),
            service('logger')->ignoreOnInvalid(),
            null,
            service('api_platform.name_converter')->ignoreOnInvalid(),
        ]);
    $services->alias(RangeFilter::class, 'ting.api_platform.range_filter');

    $services->set('ting.api_platform.extension.query_extension.eager_loading', EagerLoadingExtension::class)
        ->args([
            service(ManagerRegistry::class),
            service('api_platform.metadata.property.name_collection_factory'),
            service('api_platform.metadata.property.metadata_factory'),
            service('serializer.mapping.class_metadata_factory'),
            param('api_platform.eager_loading.max_joins'),
            param('api_platform.eager_loading.fetch_partial'),
        ])
        // After filter_eager_loading
        ->tag('ting.api_platform.query_extension.collection', ['priority' => -18])
        ->tag('ting.api_platform.query_extension.item', ['priority' => -8]);
    $services->set('ting.api_platform.extension.query_extension.filter_eager_loading', FilterEagerLoadingExtension::class)
        ->args([
            service(ManagerRegistry::class),
            service('api_platform.resource_class_resolver')->ignoreOnInvalid(),
        ])
        // Needs to be executed right after the filter extension
        ->tag('ting.api_platform.query_extension.collection', ['priority' => -17]);
    $services->set('ting.api_platform.query_extension.filter', FilterExtension::class)
        ->args([
            service('api_platform.filter_locator'),
        ])
        ->tag('ting.api_platform.query_extension.collection', ['priority' => -16]);
    $services->set('ting.api_platform.query_extension.order', OrderExtension::class)
        ->args([
            service(ManagerRegistry::class),
            param('api_platform.collection.order'),
        ])
        ->tag('ting.api_platform.query_extension.collection', ['priority' => -32]);
    $services->set('ting.api_platform.query_extension.pagination', PaginationExtension::class)
        ->args([
            service(ManagerRegistry::class),
            service('api_platform.pagination'),
        ])
        ->tag('ting.api_platform.query_extension.collection', ['priority' => -64]);

    $services->set(LinksHandler::class)
        ->args([
            service(ManagerRegistry::class),
            service('api_platform.metadata.resource.metadata_collection_factory')->nullOnInvalid(),
        ]);

    $services->set('ting.api_platform.state.collection_provider', CollectionProvider::class)
        ->args([
            service(ManagerRegistry::class),
            service(LinksHandler::class),
            tagged_iterator('ting.api_platform.query_extension.collection'),
        ])
        ->tag('api_platform.state_provider', ['priority' => -100, 'key' => 'ting.api_platform.state.collection_provider'])
        ->tag('api_platform.state_provider', ['priority' => -100, 'key' => CollectionProvider::class]);
    $services->set('ting.api_platform.state.item_provider', ItemProvider::class)
        ->args([
            service(ManagerRegistry::class),
            service(LinksHandler::class),
            tagged_iterator('ting.api_platform.query_extension.item'),
        ])
        ->tag('api_platform.state_provider', ['priority' => -100, 'key' => 'ting.api_platform.state.item_provider'])
        ->tag('api_platform.state_provider', ['priority' => -100, 'key' => ItemProvider::class]);

    $services->set('ting.api_platform.metadata.property.metadata_factory', TingPropertyMetadataFactory::class)
        ->decorate('api_platform.metadata.property.metadata_factory', null, 40)
        ->args([
            service(ManagerRegistry::class),
            service('.inner'),
        ]);

    $services->set('ting.api_platform.metadata.resource.link_factory', TingLinkFactory::class)
        ->decorate('api_platform.metadata.resource.link_factory', null, 40)
        ->args([
            service(ManagerRegistry::class),
            service('api_platform.metadata.property.name_collection_factory'),
            service('api_platform.resource_class_resolver'),
            service('.inner'),
        ]);

    $services->set('ting.api_platform.metadata.resource.metadata_collection_factory', TingResourceMetadataCollectionFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 40)
        ->args([
            service(ManagerRegistry::class),
            service('.inner'),
        ]);

    $services->set(\CCMBenchmark\Ting\ApiPlatform\Warmer\FilterWarmer::class, \CCMBenchmark\Ting\ApiPlatform\Warmer\FilterWarmer::class)
        ->args([
            service('ting.metadatarepository')
        ])
        ->tag('kernel.cache_warmer')
    ;
    $services->set(GeometryNormalizer::class)
        ->tag('serializer.normalizer')
    ;
};
