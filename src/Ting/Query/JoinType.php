<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query;

enum JoinType
{
    case INNER_JOIN;
    case LEFT_JOIN;
}
