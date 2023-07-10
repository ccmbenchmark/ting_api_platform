<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function array_fill_keys;
use function array_key_exists;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_numeric;
use function sprintf;
use function str_ends_with;

final class NumericFilter extends AbstractFilter
{
    private const TING_NUMERIC_TYPES = [
        'double' => true,
        'int' => true,
    ];

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
            ! $this->isPropertyEnabled($property, $resourceClass) ||
            ! $this->isPropertyMapped($property, $resourceClass) ||
            ! $this->isNumericField($property, $resourceClass)
        ) {
            return;
        }

        $values = $this->normalizeValues($value, $property);
        if ($values === null) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, JoinType::INNER_JOIN);
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        if (count($values) === 1) {
            $queryBuilder
                ->where(sprintf('%s.%s = :%s', $alias, $field, $valueParameter))
                ->bindValue($valueParameter, $values[0]);
        } else {
            QueryBuilderHelper::in($queryBuilder, $alias, $field, $values, $valueParameter);
        }
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool, is_collection: bool}>
     *
     * @template T of object
     */
    public function getDescription(string $resourceClass): array
    {
        if (!isset($this->filtersDescriptions) && isset($this->filterDescriptionGetter)) {
            $this->filtersDescriptions = $this->filterDescriptionGetter->getDescriptions();
        }
        if (isset ($this->filtersDescriptions[$resourceClass])) {
            return $this->filtersDescriptions[$resourceClass];
        }

        $description = [];

        $properties = $this->properties;
        if ($properties === null) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $unused) {
            if (! $this->isPropertyMapped($property, $resourceClass) || ! $this->isNumericField($property, $resourceClass)) {
                continue;
            }

            $propertyName         = $this->normalizePropertyName($property);
            $filterParameterNames = [$propertyName, $propertyName . '[]'];
            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'type' => $this->getTingFieldType($property, $resourceClass) === 'double' ? 'float' : 'int',
                    'required' => false,
                    'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                ];
            }
        }

        return $description;
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    private function isNumericField(string $property, string $resourceClass): bool
    {
        return array_key_exists($this->getTingFieldType($property, $resourceClass), self::TING_NUMERIC_TYPES);
    }

    /** @return list<float|int>|null */
    protected function normalizeValues(mixed $value, string $property): array|null
    {
        if (! is_numeric($value) && (! is_array($value) || ! $this->isNumericArray($value))) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid numeric value for "%s" property', $property)),
            ]);

            return null;
        }

        $values = (array) $value;

        foreach ($values as $key => $val) {
            if (! is_int($key)) {
                unset($values[$key]);

                continue;
            }

            $values[$key] = $val + 0; // coerce $val to the right type.
        }

        if (empty($values)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return null;
        }

        return array_values($values);
    }

    /** @param list<mixed> $values */
    protected function isNumericArray(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
