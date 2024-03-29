<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Extension;

use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use CCMBenchmark\Ting\ApiPlatform\Paginator;
use CCMBenchmark\Ting\ApiPlatform\PartialPaginator;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\Association\AssociationType;
use CCMBenchmark\Ting\ApiPlatform\Ting\Manager;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Ting\Pagination\Paginator as TingPaginator;
use CCMBenchmark\Ting\ApiPlatform\Ting\Query\SelectBuilder;
use CCMBenchmark\Ting\ApiPlatform\Util\QueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

use function array_slice;
use function explode;


/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @template T of object
 * @template-implements QueryResultCollectionExtension<T>
 */
final class PaginationExtension implements QueryResultCollectionExtension
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly Pagination $pagination,
    ) {
    }

    /** @inheritdoc */
    public function applyToCollection(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        QueryNameGenerator $queryNameGenerator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): void {
        $pagination = $this->getPagination($queryBuilder, $hydrator, $resourceClass, $operation, $context);
        if ($pagination === null) {
            return;
        }

        [$offset, $limit] = $pagination;

        $queryBuilder
            ->offset($offset)
            ->limit($limit);
    }

    /** @inheritdoc */
    public function supportsResult(string $resourceClass, Operation|null $operation = null, array $context = []): bool
    {
        if ($context['graphql_operation_name'] ?? false) {
            return $this->pagination->isGraphQlEnabled($operation, $context);
        }

        return $this->pagination->isEnabled($operation, $context);
    }

    /**
     * @return Paginator<T>|PartialPaginator<T>|array<never, never>
     *
     * @inheritdoc
     */
    public function getResult(
        SelectBuilder $queryBuilder,
        HydratorRelational $hydrator,
        string $resourceClass,
        Operation|null $operation = null,
        array $context = [],
    ): PartialPaginator|array {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return [];
        }
        $tingPaginator = new TingPaginator($queryBuilder, $manager->getRepository(), $hydrator, $manager->getClassMetadata());
        if (!is_null($operation)) {
            /** @var  class-string<T>|null $operationClassName */
            $operationClassName = $operation->getClass();
            if ($this->hasAdditionalDistinctQueryRequirement($queryBuilder, $operationClassName, $context)) {
                $tingPaginator->addRequiredWhereInClause();
            }
        }

        if ($this->pagination->isPartialEnabled($operation, $context)) {
            return new /** @template-extends PartialPaginator<T> */ class ($tingPaginator) extends PartialPaginator {
            };
        }

        return new Paginator($tingPaginator);
    }

    /**
     * @param class-string<T>|null $operationClassName
     * @param array<string,mixed> $context
     */
    private function hasAdditionalDistinctQueryRequirement(SelectBuilder $selectBuilder, string|null $operationClassName, array $context): bool
    {
        if (!isset($context['graphql_operation_name']) &&
            !is_null($selectBuilder->getLimit()) &&
            $this->containsAtLeastOneJoinToMany($selectBuilder, $operationClassName)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param class-string<T>|null $operationClassName
     */
    private function containsAtLeastOneJoinToMany(SelectBuilder $selectBuilder, string|null $operationClassName): bool
    {
        if (is_null($operationClassName)) {
            throw new InvalidArgumentException('The "$operationClassName" parameter must not be null');
        }
        $metadata = $this->managerRegistry->getManagerForClass($operationClassName)?->getClassMetadata();
        if (!is_null($metadata)) {
            $rootAlias = $selectBuilder->getRootAlias();
            if (isset($selectBuilder->getJoins()[$rootAlias])) {
                foreach ($selectBuilder->getJoins()[$rootAlias] as $join) {
                    $joinData = explode('.', $join->join);
                    $mapping = $metadata->getAssociationMapping($joinData[1]);
                    if ($mapping['type'] === AssociationType::TO_MANY) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     * @param Operation<T>|null     $operation
     * @param ApiPlatformContext    $context
     *
     * @return array{0: int, 1: int}|null
     */
    private function getPagination(SelectBuilder $queryBuilder, HydratorRelational $hydrator, string $resourceClass, Operation|null $operation, array $context): array|null
    {
        if (! $this->supportsResult($resourceClass, $operation, $context)) {
            return null;
        }

        $context = $this->addCountToContext($queryBuilder, $hydrator, $resourceClass, $context);

        return array_slice($this->pagination->getPagination($operation, $context), 1);
    }

    /**
     * @param HydratorRelational<T> $hydrator
     * @param class-string<T>       $resourceClass
     * @param ApiPlatformContext    $context
     *
     * @return ApiPlatformContext
     */
    private function addCountToContext(SelectBuilder $queryBuilder, HydratorRelational $hydrator, string $resourceClass, array $context): array
    {
        if (! ($context['graphql_operation_name'] ?? false)) {
            return $context;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);

        if ($manager instanceof Manager && isset($context['filters']['last']) && ! isset($context['filters']['before'])) {
            $context['count'] = (new TingPaginator($queryBuilder, $manager->getRepository(), $hydrator, $manager->getClassMetadata()))->count();
        }

        return $context;
    }
}
