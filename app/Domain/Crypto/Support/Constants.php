<?php

namespace App\Domain\Crypto\Support;

/**
 * Domain constants for crypto operations.
 */
final class Constants
{
    /**
     * Floating point epsilon for profit calculations.
     */
    public const FLOATING_POINT_EPSILON = 1e-12;

    /**
     * Minimum number of exchanges required for common pairs calculation.
     */
    public const MIN_EXCHANGES_REQUIRED = 2;

    /**
     * Minimum number of quotes required for best rate calculation.
     */
    public const MIN_QUOTES_REQUIRED = 2;
}

