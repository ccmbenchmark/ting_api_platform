<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use ReflectionClass;

use function array_key_exists;
use function class_exists;

/** @internal */
final class RepositoryProvider
{
    /** @var array<class-string, Repository|null> */
    private array $localCache = [];

    public function __construct(
        private RepositoryFactory $ting,
        private MetadataRepository $metadataRepository,
    ) {
    }

    /**
     * @param class-string<T> $resourceClass
     *
     * @return Repository<T>|null
     *
     * @template T of object
     */
    public function getRepositoryFromResource(string $resourceClass): Repository|null
    {
        if (array_key_exists($resourceClass, $this->localCache)) {
            return $this->localCache[$resourceClass];
        }

        if (! class_exists($resourceClass)) {
            return $this->localCache[$resourceClass] = null;
        }

        $repositoryName = null;
        $retNull = false;
        $object = (new ReflectionClass($resourceClass))->newInstanceWithoutConstructor();
        $this->metadataRepository->findMetadataForEntity(
            $object,
            static function (Metadata $metadata) use (&$repositoryName): void {
                $repositoryName = $metadata->getRepository();
            },
            static function () use (&$retNull): void {
                $retNull = true;
            },
        );

        if ($retNull || empty($repositoryName)) {
            return $this->localCache[$resourceClass] = null;
        }

        return $this->localCache[$resourceClass] = $this->ting->get($repositoryName);
    }
}
