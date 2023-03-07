<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Pagination;

use ApiPlatform\State\Pagination\PaginationOptions;
use Symfony\Component\HttpFoundation\Request;
use InvalidArgumentException;

final class PaginationConfig
{
    private bool $paginationEnabled;
    private int $itemsPerPage;
    private array $resourceClasses;

    public function __construct(
        PaginationOptions $paginationOptions,
        Request $request,
        array $context,
        public readonly string $parentResourceClass,
    ) {
        $this->paginationEnabled = $context['paginationEnabled'] ?? $paginationOptions->isPaginationEnabled();
        if ($this->paginationEnabled !== true) {
            return;
        }

        $this->itemsPerPage = $context['paginationItemsPerPage'] ?? $paginationOptions->getItemsPerPage();

        $paginationClientEnabled = $context['paginationClientEnabled'] ?? $paginationOptions->isPaginationClientEnabled();
        if ($paginationClientEnabled === true) {
            $this->itemsPerPage = $context['paginationClientItemsPerPage'] ?? $this->itemsPerPage;
        }

        $paginationMaximumItemsPerPage = $context['paginationMaximumItemsPerPage'] ?? $paginationOptions->getMaximumItemsPerPage();
        if ($paginationMaximumItemsPerPage !== null && $paginationMaximumItemsPerPage > $this->itemsPerPage) {
            $this->itemsPerPage = $paginationMaximumItemsPerPage;
        }

        if ($request->attributes->get('_graphql') !== true) {
            $page = $request->query->getInt(
                $paginationOptions->getPaginationPageParameterName(),
                1
            );
            $this->resourceClasses[$parentResourceClass] = [
                'limit' => $this->itemsPerPage,
                'offset' => ($page - 1) * $this->itemsPerPage,
            ];

            return;
        }

        foreach ($request->attributes->get('_graphql_args') as $resourceClass => $attributes) {
            $limit = isset($attributes['first']) && is_integer($attributes['first']) ? $attributes['first'] : $this->itemsPerPage;
            $offset = isset($attributes['offset']) && is_integer($attributes['offset']) ? $attributes['offset'] : 0;

            $this->resourceClasses[$resourceClass] = [
                'limit' => $limit,
                'offset' => $offset,
            ];
        }
    }

    public function getPaginationEnabled(): bool
    {
        return $this->paginationEnabled;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * @return array<string, int>
     */
    public function getByClass(string $resourceClass): array
    {
        if (isset($this->resourceClasses[$resourceClass])) {
            return $this->resourceClasses[$resourceClass];
        }

        return [
            'limit' => $this->itemsPerPage,
            'offset' => 0,
        ];
    }
}
