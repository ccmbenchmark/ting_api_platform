<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

trait SearchFilterTrait
{
    protected function getType(string|null $tingType): string
    {
        return match ($tingType) {
            'json' => 'array',
            'int' => 'int',
            'bool' => 'bool',
            'datetime' => \DateTime::class,
            'double' => 'float',
            default => 'string',
        };
    }

    protected function normalizeStrategy(mixed $strategy): string
    {
        $allowedValues = [SearchFilter::STRATEGY_EXACT, SearchFilter::STRATEGY_END, SearchFilter::STRATEGY_PARTIAL, SearchFilter::STRATEGY_START, SearchFilter::STRATEGY_WORD_START];
        if (! in_array($strategy, $allowedValues, true)) {
            return SearchFilter::STRATEGY_EXACT;
        }

        return $strategy;
    }
}
