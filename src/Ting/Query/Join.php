<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Ting\Query;

enum Join
{
    case INNER_JOIN;
    case LEFT_JOIN;
}
