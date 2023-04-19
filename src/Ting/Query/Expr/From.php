<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr;

final class From
{
    /** @param class-string $class */
    public function __construct(
        public readonly string $class,
        public readonly string $alias,
    ) {
    }
}
