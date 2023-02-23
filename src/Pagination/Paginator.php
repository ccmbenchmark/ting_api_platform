<?php

namespace CCMBenchmark\Ting\ApiPlatform\Pagination;

use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use Traversable;
use IteratorAggregate;

class Paginator implements PartialPaginatorInterface, IteratorAggregate
{
    public $maxResults = 0;
    public $firstResult = 0;
    public $totalItems = 0;

    public function __construct(
        private Traversable $iterator
    ) {
    }

    public function getCurrentPage(): float
    {
        if (0 >= $this->maxResults) {
            return 1.;
        }

        return floor($this->firstResult / $this->maxResults) + 1.;
    }

    public function getLastPage(): float
    {
        if (0 >= $this->maxResults) {
            return 1.;
        }

        return ceil($this->getTotalItems() / $this->maxResults) ?: 1.;
    }

    public function getTotalItems(): float
    {
        return (float) ($this->totalItems ?? $this->totalItems = \count($this->paginator));
    }

    /**
     * Gets the number of items by page.
     */
    public function getItemsPerPage(): float
    {
        return (float) $this->maxResults;
    }

    public function getIterator(): Traversable
    {
        return $this->iterator;
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }
}
