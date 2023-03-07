<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\Repository\Repository;

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

    public function apply(SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        //Applied by provider
    }
}
