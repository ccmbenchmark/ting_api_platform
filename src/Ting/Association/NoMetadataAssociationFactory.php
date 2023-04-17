<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Association;

use InvalidArgumentException;

final class NoMetadataAssociationFactory implements MetadataAssociationFactory
{
    public function getMetadataAssociation(string $entityClass): MetadataAssociation
    {
        return new class implements MetadataAssociation {
            public function hasAssociation(string $property): bool
            {
                return false;
            }

            /** @inheritDoc */
            public function getAssociationMapping(string $property): array
            {
                throw new InvalidArgumentException(
                    "Association name expected, '" . $property . "' is not an association.",
                );
            }

            /** @inheritdoc */
            public function getAssociationMappings(): array
            {
                return [];
            }
        };
    }
}
