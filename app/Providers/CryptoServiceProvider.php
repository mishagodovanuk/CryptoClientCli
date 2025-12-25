<?php

namespace App\Providers;

use App\Domain\Crypto\Clients\{BinanceClient,BybitClient,WhitebitClient,PoloniexClient,JbexClient,HttpClientHelper};
use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Support\ServiceProvider;

final class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register HTTP helper as singleton (shared instance for all clients)
        $this->app->singleton(HttpClientHelper::class);

        // Register exchange clients (they will receive HttpClientHelper via DI)
        $this->app->singleton(BinanceClient::class);
        $this->app->singleton(BybitClient::class);
        $this->app->singleton(WhitebitClient::class);
        $this->app->singleton(PoloniexClient::class);
        $this->app->singleton(JbexClient::class);

        // Map exchange codes to client classes
        $exchangeMap = [
            'binance' => BinanceClient::class,
            'bybit' => BybitClient::class,
            'whitebit' => WhitebitClient::class,
            'poloniex' => PoloniexClient::class,
            'jbex' => JbexClient::class,
        ];

        $clients = [];

        foreach (config('crypto.exchanges', []) as $exchange => $config) {
            if (($config['enabled'] ?? false) && isset($exchangeMap[$exchange])) {
                $clients[] = $exchangeMap[$exchange];
            }
        }

        $this->app->tag($clients, 'crypto.clients');

        $this->app->singleton(CryptoAggregator::class, function ($app) {
            return new CryptoAggregator($app->tagged('crypto.clients'));
        });
    }
}
