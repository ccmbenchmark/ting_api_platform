<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Filter\Filter;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\Hydrator;
use Psr\Container\ContainerInterface;

/**
 * @template T of object
 * @template-implements QueryCollectionExtension<T>
 */
final class FilterExtension implements QueryCollectionExtension
{
    public function __construct(private readonly ContainerInterface $filterLocator)
    {
    }

    /** @inheritdoc */
    public function applyToCollection(
        SelectBuilder $queryBuilder,
        Hydrator $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void {
        $resourceFilters = $operation?->getFilters();

        if ($resourceFilters === null || $resourceFilters === []) {
            return;
        }

        foreach ($resourceFilters as $filterId) {
            $filter = $this->filterLocator->has($filterId) ? $this->filterLocator->get($filterId) : null;
            if (! ($filter instanceof Filter)) {
                continue;
            }

            $filter->apply($queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }
}
