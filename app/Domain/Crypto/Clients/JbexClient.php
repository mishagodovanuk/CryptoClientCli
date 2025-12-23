<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Support\Facades\Cache;

final class JbexClient extends BaseHttpClient implements ExchangeClient
{
    use ClientTools;

    const EXCHANGE_CODE = 'jbex';
    const EXCHANGE_NAME = 'jbex';

    /**
     * @return array
     */
    public function listPairs(): array
    {
        $ttl = (int) config('crypto.cache.pairs_ttl');

        return Cache::remember('crypto:pairs:jbex', $ttl, function () {
            $responce = $this->http($this->baseUrl())->get('/v1/spot/public/ticker');

            if (!$responce->ok()) {
                return [];
            }

            $json = $responce->json();
            $rows = $json['data'] ?? $json;

            if (!is_array($rows)) {
                return [];
            }

            $pairs = [];

            foreach ($rows as $row) {
                $symbol = (string) ($row['symbol'] ?? $row['s'] ?? '');

                if ($symbol === '') {
                    continue;
                }

                $pairs[] = Pair::fromUnderscore($symbol);
            }

            return array_values(array_unique($pairs));
        });
    }

    /**
     * @param array $pairs
     * @return array
     */
    public function pricesForPairs(array $pairs): array
    {
        $pairs = array_values(array_unique(array_map([Pair::class, 'normalize'], $pairs)));

        if (!$pairs) {
            return [];
        }

        $ttl = (int) config('crypto.cache.prices_ttl');

        $all = Cache::remember('crypto:prices:jbex', $ttl, function () {
            $responce = $this->http($this->baseUrl())->get('/v1/spot/public/ticker/price');

            if (!$responce->ok()) {
                return [];
            }

            $json = $responce->json();
            $rows = $json['data'] ?? $json;

            if (!is_array($rows)) {
                return [];
            }

            $map = [];

            foreach ($rows as $row) {
                $symbol = (string) ($row['symbol'] ?? $row['s'] ?? '');
                $price  = $row['price'] ?? $row['p'] ?? null;

                if ($symbol === '' || $price === null || !is_numeric($price)) {
                    continue;
                }

                $map[Pair::fromUnderscore($symbol)] = (float) $price;
            }

            return $map;
        });

        $out = [];

        foreach ($pairs as $p) {
            if (isset($all[$p])) {
                $out[$p] = $all[$p];
            }
        }

        return $out;
    }

    /**
     * @param array $pairs
     * @return array
     */
    public function quotesForPairs(array $pairs): array
    {
        // Simple mock because Jbex is not active.
        return [];
    }

    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('crypto.exchanges.jbex.base_url');
    }
}
