<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

use function array_fill_keys;
use function array_map;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function str_replace;
use function strtolower;
use function strtoupper;

final class OrderFilter extends AbstractFilter implements OrderFilterInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        LoggerInterface|null $logger = null,
        array|null $properties = null,
        NameConverterInterface|null $nameConverter = null,
        private readonly string $orderParameterName = 'order',
        private readonly string|null $orderNullsComparison = null,
    ) {
        if ($properties !== null) {
            $properties = array_map(
                static function ($propertyOptions) {
                    // shorthand for default direction
                    if (is_string($propertyOptions)) {
                        $propertyOptions = ['default_direction' => $propertyOptions];
                    }

                    return $propertyOptions;
                },
                $properties,
            );
        }

        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    /** @inheritdoc */
    public function apply(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void {
        if (isset($context['filters']) && ! isset($context['filters'][$this->orderParameterName])) {
            return;
        }

        if (! isset($context['filters'][$this->orderParameterName]) || ! is_array($context['filters'][$this->orderParameterName])) {
            parent::apply($queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, $operation, $context);

            return;
        }

        foreach ($context['filters'][$this->orderParameterName] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }

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
        if (! $this->isPropertyEnabled($property, $resourceClass) || ! $this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        $value = $this->normalizeValue($value, $property);
        if ($value === null) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;
        $associations = [];

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field, $associations] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $hydrator, $queryNameGenerator, $resourceClass, Join::LEFT_JOIN);
        }

        $columnName = $this->getNestedMetadata($resourceClass, $associations)->getColumnName($field);

        if (($nullsComparison = $this->properties[$property]['nulls_comparison'] ?? $this->orderNullsComparison) !== null) {
            $nullsDirection = self::NULLS_DIRECTION_MAP[$nullsComparison][$value];

            $nullRankHiddenField = sprintf('_%s_%s_null_rank', $alias, str_replace('.', '_', $field));

            $queryBuilder
                ->rawSelect(sprintf('CASE WHEN %s.%s IS NULL THEN 0 ELSE 1 END AS %s', $alias, $columnName, $nullRankHiddenField))
                ->orderBy($nullRankHiddenField, $nullsDirection);
        }

        $queryBuilder->orderBy(sprintf('%s.%s', $alias, $columnName), $value);
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool, schema: array{type: string, enum: non-empty-list<string>}}>
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

        foreach ($properties as $property => $propertyOptions) {
            if (! $this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $propertyName = $this->normalizePropertyName($property);
            $description[sprintf('%s[%s]', $this->orderParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ];
        }

        return $description;
    }

    private function normalizeValue(mixed $value, string $property): string|null
    {
        if ($value !== null && ! is_string($value)) {
            return null;
        }

        if (empty($value) && ($defaultDirection = $this->properties[$property]['default_direction'] ?? null) !== null) {
            // fallback to default direction
            $value = $defaultDirection;
        }

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);
        if (! in_array($value, [self::DIRECTION_ASC, self::DIRECTION_DESC], true)) {
            return null;
        }

        return $value;
    }
}
