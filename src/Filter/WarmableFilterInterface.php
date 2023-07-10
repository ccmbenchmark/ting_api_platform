<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use CCMBenchmark\Ting\ApiPlatform\DependencyInjection\FilterDescriptionGetter;

interface WarmableFilterInterface
{
    public function setFilterDescriptionGetter(FilterDescriptionGetter $filterDescriptionGetter);
}
