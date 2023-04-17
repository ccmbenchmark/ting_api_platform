<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Metadata\Resource;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\LinkFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\PropertyLinkFactoryInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;

/** @template T of object */
final class TingLinkFactory implements LinkFactoryInterface, PropertyLinkFactoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
        private readonly LinkFactoryInterface&PropertyLinkFactoryInterface $linkFactory,
    ) {
    }

    /**
     * @param ApiResource<T>|Operation<T> $operation
     *
     * @inheritDoc
     */
    public function createLinkFromProperty(ApiResource|Operation $operation, string $property): Link
    {
        return $this->linkFactory->createLinkFromProperty($operation, $property);
    }

    /**
     * @param ApiResource<T>|Operation<T> $operation
     *
     * @inheritDoc
     */
    public function createLinksFromIdentifiers(ApiResource|Operation $operation): array
    {
        return $this->linkFactory->createLinksFromIdentifiers($operation);
    }

    /**
     * @param ApiResource<T>|Operation<T> $operation
     *
     * @inheritDoc
     */
    public function createLinksFromRelations(ApiResource|Operation $operation): array
    {
        $links = $this->linkFactory->createLinksFromRelations($operation);

        $resourceClass = $operation->getClass();
        if ($resourceClass === null) {
            return $links;
        }

        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if ($manager === null) {
            return $links;
        }

        $metadata = $manager->getClassMetadata();

        foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $property) {
            if (! $metadata->hasAssociation($property)) {
                continue;
            }

            $association = $metadata->getAssociationMapping($property);

            if ($association['mappedBy'] === null || ! $this->resourceClassResolver->isResourceClass($association['targetEntity'])) {
                continue;
            }

            $link = new Link(
                fromProperty: $property,
                toProperty: $association['mappedBy'],
                fromClass: $resourceClass,
                toClass: $association['targetEntity'],
            );
            $link = $this->completeLink($link);
            $links[] = $link;
        }

        return $links;
    }

    /**
     * @param ApiResource<T>|Operation<T> $operation
     *
     * @inheritDoc
     */
    public function createLinksFromAttributes(ApiResource|Operation $operation): array
    {
        return $this->linkFactory->createLinksFromAttributes($operation);
    }

    public function completeLink(Link $link): Link
    {
        return $this->linkFactory->completeLink($link);
    }
}
