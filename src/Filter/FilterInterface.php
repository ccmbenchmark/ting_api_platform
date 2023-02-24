<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Core\Api\FilterInterface as BaseFilterInterface;

interface FilterInterface extends BaseFilterInterface
{
    /**
     * @param string $property
     * @param mixed  $value
     * @param string $resourceClass
     * @return string
     */
    public function addClause(string $property, $value, string $resourceClass): string;
}
