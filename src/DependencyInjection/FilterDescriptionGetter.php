<?php

namespace CCMBenchmark\Ting\ApiPlatform\DependencyInjection;

class FilterDescriptionGetter
{
    public function __construct(private string $filePath)
    {

    }

    /**
     * @phpstan-ignore-next-line
     */
    public function getDescriptions(): ?array
    {
        if (!file_exists($this->filePath)) {
            return null;
        }
        $descriptions = include($this->filePath);
        return $descriptions;
    }
}
