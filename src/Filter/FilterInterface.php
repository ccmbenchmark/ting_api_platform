<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Api\FilterInterface as BaseFilterInterface;

interface FilterInterface extends BaseFilterInterface
{
    /**
     * @param mixed $value
     */
    public function addClause(string $property, $value): string;
}
