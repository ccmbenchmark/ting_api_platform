<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginationOptions;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryInterface;
use CCMBenchmark\Ting\ApiPlatform\Filter\FilterInterface;
use CCMBenchmark\Ting\ApiPlatform\Pagination\PaginationConfig;
use CCMBenchmark\Ting\ApiPlatform\Pagination\Paginator;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Repository;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_column;

/**
 * @template T of object
 *
 * @template-implements ProviderInterface<T>
 */
class CollectionDataProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryProvider $repositoryProvider,
        private RequestStack $requestStack,
        private PaginationOptions $paginationOptions,
        private ServiceLocator $filterLocator,
    ) {
    }

    /**
     * @return null|PaginatorInterface<T>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PaginatorInterface
    {
        $resourceClass = $operation->getClass() ?? '';
        $operationName = $operation->getName();

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || $resourceClass === '') {
            return null;
        }

        $paginationConfig = new PaginationConfig($this->paginationOptions, $request, $operation, $resourceClass);

        $repository = $this->repositoryProvider->getRepositoryFromResource($resourceClass);
        if (null === $repository) {
            return null;
        }

        $fields = array_column($repository->getMetadata()->getFields(), 'columnName');
        /** @var QueryInterface&SelectInterface $builder */
        $builder = $repository->getQueryBuilder(Repository::QUERY_SELECT);
        $builder->cols($fields);
        $builder->from($repository->getMetadata()->getTable());
        $this->applyFilters($builder, $resourceClass, $operation, $context);
        $this->applyPagination($builder, $resourceClass, $paginationConfig);
        $query = $repository->getQuery($builder->getStatement());

        /** @var PaginatorInterface<T> $paginator */
        $paginator = new Paginator($query->query($repository->getCollection(new HydratorSingleObject())), $paginationConfig, $operation);

        return $paginator;
    }

    private function applyPagination(QueryInterface&SelectInterface $queryBuilder, string $resourceClass, PaginationConfig $paginationConfig): void
    {
        if ($paginationConfig->getPaginationEnabled() === false) {
            return;
        }

        $config = $paginationConfig->getByClass($resourceClass);
        $queryBuilder->limit($config['limit']);
        $queryBuilder->offset($config['offset']);
    }

    /**
     * @param array<string, array<string, array|string>> $context
     */
    private function applyFilters(QueryInterface&SelectInterface $queryBuilder, string $resourceClass, Operation $operation, array $context): void
    {
        if ($operation->getFilters() !== null) {
            foreach ($operation->getFilters() as $name) {
                $filter = $this->filterLocator->get($name);
                if ($filter instanceof FilterInterface) {
                    $filter->apply($queryBuilder, $resourceClass, $operation, $context);
                }
            }
        }
    }
}

