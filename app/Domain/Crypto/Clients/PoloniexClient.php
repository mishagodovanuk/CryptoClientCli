<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;

final class PoloniexClient extends BaseHttpClient implements ExchangeClient
{
    use ClientTools;

    const EXCHANGE_CODE = 'poloniex';
    const EXCHANGE_NAME = 'Poloniex';

    /**
     * @return array
     */
    public function listPairs(): array
    {
        return $this->safeArray('crypto.listPairs.failed', function () {
            $responce = $this->http($this->baseUrl())->get('/markets/ticker24h');

            if (!$this->guardOk($responce, 'crypto.listPairs.failed')) {
                return [];
            }

            $data = $responce->json();

            if (!$this->guardArrayJson($data, 'crypto.listPairs.invalid_json')) {
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

            return $this->finalizePairs($pairs);
        });
    }

    /**
     * @param array $pairs
     * @return array
     */
    public function pricesForPairs(array $pairs): array
    {
        $need = $this->needMap($pairs);

        if (!$need) {
            return [];
        }

        return $this->safeArray('crypto.prices.failed_bulk', function () use ($need) {
            $responce = $this->http($this->baseUrl())->get('/markets/ticker24h');

            if (!$responce->ok()) {
                \Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $responce->status(),
                ]);

                return [];
            }

            $data = $responce->json();

            if (!$this->guardArrayJson($data, 'crypto.prices.invalid_json')) {
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
        });
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

        return $this->safeArray('crypto.quotes.failed_bulk', function () use ($need) {
            $responce = $this->http($this->baseUrl())->get('/markets/ticker24h');

            if (!$responce->ok()) {
                \Log::warning('crypto.quotes.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $responce->status(),
                ]);

                return [];
            }

            $data = $responce->json();

            if (!$this->guardArrayJson($data, 'crypto.quotes.invalid_json')) {
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
        });
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

    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('crypto.exchanges.poloniex.base_url');
    }
}
