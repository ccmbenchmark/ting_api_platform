<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Util;

use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;

use function implode;
use function sprintf;

/** @phpstan-import-type AssociationMapping from MetadataAssociation */
final class QueryBuilderHelper
{
    private function __construct()
    {
    }

    /** @param AssociationMapping $association */
    public static function addJoinOnce(
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $alias,
        array $association,
        Join|null $joinType = null,
    ): string {
        $join = self::getExistingJoin($queryBuilder, $alias, $association['fieldName']);
        if ($join !== null) {
            return $join['alias'];
        }

        $associationAlias = $queryNameGenerator->generateJoinAlias($association['fieldName']);

        if ($joinType === Join::LEFT_JOIN || QueryChecker::hasLeftJoin($queryBuilder)) {
            $queryBuilder->leftJoin($alias, $association['fieldName'], $association['targetTable'], $associationAlias, $association['joinColumns']);
        } else {
            $queryBuilder->innerJoin($alias, $association['fieldName'], $association['targetTable'], $associationAlias, $association['joinColumns']);
        }

        return $associationAlias;
    }

    /** @return array{type: Join, fieldName: string, alias: string}|null */
    public static function getExistingJoin(
        SelectBuilder $queryBuilder,
        string $alias,
        string $fieldName,
    ): array|null {
        $parts = $queryBuilder->getJoin()[$alias] ?? [];

        foreach ($parts as $part) {
            if ($part['fieldName'] === $fieldName) {
                return $part;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    public static function in(SelectBuilder $queryBuilder, string $alias, string $column, array $values, string $parameterPrefix): void
    {
        $parameters = [];
        foreach ($values as $key => $value) {
            $parameter = sprintf('%s_%d', $parameterPrefix, $key);
            $queryBuilder->bindValue($parameter, $value);
            $parameters[] = ':' . $parameter;
        }

        $queryBuilder->where(sprintf('%s.%s IN (%s)', $alias, $column, implode(', ', $parameters)));
    }
}
