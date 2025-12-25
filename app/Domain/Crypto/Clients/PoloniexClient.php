<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use Illuminate\Support\Facades\Log;

final class PoloniexClient implements ExchangeClient
{
    use ClientTools;

    private const EXCHANGE_CODE = 'poloniex';
    private const EXCHANGE_NAME = 'Poloniex';

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
        return (string) config('crypto.exchanges.poloniex.base_url');
    }

    /**
     * @return array|string[]
     */
    public function listPairs(): array
    {
        return $this->http->safeArray(function () {
            $response = $this->http->client($this->baseUrl())->get('/markets/ticker24h');

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
     * @param array $pairs
     * @return array|float[]
     */
    public function pricesForPairs(array $pairs): array
    {
        $need = $this->needMap($pairs);

        if (!$need) {
            return [];
        }

        return $this->http->safeArray(function () use ($need) {
            $response = $this->http->client($this->baseUrl())->get('/markets/ticker24h');

            if (!$response->ok()) {
                Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            if (!$this->http->guardArrayJson($data, 'crypto.prices.invalid_json', $this->code())) {
                return [];
            }

            $out = [];

            foreach ($data as $row) {
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

                $price = $row['close'] ?? $row['last'] ?? null;

                if ($price === null) {
                    continue;
                }

                $p = (float) $price;

                if ($p > 0) {
                    $out[$pair] = $p;
                }
            }

            return $out;
        }, 'crypto.prices.failed_bulk', $this->code());
    }

    /**
     * @param array $pairs
     * @return array|array[]
     */
    public function quotesForPairs(array $pairs): array
    {
        $need = $this->needMap($pairs);

        if (!$need) {
            return [];
        }

        return $this->http->safeArray(function () use ($need) {
            $response = $this->http->client($this->baseUrl())->get('/markets/ticker24h');

            if (!$response->ok()) {
                Log::warning('crypto.quotes.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            if (!$this->http->guardArrayJson($data, 'crypto.quotes.invalid_json', $this->code())) {
                return [];
            }

            $out = [];

            foreach ($data as $row) {
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

                $bid = isset($row['bid']) ? (float) $row['bid'] : 0.0;
                $ask = isset($row['ask']) ? (float) $row['ask'] : 0.0;

                if ($bid > 0 && $ask > 0) {
                    $out[$pair] = ['bid' => $bid, 'ask' => $ask];
                }
            }

            return $out;
        }, 'crypto.quotes.failed_bulk', $this->code());
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    private function symbolToPair(string $symbol): ?string
    {
        if (str_contains($symbol, '_')) {
            [$base, $quote] = explode('_', $symbol, 2);

            if ($base !== '' && $quote !== '') {
                return $base . '/' . $quote;
            }
        }

        return null;
    }
}
