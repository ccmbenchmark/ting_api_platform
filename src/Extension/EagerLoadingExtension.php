<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Exception\PropertyNotFoundException;
use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use CCMBenchmark\Ting\ApiPlatform\Mapping\ClassMetadataInfo;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\AssociationType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateFrom;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateTo;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use CCMBenchmark\Ting\Repository\Hydrator\RelationOne;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function in_array;
use function ucfirst;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @phpstan-import-type AssociationMapping from MetadataAssociation
 * @template T of object
 * @template-implements QueryCollectionExtension<T>
 * @template-implements QueryItemExtension<T>
 */
final class EagerLoadingExtension implements QueryCollectionExtension, QueryItemExtension
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly ClassMetadataFactoryInterface|null $classMetadataFactory = null,
        private readonly int $maxJoins = 30,
        private readonly bool $fetchPartial = false,
        private readonly bool $forceEager = true,
    ) {
    }

    /** @inheritDoc */
    public function applyToCollection(SelectBuilder $queryBuilder, HydratorRelational $hydrator, QueryNameGenerator $queryNameGenerator, string $resourceClass, Operation|null $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    /** @inheritdoc */
    public function applyToItem(SelectBuilder $queryBuilder, HydratorRelational $hydrator, QueryNameGenerator $queryNameGenerator, string $resourceClass, array $identifiers, Operation|null $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    /**
     * @param HydratorRelational<T> $hydratorRelational
     * @param class-string<T>       $resourceClass
     * @param Operation<T>|null     $operation
     * @param ApiPlatformContext    $context
     */
    private function apply(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydratorRelational,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation,
        array $context,
    ): void {
        $options = [];

        $forceEager = $operation?->getForceEager() ?? $this->forceEager;
        $fetchPartial = $operation?->getFetchPartial() ?? $this->fetchPartial;

        if (! isset($context['groups']) && ! isset($context['attributes'])) {
            $contextType = isset($context['api_denormalize']) ? 'denormalization_context' : 'normalization_context';
            if ($operation) {
                $context += $contextType === 'denormalization_context' ? ($operation->getDenormalizationContext() ?? []) : ($operation->getNormalizationContext() ?? []);
            }
        }

        if (! empty($context[AbstractNormalizer::GROUPS])) {
            $options['serializer_groups'] = (array) $context[AbstractNormalizer::GROUPS];
        }

        if ($operation && $normalizationGroups = $operation->getNormalizationContext()['groups'] ?? null) {
            $options['normalization_groups'] = $normalizationGroups;
        }

        if ($operation && $denormalizationGroups = $operation->getDenormalizationContext()['groups'] ?? null) {
            $options['denormalization_groups'] = $denormalizationGroups;
        }

        $this->joinRelations(
            $queryBuilder,
            $hydratorRelational,
            $queryNameGenerator,
            $resourceClass,
            $forceEager,
            $fetchPartial,
            $queryBuilder->getRootAlias(),
            $options,
            $context,
        );
    }

    /**
     * @param HydratorRelational<T>   $hydratorRelational
     * @param class-string            $resourceClass
     * @param AssociationMapping|null $parentAssociation
     */
    private function joinRelations(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydratorRelational,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        bool $forceEager,
        bool $fetchPartial,
        string $parentAlias,
        array $options = [],
        array $normalizationContext = [],
        bool $wasLeftJoin = false,
        int &$joinCount = 0,
        int|null $currentDepth = null,
        array|null $parentAssociation = null,
    ): void {
        if ($joinCount > $this->maxJoins) {
            throw new RuntimeException('The total number of joined relations has exceeded the specified maximum. Raise the limit if necessary with the "api_platform.eager_loading.max_joins" configuration key (https://api-platform.com/docs/core/performance/#eager-loading), or limit the maximum serialization depth using the "enable_max_depth" option of the Symfony serializer (https://symfony.com/doc/current/components/serializer.html#handling-serialization-depth).');
        }

        $currentDepth = $currentDepth > 0 ? $currentDepth - 1 : $currentDepth;
        $metadata     = $this->managerRegistry->getManagerForClass($resourceClass)?->getClassMetadata();
        if ($metadata === null) {
            return;
        }

        $attributesMetadata = $this->classMetadataFactory?->getMetadataFor($resourceClass)->getAttributesMetadata();

        foreach ($metadata->getAssociationMappings() as $association) {
            if ($currentDepth === 0 && ($normalizationContext[AbstractObjectNormalizer::ENABLE_MAX_DEPTH] ?? false)) {
                continue;
            }

            try {
                $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $association['fieldName'], $options);
            } catch (PropertyNotFoundException) {
                // skip properties not found
                continue;
                // @phpstan-ignore-next-line indeed this can be thrown by the SerializerPropertyMetadataFactory
            } catch (ResourceClassNotFoundException) {
                // skip associations that are not resource classes
                continue;
            }

            if (!isset($association['fetch'])) {
                $association['fetch'] = null;
            }

            if (
                // Always skip extra lazy associations
                ClassMetadataInfo::FETCH_EXTRA_LAZY === $association['fetch']
                // We don't want to interfere with ting on this association
                || (false === $forceEager && ClassMetadataInfo::FETCH_EAGER !== $association['fetch'])
            ) {
                continue;
            }

            $childNormalizationContext = $normalizationContext;
            if (isset($normalizationContext[AbstractNormalizer::ATTRIBUTES])) {
                if ($inAttributes = isset($normalizationContext[AbstractNormalizer::ATTRIBUTES][$association['fieldName']])) {
                    $childNormalizationContext[AbstractNormalizer::ATTRIBUTES] = $normalizationContext[AbstractNormalizer::ATTRIBUTES][$association['fieldName']];
                }
            } else {
                $inAttributes = null;
            }

            $fetchEager = $propertyMetadata->getFetchEager();

            if (false === $fetchEager) {
                continue;
            }

            if (true !== $fetchEager && (false === $inAttributes || false === $propertyMetadata->isReadable())) {
                continue;
            }

            // Don't interfere with API Platform
            if ($inAttributes === true && $association['type'] === AssociationType::TO_MANY) {
                continue;
            }

            // Avoid joining back to the parent that we just came from, but only on *ToOne relations
            if (
                $parentAssociation !== null
                && $association['inversedBy'] === $parentAssociation['fieldName']
                && $association['type'] === AssociationType::TO_ONE
            ) {
                continue;
            }

            $targetMetadata = $this->managerRegistry->getManagerForClass($association['targetEntity'])?->getClassMetadata();
            if ($targetMetadata === null) {
                continue;
            }

            $existingJoin = QueryBuilderHelper::getExistingJoin($queryBuilder, $parentAlias, $association['fieldName']);
            if ($existingJoin === null) {
                $isLeftJoin = ! $wasLeftJoin || $association['nullable'];

                $associationAlias = $queryNameGenerator->generateJoinAlias($association['fieldName']);
                $queryBuilder->join(
                    $isLeftJoin ? JoinType::LEFT_JOIN : JoinType::INNER_JOIN,
                    "$parentAlias.{$association['fieldName']}",
                    $associationAlias,
                );

                ++$joinCount;
            } else {
                $associationAlias = $existingJoin->alias;
                $isLeftJoin       = $existingJoin->type === JoinType::LEFT_JOIN;
            }

            if ($fetchPartial === true) {
                $this->addSelect(
                    $queryBuilder,
                    $association['targetEntity'],
                    $associationAlias,
                    $options,
                );
            } else {
                $this->addSelectOnce(
                    $queryBuilder,
                    $associationAlias,
                );
            }

            $hydratorRelational->addRelation(
                match ($association['type']) {
                    AssociationType::TO_ONE => new RelationOne(
                        new AggregateFrom($associationAlias),
                        new AggregateTo($parentAlias),
                        'set' . ucfirst($association['fieldName']),
                    ),
                    AssociationType::TO_MANY => new RelationMany(
                        new AggregateFrom($associationAlias),
                        new AggregateTo($parentAlias),
                        'set' . ucfirst($association['fieldName']),
                    ),
                },
            );

            // Avoid recursive joins for self-referencing relations
            if ($association['targetEntity'] === $resourceClass) {
                continue;
            }

            // Only join the relation's relations recursively if it's in attributes or if it's a readableLink
            if ($inAttributes !== true && $propertyMetadata->isReadableLink() !== true) {
                continue;
            }

            if (isset($attributesMetadata[$association['fieldName']])) {
                $maxDepth = $attributesMetadata[$association['fieldName']]->getMaxDepth();

                // The current depth is the lowest max depth available in the ancestor tree.
                if ($maxDepth !== null && ($currentDepth === null || $maxDepth < $currentDepth)) {
                    $currentDepth = $maxDepth;
                }
            }

            $this->joinRelations(
                $queryBuilder,
                $hydratorRelational,
                $queryNameGenerator,
                $association['targetEntity'],
                $forceEager,
                $fetchPartial,
                $associationAlias,
                $options,
                $childNormalizationContext,
                $isLeftJoin,
                $joinCount,
                $currentDepth,
                $association,
            );
        }
    }

    /**
     * @param class-string<U>    $entity
     *
     * @template U of object
     */
    private function addSelect(SelectBuilder $queryBuilder, string $entity, string $associationAlias, array $propertyMetadataOptions): void
    {
        $metadata = $this->managerRegistry->getManagerForClass($entity)->getClassMetadata();

        foreach ($this->propertyNameCollectionFactory->create($entity) as $property) {
            $propertyMetadata = $this->propertyMetadataFactory->create($entity, $property, $propertyMetadataOptions);

            if ($propertyMetadata->isIdentifier() === true) {
                $queryBuilder->addSelect("{$associationAlias}.{$property}");

                continue;
            }

            if ($metadata->hasField($property) && ($propertyMetadata->isFetchable() === true || $propertyMetadata->isReadable())) {
                $queryBuilder->addSelect("{$associationAlias}.{$property}");
            }
        }
    }

    private function addSelectOnce(SelectBuilder $queryBuilder, string $alias): void
    {
        if (in_array($alias, $queryBuilder->getSelect(), true)) {
            return;
        }

        $queryBuilder->addSelect($alias);
    }
}
