<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function is_int;
use function sprintf;
use function strpos;
use function substr;

/**
 * @template T of object
 * @template-implements QueryCollectionExtension<T>
 */
final class OrderExtension implements QueryCollectionExtension
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly string|null $order = null,
    ) {
    }

    /** @inheritDoc */
    public function applyToCollection(SelectBuilder $queryBuilder, HydratorRelational $hydrator, QueryNameGenerator $queryNameGenerator, string $resourceClass, Operation|null $operation = null, array $context = []): void
    {
        if ($queryBuilder->hasOrderBy()) {
            return;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAlias();

        $metadata = $manager->getClassMetadata();
        $identifiers = $metadata->getIdentifierFieldNames();
        $defaultOrder = $operation?->getOrder() ?? [];

        if ($defaultOrder !== []) {
            foreach ($defaultOrder as $field => $order) {
                if (is_int($field)) {
                    // Default direction
                    $field = $order;
                    $order = 'ASC';
                }

                $pos = strpos($field, '.');
                if ($pos === false) {
                    $field = "{$rootAlias}.{$metadata->getColumnName($field)}";
                } else {
                    $association = $metadata->getAssociationMapping(substr($field, 0, $pos));
                    $manager = $this->managerRegistry->getManagerForClass($association['targetEntity']);
                    if ($manager === null) {
                        continue;
                    }

                    $alias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $rootAlias, $association);
                    $field = sprintf('%s.%s', $alias, $metadata->getColumnName(substr($field, $pos + 1)));
                }

                $queryBuilder->orderBy($field, $order);
            }

            return;
        }

        if ($this->order === null) {
            return;
        }

        foreach ($identifiers as $identifier) {
            $queryBuilder->orderBy("{$rootAlias}.{$identifier}", $this->order);
        }
    }
}
