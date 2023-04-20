<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr;

use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;

final class Join
{
    public function __construct(
        public readonly JoinType $type,
        public readonly string   $join,
        public readonly string   $alias,
    ) {
    }
}
