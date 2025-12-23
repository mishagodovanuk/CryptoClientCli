<?php

namespace App\Domain\Crypto\Services;

use App\Domain\Crypto\Contracts\ExchangeClient;
use Illuminate\Support\Facades\Cache;

final class CryptoAggregator
{
    /** @var ExchangeClient[] */
    private array $clients;

    /**
     * @param iterable $clients
     */
    public function __construct(iterable $clients)
    {
        $this->clients = is_array($clients) ? $clients : iterator_to_array($clients);
    }

    /**
     * @return array
     */
    public function commonPairs(): array
    {
        $ttl = (int) config('crypto.cache.pairs_ttl');
        $requireAll = (bool) config('crypto.require_all_exchanges', true);

        return Cache::remember('crypto:commonPairs', $ttl, function () use ($requireAll) {
            $sets = [];
            $available = [];

            foreach ($this->clients as $client) {
                try {
                    $pairs = array_values(array_unique($client->listPairs()));

                    if (count($pairs) > 0) {
                        $sets[] = $pairs;
                        $available[] = $client->code();
                    } else {
                        \Log::warning('crypto.exchange.no_pairs', ['exchange' => $client->code()]);

                        if ($requireAll) {
                            return [];
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('crypto.exchange.listPairs_failed', [
                        'exchange' => $client->code(),
                        'error' => $e->getMessage(),
                    ]);

                    if ($requireAll) {
                        return [];
                    }
                }
            }

            if (count($sets) < 2) {
                \Log::warning('crypto.commonPairs.not_enough_exchanges', [
                    'available' => $available,
                    'count' => count($sets),
                ]);

                return [];
            }

            $common = array_shift($sets);

            foreach ($sets as $s) {
                $common = array_values(array_intersect($common, $s));
            }

            sort($common);

            \Log::info('crypto.commonPairs.ready', [
                'pairs_count' => count($common),
                'available_exchanges' => $available,
            ]);

            return $common;
        });
    }

    /**
     * @param string $pair
     * @return array
     */
    public function bestRate(string $pair): array
    {
        $pair = strtoupper(trim($pair));

        $common = $this->commonPairs();

        if (!in_array($pair, $common, true)) {
            return ['error' => 'PAIR_NOT_COMMON', 'pair' => $pair];
        }

        $quotes = [];
        $exchangeNames = [];

        foreach ($this->clients as $client) {
            try {
                $exchangeNames[$client->code()] = $client->name();

                $quote = $client->quotesForPairs([$pair]);

                if (isset($quote[$pair]['bid'], $quote[$pair]['ask'])) {
                    $bid = (float) $quote[$pair]['bid'];
                    $ask = (float) $quote[$pair]['ask'];

                    if ($bid > 0 && $ask > 0) {
                        $quotes[$client->code()] = ['bid' => $bid, 'ask' => $ask];
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $client->code(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        if (count($quotes) < 2) {
            return ['error' => 'NOT_ENOUGH_QUOTES', 'pair' => $pair];
        }

        $minAsk = null;
        $minAskEx = null;

        $maxBid = null;
        $maxBidEx = null;

        foreach ($quotes as $ex => $q) {
            $bid = (float) $q['bid'];
            $ask = (float) $q['ask'];

            if ($minAsk === null || $ask < $minAsk) {
                $minAsk = $ask;
                $minAskEx = $ex;
            }

            if ($maxBid === null || $bid > $maxBid) {
                $maxBid = $bid;
                $maxBidEx = $ex;
            }
        }

        return [
            'pair' => $pair,
            'buy' => [
                'exchange' => $exchangeNames[$minAskEx] ?? $minAskEx,
                'price' => $minAsk,
            ],
            'sell' => [
                'exchange' => $exchangeNames[$maxBidEx] ?? $maxBidEx,
                'price' => $maxBid,
            ],
            'quotes' => collect($quotes)->map(function ($q, $ex) use ($exchangeNames) {
                return [
                    'exchange' => $exchangeNames[$ex] ?? $ex,
                    'bid' => (float) $q['bid'],
                    'ask' => (float) $q['ask'],
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param int $limit
     * @param float $minProfit
     * @return array
     */
    public function arbitrage(int $limit = 30, float $minProfit = 0.0): array
    {
        $pairs = $this->commonPairs();

        if (count($pairs) === 0) {
            return [];
        }

        $exchangeNames = [];
        $quoteMaps = [];

        foreach ($this->clients as $client) {
            try {
                $exchangeNames[$client->code()] = $client->name();

                if ($client->code() === 'whitebit') {
                    $prices = $client->pricesForPairs($pairs);
                    $map = [];

                    foreach ($prices as $pair => $price) {
                        $p = (float) $price;

                        if ($p > 0) {
                            $map[$pair] = ['bid' => $p, 'ask' => $p];
                        }
                    }

                    $quoteMaps[$client->code()] = $map;
                    continue;
                }

                $quoteMaps[$client->code()] = $client->quotesForPairs($pairs);
            } catch (\Throwable $e) {
                $quoteMaps[$client->code()] = [];
            }
        }

        $cap = (float) config('crypto.arbitrage.max_profit_percent', 50.0);

        $out = [];

        foreach ($pairs as $pair) {
            $minAsk = null;
            $minAskEx = null;

            $maxBid = null;
            $maxBidEx = null;

            foreach ($quoteMaps as $ex => $map) {
                if (!isset($map[$pair]['bid'], $map[$pair]['ask'])) {
                    continue;
                }

                $bid = (float) $map[$pair]['bid'];
                $ask = (float) $map[$pair]['ask'];

                if ($bid <= 0 || $ask <= 0) {
                    continue;
                }

                if ($minAsk === null || $ask < $minAsk) {
                    $minAsk = $ask;
                    $minAskEx = $ex;
                }

                if ($maxBid === null || $bid > $maxBid) {
                    $maxBid = $bid;
                    $maxBidEx = $ex;
                }
            }

            if ($minAsk === null || $maxBid === null) {
                continue;
            }

            if ($minAskEx === null || $maxBidEx === null || $minAskEx === $maxBidEx) {
                continue;
            }

            if ($maxBid <= $minAsk) {
                continue;
            }

            $profit = (($maxBid - $minAsk) / $minAsk) * 100.0;

            if ($profit + 1e-12 < $minProfit) {
                continue;
            }

            if ($profit > $cap) {
                continue;
            }

            $out[] = [
                'pair' => $pair,
                'buy_exchange' => $exchangeNames[$minAskEx] ?? $minAskEx,
                'buy_price' => $minAsk,
                'sell_exchange' => $exchangeNames[$maxBidEx] ?? $maxBidEx,
                'sell_price' => $maxBid,
                'profit_percent' => $profit,
            ];
        }

        usort($out, fn($a, $b) => $b['profit_percent'] <=> $a['profit_percent']);

        return array_slice($out, 0, max(1, $limit));
    }
}
