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

    /**
     * @param string $cacheDir
     * @return string[]
     */
    public function warmUp(string $cacheDir)
    {
        $cacheDir .= '/ting_api_platform';
        if (!file_exists($cacheDir)) {
            mkdir ($cacheDir);
        }
        $filesCreated = [];
        foreach ($this->filterServices as $serviceId => $filterService) {
            $descriptions = [];
            foreach ($this->metadataRepository->getAllEntities() as $resourceClass) {
                $descriptions[$resourceClass] = $filterService->getDescription($resourceClass);
            }
            $fileName = $cacheDir . '/search_filters_descriptions_' . $serviceId . '.php';
            file_put_contents(
                $fileName,
                '<?php return ' . var_export($descriptions, true) . ';'
            );
            $filesCreated[] = $fileName;
        }
        return $filesCreated;
    }

}
