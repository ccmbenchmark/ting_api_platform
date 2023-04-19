<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query;

use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\From;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Expr\WhereInSubquery;
use CCMBenchmark\Ting\Repository\Repository;
use LogicException;
use RuntimeException;

use function array_flip;
use function array_keys;
use function array_merge;
use function assert;
use function implode;
use function in_array;
use function is_object;
use function preg_replace_callback;
use function sprintf;

final class SelectBuilder
{
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
        return $this->from[0]->alias ?? throw new RuntimeException('No alias was set before invoking getRootAlias().');
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

    /** @return array<string, list<Join>> */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /** @return list<string> */
    public function getOrderBy(): array
    {
        return $this->orderBy;
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

    public function leftJoin(string $parentAlias, string $fieldName, string $alias): self
    {
        return $this->join(JoinType::LEFT_JOIN, $parentAlias, $fieldName, $alias);
    }

    public function innerJoin(string $parentAlias, string $fieldName, string $alias): self
    {
        return $this->join(JoinType::INNER_JOIN, $parentAlias, $fieldName, $alias);
    }

    public function join(JoinType $joinType, string $parentAlias, string $fieldName, string $alias): self
    {
        $this->joins[$this->findRootAlias($alias, $parentAlias)][] = new Join(
            $joinType,
            $parentAlias,
            $fieldName,
            $alias,
        );

        return $this;
    }

    public function where(string $where): self
    {
        $this->where[] = $where;

        return $this;
    }

    public function whereInSubquery(string $alias, string $field, SelectBuilder $subQuery): self
    {
        $this->whereInSubqueries[] = new WhereInSubquery($alias, $field, $subQuery);

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
        return $this->from[0]->class ?? throw new RuntimeException('No alias was set before invoking getRootEntity().');
    }

    /** @return ClassMetadata<object> */
    private function getClassMetadataByAlias(string $alias): ClassMetadata
    {
        if (isset($this->entityClassAliases[$alias])) {
            return $this->getClassMetadata($this->entityClassAliases[$alias]);
        }

        foreach ($this->from as $from) {
            if ($from->alias === $alias) {
                return $this->getClassMetadata($this->entityClassAliases[$alias] = $from->class);
            }
        }

        foreach ($this->joins as $joins) {
            foreach ($joins as $join) {
                if ($join->alias === $alias) {
                    $parentClassMetadata = $this->getClassMetadataByAlias($join->parentAlias);
                    $association         = $parentClassMetadata->getAssociationMapping($join->property);

                    return $this->getClassMetadata($this->entityClassAliases[$alias] = $association['targetEntity']);
                }
            }
        }

        throw new LogicException(sprintf('Alias "%s" doesn\'t belong to the SelectBuilder.', $alias));
    }

    public function getStatement(): string
    {
        $select = $this->managerRegistry->getManagerForClass($this->getRootEntity())->getRepository()->getQueryBuilder(Repository::QUERY_SELECT);
        assert($select instanceof SelectInterface);

        $this->buildSelect($select);
        $this->buildFrom($select);
        $this->buildWhere($select);
        $this->buildOrderBy($select);
        $this->buildOffsetAndLimit($select);

        return $select->getStatement();
    }

    private function buildSelect(SelectInterface $select): void
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
                '#(\w+)\.(\w+)#',
                function (array $matched): string {
                    $metadata = $this->getClassMetadataByAlias($matched[1]);

                    return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
                },
                $spec,
            );
        }

        $select->cols($cols);
    }

    private function buildFrom(SelectInterface $select): void
    {
        foreach ($this->from as $from) {
            $select->from("{$this->getClassMetadata($from->class)->getTableName()} AS {$from->alias}");

            foreach ($this->joins[$from->alias] ?? [] as $join) {
                $parentClassMetadata = $this->getClassMetadataByAlias($join->parentAlias);
                $association         = $parentClassMetadata->getAssociationMapping($join->property);
                $condParts           = [];

                foreach ($association['joinColumns'] as $joinColumn) {
                    $condParts[] = "{$join->parentAlias}.{$joinColumn['sourceName']} = {$join->alias}.{$joinColumn['targetName']}";
                }

                $select->join(
                    match ($join->type) {
                        JoinType::INNER_JOIN => 'INNER',
                        JoinType::LEFT_JOIN => 'LEFT',
                    },
                    "{$association['targetTable']} AS {$join->alias}",
                    implode(' AND ', $condParts),
                );
            }
        }
    }

    private function buildWhere(SelectInterface $select): void
    {
        $conds = preg_replace_callback(
            '#(\w+)\.(\w+)#',
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
            $metadata = $this->getClassMetadataByAlias($whereInSubquery->alias);

            $select->where("{$whereInSubquery->alias}.{$metadata->getColumnName($whereInSubquery->property)} IN ({$whereInSubquery->subQuery->getStatement()})");
        }
    }

    private function buildOrderBy(SelectInterface $select): void
    {
        $specs = preg_replace_callback(
            '#(\w+)\.(\w+)#',
            function (array $matched): string {
                $metadata = $this->getClassMetadataByAlias($matched[1]);

                return "{$matched[1]}.{$metadata->getColumnName($matched[2])}";
            },
            $this->orderBy,
        );

        if ($specs === null) {
            return;
        }

        $select->orderBy($specs);
    }

    private function buildOffsetAndLimit(SelectInterface $select): void
    {
        $select->offset($this->offset)->limit($this->limit ?? 0);
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
                $whereInSubquery->alias,
                $whereInSubquery->property,
                $whereInSubquery->subQuery,
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
