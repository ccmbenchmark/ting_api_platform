<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Util;

use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;

final class QueryChecker
{
    private function __construct()
    {
    }

    public static function hasLeftJoin(SelectBuilder $queryBuilder): bool
    {
        foreach ($queryBuilder->getJoins() as $joins) {
            foreach ($joins as $join) {
                if ($join->type === JoinType::LEFT_JOIN) {
                    return true;
                }
            }
        }

        return false;
    }
}
