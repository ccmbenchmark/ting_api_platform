<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\Join;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryBuilderHelper;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function implode;
use function sprintf;
use function strpos;
use function substr;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @phpstan-import-type AssociationMapping from MetadataAssociation
 */
abstract class AbstractFilter implements Filter
{
    protected LoggerInterface $logger;

    /** @param array<string, string|array{default_direction?: string, nulls_comparison?: string}>|null $properties */
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        LoggerInterface|null $logger = null,
        protected array|null $properties = null,
        protected NameConverterInterface|null $nameConverter = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
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
        foreach ($context['filters'] ?? [] as $property => $value) {
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

    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     * @param Operation<T>|null     $operation
     * @param ApiPlatformContext    $context
     *
     * @template T of object
     */
    abstract protected function filterProperty(
        string $property,
        mixed $value,
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void;

    /**
     * @param class-string<T> $resourceClass
     *
     * @template T of object
     */
    protected function getTingFieldType(string $property, string $resourceClass): string|null
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

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
            $metadata = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            $property = $propertyParts['field'];
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
     * @return array{associations: list<AssociationMapping>, field: string}
     *
     * @template T of object
     */
    protected function splitPropertyParts(string $property, string $resourceClass): array
    {
        $parts = explode('.', $property);

        $metadata = $this->getClassMetadata($resourceClass);
        $slice = 0;
        $associations = [];

        foreach ($parts as $part) {
            if (! $metadata->hasAssociation($part)) {
                continue;
            }

            $associations[] = $association = $metadata->getAssociationMapping($part);
            $metadata = $this->getClassMetadata($association['targetEntity']);
            ++$slice;
        }

        if (count($parts) === $slice) {
            --$slice;
        }

        return [
            'associations' => $associations,
            'field' => implode('.', array_slice($parts, $slice)),
        ];
    }

    /**
     * @param class-string<T>          $resourceClass
     * @param list<AssociationMapping> $associations
     *
     * @return ClassMetadata<T|object>
     *
     * @template T of object
     */
    protected function getNestedMetadata(string $resourceClass, array $associations): ClassMetadata
    {
        $metadata = $this->getClassMetadata($resourceClass);

        foreach ($associations as $association) {
            if (! $metadata->hasAssociation($association['fieldName'])) {
                continue;
            }

            $associationClass = $metadata->getAssociationMapping($association['fieldName'])['targetEntity'];

            $metadata = $this->getClassMetadata($associationClass);
        }

        return $metadata;
    }

    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     *
     * @return array{0: string, 1: string, 2: list<AssociationMapping>}
     *
     * @template T of object
     */
    protected function addJoinsForNestedProperty(
        string $property,
        string $rootAlias,
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Join $joinType,
    ): array {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $parentAlias = $rootAlias;
        $alias = null;

        foreach ($propertyParts['associations'] as $association) {
            $alias = QueryBuilderHelper::addJoinOnce(
                $queryBuilder,
                $queryNameGenerator,
                $parentAlias,
                $association,
                $joinType,
            );
            $parentAlias = $alias;
        }

        if ($alias === null) {
            throw new InvalidArgumentException(sprintf(
                'Cannot add joins for property "%s" - property is not nested.',
                $property,
            ));
        }

        return [$alias, $propertyParts['field'], $propertyParts['associations']];
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
