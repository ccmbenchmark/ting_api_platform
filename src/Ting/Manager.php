<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use Aura\SqlQuery\Common\Select;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\Repository\Repository;

use function assert;

/**
 * @internal
 *
 * @template T of object
 */
final class Manager
{
    /** @param Repository<T> $repository */
    public function __construct(
        private Repository $repository,
        private ClassMetadataFactory $classMetadataFactory,
    ) {
    }

    /** @return ClassMetadata<T> */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadataFactory->getMetadataFor($this->repository);
    }

    public function createQueryBuilder(string $alias): SelectBuilder
    {
        $metadata = $this->getClassMetadata();

        $innerQueryBuilder = $this->repository->getQueryBuilder(Repository::QUERY_SELECT);
        assert($innerQueryBuilder instanceof Select);
        $queryBuilder = new SelectBuilder($innerQueryBuilder);

        $queryBuilder
            ->select($alias, $this->getClassMetadata()->getColumnNames())
            ->from($metadata->getTableName(), $alias);

        return $queryBuilder;
    }

    /** @return Repository<T> */
    public function getRepository(): Repository
    {
        return $this->repository;
    }
}
