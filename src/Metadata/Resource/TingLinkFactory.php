<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Metadata\Resource;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Metadata;
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
     * @inheritDoc
     */
    public function createLinkFromProperty(Metadata $operation, string $property): Link
    {
        return $this->linkFactory->createLinkFromProperty($operation, $property);
    }

    /**
     * @inheritDoc
     */
    public function createLinksFromIdentifiers(Metadata $operation): array
    {
        return $this->linkFactory->createLinksFromIdentifiers($operation);
    }

    /**
     * @inheritDoc
     */
    public function createLinksFromRelations(Metadata $operation): array
    {
        $links = $this->linkFactory->createLinksFromRelations($operation);

        /** @var class-string<object>|null $resourceClass */
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

            $link    = new Link(
                fromProperty: $property,
                toProperty: $association['mappedBy'],
                fromClass: $resourceClass,
                toClass: $association['targetEntity'],
            );
            $link    = $this->completeLink($link);
            $links[] = $link;
        }

        return $links;
    }

    /**
     * @inheritDoc
     */
    public function createLinksFromAttributes(Metadata $operation): array
    {
        return $this->linkFactory->createLinksFromAttributes($operation);
    }

    public function completeLink(Link $link): Link
    {
        return $this->linkFactory->completeLink($link);
    }
}
