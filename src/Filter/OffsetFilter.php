<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryInterface;

final class OffsetFilter extends AbstractFilter implements FilterInterface
{
    public function getDescription(string $resourceClass): array
    {
        return [
            'offset' => [
                'property' => 'offset',
                'type' => 'int',
                'required' => false
            ]
        ];
    }

    public function apply(QueryInterface&SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        //Applied by provider
    }
}
