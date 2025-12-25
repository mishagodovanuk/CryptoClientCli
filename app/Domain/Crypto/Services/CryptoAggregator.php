<?php

namespace App\Domain\Crypto\Services;

use App\Domain\Crypto\Contracts\ExchangeClient;
use App\Domain\Crypto\Exceptions\InsufficientQuotesException;
use App\Domain\Crypto\Exceptions\PairNotFoundException;
use App\Domain\Crypto\Support\CacheKeys;
use App\Domain\Crypto\Support\Constants;
use App\Domain\Crypto\ValueObjects\ArbitrageOpportunity;
use App\Domain\Crypto\ValueObjects\BestRateResult;
use App\Domain\Crypto\ValueObjects\ExchangeQuote;
use App\Domain\Crypto\ValueObjects\Quote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CryptoAggregator
{
    /** @var ExchangeClient[] */
    private array $clients;

    public function __construct(iterable $clients)
    {
        $this->clients = is_array($clients) ? $clients : iterator_to_array($clients);
    }

    /**
     * Get common trading pairs available on all exchanges.
     *
     * @return array<string>
     */
    public function commonPairs(): array
    {
        $ttl = (int) config('crypto.cache.pairs_ttl');
        $requireAll = (bool) config('crypto.require_all_exchanges', true);

        return Cache::remember(CacheKeys::commonPairs(), $ttl, function () use ($requireAll) {
            $sets = [];
            $available = [];

            foreach ($this->clients as $client) {
                try {
                    $pairs = array_values(array_unique($client->listPairs()));

                    if (count($pairs) > 0) {
                        $sets[] = $pairs;
                        $available[] = $client->code();
                    } else {
                        Log::warning('crypto.exchange.no_pairs', ['exchange' => $client->code()]);

                        if ($requireAll) {
                            return [];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('crypto.exchange.listPairs_failed', [
                        'exchange' => $client->code(),
                        'error' => $e->getMessage(),
                    ]);

                    if ($requireAll) {
                        return [];
                    }
                }
            }

            if (count($sets) < Constants::MIN_EXCHANGES_REQUIRED) {
                Log::warning('crypto.commonPairs.not_enough_exchanges', [
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

            Log::info('crypto.commonPairs.ready', [
                'pairs_count' => count($common),
                'available_exchanges' => $available,
            ]);

            return $common;
        });
    }

    /**
     * Get best buy and sell rates for a trading pair.
     *
     * @param string $pair
     * @return BestRateResult
     * @throws PairNotFoundException
     * @throws InsufficientQuotesException
     */
    public function bestRate(string $pair): BestRateResult
    {
        $pair = strtoupper(trim($pair));

        $common = $this->commonPairs();

        if (!in_array($pair, $common, true)) {
            throw PairNotFoundException::forPair($pair);
        }

        $quotes = [];
        $exchangeNames = [];

        foreach ($this->clients as $client) {
            try {
                $exchangeNames[$client->code()] = $client->name();

                $quoteData = $client->quotesForPairs([$pair]);

                if (isset($quoteData[$pair]['bid'], $quoteData[$pair]['ask'])) {
                    $bid = (float) $quoteData[$pair]['bid'];
                    $ask = (float) $quoteData[$pair]['ask'];

                    if ($bid > 0 && $ask > 0) {
                        $quotes[] = new Quote($bid, $ask, $pair, $client->code());
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('crypto.prices.failed_bulk', [
                    'exchange' => $client->code(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        if (count($quotes) < Constants::MIN_QUOTES_REQUIRED) {
            throw InsufficientQuotesException::forPair($pair, Constants::MIN_QUOTES_REQUIRED, count($quotes));
        }

        $minAsk = null;
        $minAskQuote = null;
        $maxBid = null;
        $maxBidQuote = null;

        foreach ($quotes as $quote) {
            if ($minAsk === null || $quote->ask < $minAsk) {
                $minAsk = $quote->ask;
                $minAskQuote = $quote;
            }

            if ($maxBid === null || $quote->bid > $maxBid) {
                $maxBid = $quote->bid;
                $maxBidQuote = $quote;
            }
        }

        return new BestRateResult(
            $pair,
            new ExchangeQuote($exchangeNames[$minAskQuote->exchange] ?? $minAskQuote->exchange, $minAsk),
            new ExchangeQuote($exchangeNames[$maxBidQuote->exchange] ?? $maxBidQuote->exchange, $maxBid),
            $quotes
        );
    }

    /**
     * Find arbitrage opportunities across exchanges.
     *
     * @param int $limit
     * @param float $minProfit
     * @return ArbitrageOpportunity[]
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
                $quoteMaps[$client->code()] = $client->quotesForPairs($pairs);
            } catch (\Throwable $e) {
                Log::warning('crypto.arbitrage.client_failed', [
                    'exchange' => $client->code(),
                    'error' => $e->getMessage(),
                ]);
                $quoteMaps[$client->code()] = [];
            }
        }

        $cap = (float) config('crypto.arbitrage.max_profit_percent', 50.0);
        $opportunities = [];

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

            if ($profit + Constants::FLOATING_POINT_EPSILON < $minProfit) {
                continue;
            }

            if ($profit > $cap) {
                continue;
            }

            $opportunities[] = new ArbitrageOpportunity(
                $pair,
                $exchangeNames[$minAskEx] ?? $minAskEx,
                $minAsk,
                $exchangeNames[$maxBidEx] ?? $maxBidEx,
                $maxBid,
                $profit
            );
        }

        usort($opportunities, fn($a, $b) => $b->profitPercent <=> $a->profitPercent);

        return array_slice($opportunities, 0, max(1, $limit));
    }
}
