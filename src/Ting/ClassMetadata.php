<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use CCMBenchmark\Ting\Repository\Metadata;

use function array_column;
use function array_filter;
use function array_key_exists;
use function array_values;

/**
 * @template T of object
 * @phpstan-import-type Field from Metadata
 * @phpstan-import-type AssociationMapping from MetadataAssociation
 */
final class ClassMetadata implements MetadataAssociation
{
    /** @var array<string, Field> */
    private array $fields;
    /** @var list<Field> */
    private array|null $identifiers;

    /** @param Metadata<T> $metadata */
    public function __construct(
        private readonly Metadata $metadata,
        private readonly MetadataAssociation $metadataAssociation,
    ) {
        $this->fields = array_column($this->metadata->getFields(), null, 'fieldName');
    }

    /** @return class-string<T> */
    public function getName(): string
    {
        return $this->metadata->getEntity();
    }

    public function getTableName(): string
    {
        return $this->metadata->getTable();
    }

    /** @return list<string> */
    public function getIdentifierFieldNames(): array
    {
        return array_column($this->getIdentifiers(), 'fieldName');
    }

    /** @return list<string> */
    public function getIdentifierColumnNames(): array
    {
        return array_column($this->getIdentifiers(), 'columnName');
    }

    /** @return list<Field> */
    public function getIdentifiers(): array
    {
        return $this->identifiers ?? $this->identifiers = array_values(array_filter(
            $this->fields,
            static function (array $field): bool {
                return $field['primary'] ?? false;
            },
        ));
    }

    public function hasField(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->fields);
    }

    /** @return Field */
    public function getField(string $fieldName): array
    {
        return $this->fields[$fieldName];
    }

    /** @return list<string> */
    public function getFieldNames(): array
    {
        return array_column($this->fields, 'fieldName');
    }

    /** @return list<Field> */
    public function getFields(): array
    {
        return array_values($this->fields);
    }

    public function getColumnName(string $fieldName): string
    {
        return $this->fields[$fieldName]['columnName'];
    }

    /** @return list<string> */
    public function getColumnNames(): array
    {
        return array_column($this->fields, 'columnName');
    }

    public function getTypeOfField(string $fieldName): string
    {
        return $this->fields[$fieldName]['type'];
    }

    public function hasAssociation(string $property): bool
    {
        return $this->metadataAssociation->hasAssociation($property);
    }

    /** @return AssociationMapping */
    public function getAssociationMapping(string $property): array
    {
        return $this->metadataAssociation->getAssociationMapping($property);
    }

    public function getAssociationMappings(): array
    {
        return $this->metadataAssociation->getAssociationMappings();
    }
}
