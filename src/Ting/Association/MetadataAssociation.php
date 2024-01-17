<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Association;

/**
 * @phpstan-type JoinColumnData array{
 *     sourceName: string,
 *     targetName: string
 * }
 * @phpstan-type AssociationMapping array{
 *     fieldName: string,
 *     sourceEntity: class-string,
 *     joinColumns: list<JoinColumnData>,
 *     targetEntity: class-string,
 *     targetTable: string,
 *     type: AssociationType,
 *     nullable: bool,
 *     mappedBy: string|null,
 *     inversedBy: string|null,
 *     fetch?: int
 * }
 */
interface MetadataAssociation
{
    public function hasAssociation(string $property): bool;

    /** @return AssociationMapping */
    public function getAssociationMapping(string $property): array;

    /** @return list<AssociationMapping> */
    public function getAssociationMappings(): array;
}
