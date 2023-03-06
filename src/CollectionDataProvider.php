<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Api\FilterLocatorTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginationOptions;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\ApiPlatform\Filter\FilterInterface;
use CCMBenchmark\Ting\ApiPlatform\Filter\OrderFilter;
use CCMBenchmark\Ting\ApiPlatform\Pagination\Paginator;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Repository;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

use function array_column;

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
     * {@inheritdoc}
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PartialPaginatorInterface
    {
        $resourceClass = $operation->getClass() ?? '';
        $operationName = $operation->getName();

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $repository = $this->repositoryProvider->getRepositoryFromResource($resourceClass);
        if (null === $repository) {
            return null;
        }

        $fields = array_column($repository->getMetadata()->getFields(), 'columnName');
        /** @var SelectInterface $builder */
        $builder = $repository->getQueryBuilder(Repository::QUERY_SELECT);
        $builder->cols($fields);
        $builder->from($repository->getMetadata()->getTable());
        $this->applyFilters($builder, $resourceClass, $operation, $context);
        $this->getCurrentPage($operation, $request, $builder);
        $this->getItemsPerPage($operation, $request, $builder);

        $query = $repository->getQuery($builder->getStatement());
        //TODO : Implements pagination in Paginator: $maxResults, $firstResult, $totalItems;
        return new Paginator($query->query($repository->getCollection(new HydratorSingleObject())));
    }

    private function getCurrentPage(Operation $operation, Request $request, SelectInterface $queryBuilder): void
    {
        $page = $request->query->getInt(
            $operation->getPaginationType() ?? $this->paginationOptions->getPaginationPageParameterName(),
            1
        );
        if ($page < 2) {
            return;
        }

        $page -= 1;
        $itemsPerPage = $request->query->getInt(
            $this->paginationOptions->getItemsPerPageParameterName(),
            (int) $operation->getPaginationItemsPerPage()
        );

        $queryBuilder->offset($page * $itemsPerPage);
    }

    private function getItemsPerPage(Operation $operation, Request $request, SelectInterface $queryBuilder): void
    {
        $queryBuilder->limit((int) $request->query->get(
            $this->paginationOptions->getItemsPerPageParameterName(),
            $operation->getPaginationItemsPerPage()
        ));
    }

    /**
     * @param array<string, array<string, array|string>> $context
     */
    private function applyFilters(SelectInterface $queryBuilder, string $resourceClass, Operation $operation, array $context): void
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

