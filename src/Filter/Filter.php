<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

/** @phpstan-import-type ApiPlatformContext from CollectionProvider */
interface Filter extends FilterInterface
{
    /**
     * @param ApiPlatformContext    $context
     * @param HydratorRelational<T> $hydrator
     * @param Operation<T>|null     $operation
     * @param class-string<T>       $resourceClass
     *
     * @template T of object
     */
    public function apply(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void;
}
