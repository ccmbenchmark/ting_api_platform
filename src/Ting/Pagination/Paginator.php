<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Pagination;

use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorArray;
use CCMBenchmark\Ting\Repository\HydratorInterface;
use CCMBenchmark\Ting\Repository\Repository;
use Countable;
use IteratorAggregate;

use function array_map;
use function array_sum;
use function current;
use function implode;
use function iterator_to_array;
use function sprintf;

/**
 * @template T of object
 * @template-implements IteratorAggregate<int, T>
 */
final class Paginator implements Countable, IteratorAggregate
{
    private int|null $count = null;

    /**
     * @param Repository<T>        $repository
     * @param HydratorInterface<T> $hydrator
     * @param ClassMetadata<T>     $classMetadata
     */
    public function __construct(
        private readonly SelectBuilder $queryBuilder,
        private readonly Repository $repository,
        private readonly HydratorInterface $hydrator,
        private readonly ClassMetadata $classMetadata,
    ) {
    }

    /** @return CollectionInterface<T> */
    public function getIterator(): CollectionInterface
    {
        $query = $this->repository->getQuery($this->queryBuilder->getStatement());
        $query->setParams($this->queryBuilder->getBindValues());

        return $query->query($this->repository->getCollection($this->hydrator));
    }

    public function count(): int
    {
        return $this->count ??= (int) array_sum(array_map(
            current(...),
            iterator_to_array($this->getCountQuery()->query($this->repository->getCollection(new HydratorArray()))),
        ));
    }

    private function getCountQuery(): Query
    {
        $countBuilder = clone $this->queryBuilder;
        $rootAlias = $countBuilder->getRootAlias();
        $countBuilder->resetSelect()->resetJoin()->offset(0)->limit(0);

        $countBuilder->rawSelect(
            sprintf(
                'COUNT(%s) AS ting_count',
                implode(
                    ', ',
                    array_map(
                        static fn (string $column) => "{$rootAlias}.{$column}",
                        $this->classMetadata->getIdentifierColumnNames(),
                    ),
                ),
            ),
        );

        $countQuery = $this->repository->getQuery($countBuilder->getStatement());
        $countQuery->setParams($countBuilder->getBindValues());

        return $countQuery;
    }

    public function getQueryBuilder(): SelectBuilder
    {
        return $this->queryBuilder;
    }
}
