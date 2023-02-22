<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Api\FilterLocatorTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Aura\SqlQuery\Common\SelectInterface;
use CCMBenchmark\Ting\ApiPlatform\Filter\FilterInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Repository;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_column;
use function dump;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function str_replace;

class CollectionDataProvider implements ProviderInterface
{
    const PAGE_PARAMETER_NAME_DEFAULT = 'page';

    public function __construct(
        private RepositoryProvider $repositoryProvider,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resourceClass = $operation->getClass();
        $operationName = $operation->getName();

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $repository = $this->repositoryProvider->getRepositoryFromResource($resourceClass);

        if ($repository === null) {
            return [];
        }

        $fields = array_column($repository->getMetadata()->getFields(), 'columnName');

        /** @var SelectInterface $builder */
        $builder = $repository->getQueryBuilder(Repository::QUERY_SELECT);
        $builder->cols($fields);
        $builder->from($repository->getMetadata()->getTable());
        $this->getWhere($operation, $request, $repository, $builder);
        $this->getCurrentPage($operation, $request, $builder);
        $this->getItemsPerPage($operation, $request, $builder);
        $this->getOrder($operation, $request, $builder);
        $query = $repository->getQuery($builder->getStatement());

        return iterator_to_array($query->query($repository->getCollection(new HydratorSingleObject())));
    }

    private function getCurrentPage(Operation $operation, Request $request, SelectInterface $queryBuilder): void
    {
        $page = $request->query->getInt(
            $operation->getPaginationType() ?? static::PAGE_PARAMETER_NAME_DEFAULT,
            1
        );
        if ($page < 2) {
            return;
        }

        $page -= 1;
        $itemsPerPage = $request->query->getInt(
            'itemsPerPage',
            $operation->getPaginationItemsPerPage()
        );

        $queryBuilder->offset($page * $itemsPerPage);
    }

    private function getItemsPerPage(Operation $operation, Request $request, SelectInterface $queryBuilder): void
    {
        $queryBuilder->limit($request->query->get(
            'itemsPerPage',
            $operation->getPaginationItemsPerPage()
        ));
    }

    private function getOrder(Operation $operation, Request $request, SelectInterface $queryBuilder): void
    {
        $properties = $operation->getOrder();
        if ($properties === null) {
            return;
        }

        $suffix = [];
        foreach ($properties as $property => $order) {
            $suffix[] = "$property $order";
        }
        if ($suffix !== []) {
            $queryBuilder->orderBy($suffix);
        }
    }

    private function getWhere(Operation $operation, Request $request, Repository $repository, SelectInterface $queryBuilder): void
    {
        $properties = $repository->getMetadata()->getFields();

        $where = '';
        foreach ($properties as $property) {
            if ($request->query->has($property['fieldName'])) {
                $value = $request->query->get($property['fieldName']);
                $where = $this->getClauseFilter($operation, $property['columnName'], $value);
            }
        }
        if ($where != '') {
            $where = str_replace('<<', "'", $where);
            $where = str_replace('>>', "'", $where);

            $queryBuilder->where($where);
        }
    }

    private function getClauseFilter(Operation $operation, string $property, $value): string
    {
        $resourceFilters = $operation->getFilters();

        $where = '';
        if (empty($resourceFilters)) {
            return $where;
        }

        foreach ($resourceFilters as $filter) {
            if ($filter instanceof FilterInterface) {
                $where = $filter->addClause($property, $value, $operation->getClass());
            } else {
                if (is_array($value)) {
                    $where = sprintf('%s in (%s)', $property, '<<' . implode('>>,<<', $value).'>>');
                } else {
                    $where = "$property = " . $this->cast($value);
                }
            }
        }

        return $where;
    }

    private function cast($value): string
    {
        if (is_string($value)) {
            return "<<$value>>";
        }

        return $value;
    }
}

