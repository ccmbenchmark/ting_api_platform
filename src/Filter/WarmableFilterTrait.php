<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use CCMBenchmark\Ting\ApiPlatform\DependencyInjection\FilterDescriptionGetter;

trait WarmableFilterTrait
{
    protected ?array $filtersDescriptions = null;
    protected FilterDescriptionGetter $filterDescriptionGetter;
    public function setFilterDescriptionGetter(FilterDescriptionGetter $filterDescriptionGetter): void
    {
        $this->filterDescriptionGetter = $filterDescriptionGetter;
    }
}
