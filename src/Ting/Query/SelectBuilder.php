<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\SubselectInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\From;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\WhereInSubquery;
use CCMBenchmark\Ting\Repository\Repository;
use LogicException;

use function array_flip;
use function array_keys;
use function array_merge;
use function assert;
use function implode;
use function in_array;
use function is_object;
use function preg_replace_callback;
use function sprintf;
use function strpos;
use function substr;

final class SelectBuilder
{
    private const FIELD_REGEX = '#(\w+)\.(\w+)#';

    /** @var list<string> */
    private array $select = [];

    /** @var list<From> */
    private array $from = [];

    /** @var array<string, list<Join>> */
    private array $joins = [];

    /** @var list<string> */
    private array $where = [];

    /** @var list<WhereInSubquery> */
    private array $whereInSubqueries = [];

    /** @var list<string> */
    private array $orderBy = [];

    private int $offset = 0;

    private int|null $limit = null;

    /**
     * Keeps root entity alias names for join entities.
     *
     * @var array<string, string>
     */
    private array $joinRootAliases = [];

    /**
     * Keep a map of aliases to corresponding entity class
     *
     * @var array<string, class-string>
     */
    private array $entityClassAliases = [];

    /** @var array<string, mixed> */
    private array $bindedValues = [];

    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    /** @return list<string> */
    public function getRootAliases(): array
    {
        $aliases = [];
        foreach ($this->from as $from) {
            $aliases[] = $from->alias;
        }

        return $aliases;
    }

    public function getRootAlias(): string
    {
        return $this->from[0]->alias ?? throw new LogicException('No alias was set before invoking getRootAlias().');
    }

    /** @return list<string> */
    public function getAllAliases(): array
    {
        return array_merge($this->getRootAliases(), array_keys($this->joinRootAliases));
    }

    /** @return list<string> */
    public function getSelect(): array
    {
        return $this->select;
    }

    /** @return list<From> */
    public function getFrom(): array
    {
        return $this->from;
    }

    public function resetFrom(): self
    {
        $this->from = [];

        return $this;
    }

    /** @return array<string, list<Join>> */
    public function getJoins(): array
    {
        return $this->joins;
    }

    public function resetJoins(): self
    {
        $this->joins = [];
        $this->joinRootAliases = [];

        return $this;
    }

    /** @return list<string> */
    public function getWhere(): array
    {
        return $this->where;
    }

    /** @param list<string> $where */
    public function setWhere(array $where): self
    {
        $this->where = $where;

        return $this;
    }

    public function resetWhere(): self
    {
        $this->where = [];

        return $this;
    }

    /** @return list<WhereInSubquery> */
    public function getWhereInSubqueries(): array
    {
        return $this->whereInSubqueries;
    }

    /** @param list<WhereInSubquery> $whereInSubqueries */
    public function setWhereInSubqueries(array $whereInSubqueries): self
    {
        $this->whereInSubqueries = $whereInSubqueries;

        return $this;
    }

    public function resetWhereInSubqueries(): self
    {
        $this->whereInSubqueries = [];

        return $this;
    }

    /** @return list<string> */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function resetOrderBy(): self
    {
        $this->orderBy = [];

        return $this;
    }

    public function bindValue(string $name, mixed $value): self
    {
        $this->bindedValues[$name] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getBindedValues(): array
    {
        return $this->bindedValues;
    }

    public function select(string ...$selects): self
    {
        $this->select = [];

        return $this->addSelect(...$selects);
    }

    public function addSelect(string ...$selects): self
    {
        foreach ($selects as $select) {
            $this->select[] = $select;
        }

        return $this;
    }

    /** @param class-string $entity */
    public function from(string $entity, string $alias): self
    {
        $this->from[] = new From($entity, $alias);

        return $this;
    }

    public function leftJoin(string $join, string $alias, ConditionType|null $conditionType = null, string|null $condition = null): self
    {
        return $this->join(JoinType::LEFT_JOIN, $join, $alias, $conditionType, $condition);
    }

    public function innerJoin(string $join, string $alias, ConditionType|null $conditionType = null, string|null $condition = null): self
    {
        return $this->join(JoinType::INNER_JOIN, $join, $alias, $conditionType, $condition);
    }

    public function join(JoinType $joinType, string $join, string $alias, ConditionType|null $conditionType = null, string|null $condition = null): self
    {
        $parentAlias = substr($join, 0, (int) strpos($join, '.'));

        $this->joins[$this->findRootAlias($alias, $parentAlias)][] = new Join(
            $joinType,
            $join,
            $alias,
            $conditionType,
            $condition
        );

        return $this;
    }

    public function where(string $where): self
    {
        $this->where[] = $where;

        return $this;
    }

    public function whereInSubquery(string $property, SelectBuilder $subQuery): self
    {
        $this->whereInSubqueries[] = new WhereInSubquery($property, $subQuery);

        return $this;
    }

    public function orderBy(string $orderBy): self
    {
        $this->orderBy[] = $orderBy;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function limit(int|null $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): int|null
    {
        return $this->limit;
    }

    /** @return class-string */
    private function getRootEntity(): string
    {
        return $this->from[0]->from ?? throw new LogicException('No alias was set before invoking getRootEntity().');
    }

    /** @return ClassMetadata<object> */
    private function getClassMetadataByAlias(string $alias): ClassMetadata
    {
        if (isset($this->entityClassAliases[$alias])) {
            return $this->getClassMetadata($this->entityClassAliases[$alias]);
        }

        foreach ($this->from as $from) {
            if ($from->alias === $alias) {
                return $this->getClassMetadata($this->entityClassAliases[$alias] = $from->from);
            }
        }

        foreach ($this->joins as $joins) {
            foreach ($joins as $join) {
                if ($join->alias === $alias) {
                    $pos = strpos($join->join, '.');
                    if ($pos === false) {
                        return $this->getClassMetadata($this->entityClassAliases[$alias] = $join->join);
                    }

                    $parentAlias = substr($join->join, 0, $pos);
                    $association = substr($join->join, $pos + 1);

                    $parentClassMetadata = $this->getClassMetadataByAlias($parentAlias);
                    $association         = $parentClassMetadata->getAssociationMapping($association);

                    return $this->getClassMetadata($this->entityClassAliases[$alias] = $association['targetEntity']);
                }
            }
        }

        throw new LogicException(sprintf('Alias "%s" doesn\'t belong to the SelectBuilder.', $alias));
    }

    public function getStatement(): string
    {
        return $this->getBuilder()->getStatement();
    }

    private function getBuilder(): SelectInterface&SubselectInterface
    {
        $select = $this->managerRegistry->getManagerForClass($this->getRootEntity())->getRepository()->getQueryBuilder(Repository::QUERY_SELECT);
        assert($select instanceof SelectInterface && $select instanceof SubselectInterface);

        $this
            ->buildSelect($select)
            ->buildFrom($select)
            ->buildWhere($select)
            ->buildOrderBy($select)
            ->buildOffsetAndLimit($select);

        return $select;
    }

    private function buildSelect(SelectInterface $select): self
    {
        $cols    = [];
        $aliases = array_flip($this->getAllAliases());

        foreach ($this->select as $spec) {
            if (isset($aliases[$spec])) {
                foreach ($this->getClassMetadataByAlias($spec)->getColumnNames() as $columnName) {
                    $cols[] = "{$spec}.{$columnName}";
                }

                continue;
            }

            $cols[] = preg_replace_callback(
                self::FIELD_REGEX,
                function (array $matched): string {
                    $metadata = $this->getClassMetadataByAlias($matched[1]);

                    return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
                },
                $spec,
            );
        }

        $select->cols($cols);

        return $this;
    }

    private function buildFrom(SelectInterface $select): self
    {
        foreach ($this->from as $from) {
            $select->from("{$this->getClassMetadata($from->from)->getTableName()} AS {$from->alias}");

            foreach ($this->joins[$from->alias] ?? [] as $join) {
                $pos = strpos($join->join, '.');
                $associationMapping = null;
                $parentAlias = null;

                if ($pos === false) {
                    $joinMetadata = $this->getClassMetadata($join->join);
                    $tableName = $joinMetadata->getTableName();
                } else {
                    $parentAlias         = substr($join->join, 0, $pos);
                    $association         = substr($join->join, $pos + 1);
                    $parentClassMetadata = $this->getClassMetadataByAlias($parentAlias);
                    $associationMapping  = $parentClassMetadata->getAssociationMapping($association);
                    $tableName           = $associationMapping['targetTable'];
                }

                $condParts = [];

                if ($join->conditionType !== ConditionType::ON && $associationMapping !== null) {
                    foreach ($associationMapping['joinColumns'] as $joinColumn) {
                        $condParts[] = "{$parentAlias}.{$joinColumn['sourceName']} = {$join->alias}.{$joinColumn['targetName']}";
                    }
                }

                if ($join->conditionType !== null) {
                    $condParts[] = preg_replace_callback(
                        self::FIELD_REGEX,
                        function (array $matched): string {
                            $metadata = $this->getClassMetadataByAlias($matched[1]);

                            return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
                        },
                        '(' . ($join->condition ?? '') . ')',
                    );
                }

                $select->join(
                    match ($join->type) {
                        JoinType::INNER_JOIN => 'INNER',
                        JoinType::LEFT_JOIN => 'LEFT',
                    },
                    "{$tableName} AS {$join->alias}",
                    implode(' AND ', $condParts),
                );
            }
        }

        return $this;
    }

    private function buildWhere(SelectInterface $select): self
    {
        $conds = preg_replace_callback(
            self::FIELD_REGEX,
            function (array $matched): string {
                $metadata = $this->getClassMetadataByAlias($matched[1]);

                return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
            },
            $this->where,
        );

        foreach ($conds ?? [] as $cond) {
            $select->where($cond);
        }

        foreach ($this->whereInSubqueries as $whereInSubquery) {
            $pos = strpos($whereInSubquery->property, '.');
            if ($pos === false) {
                throw new LogicException('Property is not attached to any alias.');
            }

            $alias = substr($whereInSubquery->property, 0, $pos);
            $property = substr($whereInSubquery->property, $pos + 1);
            $metadata = $this->getClassMetadataByAlias($alias);

            $select->where("{$alias}.{$metadata->getColumnName($property)} IN (?)", $whereInSubquery->subQuery->getBuilder());
        }

        return $this;
    }

    private function buildOrderBy(SelectInterface $select): self
    {
        $specs = preg_replace_callback(
            self::FIELD_REGEX,
            function (array $matched): string {
                $metadata = $this->getClassMetadataByAlias($matched[1]);

                return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
            },
            $this->orderBy,
        );

        if ($specs === null) {
            return $this;
        }

        $select->orderBy($specs);

        return $this;
    }

    private function buildOffsetAndLimit(SelectInterface $select): self
    {
        $select->offset($this->offset)->limit($this->limit ?? 0);

        return $this;
    }

    /**
     * Finds the root entity alias of the joined entity.
     *
     * @param string $alias       The alias of the new join entity
     * @param string $parentAlias The parent entity alias of the join relationship
     */
    private function findRootAlias(string $alias, string $parentAlias): string
    {
        if (in_array($parentAlias, $this->getRootAliases(), true)) {
            $rootAlias = $parentAlias;
        } elseif (isset($this->joinRootAliases[$parentAlias])) {
            $rootAlias = $this->joinRootAliases[$parentAlias];
        } else {
            $rootAlias = $this->getRootAlias();
        }

        $this->joinRootAliases[$alias] = $rootAlias;

        return $rootAlias;
    }

    /**
     * @param class-string<T> $className
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    private function getClassMetadata(string $className): ClassMetadata
    {
        return $this->managerRegistry->getManagerForClass($className)->getClassMetadata();
    }

    public function __clone(): void
    {
        foreach ($this->from as $key => $from) {
            $this->from[$key] = clone $from;
        }

        foreach ($this->joins as $i => $joins) {
            foreach ($joins as $k => $join) {
                $this->joins[$i][$k] = clone $join;
            }
        }

        foreach ($this->whereInSubqueries as $key => $whereInSubquery) {
            $this->whereInSubqueries[$key] = new WhereInSubquery(
                $whereInSubquery->property,
                clone $whereInSubquery->subQuery,
            );
        }

        foreach ($this->bindedValues as $name => $value) {
            if (! is_object($value)) {
                continue;
            }

            $this->bindedValues[$name] = clone $value;
        }
    }
}
