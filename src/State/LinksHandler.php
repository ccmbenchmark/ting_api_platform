<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\State;

use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\GraphQl\Operation as GraphQlOperation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function array_reverse;
use function array_shift;
use function assert;
use function count;
use function sprintf;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @template T of object
 */
final class LinksHandler
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceMetadataCollectionFactoryInterface|null $resourceMetadataCollectionFactory,
    ) {
    }

    /**
     * @param array<string, mixed>  $identifiers
     * @param HydratorRelational<T> $hydrator
     * @param ApiPlatformContext    $context
     * @param class-string<T>       $entityClass
     * @param Operation<T>          $operation
     */
    public function handleLinks(
        SelectBuilder $queryBuilder,
        array $identifiers,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        array $context,
        string $entityClass,
        Operation $operation,
    ): void {
        if ($identifiers === []) {
            return;
        }

        $alias = $queryBuilder->getRootAlias();

        $links = $this->getLinks($entityClass, $operation, $context);
        if ($links === []) {
            return;
        }

        $identifiers = array_reverse($identifiers);

        foreach (array_reverse($links) as $link) {
            assert($link instanceof Link);

            if ($link->getExpandedValue() !== null || $link->getFromClass() === null || $link->getFromProperty()) {
                continue;
            }

            $metadata = $this->managerRegistry->getManagerForClass($link->getFromClass())?->getClassMetadata();
            if ($metadata === null) {
                continue;
            }

            $identifierProperties = $link->getIdentifiers() ?? [];
            $hasCompositeIdentifiers = count($identifierProperties) > 1;

            $currentAlias = $link->getFromClass() === $entityClass ? $alias : $queryNameGenerator->generateJoinAlias($alias);

            foreach ($identifierProperties as $identifierProperty) {
                $placeholder = $queryNameGenerator->generateParameterName($identifierProperty);
                $queryBuilder->where("$currentAlias.{$metadata->getColumnName($identifierProperty)} = :$placeholder");
                $queryBuilder->bindValue($placeholder, $this->getIdentifierValue($identifiers, $hasCompositeIdentifiers ? $identifierProperty : null));
            }
        }
    }

    /**
     * @param class-string<T>    $resourceClass
     * @param Operation<T>       $operation
     * @param ApiPlatformContext $context
     *
     * @return list<Link>
     */
    private function getLinks(string $resourceClass, Operation $operation, array $context): array
    {
        $links = $this->getOperationLinks($operation);

        $linkClass = $context['linkClass'] ?? null;
        if ($linkClass === null) {
            return $links;
        }

        $newLink = null;
        $linkProperty = $context['linkProperty'] ?? null;

        foreach ($links as $link) {
            if ($linkClass === $link->getFromClass() && $linkProperty === $link->getFromProperty()) {
                $newLink = $link;
                break;
            }
        }

        if ($newLink !== null) {
            return [$newLink];
        }

        if ($this->resourceMetadataCollectionFactory === null) {
            return [];
        }

        // Using GraphQL, it's possible that we won't find a GraphQL Operation of the same type (e.g. it is disabled).
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($linkClass);
        try {
            $linkedOperation = $resourceMetadataCollection->getOperation($operation->getName());
        } catch (OperationNotFoundException $e) {
            if (! $operation instanceof GraphQlOperation) {
                throw $e;
            }

            // Instead, we'll look for the first Query available.
            foreach ($resourceMetadataCollection as $resourceMetadata) {
                foreach ($resourceMetadata->getGraphQlOperations() ?? [] as $op) {
                    if (! ($op instanceof Query)) {
                        continue;
                    }

                    $linkedOperation = $op;
                }
            }
        }

        foreach ($this->getOperationLinks($linkedOperation ?? null) as $link) {
            if ($resourceClass === $link->getToClass() && $linkProperty === $link->getFromProperty()) {
                $newLink = $link;
                break;
            }
        }

        if (! $newLink) {
            throw new RuntimeException(sprintf('The class "%s" cannot be retrieved from "%s".', $resourceClass, $linkClass));
        }

        return [$newLink];
    }

    /**
     * @param Operation<T>|null $operation
     *
     * @return list<Link>
     */
    private function getOperationLinks(Operation|null $operation = null): array
    {
        if ($operation instanceof GraphQlOperation) {
            return $operation->getLinks() ?? [];
        }

        if ($operation instanceof HttpOperation) {
            return $operation->getUriVariables() ?? [];
        }

        return [];
    }

    /** @param array<string, mixed> $identifiers */
    private function getIdentifierValue(array &$identifiers, string|null $name = null): mixed
    {
        if (isset($identifiers[$name])) {
            $value = $identifiers[$name];
            unset($identifiers[$name]);

            return $value;
        }

        return array_shift($identifiers);
    }
}
