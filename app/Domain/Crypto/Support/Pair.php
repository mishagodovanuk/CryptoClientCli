<?php

namespace App\Domain\Crypto\Support;

final class Pair
{
    /**
     * @param string $pair
     * @return string
     */
    public static function normalize(string $pair): string
    {
        $pair = trim($pair);
        $pair = str_replace(['-', '_'], '/', $pair);

        return strtoupper($pair);
    }

    /**
     * @param string $symbol
     * @return string
     */
    public static function fromUnderscore(string $symbol): string
    {
        return self::normalize(str_replace('_', '/', $symbol));
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    public static function fromConcat(string $symbol): ?string
    {
        $symbol = strtoupper(trim($symbol));

        if (str_contains($symbol, '/') || str_contains($symbol, '_') || str_contains($symbol, '-')) {
            return self::normalize($symbol);
        }

        $quotes = ['USDT','USDC','FDUSD','TUSD','BUSD','BTC','ETH','EUR','GBP','JPY','TRY','BRL','UAH'];

        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                $base = substr($symbol, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return null;
    }
}
