<?php

namespace App\Domain\Crypto\ValueObjects;

/**
 * Represents an arbitrage opportunity between exchanges.
 */
final readonly class ArbitrageOpportunity
{
    /**
     * @param string $pair
     * @param string $buyExchange
     * @param float $buyPrice
     * @param string $sellExchange
     * @param float $sellPrice
     * @param float $profitPercent
     */
    public function __construct(
        public readonly string $pair,
        public readonly string $buyExchange,
        public readonly float $buyPrice,
        public readonly string $sellExchange,
        public readonly float $sellPrice,
        public readonly float $profitPercent
    ) {
        if ($buyPrice <= 0 || $sellPrice <= 0) {
            throw new \InvalidArgumentException('Prices must be positive');
        }

        if ($profitPercent < 0) {
            throw new \InvalidArgumentException('Profit percent cannot be negative');
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'pair' => $this->pair,
            'buy_exchange' => $this->buyExchange,
            'buy_price' => $this->buyPrice,
            'sell_exchange' => $this->sellExchange,
            'sell_price' => $this->sellPrice,
            'profit_percent' => $this->profitPercent,
        ];
    }
}

