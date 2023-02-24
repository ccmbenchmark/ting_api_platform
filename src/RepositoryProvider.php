<?php

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\Exception\ResourceClassNotSupportedException;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;

use function class_exists;

class RepositoryProvider
{
    /**
     * @var string
     */
    private $repositoryName = '';

    public function __construct(
        private RepositoryFactory $ting,
        private MetadataRepository $metadataRepository
    ) {
    }

    /**
     * @param string $resourceClass
     * @return Repository|null
     * @throws ResourceClassNotSupportedException
     */
    public function getRepositoryFromResource(string $resourceClass): ?Repository
    {
        if (! class_exists($resourceClass)) {
            return null;
        }

        $object = new $resourceClass();
        $this->metadataRepository->findMetadataForEntity($object,
            function (Metadata $metadata) {
                $this->repositoryName = $metadata->getRepository();
            },
            function () {
                throw new ResourceClassNotSupportedException();
            }
        );

        if (empty($this->repositoryName)) {
            return null;
        }
        /** @var Repository|null $repository */
        $repository = $this->ting->get($this->repositoryName);

        return $repository;
    }
}

