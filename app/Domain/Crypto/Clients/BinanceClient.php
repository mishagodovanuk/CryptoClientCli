<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Http\Client\ConnectionException;

final class BinanceClient implements ExchangeClient
{
    use ClientTools;

    private const EXCHANGE_CODE = 'binance';
    private const EXCHANGE_NAME = 'Binance';

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
     * @return array|string[]
     */
    public function listPairs(): array
    {
        return $this->http->safeArray(function () {
            $response = $this->http->client($this->baseUrl())->get('/api/v3/ticker/price');

            if (!$this->http->guardOk($response, 'crypto.listPairs.failed', $this->code())) {
                return [];
            }

            $data = $response->json();

            if (!$this->http->guardArrayJson($data, 'crypto.listPairs.invalid_json', $this->code())) {
                return [];
            }

            $pairs = [];

            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $symbol = $row['symbol'] ?? null;

                if (!is_string($symbol) || $symbol === '') {
                    continue;
                }

                $pair = $this->symbolToPair($symbol);

                if ($pair) {
                    $pairs[] = $pair;
                }
            }

            return $this->http->finalizePairs($pairs);
        }, 'crypto.listPairs.failed', $this->code());
    }

    /**
     * @throws ConnectionException
     */
    public function pricesForPairs(array $pairs): array
    {
        $pairs = $this->normPairs($pairs);

        if (!$pairs) {
            return [];
        }

        $ttl = (int) config('crypto.cache.prices_ttl');

        $all = $this->remember(\App\Domain\Crypto\Support\CacheKeys::prices('binance'), $ttl, function () {
            $response = $this->http->client($this->baseUrl())->get('/api/v3/ticker/price');

            if (!$response->ok()) {
                return [];
            }

            $rows = $response->json();

            if (!is_array($rows)) {
                return [];
            }

            $map = [];

            foreach ($rows as $row) {
                $symbol = (string) ($row['symbol'] ?? '');
                $price  = $row['price'] ?? null;

                $norm = Pair::fromConcat($symbol);

                if (!$norm || $price === null || !is_numeric($price)) {
                    continue;
                }

                $map[$norm] = (float) $price;
            }

            return $map;
        });

        return $this->pickPairs($all, $pairs);
    }

    /**
     * @param array $pairs
     * @return array
     */
    public function quotesForPairs(array $pairs): array
    {
        $need = $this->needMap($pairs);

        if (!$need) {
            return [];
        }

        $rows = $this->http->getArrayJson(
            $this->baseUrl(),
            '/api/v3/ticker/bookTicker',
            [],
            'crypto.quotes.failed_bulk',
            'crypto.quotes.invalid_json',
            $this->code()
        );

        if (!$rows) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $symbol = $row['symbol'] ?? null;

            if (!is_string($symbol) || $symbol === '') {
                continue;
            }

            $pair = $this->symbolToPair($symbol);

            if (!$pair || !isset($need[$pair])) {
                continue;
            }

            $bid = isset($row['bidPrice']) ? (float) $row['bidPrice'] : 0.0;
            $ask = isset($row['askPrice']) ? (float) $row['askPrice'] : 0.0;

            if ($bid > 0 && $ask > 0) {
                $out[$pair] = ['bid' => $bid, 'ask' => $ask];
            }
        }

        return $out;
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    private function symbolToPair(string $symbol): ?string
    {
        $quotes = config('crypto.quote_currencies', []);

        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                $base = substr($symbol, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    private function baseUrl(): string
    {
        return (string) config('crypto.exchanges.binance.base_url');
    }
}
