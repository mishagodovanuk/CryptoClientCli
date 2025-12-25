<?php

return [
    'cache' => [
        'pairs_ttl' => 600,
        'prices_ttl' => 3,
    ],

    'require_all_exchanges' => false,

    'arbitrage' => [
        'max_profit_percent' => 50.0,
        'use_bid_ask' => true,
    ],

    'http' => [
        'timeout' => 12,
        'connect_timeout' => 7,
        'retry_times' => 2,
        'retry_sleep_ms' => 200,
    ],

    'quote_currencies' => [
        'USDT', 'USDC', 'FDUSD', 'TUSD', 'BUSD',
        'BTC', 'ETH', 'BNB',
        'EUR', 'GBP', 'JPY', 'TRY', 'BRL', 'UAH', 'AUD', 'RUB',
    ],

    'exchanges' => [
        'binance' => [
            'enabled' => true,
            'base_url' => 'https://data-api.binance.vision',
        ],
        'bybit' => [
            'enabled' => true,
            'base_url' => 'https://api.bybit.com',
        ],
        'whitebit' => [
            'enabled' => true,
            'base_url' => 'https://whitebit.com',
        ],
        'poloniex' => [
            'enabled' => true,
            'base_url' => 'https://api.poloniex.com',
        ],

        //TODO: Uncomment when service will be available.
        'jbex' => [
            'enabled' => false,
            'base_url' => 'https://api.jucoin.io',
        ],
    ],
];
