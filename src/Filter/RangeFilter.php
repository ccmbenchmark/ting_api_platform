<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Common\Filter\RangeFilterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function array_fill_keys;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;

/** @phpstan-type FilterDescription array<string, array{property: string, type: string, required: bool}> */
final class RangeFilter extends AbstractFilter implements RangeFilterInterface
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
            ! is_array($value) ||
            ! $this->isPropertyEnabled($property, $resourceClass) ||
            ! $this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $value = $this->normalizeValues($value, $property);
        if ($value === null) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;
        $associations = [];

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field, $associations] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $column = $this->getNestedMetadata($resourceClass, $associations)->getColumnName($field);

        foreach ($value as $operator => $value) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $column,
                $operator,
                $value,
            );
        }
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return FilterDescription
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

        foreach ($properties as $property => $unused) {
            if (! $this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_BETWEEN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN_OR_EQUAL);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN_OR_EQUAL);
        }

        return $description;
    }

    /** @return FilterDescription */
    private function getFilterDescription(string $fieldName, string $operator): array
    {
        $propertyName = $this->normalizePropertyName($fieldName);

        return [
            sprintf('%s[%s]', $propertyName, $operator) => [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }

    protected function addWhere(
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $alias,
        string $column,
        string $operator,
        string $value,
    ): void {
        $valueParameter = $queryNameGenerator->generateParameterName($column);

        switch ($operator) {
            case self::PARAMETER_BETWEEN:
                $rangeValue = explode('..', $value);

                $rangeValue = $this->normalizeBetweenValues($rangeValue);
                if ($rangeValue === null) {
                    return;
                }

                if ($rangeValue[0] === $rangeValue[1]) {
                    $queryBuilder
                        ->where(sprintf('%s.%s = :%s', $alias, $column, $valueParameter))
                        ->bindValue($valueParameter, $rangeValue[0]);

                    return;
                }

                $queryBuilder
                    ->where(sprintf('%1$s.%2$s BETWEEN :%3$s_1 AND :%3$s_2', $alias, $column, $valueParameter))
                    ->bindValue(sprintf('%s_1', $valueParameter), $rangeValue[0])
                    ->bindValue(sprintf('%s_2', $valueParameter), $rangeValue[1]);

                break;
            case self::PARAMETER_GREATER_THAN:
                $value = $this->normalizeValue($value, $operator);
                if ($value === null) {
                    return;
                }

                $queryBuilder
                    ->where(sprintf('%s.%s > :%s', $alias, $column, $valueParameter))
                    ->bindValue($valueParameter, $value);

                break;
            case self::PARAMETER_GREATER_THAN_OR_EQUAL:
                $value = $this->normalizeValue($value, $operator);
                if ($value === null) {
                    return;
                }

                $queryBuilder
                    ->where(sprintf('%s.%s >= :%s', $alias, $column, $valueParameter))
                    ->bindValue($valueParameter, $value);

                break;
            case self::PARAMETER_LESS_THAN:
                $value = $this->normalizeValue($value, $operator);
                if ($value === null) {
                    return;
                }

                $queryBuilder
                    ->where(sprintf('%s.%s < :%s', $alias, $column, $valueParameter))
                    ->bindValue($valueParameter, $value);

                break;
            case self::PARAMETER_LESS_THAN_OR_EQUAL:
                $value = $this->normalizeValue($value, $operator);
                if ($value === null) {
                    return;
                }

                $queryBuilder
                    ->where(sprintf('%s.%s <= :%s', $alias, $column, $valueParameter))
                    ->bindValue($valueParameter, $value);

                break;
        }
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return non-empty-array<string, string>|null
     */
    private function normalizeValues(array $values, string $property): array|null
    {
        $normalized = [];
        $operators = [self::PARAMETER_BETWEEN, self::PARAMETER_GREATER_THAN, self::PARAMETER_GREATER_THAN_OR_EQUAL, self::PARAMETER_LESS_THAN, self::PARAMETER_LESS_THAN_OR_EQUAL];

        foreach ($values as $operator => $value) {
            if (! is_string($value) || ! in_array($operator, $operators, true)) {
                continue;
            }

            $normalized[$operator] = $value;
        }

        if ($normalized === []) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one valid operator ("%s") is required for "%s" property', implode('", "', $operators), $property)),
            ]);

            return null;
        }

        return $normalized;
    }

    /**
     * @param list<mixed> $values
     *
     * @return array{0: float|int, 1: float|int}|null
     */
    private function normalizeBetweenValues(array $values): array|null
    {
        if (count($values) !== 2) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid format for "[%s]", expected "<min>..<max>"', self::PARAMETER_BETWEEN)),
            ]);

            return null;
        }

        if (! is_numeric($values[0]) || ! is_numeric($values[1])) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid values for "[%s]" range, expected numbers', self::PARAMETER_BETWEEN)),
            ]);

            return null;
        }

        return [$values[0] + 0, $values[1] + 0]; // coerce to the right types.
    }

    private function normalizeValue(string $value, string $operator): float|int|null
    {
        if (! is_numeric($value)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
            ]);

            return null;
        }

        return $value + 0; // coerce $value to the right type.
    }
}
