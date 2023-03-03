<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Filter;

interface OrderFilterInterface
{
    public const DIRECTION_ASC = 'ASC';
    public const DIRECTION_DESC = 'DESC';
    public const NULLS_SMALLEST = 'nulls_smallest';
    public const NULLS_LARGEST = 'nulls_largest';
    public const NULLS_ALWAYS_FIRST = 'nulls_always_first';
    public const NULLS_ALWAYS_LAST = 'nulls_always_last';
    public const NULLS_DIRECTION_MAP = [
        self::NULLS_SMALLEST => [
            'ASC' => 'ASC',
            'DESC' => 'DESC',
        ],
        self::NULLS_LARGEST => [
            'ASC' => 'DESC',
            'DESC' => 'ASC',
        ],
        self::NULLS_ALWAYS_FIRST => [
            'ASC' => 'ASC',
            'DESC' => 'ASC',
        ],
        self::NULLS_ALWAYS_LAST => [
            'ASC' => 'DESC',
            'DESC' => 'DESC',
        ],
    ];
}
