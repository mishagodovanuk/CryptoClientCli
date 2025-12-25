<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Support\Facades\Log;

final class BybitClient implements ExchangeClient
{
    use ClientTools;

    private const EXCHANGE_CODE = 'bybit';
    private const EXCHANGE_NAME = 'Bybit';

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
        $rows = $this->http->getArrayJsonPath(
            $this->baseUrl(),
            '/v5/market/tickers',
            ['category' => 'spot'],
            'crypto.listPairs.failed',
            'crypto.listPairs.invalid_json',
            'result.list',
            $this->code()
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

        return $this->http->finalizePairs($pairs);
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

        return $this->http->safeArray(function () use ($need) {
            $response = $this->http->client($this->baseUrl())->get('/v5/market/tickers', ['category' => 'spot']);

            if (!$response->ok()) {
                Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $response->status(),
                ]);

                return [];
            }

            $json = $response->json();
            $rows = $json['result']['list'] ?? null;

            if (!$this->http->guardArrayJson($rows, 'crypto.prices.invalid_json', $this->code())) {
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
            $response = $this->http->client($this->baseUrl())->get('/v5/market/tickers', ['category' => 'spot']);

            if (!$response->ok()) {
                Log::warning('crypto.quotes.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $response->status(),
                ]);

                return [];
            }

            $json = $response->json();
            $rows = $json['result']['list'] ?? null;

            if (!$this->http->guardArrayJson($rows, 'crypto.quotes.invalid_json', $this->code())) {
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
        }, 'crypto.quotes.failed_bulk', $this->code());
    }

    /**
     * @return string
     */
    private function baseUrl(): string
    {
        return (string) config('crypto.exchanges.bybit.base_url');
    }
}
