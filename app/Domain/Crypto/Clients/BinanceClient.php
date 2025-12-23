<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Http\Client\ConnectionException;

final class BinanceClient extends BaseHttpClient implements ExchangeClient
{
    use ClientTools;

    public const EXCHANGE_CODE = 'binance';
    public const EXCHANGE_NAME = 'Binance';

    /**
     * @return array
     */
    public function listPairs(): array
    {
        return $this->safeArray('crypto.listPairs.failed', function () {
            $responce = $this->http($this->baseUrl())->get('/api/v3/ticker/price');

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
     * @throws ConnectionException
     */
    public function pricesForPairs(array $pairs): array
    {
        $pairs = $this->normPairs($pairs);

        if (!$pairs) {
            return [];
        }

        $ttl = (int) config('crypto.cache.prices_ttl');

        $all = $this->remember('crypto:prices:binance', $ttl, function () {
            $responce = $this->http($this->baseUrl())->get('/api/v3/ticker/price');

            if (!$responce->ok()) {
                return [];
            }

            $rows = $responce->json();

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

        $rows = $this->getArrayJson(
            '/api/v3/ticker/bookTicker',
            [],
            'crypto.quotes.failed_bulk',
            'crypto.quotes.invalid_json'
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
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('crypto.exchanges.binance.base_url');
    }

    /**
     * @param string $symbol
     * @return string|null
     */
    private function symbolToPair(string $symbol): ?string
    {
        $quotes = ['USDT','USDC','BUSD','FDUSD','BTC','ETH','BNB','EUR','TRY','BRL','UAH','GBP','JPY','AUD','RUB'];

        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                $base = substr($symbol, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return null;
    }
}
