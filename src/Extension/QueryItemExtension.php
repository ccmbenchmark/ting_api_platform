<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @template T of object
 */
interface QueryItemExtension
{
    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     * @param array<string, mixed>  $identifiers
     * @param Operation<T>|null     $operation
     * @param ApiPlatformContext    $context
     */
    public function applyToItem(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        Operation|null $operation = null,
        array $context = [],
    ): void;
}
