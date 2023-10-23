<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryCollectionExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryResultCollectionExtension;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Util\IncrementedQueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

/**
 * @phpstan-type ApiPlatformContext array{
 *     filters?: array<string, mixed>,
 *     graphql_operation_name?: string,
 *     linkClass?: class-string,
 *     linkProperty?: string,
 *     attributes?: array<string, mixed>,
 *     groups?: mixed,
 *     api_denormalize?: mixed,
 *     count?: int
 * }
 * @template T of object
 * @template-implements ProviderInterface<T>
 */
final class CollectionProvider implements ProviderInterface
{
    /**
     * @param LinksHandler<T>                      $linksHandler
     * @param iterable<QueryCollectionExtension<T> &QueryResultCollectionExtension<T>> $collectionExtensions
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly LinksHandler $linksHandler,
        private readonly iterable $collectionExtensions,
    ) {
    }

    /**
     * @param Operation<T>         $operation
     * @param array<string, mixed> $uriVariables
     * @param ApiPlatformContext   $context
     *
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var class-string<T> $entityClass */
        $entityClass = $operation->getClass() ?? '';
        if ($entityClass === '') {
            return null;
        }

        $manager = $this->managerRegistry->getManagerForClass($entityClass);
        if ($manager === null) {
            return null;
        }

        $queryBuilder       = $manager->createQueryBuilder('o');
        $queryNameGenerator = new IncrementedQueryNameGenerator();
        $hydrator           = new HydratorRelational();
        $hydrator->callableFinalizeAggregate(
            static function (array $row) {
                return $row['o'];
            },
        );

        $this->linksHandler->handleLinks(
            $queryBuilder,
            $uriVariables,
            $queryNameGenerator,
            $context,
            $entityClass,
            $operation,
        );

        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($queryBuilder, $hydrator, $queryNameGenerator, $entityClass, $operation, $context);

            if ($extension instanceof QueryResultCollectionExtension && $extension->supportsResult($entityClass, $operation, $context)) {
                return $extension->getResult($queryBuilder, $hydrator, $entityClass, $operation, $context);
            }
        }

        $repository = $manager->getRepository();
        $query      = $repository->getQuery($queryBuilder->getStatement());
        $query->setParams($queryBuilder->getBindedValues());

        return $query->query($repository->getCollection($hydrator));
    }
}
