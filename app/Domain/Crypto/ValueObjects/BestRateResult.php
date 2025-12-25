<?php

namespace App\Domain\Crypto\ValueObjects;

/**
 * Result of best rate calculation.
 */
final readonly class BestRateResult
{
    /**
     * @param string $pair
     * @param ExchangeQuote $buy
     * @param ExchangeQuote $sell
     * @param Quote[] $quotes
     */
    public function __construct(
        public readonly string $pair,
        public readonly ExchangeQuote $buy,
        public readonly ExchangeQuote $sell,
        public readonly array $quotes = []
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'pair' => $this->pair,
            'buy' => $this->buy->toArray(),
            'sell' => $this->sell->toArray(),
            'quotes' => array_map(fn(Quote $q) => $q->toArray(), $this->quotes),
        ];
    }
}

