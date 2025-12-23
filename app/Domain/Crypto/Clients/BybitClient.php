<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;

final class BybitClient extends BaseHttpClient implements ExchangeClient
{
    use ClientTools;

    public const EXCHANGE_CODE = 'bybit';
    public const EXCHANGE_NAME = 'Bybit';

    /**
     * @return array
     */
    public function listPairs(): array
    {
        $rows = $this->getArrayJsonPath(
            '/v5/market/tickers',
            ['category' => 'spot'],
            'crypto.listPairs.failed',
            'crypto.listPairs.invalid_json',
            'result.list'
        );

        if (!$rows) {
            return [];
        }

        $pairs = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $symbol = (string) ($row['symbol'] ?? '');

            if ($symbol === '') {
                continue;
            }

            $pair = Pair::fromConcat($symbol);

            if ($pair) {
                $pairs[] = $pair;
            }
        }

        return $this->finalizePairs($pairs);
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
            $responce = $this->http($this->baseUrl())->get('/v5/market/tickers', ['category' => 'spot']);

            if (!$responce->ok()) {
                \Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $responce->status(),
                ]);

                return [];
            }

            $json = $responce->json();
            $rows = $json['result']['list'] ?? null;

            if (!$this->guardArrayJson($rows, 'crypto.prices.invalid_json')) {
                return [];
            }

            $out = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $symbol = (string) ($row['symbol'] ?? '');

                if ($symbol === '') {
                    continue;
                }

                $pair = Pair::fromConcat($symbol);

                if (!$pair || !isset($need[$pair])) {
                    continue;
                }

                $last = $row['lastPrice'] ?? null;

                if ($last === null || !is_numeric($last)) {
                    continue;
                }

                $p = (float) $last;

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
            $responce = $this->http($this->baseUrl())->get('/v5/market/tickers', ['category' => 'spot']);

            if (!$responce->ok()) {
                \Log::warning('crypto.quotes.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $responce->status(),
                ]);

                return [];
            }

            $json = $responce->json();
            $rows = $json['result']['list'] ?? null;

            if (!$this->guardArrayJson($rows, 'crypto.quotes.invalid_json')) {
                return [];
            }

            $out = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $symbol = (string) ($row['symbol'] ?? '');

                if ($symbol === '') {
                    continue;
                }

                $pair = Pair::fromConcat($symbol);

                if (!$pair || !isset($need[$pair])) {
                    continue;
                }

                $bid = $row['bid1Price'] ?? null;
                $ask = $row['ask1Price'] ?? null;

                if ($bid === null || $ask === null || !is_numeric($bid) || !is_numeric($ask)) {
                    continue;
                }

                $b = (float) $bid;
                $a = (float) $ask;

                if ($b > 0 && $a > 0) {
                    $out[$pair] = ['bid' => $b, 'ask' => $a];
                }
            }

            return $out;
        });
    }

    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('crypto.exchanges.bybit.base_url');
    }
}
