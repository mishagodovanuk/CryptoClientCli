<?php

namespace App\Domain\Crypto\Support;

final class CacheKeys
{
    private const PREFIX = 'crypto';

    /**
     * @return string
     */
    public static function commonPairs(): string
    {
        return self::PREFIX . ':commonPairs';
    }

    /**
     * @param string $exchange
     * @return string
     */
    public static function prices(string $exchange): string
    {
        return self::PREFIX . ":prices:{$exchange}";
    }

    /**
     * @param string $exchange
     * @return string
     */
    public static function pairs(string $exchange): string
    {
        return self::PREFIX . ":pairs:{$exchange}";
    }
}

