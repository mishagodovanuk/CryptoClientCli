<?php

namespace App\Domain\Crypto\Exceptions;

use DomainException;

/**
 * Thrown when a trading pair is not found or not available.
 */
final class PairNotFoundException extends DomainException
{
    public static function forPair(string $pair): self
    {
        return new self("Trading pair '{$pair}' not found or not available on any exchange.");
    }
}

