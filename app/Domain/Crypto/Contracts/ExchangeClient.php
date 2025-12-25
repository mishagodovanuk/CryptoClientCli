<?php

namespace App\Domain\Crypto\Contracts;

/**
 * Contract for exchange clients.
 */
interface ExchangeClient
{
    /**
     * Get the exchange code ('binance', 'bybit').
     */
    public function code(): string;

    /**
     * Get the exchange display name ('Binance', 'Bybit').
     */
    public function name(): string;

    /**
     * List all available trading pairs on this exchange.
     *
     * @return array<string>
     */
    public function listPairs(): array;

    /**
     * Get last prices for specified pairs.
     *
     * @param array<string> $pairs
     * @return array<string, float>
     */
    public function pricesForPairs(array $pairs): array;

    /**
     * Get bid/ask quotes for specified pairs.
     *
     * @param array<string> $pairs
     * @return array<string, array{bid: float, ask: float}>
     */
    public function quotesForPairs(array $pairs): array;
}
