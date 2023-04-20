<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Safe\Exception;

use Exception;
use const PREG_BACKTRACK_LIMIT_ERROR;
use const PREG_BAD_UTF8_ERROR;
use const PREG_BAD_UTF8_OFFSET_ERROR;
use const PREG_INTERNAL_ERROR;
use const PREG_JIT_STACKLIMIT_ERROR;
use const PREG_RECURSION_LIMIT_ERROR;
use function preg_last_error;

final class PcreException extends Exception
{
    private const ERROR_MAP = [
        PREG_INTERNAL_ERROR => 'PREG_INTERNAL_ERROR: Internal error',
        PREG_BACKTRACK_LIMIT_ERROR => 'PREG_BACKTRACK_LIMIT_ERROR: Backtrack limit reached',
        PREG_RECURSION_LIMIT_ERROR => 'PREG_RECURSION_LIMIT_ERROR: Recursion limit reached',
        PREG_BAD_UTF8_ERROR => 'PREG_BAD_UTF8_ERROR: Invalid UTF8 character',
        PREG_BAD_UTF8_OFFSET_ERROR => 'PREG_BAD_UTF8_OFFSET_ERROR',
        PREG_JIT_STACKLIMIT_ERROR => 'PREG_JIT_STACKLIMIT_ERROR',
    ];

    public static function createFromPhpError(): self
    {
        $errMsg = self::ERROR_MAP[preg_last_error()] ?? 'Unknown PCRE error: ' . preg_last_error();

        return new self($errMsg, preg_last_error());
    }
}
