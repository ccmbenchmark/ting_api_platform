<?php

namespace CCMBenchmark\Ting\ApiPlatform\Pagination;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use Traversable;
use IteratorAggregate;

/**
 * @template T of object
 *
 * @template-implements PaginatorInterface<T>
 * @template-implements IteratorAggregate<mixed, T>
 */
final class Paginator implements PaginatorInterface, IteratorAggregate
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

    public function getLastPage(): float
    {
        return 0;
    }

    public function getTotalItems(): float
    {
        //TODO : implements SQL_CALC_FOUND_ROWS in Ting
        return 0;
    }
}
