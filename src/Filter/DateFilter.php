<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use DateTime;
use Throwable;

use function array_fill_keys;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/** @phpstan-type FilterDescription array<string, array{property: string, type: class-string<DateTime>, required: bool}> */
final class DateFilter extends AbstractFilter implements DateFilterInterface
{
    private const TING_DATE_TYPES = [
        'datetime' => 'Y-m-d H:i:s',
        'date' => 'Y-m-d',
        'time' => 'H:i:s',
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
        // Expect $values to be an array having the period as keys and the date value as values
        if (
            ! is_array($value) ||
            ! $this->isPropertyEnabled($property, $resourceClass) ||
            ! $this->isPropertyMapped($property, $resourceClass) ||
            ! $this->isDateField($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, JoinType::INNER_JOIN);
        }

        $nullManagement = $this->normalizeNullManagement($this->properties[$property] ?? null, $property);

        if ($nullManagement === self::EXCLUDE_NULL) {
            $queryBuilder->where(sprintf('%s.%s IS NOT NULL', $alias, $field));
        }

        if (isset($value[self::PARAMETER_BEFORE])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_BEFORE,
                $value[self::PARAMETER_BEFORE],
                self::TING_DATE_TYPES[$this->getTingFieldType($property, $resourceClass)],
                $nullManagement,
            );
        }

        if (isset($value[self::PARAMETER_STRICTLY_BEFORE])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_STRICTLY_BEFORE,
                $value[self::PARAMETER_STRICTLY_BEFORE],
                self::TING_DATE_TYPES[$this->getTingFieldType($property, $resourceClass)],
                $nullManagement,
            );
        }

        if (isset($value[self::PARAMETER_AFTER])) {
            $this->addWhere(
                $queryBuilder,
                $queryNameGenerator,
                $alias,
                $field,
                self::PARAMETER_AFTER,
                $value[self::PARAMETER_AFTER],
                self::TING_DATE_TYPES[$this->getTingFieldType($property, $resourceClass)],
                $nullManagement,
            );
        }

        if (! isset($value[self::PARAMETER_STRICTLY_AFTER])) {
            return;
        }

        $this->addWhere(
            $queryBuilder,
            $queryNameGenerator,
            $alias,
            $field,
            self::PARAMETER_STRICTLY_AFTER,
            $value[self::PARAMETER_STRICTLY_AFTER],
            self::TING_DATE_TYPES[$this->getTingFieldType($property, $resourceClass)],
            $nullManagement,
        );
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

        foreach ($properties as $property => $nullManagement) {
            if (! $this->isPropertyMapped($property, $resourceClass) || ! $this->isDateField($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_AFTER);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_AFTER);
        }

        return $description;
    }

    /** @return FilterDescription */
    private function getFilterDescription(string $property, string $period): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s[%s]', $propertyName, $period) => [
                'property' => $propertyName,
                'type' => DateTime::class,
                'required' => false,
            ],
        ];
    }

    protected function addWhere(
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $alias,
        string $field,
        string $operator,
        mixed $value,
        string $format,
        string|null $nullManagement = null,
    ): void {
        $value = $this->normalizeValue($value, $operator);
        if ($value === null) {
            return;
        }

        try {
            $value = new DateTime($value);
        } catch (Throwable) {
            // Silently ignore this filter if it can not be transformed to a \DateTime
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('The field "%s" has a wrong date format. Use one accepted by the \DateTime constructor', $field)),
            ]);

            return;
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);
        $operatorValue  = [
            self::PARAMETER_BEFORE => '<=',
            self::PARAMETER_STRICTLY_BEFORE => '<',
            self::PARAMETER_AFTER => '>=',
            self::PARAMETER_STRICTLY_AFTER => '>',
        ];
        $baseWhere      = sprintf('%s.%s %s :%s', $alias, $field, $operatorValue[$operator], $valueParameter);

        if ($nullManagement === null || $nullManagement === self::EXCLUDE_NULL) {
            $queryBuilder->where($baseWhere);
        } elseif (
            (
                $nullManagement === self::INCLUDE_NULL_BEFORE
                && in_array(
                    $operator,
                    [
                        self::PARAMETER_BEFORE,
                        self::PARAMETER_STRICTLY_BEFORE,
                    ],
                    true,
                )
            )
            || (
                $nullManagement === self::INCLUDE_NULL_AFTER
                && in_array(
                    $operator,
                    [
                        self::PARAMETER_AFTER,
                        self::PARAMETER_STRICTLY_AFTER,
                    ],
                    true,
                )
            )
            || (
                $nullManagement === self::INCLUDE_NULL_BEFORE_AND_AFTER
                && in_array(
                    $operator,
                    [
                        self::PARAMETER_AFTER,
                        self::PARAMETER_STRICTLY_AFTER,
                        self::PARAMETER_BEFORE,
                        self::PARAMETER_STRICTLY_BEFORE,
                    ],
                    true,
                )
            )
        ) {
            $queryBuilder->where(sprintf('(%s OR %s.%s IS NULL)', $baseWhere, $alias, $field));
        } else {
            $queryBuilder->where(sprintf('(%s OR %s.%s IS NOT NULL)', $baseWhere, $alias, $field));
        }

        $queryBuilder->bindValue($valueParameter, $value->format($format));
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    private function isDateField(string $property, string $resourceClass): bool
    {
        return isset(self::TING_DATE_TYPES[$this->getTingFieldType($property, $resourceClass)]);
    }

    private function normalizeValue(mixed $value, string $operator): string|null
    {
        if (is_string($value) === false) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected string', $operator)),
            ]);

            return null;
        }

        return $value;
    }

    private function normalizeNullManagement(mixed $nullManagement, string $property): string|null
    {
        if ($nullManagement === null) {
            return null;
        }

        $allowedValues = [self::EXCLUDE_NULL, self::INCLUDE_NULL_AFTER, self::INCLUDE_NULL_BEFORE, self::INCLUDE_NULL_BEFORE_AND_AFTER];
        if (! in_array($nullManagement, $allowedValues, true)) {
            $this->logger->notice('Invalid filter configuration', [
                'exception' => new InvalidArgumentException(sprintf(
                    'Invalid null management value for "%s" property, expected one of ( "%s" )',
                    $property,
                    implode(
                        '" | "',
                        $allowedValues,
                    ),
                )),
            ]);

            return null;
        }

        return $nullManagement;
    }
}
