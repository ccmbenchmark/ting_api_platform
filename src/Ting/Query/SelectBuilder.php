<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query;

use Aura\SqlQuery\Common\Select;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use RuntimeException;

use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function implode;

/**
 * À deux doigts d'inventer le Ting Query Language
 *
 * @phpstan-import-type JoinColumnData from MetadataAssociation
 */
final class SelectBuilder
{
    /** @var array<string, list<string>> */
    private array $select = [];
    /** @var list<array{from: string, alias: string}> */
    private array $from = [];
    /** @var array<string, list<array{type: Join, fieldName: string, alias: string}>> */
    private array $join = [];
    private bool $hasOrderBy = false;
    private int $offset = 0;
    private int|null $limit = null;

    public function __construct(private Select $selectBuilder)
    {
    }

    /** @param list<string> $columns */
    public function select(string $alias, array $columns): self
    {
        $this->selectBuilder->cols(
            array_map(
                static function (string $columnName) use ($alias): string {
                    return "{$alias}.{$columnName}";
                },
                $columns,
            ),
        );
        $this->select[$alias] = array_values(array_unique(array_merge(
            $this->parts['select'][$alias] ?? [],
            $columns,
        )));

        return $this;
    }

    public function rawSelect(string $spec): self
    {
        $this->selectBuilder->cols([$spec]);

        return $this;
    }

    /** @return array<string, list<string>> */
    public function getSelect(): array
    {
        return $this->select;
    }

    /** @return array<string, list<array{type: Join, fieldName: string, alias: string}>> */
    public function getJoin(): array
    {
        return $this->join;
    }

    public function from(string $from, string $alias): self
    {
        $this->selectBuilder->from("$from AS $alias");
        $this->from[] = ['from' => $from, 'alias' => $alias];

        return $this;
    }

    public function getRootAlias(): string
    {
        return $this->from[0]['alias'] ?? throw new RuntimeException('No alias was set before invoking getRootAlias().');
    }

    /** @param list<JoinColumnData> $joinColumns */
    public function innerJoin(string $sourceAlias, string $sourceFieldName, string $targetTable, string $targetAlias, array $joinColumns): self
    {
        return $this->join(Join::INNER_JOIN, $sourceAlias, $sourceFieldName, $targetTable, $targetAlias, $joinColumns);
    }

    /** @param list<JoinColumnData> $joinColumns */
    public function leftJoin(string $sourceAlias, string $sourceFieldName, string $targetTable, string $targetAlias, array $joinColumns): self
    {
        return $this->join(Join::LEFT_JOIN, $sourceAlias, $sourceFieldName, $targetTable, $targetAlias, $joinColumns);
    }

    /** @param list<JoinColumnData> $joinColumns */
    public function join(Join $joinType, string $sourceAlias, string $sourceFieldName, string $targetTable, string $targetAlias, array $joinColumns): self
    {
        $condition = implode(
            ' AND ',
            array_map(
                static function (array $joinColumn) use ($sourceAlias, $targetAlias): string {
                    return "{$sourceAlias}.{$joinColumn['sourceName']} = {$targetAlias}.{$joinColumn['targetName']}";
                },
                $joinColumns,
            ),
        );

        $this->selectBuilder->join(
            match ($joinType) {
                Join::INNER_JOIN => 'INNER',
                Join::LEFT_JOIN => 'LEFT',
            },
            "$targetTable AS $targetAlias",
            $condition,
        );
        $this->join[$sourceAlias][] = ['type' => $joinType, 'fieldName' => $sourceFieldName, 'alias' => $targetAlias];

        return $this;
    }

    public function where(string $cond): self
    {
        $this->selectBuilder->where($cond);

        return $this;
    }

    public function orderBy(string $column, string $direction): self
    {
        $this->selectBuilder->orderBy(["{$column} {$direction}"]);
        $this->hasOrderBy = true;

        return $this;
    }

    public function hasOrderBy(): bool
    {
        return $this->hasOrderBy;
    }

    public function offset(int $offset): self
    {
        $this->selectBuilder->offset($this->offset = $offset);

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function limit(int|null $limit): self
    {
        $this->selectBuilder->limit($this->limit = $limit ?? 0);

        return $this;
    }

    public function getLimit(): int|null
    {
        return $this->limit;
    }

    public function bindValue(string $name, mixed $value): self
    {
        $this->selectBuilder->bindValue($name, $value);

        return $this;
    }

    public function getStatement(): string
    {
        return $this->selectBuilder->getStatement();
    }

    /** @return array<string, mixed> */
    public function getBindValues(): array
    {
        return $this->selectBuilder->getBindValues();
    }

    public function resetSelect(): self
    {
        $this->select = [];
        $this->selectBuilder->resetCols();

        return $this;
    }

    public function resetJoin(): self
    {
        $this->join = [];
        $this->selectBuilder->resetTables();
        foreach ($this->from as $from) {
            $this->selectBuilder->from("{$from['from']} AS {$from['alias']}");
        }

        return $this;
    }

    public function __clone(): void
    {
        $this->selectBuilder = clone $this->selectBuilder;
    }
}
