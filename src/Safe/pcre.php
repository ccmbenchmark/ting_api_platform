<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\Safe;

use CCMBenchmark\Ting\ApiPlatform\Safe\Exception\PcreException;
use const PREG_NO_ERROR;
use function error_clear_last;
use function preg_last_error;

/**
 * @param string|list<string> $pattern
 * @param string|list<string> $replacement
 * @param string|list<string> $subject
 * @return ($subject is list<string> ? list<string> : string)
 */
function preg_replace(string|array $pattern, string|array $replacement, string|array $subject, int $limit = -1, int|null &$count = null) : string|array
{
    error_clear_last();

    $result = \preg_replace($pattern, $replacement, $subject, $limit, $count);
    if (preg_last_error() !== PREG_NO_ERROR || $result === null) {
        throw PcreException::createFromPhpError();
    }

    return $result;
}
