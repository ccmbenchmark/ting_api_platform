<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\Repository\HydratorRelational;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @template T of object
 * @template-extends QueryItemExtension<T>
 */
interface QueryResultItemExtension extends QueryItemExtension
{
    /**
     * @param class-string<T>    $resourceClass
     * @param Operation<T>|null  $operation
     * @param ApiPlatformContext $context
     */
    public function supportsResult(string $resourceClass, Operation|null $operation = null, array $context = []): bool;

    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     * @param Operation<T>|null     $operation
     * @param ApiPlatformContext    $context
     *
     * @return T|null
     */
    public function getResult(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): object|null;
}
