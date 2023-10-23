<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use CCMBenchmark\Ting\ApiPlatform\State\CollectionProvider;
use CCMBenchmark\Ting\ApiPlatform\State\ItemProvider;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;

use function assert;

final class TingResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        foreach ($resourceMetadataCollection as $i => $resourceMetadata) {
            assert($resourceMetadata instanceof ApiResource);

            $operations = $resourceMetadata->getOperations();
            if ($operations) {
                foreach ($operations as $operationName => $operation) {
                    assert($operation instanceof Operation);

                    /** @var class-string<object> $entityClass */
                    $entityClass = $operation->getClass() ?? '';

                    if ($entityClass === '' || $this->managerRegistry->getManagerForClass($entityClass) === null) {
                        continue;
                    }

                    $operations->add($operationName, $this->addDefaults($operation));
                }

                $resourceMetadata = $resourceMetadata->withOperations($operations);
            }

            $graphQlOperations = $resourceMetadata->getGraphQlOperations();

            if ($graphQlOperations) {
                foreach ($graphQlOperations as $operationName => $graphQlOperation) {
                    /** @var class-string<object> $entityClass */
                    $entityClass = $graphQlOperation->getClass() ?? '';

                    if ($entityClass === '' || $this->managerRegistry->getManagerForClass($entityClass) === null) {
                        continue;
                    }

                    $graphQlOperations[$operationName] = $this->addDefaults($graphQlOperation);
                }

                $resourceMetadata = $resourceMetadata->withGraphQlOperations($graphQlOperations);
            }

            $resourceMetadataCollection[$i] = $resourceMetadata;
        }

        return $resourceMetadataCollection;
    }

    /**
     * @param Operation<T> $operation
     *
     * @return Operation<T>
     *
     * @template T of object
     */
    private function addDefaults(Operation $operation): Operation
    {
        if ($operation->getProvider() === null) {
            $operation = $operation->withProvider($this->getProvider($operation));
        }

        return $operation;
    }

    /**
     * @param Operation<T> $operation
     *
     * @template T of object
     */
    private function getProvider(Operation $operation): string
    {
        if ($operation instanceof CollectionOperationInterface) {
            return CollectionProvider::class;
        }

        return ItemProvider::class;
    }
}
