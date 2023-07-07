<?php

namespace CCMBenchmark\Ting\ApiPlatform\Warmer;

use CCMBenchmark\Ting\ApiPlatform\Filter\FilterTrait;
use CCMBenchmark\Ting\ApiPlatform\Filter\SearchFilter;
use CCMBenchmark\Ting\ApiPlatform\Filter\SearchFilterTrait;
use CCMBenchmark\Ting\ApiPlatform\Ting\ManagerRegistry;
use CCMBenchmark\Ting\MetadataRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class SearchFiltersDescriptionWarmer implements CacheWarmerInterface
{
    use FilterTrait;
    use SearchFilterTrait;

    /** @param array<string, string|array{default_direction?: string, nulls_comparison?: string}>|null $properties */
    public function __construct(
        protected MetadataRepository $metadataRepository,
        protected ManagerRegistry $managerRegistry,
        LoggerInterface|null $logger = null,
        protected array|null $properties = null,
        protected NameConverterInterface|null $nameConverter = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function isOptional()
    {
        return false;
    }

    public function warmUp(string $cacheDir)
    {
        $entities = $this->metadataRepository->getAllEntities();
        $descriptions = [];
        foreach ($entities as $resourceClass) {
            $descriptions[$resourceClass] = $this->getDescription($resourceClass);
        }
        file_put_contents($cacheDir . '/search_filters_descriptions.php', '<?php return ' . var_export($descriptions, true) . ';');
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->properties;
        if ($properties === null) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $strategy) {
            if (! $this->isPropertyMapped($property, $resourceClass, false)) {
                continue;
            }

            if ($this->isPropertyNested($property, $resourceClass)) {
                $propertyParts = $this->splitPropertyParts($property, $resourceClass);
                $field         = $propertyParts['field'];
                $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            } else {
                $field    = $property;
                $metadata = $this->getClassMetadata($resourceClass);
            }

            $propertyName = $this->normalizePropertyName($property);
            if ($metadata->hasField($field)) {
                $typeOfField          = $this->getType($metadata->getTypeOfField($field));
                $strategy             = $this->normalizeStrategy($this->properties[$property] ?? SearchFilter::STRATEGY_EXACT);
                $filterParameterNames = [$propertyName];

                if ($strategy === SearchFilter::STRATEGY_EXACT) {
                    $filterParameterNames[] = $propertyName . '[]';
                }

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $propertyName,
                        'type' => $typeOfField,
                        'required' => false,
                        'strategy' => $strategy,
                        'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                    ];
                }
            } elseif ($metadata->hasAssociation($field)) {
                $filterParameterNames = [
                    $propertyName,
                    $propertyName . '[]',
                ];

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $propertyName,
                        'type' => 'string',
                        'required' => false,
                        'strategy' => SearchFilter::STRATEGY_EXACT,
                        'is_collection' => str_ends_with((string) $filterParameterName, '[]'),
                    ];
                }
            }
        }

        return $description;
    }
}
