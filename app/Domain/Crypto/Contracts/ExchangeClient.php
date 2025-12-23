<?php

namespace App\Domain\Crypto\Contracts;

interface ExchangeClient
{
    /**
     * @return array
     */
    public function listPairs(): array;

    /**
     * @param array $pairs
     * @return array
     */
    public function pricesForPairs(array $pairs): array;

    /**
     * @param array $pairs
     * @return array
     */
    public function quotesForPairs(array $pairs): array;
}
