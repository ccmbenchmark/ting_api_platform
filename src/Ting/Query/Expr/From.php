<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr;

final class From
{
    /** @param class-string $from */
    public function __construct(
        public readonly string $from,
        public readonly string $alias,
    ) {
    }
}
