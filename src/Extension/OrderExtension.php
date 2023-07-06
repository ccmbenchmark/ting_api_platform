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
        if ($queryBuilder->getOrderBy() !== []) {
            return;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAlias();

        $metadata     = $manager->getClassMetadata();
        $identifiers  = $metadata->getIdentifierFieldNames();
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
                    $alias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $rootAlias, substr($field, 0, $pos));
                    $field = sprintf('%s.%s', $alias, substr($field, $pos + 1));
                }

                $queryBuilder->orderBy("{$field} {$order}");
            }

            return;
        }

        if ($this->order === null) {
            return;
        }

        foreach ($identifiers as $identifier) {
            $queryBuilder->orderBy("{$rootAlias}.{$identifier} {$this->order}");
        }
    }
}
