<?php

namespace CCMBenchmark\Ting\ApiPlatform\Warmer;

use CCMBenchmark\Ting\ApiPlatform\Filter\Filter;
use CCMBenchmark\Ting\MetadataRepository;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class FilterWarmer implements CacheWarmerInterface
{
    /**
     * @param MetadataRepository $metadataRepository
     * @param Filter[] $filterServices
     */
    public function __construct(
        private MetadataRepository $metadataRepository,
        private array $filterServices
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir): void
    {
        $cacheDir .= '/ting_api_platform';
        if (!file_exists($cacheDir)) {
            mkdir ($cacheDir); // @todo parameter
        }
        foreach ($this->filterServices as $serviceId => $filterService) {
            $descriptions = [];
            foreach ($this->metadataRepository->getAllEntities() as $resourceClass) {
                $descriptions[$resourceClass] = $filterService->getDescription($resourceClass);
            }
            file_put_contents(
                $cacheDir . '/search_filters_descriptions_' . $serviceId . '.php',
                '<?php return ' . var_export($descriptions, true) . ';'
            );
        }
    }

}
