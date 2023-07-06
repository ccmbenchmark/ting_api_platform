<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\Repository\Repository;

/**
 * @internal
 *
 * @template T of object
 */
final class Manager
{
    /** @var ClassMetadata<T>|null */
    private ClassMetadata|null $classMetadata = null;

    /** @param Repository<T> $repository */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly Repository $repository,
        private readonly ClassMetadataFactory $classMetadataFactory,
    ) {
    }

    /** @return ClassMetadata<T> */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata ??= $this->classMetadataFactory->getMetadataFor($this->repository);
    }

    public function createQueryBuilder(string $alias): SelectBuilder
    {
        $queryBuilder = new SelectBuilder($this->managerRegistry);

        $queryBuilder
            ->select($alias)
            ->from($this->getClassMetadata()->getName(), $alias);

        return $queryBuilder;
    }

    /** @return Repository<T> */
    public function getRepository(): Repository
    {
        return $this->repository;
    }
}
