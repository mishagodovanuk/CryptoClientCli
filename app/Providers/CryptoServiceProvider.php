<?php

namespace App\Providers;

use App\Domain\Crypto\Clients\{BinanceClient,BybitClient,WhitebitClient,PoloniexClient,JbexClient};
use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Support\ServiceProvider;

final class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BinanceClient::class);
        $this->app->singleton(BybitClient::class);
        $this->app->singleton(WhitebitClient::class);
        $this->app->singleton(PoloniexClient::class);
        $this->app->singleton(JbexClient::class);

        $clients = [];

        if (config('crypto.exchanges.binance.enabled')) {
            $clients[] = BinanceClient::class;
        }

        if (config('crypto.exchanges.bybit.enabled')) {
            $clients[] = BybitClient::class;
        }

        if (config('crypto.exchanges.whitebit.enabled')) {
            $clients[] = WhitebitClient::class;
        }

        if (config('crypto.exchanges.poloniex.enabled')) {
            $clients[] = PoloniexClient::class;
        }

        if (config('crypto.exchanges.jbex.enabled')) {
            $clients[] = JbexClient::class;
        }

        $this->app->tag($clients, 'crypto.clients');

        $this->app->singleton(CryptoAggregator::class, function ($app) {
            return new CryptoAggregator($app->tagged('crypto.clients'));
        });
    }
}
