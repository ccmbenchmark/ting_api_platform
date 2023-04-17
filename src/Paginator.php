<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\State\Pagination\PaginatorInterface;

use function ceil;
use function count;

/**
 * @template T of object
 * @template-extends PartialPaginator<T>
 * @template-implements PaginatorInterface<T>
 */
final class Paginator extends PartialPaginator implements PaginatorInterface
{
    private int|null $totalItems = null;

    public function getLastPage(): float
    {
        if ($this->limit <= 0) {
            return 1.;
        }

        return ceil($this->getTotalItems() / $this->limit) ?: 1.;
    }

    public function getTotalItems(): float
    {
        return (float) ($this->totalItems ?? $this->totalItems = count($this->paginator));
    }
}
