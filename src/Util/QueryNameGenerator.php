<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Util;

interface QueryNameGenerator
{
    public function generateJoinAlias(string $association): string;

    public function generateParameterName(string $name): string;
}
