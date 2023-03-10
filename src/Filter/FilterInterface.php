<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Api\FilterInterface as BaseFilterInterface;
use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryInterface;

interface FilterInterface extends BaseFilterInterface
{
    /**
     * @param array<string, array<string, array|string>> $context
     */
    public function apply(QueryInterface&SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void;
}
