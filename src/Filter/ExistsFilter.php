<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Common\Filter\ExistsFilterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

use function array_fill_keys;
use function implode;
use function in_array;
use function sprintf;

final class ExistsFilter extends AbstractFilter implements ExistsFilterInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        LoggerInterface|null $logger = null,
        array|null $properties = null,
        NameConverterInterface|null $nameConverter = null,
        private readonly string $existsParameterName = self::QUERY_PARAMETER_KEY,
    ) {
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
        foreach ($context['filters'][$this->existsParameterName] ?? [] as $property => $value) {
            $this->filterProperty(
                $this->denormalizePropertyName($property),
                $value,
                $queryBuilder,
                $hydrator,
                $queryNameGenerator,
                $resourceClass,
                $operation,
                $context,
            );
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
        if (
            ! $this->isPropertyEnabled($property, $resourceClass) ||
            ! $this->isPropertyMapped($property, $resourceClass, false)
        ) {
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
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, JoinType::INNER_JOIN);
        }

        $metadata = $this->getNestedMetadata($resourceClass, $associations);

        if (! $metadata->hasField($field)) {
            return;
        }

        $queryBuilder
            ->where(sprintf('%s.%s %s NULL', $alias, $field, $value ? 'IS NOT' : 'IS'));
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool}>
     *
     * @inheritDoc
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
            if (! $this->isPropertyMapped($property, $resourceClass, false)) {
                continue;
            }

            $propertyName                                                              = $this->normalizePropertyName($property);
            $description[sprintf('%s[%s]', $this->existsParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'bool',
                'required' => false,
            ];
        }

        $this->filtersDescriptions[$resourceClass] = $description;

        return $description;
    }

    private function normalizeValue(mixed $value, string $property): bool|null
    {
        if (in_array($value, [true, 'true', '1', '', null], true)) {
            return true;
        }

        if (in_array($value, [false, 'false', '0'], true)) {
            return false;
        }

        $this->logger->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(sprintf('Invalid value for "%s[%s]", expected one of ( "%s" )', $this->existsParameterName, $property, implode('" | "', [
                'true',
                'false',
                '1',
                '0',
            ]))),
        ]);

        return null;
    }
}
