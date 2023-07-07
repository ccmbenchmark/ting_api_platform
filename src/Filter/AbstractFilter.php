<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociation;
use CCMBenchmark\Ting\ApiPlatform\Ting\ClassMetadata;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\JoinType;
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
    use FilterTrait;
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
     * @return array{0: string, 1: string, 2: list<string>}
     *
     * @template T of object
     */
    protected function addJoinsForNestedProperty(
        string $property,
        string $rootAlias,
        SelectBuilder $queryBuilder,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        JoinType $joinType,
    ): array {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $parentAlias   = $rootAlias;
        $alias         = null;

        foreach ($propertyParts['associations'] as $association) {
            $alias       = QueryBuilderHelper::addJoinOnce(
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
}
