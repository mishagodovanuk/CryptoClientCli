<?php

namespace App\Domain\Crypto\ValueObjects;

/**
 * Represents a quote from a specific exchange.
 */
final readonly class ExchangeQuote
{
    /**
     * @param string $exchange
     * @param float $price
     */
    public function __construct(
        public readonly string $exchange,
        public readonly float $price
    ) {
        if ($price <= 0) {
            throw new \InvalidArgumentException('Price must be positive');
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'exchange' => $this->exchange,
            'price' => $this->price,
        ];
    }
}

