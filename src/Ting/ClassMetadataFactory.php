<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting;

use CCMBenchmark\Ting\ApiPlatform\Ting\Association\MetadataAssociationFactory;
use CCMBenchmark\Ting\Repository\Repository;

use function array_key_exists;

final class ClassMetadataFactory
{
    /** @var array<class-string<Repository>, ClassMetadata> */
    private array $loadedMetadata = [];

    public function __construct(private readonly MetadataAssociationFactory $tingMetadataAssociationFactory)
    {
    }

    /**
     * @param Repository<T> $repository
     *
     * @return ClassMetadata<T>
     *
     * @template T of object
     */
    public function getMetadataFor(Repository $repository): ClassMetadata
    {
        if (array_key_exists($repository::class, $this->loadedMetadata)) {
            return $this->loadedMetadata[$repository::class];
        }

        $metadata = $repository->getMetadata();

        return $this->loadedMetadata[$repository::class] = new ClassMetadata(
            $metadata,
            $this->tingMetadataAssociationFactory->getMetadataAssociation($metadata->getEntity()),
        );
    }
}
