<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryItemExtension;
use CCMBenchmark\Ting\ApiPlatform\Extension\QueryResultItemExtension;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\ApiPlatform\Util\IncrementedQueryNameGenerator;
use CCMBenchmark\Ting\Repository\HydratorRelational;

/**
 * @phpstan-import-type ApiPlatformContext from CollectionProvider
 * @template T of object
 * @template-implements ProviderInterface<T>
 */
final class ItemProvider implements ProviderInterface
{
    /**
     * @param LinksHandler<T>                 $linksHandler
     * @param iterable<QueryItemExtension<T>> $itemExtensions
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly LinksHandler $linksHandler,
        private readonly iterable $itemExtensions,
    ) {
    }

    /**
     * @param Operation<T>         $operation
     * @param array<string, mixed> $uriVariables
     * @param ApiPlatformContext   $context
     *
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|null
    {
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

        $this->linksHandler->handleLinks($queryBuilder, $uriVariables, $queryNameGenerator, $context, $entityClass, $operation);

        foreach ($this->itemExtensions as $extension) {
            $extension->applyToItem($queryBuilder, $hydrator, $queryNameGenerator, $entityClass, $uriVariables, $operation, $context);

            if ($extension instanceof QueryResultItemExtension && $extension->supportsResult($entityClass, $operation, $context)) {
                return $extension->getResult($queryBuilder, $hydrator, $entityClass, $operation, $context);
            }
        }

        $repository = $manager->getRepository();
        $query      = $repository->getQuery($queryBuilder->getStatement());
        $query->setParams($queryBuilder->getBindedValues());

        return $query->query($repository->getCollection($hydrator))->first();
    }
}
