<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;


use ApiPlatform\Api\FilterInterface as LegacyFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use CCMBenchmark\Ting\ApiPlatform\Filter\AbstractFilter;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class EnumFilter extends AbstractFilter
{

    public function __construct(
        protected ManagerRegistry $managerRegistry,
        LoggerInterface|null $logger = null,
        protected array|null $properties = null,
        protected NameConverterInterface|null $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    /** @inheritDoc */
    protected function filterProperty(
        string $property,
        mixed $value,
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (! $this->isPropertyEnabled($property, $resourceClass) || ! $this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();
        $value = $this->normalizeValue($value);

        if ($value === null) {
            return;
        }

        $queryBuilder->where(sprintf('%s.%s LIKE "%s"', $alias, $property, $value));
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array<string, array{property: string, type: string, required: bool, schema: array{type: string, enum: array<string>}}>
     *
     * @template T of object
     */
    public function getDescription(string $resourceClass): array
    {
        $properties = $this->properties;
        if ($properties === null) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        $description = [];
        /**
         * @var string $propertyOptions
         */
        foreach ($properties as $property => $propertyOptions) {
            if (! $this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }
            $this->isEnum($propertyOptions);

            $propertyName = $this->normalizePropertyName($property);
            $enums = array_map(fn($enum) => $enum->value, $propertyOptions::cases());

            $description[sprintf('%s', $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => $enums ,
                ],
            ];
        }

        return $description;
    }

    protected function isEnum(string $className): void {

        if (!enum_exists($className)) {
            throw new InvalidArgumentException("$className should be string type.");
        }
    }

    protected function normalizeValue(mixed $value): string|null
    {
        if ($value !== null && !is_string($value)) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        return $value;
    }

}
