<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\CacheKeys;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Support\Facades\Cache;

final class JbexClient implements ExchangeClient
{
    use ClientTools;

    private const EXCHANGE_CODE = 'jbex';
    private const EXCHANGE_NAME = 'jbex';

    /**
     * @param HttpClientHelper $http
     */
    public function __construct(
        private readonly HttpClientHelper $http
    ) {
    }

    /**
     * @return string
     */
    public function code(): string
    {
        return self::EXCHANGE_CODE;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return self::EXCHANGE_NAME;
    }

    /**
     * @return string
     */
    private function baseUrl(): string
    {
        return (string) config('crypto.exchanges.jbex.base_url');
    }

    /**
     * @return array|string[]
     */
    public function listPairs(): array
    {
        $ttl = (int) config('crypto.cache.pairs_ttl');

        return Cache::remember(CacheKeys::pairs('jbex'), $ttl, function () {
            $response = $this->http->client($this->baseUrl())->get('/v1/spot/public/ticker');

            if (!$response->ok()) {
                return [];
            }

            $json = $response->json();
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

        $all = Cache::remember(CacheKeys::prices('jbex'), $ttl, function () {
            $response = $this->http->client($this->baseUrl())->get('/v1/spot/public/ticker/price');

            if (!$response->ok()) {
                return [];
            }

            $json = $response->json();
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
}
