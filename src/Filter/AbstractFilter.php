<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;

abstract class AbstractFilter
{
    public function __construct(
        private RepositoryProvider $repositoryProvider,
        protected MetadataRepository $metadataRepository,
        protected array $properties = [],
    ) {
    }

    protected function getTypeMariaForProperty(string $property, string $resourceClass): string
    {
        $fields = $this->getMetadataForResourceClass($resourceClass)?->getFields();
        if ($fields !== null) {
            foreach ($fields as $field) {
                if ($field['fieldName'] === $property) {
                    return $field['type'];
                }
            }
        }

        return 'varchar';
    }

    protected function getTypeForProperty(string $property, string $resourceClass): string
    {
        $fields = $this->getMetadataForResourceClass($resourceClass)?->getFields();
        if ($fields !== null) {
            foreach ($fields as $field) {
                if ($field['fieldName'] === $property) {
                    return $field['type'];
                }
            }
        }

        return 'varchar';
    }

    protected function getFieldNamesForResource(string $resourceClass): array
    {
        $metadata = $this->getMetadataForResourceClass($resourceClass);

        return $metadata ? $metadata->getFields() : [];
    }

    protected function getPropertiesForFilter(
        string $resourceClass,
        array $context,
        array $description,
        callable $callback,
    ): void {
        if (!isset($context['filters'])) {
            return;
        }
        $filters = $context['filters'];
        $properties = $this->getFieldNamesForResource($resourceClass);
        foreach ($properties as $property) {
            if (isset($filters[$property['fieldName']])) {
                $value = $filters[$property['fieldName']];
                if (isset($description[$property['fieldName']])) {
                    $callback($property, $value);
                }
            }
        }
    }

    private function getMetadataForResourceClass(string $resourceClass): ?Metadata
    {
        $repository = $this->repositoryProvider->getRepositoryFromResource($resourceClass);

        return $repository ? $repository->getMetadata() : null;
    }
}
