<?php

namespace App\Domain\Crypto\Clients\Traits;

use App\Domain\Crypto\Support\Pair;
use Illuminate\Support\Facades\Cache;

trait ClientTools
{
    /**
     * Norm pair.
     *
     * For normalize pairs.
     *
     * @param array $pairs
     * @return array
     */
    protected function normPairs(array $pairs): array
    {
        $pairs = array_map([Pair::class, 'normalize'], $pairs);
        $pairs = array_values(array_unique(array_filter($pairs)));
        sort($pairs);

        return $pairs;
    }

    /**
     * @param array $pairs
     * @return array
     */
    protected function needMap(array $pairs): array
    {
        return array_flip($this->normPairs($pairs));
    }

    /**
     * Used to cache ttl.
     *
     * @param string $key
     * @param int $ttl
     * @param callable $fn
     * @return mixed
     */
    protected function remember(string $key, int $ttl, callable $fn): mixed
    {
        if ($ttl <= 0) {
            return $fn();
        }

        return Cache::remember($key, $ttl, $fn);
    }

    /**
     * Pick pairs.
     *
     * @param array $allMap
     * @param array $pairs
     * @return array
     */
    protected function pickPairs(array $allMap, array $pairs): array
    {
        $out = [];

        foreach ($this->normPairs($pairs) as $p) {
            if (isset($allMap[$p])) {
                $out[$p] = $allMap[$p];
            }
        }

        return $out;
    }
}
