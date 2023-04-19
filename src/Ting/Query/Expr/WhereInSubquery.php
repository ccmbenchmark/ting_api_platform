<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr;

use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;

final class WhereInSubquery
{
    public function __construct(
        public readonly string $alias,
        public readonly string $property,
        public readonly SelectBuilder $subQuery,
    ) {
    }
}
