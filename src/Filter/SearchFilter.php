<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use DateTime;

use function array_fill_keys;
use function array_map;
use function array_values;
use function count;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;

use const FILTER_VALIDATE_INT;

final class SearchFilter extends AbstractFilter implements SearchFilterInterface
{
    /** @inheritDoc */
    protected function filterProperty(
        string $property,
        mixed $value,
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void {
        if (
            $value === null
            || ! $this->isPropertyEnabled($property, $resourceClass)
            || ! $this->isPropertyMapped($property, $resourceClass, false)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;

        $values = $this->normalizeValues((array) $value, $property);
        if ($values === null) {
            return;
        }

        $associations = [];
        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field, $associations] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $caseSensitive = true;
        $strategy = $this->normalizeStrategy($this->properties[$property] ?? self::STRATEGY_EXACT);

        // prefixing the strategy with i makes it case-insensitive
        if (str_starts_with($strategy, 'i')) {
            $strategy = substr($strategy, 1);
            $caseSensitive = false;
        }

        $metadata = $this->getNestedMetadata($resourceClass, $associations);

        if (! $metadata->hasField($field)) {
            return;
        }

        if (! $this->hasValidValues($values, $this->getTingFieldType($property, $resourceClass))) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the ting type.', $field)),
            ]);

            return;
        }

        $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $alias, $metadata->getColumnName($field), $values, $caseSensitive);
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool, strategy: string, is_collection: bool}>
     *
     * @template T of object
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->properties;
        if ($properties === null) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $strategy) {
            if (! $this->isPropertyMapped($property, $resourceClass, false)) {
                continue;
            }

            if ($this->isPropertyNested($property, $resourceClass)) {
                $propertyParts = $this->splitPropertyParts($property, $resourceClass);
                $field = $propertyParts['field'];
                $metadata = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            } else {
                $field = $property;
                $metadata = $this->getClassMetadata($resourceClass);
            }

            $propertyName = $this->normalizePropertyName($property);
            if ($metadata->hasField($field)) {
                $typeOfField = $this->getType($metadata->getTypeOfField($field));
                $strategy = $this->normalizeStrategy($this->properties[$property] ?? self::STRATEGY_EXACT);
                $filterParameterNames = [$propertyName];

                if ($strategy === self::STRATEGY_EXACT) {
                    $filterParameterNames[] = $propertyName . '[]';
                }

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $propertyName,
                        'type' => $typeOfField,
                        'required' => false,
                        'strategy' => $strategy,
                        'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                    ];
                }
            } elseif ($metadata->hasAssociation($field)) {
                $filterParameterNames = [
                    $propertyName,
                    $propertyName . '[]',
                ];

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $propertyName,
                        'type' => 'string',
                        'required' => false,
                        'strategy' => self::STRATEGY_EXACT,
                        'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                    ];
                }
            }
        }

        return $description;
    }

    protected function addWhereByStrategy(
        string $strategy,
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $alias,
        string $column,
        mixed $values,
        bool $caseSensitive,
    ): void {
        if (! is_array($values)) {
            $values = [$values];
        }

        $wrapCase = $this->createWrapCase($caseSensitive);
        $valueParameter = $queryNameGenerator->generateParameterName($column);
        $aliasedField = sprintf('%s.%s', $alias, $column);

        if (! $strategy || $strategy === self::STRATEGY_EXACT) {
            if (count($values) === 1) {
                $queryBuilder
                    ->where(sprintf('%s = %s', $wrapCase($aliasedField), $wrapCase(':' . $valueParameter)))
                    ->bindValue($valueParameter, $values[0]);

                return;
            }

            QueryBuilderHelper::in($queryBuilder, $alias, $column, $caseSensitive ? $values : array_map(strtolower(...), $values), $valueParameter);

            return;
        }

        $ors = [];
        foreach ($values as $key => $value) {
            $keyValueParameter = sprintf(':%s_%s', $valueParameter, $key);
            $queryBuilder->bindValue($keyValueParameter, $caseSensitive ? $value : strtolower($value));

            $ors[] = match ($strategy) {
                self::STRATEGY_PARTIAL => sprintf(
                    '%s LIKE %s',
                    $wrapCase($aliasedField),
                    $wrapCase(sprintf('CONCAT("%%", %s, "%%")', $keyValueParameter)),
                ),
                self::STRATEGY_START => sprintf(
                    '%s LIKE %s',
                    $wrapCase($aliasedField),
                    $wrapCase(sprintf('CONCAT(%s, "%%")', $keyValueParameter)),
                ),
                self::STRATEGY_END => sprintf(
                    '%s LIKE %s',
                    $wrapCase($aliasedField),
                    $wrapCase(sprintf('CONCAT("%%", %s)', $keyValueParameter)),
                ),
                self::STRATEGY_WORD_START => sprintf(
                    '(%s)',
                    implode(
                        ' OR ',
                        [
                            sprintf(
                                '%s LIKE %s',
                                $wrapCase($aliasedField),
                                $wrapCase(sprintf('CONCAT(%s, "%%")', $keyValueParameter)),
                            ),
                            sprintf(
                                '%s LIKE %s',
                                $wrapCase($aliasedField),
                                $wrapCase(sprintf('CONCAT("%% ", %s, "%%")', $keyValueParameter)),
                            ),
                        ],
                    ),
                ),
                default => throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy)),
            };
        }

        $queryBuilder->where(sprintf('(%s)', implode(' OR ', $ors)));
    }

    /** @return callable(string):string */
    protected function createWrapCase(bool $caseSensitive): callable
    {
        return static function (string $expr) use ($caseSensitive): string {
            if ($caseSensitive) {
                return $expr;
            }

            return sprintf('LOWER(%s)', $expr);
        };
    }

    private function getType(string|null $tingType): string
    {
        return match ($tingType) {
            'json' => 'array',
            'int' => 'int',
            'bool' => 'bool',
            'datetime' => DateTime::class,
            'double' => 'float',
            default => 'string',
        };
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return non-empty-list<string|int>|null
     */
    private function normalizeValues(array $values, string $property): array|null
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            if (! is_int($key) || (! is_string($value) && ! is_int($value))) {
                continue;
            }

            $normalized[$key] = $value;
        }

        if ($normalized === []) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return null;
        }

        return array_values($normalized);
    }

    /** @param non-empty-list<string|int> $values */
    private function hasValidValues(array $values, string|null $type = null): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $type === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                return false;
            }
        }

        return true;
    }

    private function normalizeStrategy(mixed $strategy): string
    {
        $allowedValues = [self::STRATEGY_EXACT, self::STRATEGY_END, self::STRATEGY_PARTIAL, self::STRATEGY_START, self::STRATEGY_WORD_START];
        if (! in_array($strategy, $allowedValues, true)) {
            return self::STRATEGY_EXACT;
        }

        return $strategy;
    }
}
