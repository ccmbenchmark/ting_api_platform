<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function array_fill_keys;
use function implode;
use function in_array;
use function sprintf;

final class BooleanFilter extends AbstractFilter
{
    /** @inheritdoc */
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
            ! $this->isBooleanField($property, $resourceClass)
        ) {
            return;
        }

        $value = $this->normalizeValue($value, $property);
        if ($value === null) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty(
                $property,
                $alias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                JoinType::INNER_JOIN,
            );
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        $queryBuilder
            ->where(sprintf('%s.%s = :%s', $alias, $field, $valueParameter))
            ->bindValue($valueParameter, $value);
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool}>
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

        $properties = $this->properties ?? array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);

        foreach ($properties as $property => $unused) {
            if (! $this->isPropertyMapped($property, $resourceClass) || ! $this->isBooleanField($property, $resourceClass)) {
                continue;
            }

            $propertyName               = $this->normalizePropertyName($property);
            $description[$propertyName] = [
                'property' => $propertyName,
                'type' => 'bool',
                'required' => false,
            ];
        }

        $this->filtersDescriptions[$resourceClass] = $description;

        return $description;
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    private function isBooleanField(string $property, string $resourceClass): bool
    {
        return $this->getTingFieldType($property, $resourceClass) === 'bool';
    }

    private function normalizeValue(mixed $value, string $property): int|null
    {
        if (in_array($value, [true, 'true', '1'], true)) {
            return 1;
        }

        if (in_array($value, [false, 'false', '0'], true)) {
            return 0;
        }

        $this->logger->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(sprintf(
                'Invalid boolean value for "%s" property, expected one of ( "%s" )',
                $property,
                implode(
                    '" | "',
                    [
                        'true',
                        'false',
                        '1',
                        '0',
                    ],
                ),
            )),
        ]);

        return null;
    }
}
