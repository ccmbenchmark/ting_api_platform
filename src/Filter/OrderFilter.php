<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Repository;

class OrderFilter extends AbstractFilter implements OrderFilterInterface, FilterInterface
{
    private readonly string $orderParameterName;

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

        parent::__construct($repositoryProvider, $metadataRepository, $properties);

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

    public function apply(SelectInterface $queryBuilder, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (!isset($context['filters'][$this->orderParameterName])) {
            return;
        }

        /** @var array<string, array> $context['filters'] */
        $orderFilters = $context['filters'][$this->orderParameterName];
        foreach ($orderFilters as $property => $order) {
            if (!in_array(strtoupper($order), [self::DIRECTION_ASC, self::DIRECTION_DESC])) {
                continue;
            }

            $queryBuilder->orderBy([sprintf('%s %s', $property, $order)]);
        }
    }
}
