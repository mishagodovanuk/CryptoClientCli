<?php

namespace App\Domain\Crypto\ValueObjects;

/**
 * Represents a bid/ask quote for a trading pair.
 */
final readonly class Quote
{
    /**
     * @param float $bid
     * @param float $ask
     * @param string $pair
     * @param string $exchange
     */
    public function __construct(
        public readonly float $bid,
        public readonly float $ask,
        public readonly string $pair,
        public readonly string $exchange
    ) {
        if ($bid <= 0 || $ask <= 0) {
            throw new \InvalidArgumentException('Bid and ask must be positive');
        }

        if ($ask < $bid) {
            throw new \InvalidArgumentException('Ask price cannot be less than bid price');
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'bid' => $this->bid,
            'ask' => $this->ask,
            'pair' => $this->pair,
            'exchange' => $this->exchange,
        ];
    }
}

