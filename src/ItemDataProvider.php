<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CCMBenchmark\Ting\ApiPlatform\RepositoryProvider;
use CCMBenchmark\Ting\Repository\Repository;
use Symfony\Component\HttpFoundation\RequestStack;

class ItemDataProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryProvider $repositoryProvider,
        private RequestStack $requestStack,
        private string $primaryKeyIdentifier = 'id',
    ) {
    }

    /**
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        /** @var Repository $repository */
        $repository = $this->repositoryProvider->getRepositoryFromResource($operation->getClass());

        return $repository->getBy([$this->primaryKeyIdentifier => $request->get($this->primaryKeyIdentifier)]);
    }
}

