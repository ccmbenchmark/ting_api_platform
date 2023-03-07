<?php

namespace CCMBenchmark\Ting\ApiPlatform\Pagination;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use Traversable;
use IteratorAggregate;

final class Paginator implements PartialPaginatorInterface, IteratorAggregate
{
    public float $maxResults = 0;
    public int $firstResult = 0;

    public function __construct(
        private Traversable $iterator,
        PaginationConfig $paginationConfig,
        Operation $operation,
    ) {
        if ($paginationConfig->getPaginationEnabled() === true) {
            $this->maxResults = $paginationConfig->getItemsPerPage();
            $this->firstResult = $paginationConfig->getByClass($operation->getClass() ?? '')['offset'];
        }
    }

    public function getCurrentPage(): float
    {
        if (0 >= $this->maxResults) {
            return 1.;
        }

        return floor($this->firstResult / $this->maxResults) + 1.;
    }

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
