<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CCMBenchmark\Ting\Repository\Repository;
use Symfony\Component\Uid\Uuid;
use function array_column;
use function array_filter;
use function sprintf;

class ItemDataProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryProvider $repositoryProvider
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        /** @var Repository $repository */
        $repository = $this->repositoryProvider->getRepositoryFromResource($operation->getClass());

        $criteria = [];
        foreach($uriVariables as $name => $variable) {
            $criteria[$name] = $variable instanceof Uuid ? $variable->toRfc4122() : $variable;
        }

        return $repository->getOneBy($criteria);
    }
}
