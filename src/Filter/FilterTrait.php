<?php

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

trait FilterTrait
{
    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    protected function getTingFieldType(string $property, string $resourceClass): string
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        return $metadata->getTypeOfField($propertyParts['field']);
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    protected function isPropertyEnabled(string $property, string $resourceClass): bool
    {
        if ($this->properties === null) {
            // to ensure sanity, nested properties must still be explicitly enabled
            return ! $this->isPropertyNested($property, $resourceClass);
        }

        return array_key_exists($property, $this->properties);
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    protected function isPropertyMapped(string $property, string $resourceClass, bool $allowAssociation = false): bool
    {
        if ($this->isPropertyNested($property, $resourceClass)) {
            $propertyParts = $this->splitPropertyParts($property, $resourceClass);
            $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            $property      = $propertyParts['field'];
        } else {
            $metadata = $this->getClassMetadata($resourceClass);
        }

        return $metadata->hasField($property) || ($allowAssociation && $metadata->hasAssociation($property));
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    protected function isPropertyNested(string $property, string $resourceClass): bool
    {
        $pos = strpos($property, '.');
        if ($pos === false) {
            return false;
        }

        return $this->getClassMetadata($resourceClass)->hasAssociation(substr($property, 0, $pos));
    }

    protected function denormalizePropertyName(string $property): string
    {
        if (! $this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode(
            '.',
            array_map($this->nameConverter->denormalize(...), explode('.', $property)),
        );
    }

    protected function normalizePropertyName(string $property): string
    {
        if (! $this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->normalize(...), explode('.', $property)));
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return array{associations: list<string>, field: string}
     *
     * @template T of object
     */
    protected function splitPropertyParts(string $property, string $resourceClass): array
    {
        $parts = explode('.', $property);

        $metadata = $this->getClassMetadata($resourceClass);
        $slice    = 0;

        foreach ($parts as $part) {
            if (! $metadata->hasAssociation($part)) {
                continue;
            }

            $metadata = $this->getClassMetadata($metadata->getAssociationMapping($part)['targetEntity']);
            ++$slice;
        }

        if (count($parts) === $slice) {
            --$slice;
        }

        return [
            'associations' => array_slice($parts, 0, $slice),
            'field' => implode('.', array_slice($parts, $slice)),
        ];
    }

    /**
     * @param class-string<T> $resourceClass
     * @param list<string>    $associations
     *
     * @return ClassMetadata<T|object>
     *
     * @template T of object
     */
    protected function getNestedMetadata(string $resourceClass, array $associations): ClassMetadata
    {
        $metadata = $this->getClassMetadata($resourceClass);

        foreach ($associations as $association) {
            if (! $metadata->hasAssociation($association)) {
                continue;
            }

            $associationClass = $metadata->getAssociationMapping($association)['targetEntity'];

            $metadata = $this->getClassMetadata($associationClass);
        }

        return $metadata;
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    protected function getClassMetadata(string $resourceClass): ClassMetadata
    {
        return $this->managerRegistry->getManagerForClass($resourceClass)->getClassMetadata();
    }
}
