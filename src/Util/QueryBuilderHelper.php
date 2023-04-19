<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Util;

use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;

use function implode;
use function sprintf;

final class QueryBuilderHelper
{
    private function __construct()
    {
    }

    public static function addJoinOnce(
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $alias,
        string $association,
        JoinType|null $joinType = null,
    ): string {
        $join = self::getExistingJoin($queryBuilder, $alias, $association);
        if ($join !== null) {
            return $join->alias;
        }

        $associationAlias = $queryNameGenerator->generateJoinAlias($association);

        if ($joinType === JoinType::LEFT_JOIN || QueryChecker::hasLeftJoin($queryBuilder)) {
            $queryBuilder->leftJoin($alias, $association, $associationAlias);
        } else {
            $queryBuilder->innerJoin($alias, $association, $associationAlias);
        }

        return $associationAlias;
    }

    public static function getExistingJoin(
        SelectBuilder $queryBuilder,
        string $alias,
        string $association,
    ): Join|null {
        $parts     = $queryBuilder->getJoins();
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (! isset($parts[$rootAlias])) {
            return null;
        }

        foreach ($parts[$rootAlias] as $join) {
            if ($join->alias === $alias && $join->property === $association) {
                return $join;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    public static function in(SelectBuilder $queryBuilder, string $alias, string $field, array $values, string $parameterPrefix): void
    {
        $parameters = [];
        foreach ($values as $key => $value) {
            $parameters[] = $parameter = sprintf('%s_%d', $parameterPrefix, $key);
            $queryBuilder->bindValue($parameter, $value);
        }

        $queryBuilder->where(sprintf('%s.%s IN (:%s)', $alias, $field, implode(', :', $parameters)));
    }
}
