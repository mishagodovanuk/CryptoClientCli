<?php

namespace App\Domain\Crypto\Support;

final class Pair
{
    /**
     * Normalize trading pair string to standard format (BASE/QUOTE).
     *
     * Handles various formats:
     * - BTC/USDT -> BTC/USDT
     * - BTC-USDT -> BTC/USDT
     * - BTC_USDT -> BTC/USDT
     * - BTCUSDT -> BTC/USDT
     *
     * @param string $pair
     * @return string
     */
    public static function normalize(string $pair): string
    {
        $s = strtoupper(trim($pair));
        $s = str_replace(['-', '_', ' '], '/', $s);

        if (str_contains($s, '/')) {
            $parts = array_values(array_filter(explode('/', $s)));

            if (count($parts) >= 2) {
                return $parts[0] . '/' . $parts[1];
            }

            return $s;
        }

        $quotes = config('crypto.quote_currencies', []);

        foreach ($quotes as $q) {
            if (str_ends_with($s, $q) && strlen($s) > strlen($q)) {
                $base = substr($s, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return $s;
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

        $quotes = config('crypto.quote_currencies', []);

        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                $base = substr($symbol, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return null;
    }
}
