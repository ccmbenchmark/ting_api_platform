<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use function array_key_exists;

final class ManagerRegistry
{
    /** @var array<class-string, Manager> */
    private array $instancedManagers = [];

    public function __construct(
        private readonly RepositoryProvider $repositoryProvider,
        private readonly ClassMetadataFactory $classMetadataFactory,
    ) {
    }

    /**
     * @param class-string<T> $className
     *
     * @return Manager<T>|null
     *
     * @template T of object
     */
    public function getManagerForClass(string $className): Manager|null
    {
        if (array_key_exists($className, $this->instancedManagers)) {
            return $this->instancedManagers[$className];
        }

        $repository = $this->repositoryProvider->getRepositoryFromResource($className);
        if ($repository === null) {
            return null;
        }

        return $this->instancedManagers[$className] = new Manager($repository, $this->classMetadataFactory);
    }
}
