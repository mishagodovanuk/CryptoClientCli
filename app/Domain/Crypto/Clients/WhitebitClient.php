<?php

namespace App\Domain\Crypto\Clients;

use App\Domain\Crypto\Clients\Traits\ClientTools;
use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Support\Pair;

final class WhitebitClient extends BaseHttpClient implements ExchangeClient
{
    use ClientTools;

    public const EXCHANGE_CODE = 'whitebit';
    public const EXCHANGE_NAME = 'WhiteBIT';

    /**
     * @return array
     */
    public function listPairs(): array
    {
        return $this->safeArray('crypto.listPairs.failed', function () {
            $responce = $this->http($this->baseUrl())->get('/api/v4/public/markets');

            if (!$this->guardOk($responce, 'crypto.listPairs.failed')) {
                return [];
            }

            $rows = $responce->json();

            if (!$this->guardArrayJson($rows, 'crypto.listPairs.invalid_json')) {
                return [];
            }

            $pairs = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $symbol = (string) ($row['name'] ?? '');

                if ($symbol === '') {
                    continue;
                }

                $isActive = (bool) ($row['isActive'] ?? $row['is_active'] ?? true);

                if (!$isActive) {
                    continue;
                }

                $pair = Pair::fromUnderscore($symbol);

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
            $r = $this->http($this->baseUrl())->get('/api/v4/public/ticker');

            if (!$r->ok()) {
                \Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $this->code(),
                    'status' => $r->status(),
                ]);

                return [];
            }

            $json = $r->json();

            if (!$this->guardArrayJson($json, 'crypto.prices.invalid_json')) {
                return [];
            }

            $out = [];

            foreach ($json as $symbol => $row) {
                $pair = Pair::fromUnderscore((string) $symbol);

                if (!$pair || !isset($need[$pair])) {
                    continue;
                }

                if (!is_array($row)) {
                    continue;
                }

                $last = $row['last_price'] ?? $row['lastPrice'] ?? $row['last'] ?? null;

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
        $pairs = array_values(array_unique(array_map([Pair::class, 'normalize'], $pairs)));

        if (!$pairs) {
            return [];
        }

        $out = [];

        foreach ($pairs as $pair) {
            $market = $this->pairToMarket($pair);

            if (!$market) {
                continue;
            }

            try {
                $responce = $this->http($this->baseUrl())->get('/api/v4/public/orderbook/' . $market, [
                    'limit' => 1,
                ]);

                if (!$responce->ok()) {
                    \Log::debug('crypto.quotes.whitebit.orderbook_failed', [
                        'exchange' => $this->code(),
                        'pair' => $pair,
                        'status' => $responce->status(),
                    ]);

                    continue;
                }

                $json = $responce->json();

                if (!is_array($json)) {
                    continue;
                }

                $bids = $json['bids'] ?? null;
                $asks = $json['asks'] ?? null;

                $bid = (is_array($bids) && isset($bids[0][0])) ? (float) $bids[0][0] : 0.0;
                $ask = (is_array($asks) && isset($asks[0][0])) ? (float) $asks[0][0] : 0.0;

                if ($bid > 0 && $ask > 0) {
                    $out[$pair] = ['bid' => $bid, 'ask' => $ask];
                }
            } catch (\Throwable $e) {
                \Log::debug('crypto.quotes.whitebit.orderbook_failed', [
                    'exchange' => $this->code(),
                    'pair' => $pair,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $out;
    }

    /**
     * @param string $pair
     * @return string|null
     */
    private function pairToMarket(string $pair): ?string
    {
        if (!str_contains($pair, '/')) {
            return null;
        }

        [$base, $quote] = explode('/', $pair, 2);

        if ($base === '' || $quote === '') {
            return null;
        }

        return $base . '_' . $quote;
    }

    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('crypto.exchanges.whitebit.base_url');
    }
}
