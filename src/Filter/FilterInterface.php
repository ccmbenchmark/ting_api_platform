<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Api\FilterInterface as BaseFilterInterface;
use Aura\SqlQuery\Common\SelectInterface;
use ApiPlatform\Metadata\Operation;

interface FilterInterface extends BaseFilterInterface
{
    /**
     * @param array<string, array<string, array|string>> $context
     */
    public function apply(SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void;
}
