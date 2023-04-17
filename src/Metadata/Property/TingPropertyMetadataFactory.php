<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Metadata\Property;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;

final class TingPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly PropertyMetadataFactoryInterface $decorated,
    ) {
    }

    /**
     * @param class-string<T>      $resourceClass
     * @param array<string, mixed> $options
     *
     * @inheritDoc
     * @template T of object
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        if ($propertyMetadata->isIdentifier() !== null) {
            return $propertyMetadata;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return $propertyMetadata;
        }

        $metadata = $manager->getClassMetadata();

        foreach ($metadata->getIdentifiers() as $identifier) {
            if ($identifier['fieldName'] !== $property) {
                continue;
            }

            $propertyMetadata = $propertyMetadata->withIdentifier(true);

            if ($propertyMetadata->isWritable() !== null) {
                break;
            }

            $propertyMetadata = $propertyMetadata->withWritable(! ($identifier['autoincrement'] ?? false));

            break;
        }

        return $propertyMetadata;
    }
}
