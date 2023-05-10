<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\AssociationType;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use LogicException;
use function array_key_exists;
use function array_map;
use function CCMBenchmark\Ting\Safe\preg_replace;
use function count;
use function preg_match;
use function preg_quote;
use function strpos;
use function substr;

/**
 * Fixes filters on OneToMany associations
 * https://github.com/api-platform/core/issues/944.
 *
 * @template T of object
 * @template-implements QueryCollectionExtension<T>
 */
final class FilterEagerLoadingExtension implements QueryCollectionExtension
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceClassResolverInterface|null $resourceClassResolver = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function applyToCollection(SelectBuilder $queryBuilder, HydratorRelational $hydrator, QueryNameGenerator $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = [],): void
    {
        $classMetadata = $this->managerRegistry->getManagerForClass($resourceClass)?->getClassMetadata();

        if ($classMetadata === null || !$this->hasToManyInWhere($queryBuilder, $classMetadata)) {
            return;
        }

        $wherePart = $queryBuilder->getWhere();
        $whereInSubQueryPart = $queryBuilder->getWhereInSubqueries();

        if (!$wherePart && !$whereInSubQueryPart) {
            return;
        }

        $joinParts = $queryBuilder->getJoins();
        $originAlias = $queryBuilder->getRootAlias();

        if (!$joinParts || !isset($joinParts[$originAlias])) {
            return;
        }

        $queryBuilderClone = clone $queryBuilder;
        $queryBuilderClone->resetWhere();

        $identifiers = $this->managerRegistry->getManagerForClass($resourceClass)->getClassMetadata()->getIdentifierFieldNames();
        if (count($identifiers) > 1) {
            throw new LogicException('Composite identifiers are not supported.');
        }

        $replacementAlias = $queryNameGenerator->generateJoinAlias($originAlias);
        $in = $this->getQueryBuilderWithNewAliases($queryBuilder, $queryNameGenerator, $originAlias, $replacementAlias);
        $in->select("$replacementAlias.{$identifiers[0]}");

        $queryBuilderClone->whereInSubquery("$originAlias.{$identifiers[0]}", $in);

        $queryBuilder
            ->resetWhere()
            ->resetWhereInSubqueries()
            ->setWhere($queryBuilderClone->getWhere())
            ->setWhereInSubqueries($queryBuilderClone->getWhereInSubqueries());
    }

    private function getQueryBuilderWithNewAliases(SelectBuilder $queryBuilder, QueryNameGenerator $queryNameGenerator, string $originAlias, string $replacement): SelectBuilder
    {
        $queryBuilderClone = clone $queryBuilder;

        $joinParts = $queryBuilder->getJoins();
        $whereParts = $queryBuilder->getWhere();
        $whereInSubqueryParts = $queryBuilder->getWhereInSubqueries();

        $queryBuilderClone
            ->resetJoins()
            ->resetWhere()
            ->resetWhereInSubqueries()
            ->resetOrderBy();

        $from = $queryBuilderClone->getFrom()[0];
        $queryBuilderClone->resetFrom();
        $queryBuilderClone->from($from->from, $replacement);

        $aliases = ["$originAlias."];
        $replacements = ["$replacement."];

        foreach ($joinParts[$originAlias] as $joinPart) {
            $joinString = preg_replace($this->buildReplacePatterns($aliases), $replacements, $joinPart->join);
            $pos = strpos($joinString, '.');
            if ($pos === false) {
                if ($this->resourceClassResolver === null || $joinPart->condition === null || !$this->resourceClassResolver->isResourceClass($joinString)) {
                    continue;
                }

                $newAlias = $queryNameGenerator->generateJoinAlias($joinPart->alias);
                $aliases[] = "{$joinPart->alias}.";
                $replacements[] = "$newAlias.";
                $condition = preg_replace($this->buildReplacePatterns($aliases), $replacements, $joinPart->condition);

                $queryBuilderClone->join(
                    $joinPart->type,
                    $joinString,
                    $newAlias,
                    $joinPart->conditionType,
                    $condition
                );

                continue;
            }

            $alias = substr($joinString, 0, $pos);
            $association = substr($joinString, $pos + 1);
            $newAlias = $queryNameGenerator->generateJoinAlias($association);
            $aliases[] = "{$joinPart->alias}.";
            $replacements[] = "$newAlias.";

            QueryBuilderHelper::addJoinOnce(
                $queryBuilderClone,
                $queryNameGenerator,
                $alias,
                $association,
                $joinPart->type,
                $originAlias,
                $newAlias
            );
        }

        $replacePatterns = $this->buildReplacePatterns($aliases);
        foreach ($whereParts as $wherePart) {
            $queryBuilderClone->where(preg_replace($replacePatterns, $replacements, $wherePart));
        }
        foreach ($whereInSubqueryParts as $whereInSubqueryPart) {
            $subOriginAlias = $whereInSubqueryPart->subQuery->getRootAlias();
            $subReplacementAlias = $queryNameGenerator->generateJoinAlias($subOriginAlias);
            $newSubQuery = $this->getQueryBuilderWithNewAliases($whereInSubqueryPart->subQuery, $queryNameGenerator, $subOriginAlias, $subReplacementAlias);

            $queryBuilderClone->whereInSubquery(
                preg_replace($replacePatterns, $replacements, $whereInSubqueryPart->property),
                $newSubQuery
            );
        }

        return $queryBuilderClone;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function hasToManyInWhere(SelectBuilder $queryBuilder, ClassMetadata $classMetadata): bool
    {
        $wherePart = $queryBuilder->getWhere();
        $aliasMapping = $this->createAliasMapping($queryBuilder);
        $checked = [];

        foreach ($wherePart as $part) {
            foreach ($aliasMapping as $alias => $fieldPath) {
                if ('' === $fieldPath) {
                    // Root alias
                    continue;
                }

                if (str_contains($part, $alias . '.')) {
                    $fields = explode('.', $fieldPath);
                    $targetEntityClassMetadata = $classMetadata;
                    foreach ($fields as $field) {
                        if ($targetEntityClassMetadata === null || !$targetEntityClassMetadata->hasAssociation($field)) {
                            continue;
                        }

                        $mapping = $targetEntityClassMetadata->getAssociationMapping($field);
                        $fieldId = $field.'-'.$mapping['targetEntity'];
                        if (array_key_exists($fieldId, $checked)) {
                            $targetEntityClassMetadata = $this->managerRegistry->getManagerForClass($mapping['targetEntity'])?->getClassMetadata();
                            continue;
                        }

                        $checked[$fieldId] = true;

                        if ($mapping['type'] === AssociationType::TO_MANY) {
                            return true;
                        }

                        $targetEntityClassMetadata = $this->managerRegistry->getManagerForClass($mapping['targetEntity'])?->getClassMetadata();
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function createAliasMapping(SelectBuilder $queryBuilder): array
    {
        $rootAlias = $queryBuilder->getRootAlias();
        $aliasMapping = [$rootAlias => ''];
        foreach ($queryBuilder->getJoins() as $joins) {
            foreach ($joins as $join) {
                $joinTargetField = $this->getJoinTargetField($join);
                if ($joinTargetField && isset($aliasMapping[$joinTargetField[0]])) {
                    $aliasMapping[$join->alias] = trim($aliasMapping[$joinTargetField[0]].'.'.$joinTargetField[1], '.');
                }
            }
        }

        return $aliasMapping;
    }

    /**
     * @return list<string>
     */
    private function getJoinTargetField(Join $join): array
    {
        $targetField = explode('.', $join->join);
        if ($join->condition !== null && preg_match('#([\w.:]+)\s*=\s*([\w.:]+)#', $join->condition, $matches) === 1) {
            $operand = str_starts_with($matches[1], $join->alias . '.') ? $matches[2] : $matches[1];
            $targetField = explode('.', $operand);
        }

        return $targetField;
    }

    /**
     * @param list<string> $aliases
     * @return list<string>
     */
    private function buildReplacePatterns(array $aliases): array
    {
        return array_map(static fn (string $alias): string => '/\b' . preg_quote($alias, '/') . '/', $aliases);
    }
}
