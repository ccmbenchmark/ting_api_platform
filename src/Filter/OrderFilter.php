<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use CCMBenchmark\Ting\MetadataRepository;

class OrderFilter extends AbstractFilter implements OrderFilterInterface, FilterInterface
{
    public readonly string $orderParameterName;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        MetadataRepository $metadataRepository,
        array $properties = [],
        string $orderParameterName = 'order',
    ) {
        if (null !== $properties) {
            $properties = array_map(static function ($propertyOptions) {
                // shorthand for default direction
                if (\is_string($propertyOptions)) {
                    $propertyOptions = [
                        'default_direction' => $propertyOptions,
                    ];
                }

                return $propertyOptions;
            }, $properties);
        }

        parent::__construct($repositoryProvider, $metadataRepository, $properties, $orderParameterName);

        $this->orderParameterName = $orderParameterName;
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $propertyName => $propertyOptions) {
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

    /**
     * @param mixed $value
     */
    public function addClause(string $property, $value): string
    {
        if (!in_array(strtoupper($value), [self::DIRECTION_ASC, self::DIRECTION_DESC])) {
            return '';
        }

        return sprintf('%s %s', $property, $value);
    }
}
