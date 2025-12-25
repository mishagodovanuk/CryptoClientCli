<?php

namespace App\Domain\Crypto\Exceptions;

use DomainException;

/**
 * Thrown when there are not enough quotes to perform an operation.
 */
final class InsufficientQuotesException extends DomainException
{
    public static function forPair(string $pair, int $required = 2, int $actual = 0): self
    {
        return new self(
            "Insufficient quotes for pair '{$pair}'. Required: {$required}, Actual: {$actual}."
        );
    }
}

