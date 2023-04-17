<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Association;

interface MetadataAssociationFactory
{
    /** @param class-string $entityClass */
    public function getMetadataAssociation(string $entityClass): MetadataAssociation;
}
