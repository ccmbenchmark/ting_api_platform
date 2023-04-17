<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform;

use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use CCMBenchmark\Ting\ApiPlatform\Ting\Pagination\Paginator;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use IteratorAggregate;
use Traversable;

use function floor;
use function iterator_count;

/**
 * @template T of object
 * @template-implements IteratorAggregate<T>
 * @template-implements PartialPaginatorInterface<T>
 */
abstract class PartialPaginator implements IteratorAggregate, PartialPaginatorInterface
{
    protected int $offset;
    protected int $limit;
    /** @var CollectionInterface<T>|null */
    protected CollectionInterface|null $result = null;

    /** @param Paginator<T> $paginator */
    public function __construct(protected readonly Paginator $paginator)
    {
        $query = $this->paginator->getQueryBuilder();
        $this->offset = $query->getOffset();
        $this->limit = $query->getLimit() ?? 0;
    }

    /** @return Traversable<T> */
    public function getIterator(): Traversable
    {
        $this->result ??= $this->paginator->getIterator();

        return $this->result->getIterator();
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function getCurrentPage(): float
    {
        if ($this->limit <= 0) {
            return 1.;
        }

        return floor($this->offset / $this->limit) + 1.;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->limit;
    }
}
