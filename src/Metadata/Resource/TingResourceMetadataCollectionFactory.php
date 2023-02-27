<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use CCMBenchmark\Ting\ApiPlatform\CollectionDataProvider;
use CCMBenchmark\Ting\ApiPlatform\ItemDataProvider;
use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use CCMBenchmark\Ting\Repository\Repository;
use function assert;

final class TingResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly RepositoryProvider $repositoryProvider,
        private readonly ResourceMetadataCollectionFactoryInterface $decorated
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

                    $entityClass = $operation->getClass();

                    if (!$this->repositoryProvider->getRepositoryFromResource($entityClass) instanceof Repository) {
                        continue;
                    }

                    $operations->add($operationName, $this->addDefaults($operation));
                }

                $resourceMetadata = $resourceMetadata->withOperations($operations);
            }

            $graphQlOperations = $resourceMetadata->getGraphQlOperations();

            if ($graphQlOperations) {
                foreach ($graphQlOperations as $operationName => $graphQlOperation) {
                    $entityClass = $graphQlOperation->getClass();

                    if (!$this->repositoryProvider->getRepositoryFromResource($entityClass) instanceof Repository) {
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

    private function addDefaults(Operation $operation): Operation
    {
        if ($operation->getProvider() === null) {
            $operation = $operation->withProvider($this->getProvider($operation));
        }

        return $operation;
    }

    private function getProvider(Operation $operation): string
    {
        if ($operation instanceof CollectionOperationInterface) {
            return CollectionDataProvider::class;
        }

        return ItemDataProvider::class;
    }
}
