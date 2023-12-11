<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Pagination;

use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Repository\Collection;
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
use function str_contains;

/**
 * @template T of object
 * @template-implements IteratorAggregate<int, T>
 */
final class Paginator implements Countable, IteratorAggregate
{
    private int|null $count = null;
    private SelectBuilder|null $countQueryBuilder = null;

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
        $query->setParams($this->queryBuilder->getBindedValues());

        return $query->query($this->repository->getCollection($this->hydrator));
    }

    public function addRequiredWhereInClause(): void
    {
        $distinctIds = $this->getIdsFromDistinctQueryWithLimitAndOffset();
        // Save the current builder as countQueryBuilder for $this->getCountQuery().
        $this->countQueryBuilder = clone $this->queryBuilder;
        $whereInClause = $this->queryBuilder->getRootAlias() . '.' . $this->classMetadata->getIdentifierFieldNames()[0] . ' IN (' . implode(',', $distinctIds) . ')';
        // Make sure query won't return any result if $distinctIds is empty.
        if (empty($distinctIds)) {
            $whereInClause = '1 = 0';
        }
        $this->queryBuilder->where($whereInClause);
        $this->queryBuilder->limit(0);
        $this->queryBuilder->offset(0);
    }

    /**
     * @return array<int>
     */
    private function getIdsFromDistinctQueryWithLimitAndOffset(): array
    {
        $subQueryBuilder = clone $this->queryBuilder;
        $rootAlias = $subQueryBuilder->getRootAlias();
        $subQueryBuilder->select('DISTINCT(' . $rootAlias . '.' . $this->classMetadata->getIdentifierFieldNames()[0]. ') AS id');
        $subQuery = $this->repository->getQuery($subQueryBuilder->getStatement());
        $subQuery->setParams($subQueryBuilder->getBindedValues());
        /** @var Collection<T> $results */
        $results = $subQuery->query($this->repository->getCollection(new HydratorArray()));
        $ids = [];
        /** @var array<int> $result */
        foreach ($results as $result) {
            $ids[] = $result['id'];
        }

        return $ids;
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
        $countBuilder = $this->countQueryBuilder ?? clone $this->queryBuilder;
        $rootAlias    = $countBuilder->getRootAlias();
        $countBuilder
            ->select(
                sprintf(
                    'COUNT(DISTINCT (%s)) AS ting_count',
                    implode(
                        ', ',
                        array_map(
                            static fn (string $field) => "{$rootAlias}.{$field}",
                            $this->classMetadata->getIdentifierFieldNames(),
                        ),
                    ),
                ),
            )
            ->resetOrderBy()
            ->offset(0)
            ->limit(0);
        $this->keepOnlyMandatoryJoinsForCountQuery($countBuilder);
        $countQuery = $this->repository->getQuery($countBuilder->getStatement());
        $countQuery->setParams($countBuilder->getBindedValues());

        return $countQuery;
    }

    private function keepOnlyMandatoryJoinsForCountQuery(SelectBuilder $countBuilder): void
    {
        $initialJoinsArray = $countBuilder->getJoins();
        if ($initialJoinsArray === []) {
            return;
        }
        $countBuilder->resetJoins();
        foreach ($initialJoinsArray as $joins) {
            foreach ($joins as $join) {
                if ($this->joinIsInWhereClauses($join, $countBuilder) || $this->joinIsInWhereInSubQueries($join, $countBuilder) || $join->type === JoinType::INNER_JOIN) {
                    $countBuilder->join($join->type, $join->join, $join->alias, $join->conditionType, $join->condition);
                }
            }
        }
    }

    private function joinIsInWhereClauses(Join $join, SelectBuilder $builder): bool
    {
        $whereClauses = $builder->getWhere();
        foreach ($whereClauses as $whereClause) {
            if (str_contains($whereClause, $join->alias)) {
                return true;
            }
        }

        return false;
    }

    private function joinIsInWhereInSubQueries(Join $join, SelectBuilder $builder): bool
    {
        $whereInSubQueries = $builder->getWhereInSubqueries();
        foreach ($whereInSubQueries as $whereInSubQuery) {
            if (str_contains($whereInSubQuery->property, $join->alias) || str_contains($whereInSubQuery->subQuery->getStatement(), $join->alias)) {
                return true;
            }
        }

        return false;
    }

    public function getQueryBuilder(): SelectBuilder
    {
        return $this->queryBuilder;
    }
}
