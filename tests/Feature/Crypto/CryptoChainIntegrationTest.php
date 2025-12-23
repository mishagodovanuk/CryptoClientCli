<?php

namespace Tests\Feature\Crypto;

use App\Domain\Crypto\Clients\BinanceClient;
use App\Domain\Crypto\Clients\BybitClient;
use App\Domain\Crypto\Clients\PoloniexClient;
use App\Domain\Crypto\Clients\WhitebitClient;
use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CryptoChainIntegrationTest extends TestCase
{
    private const PAIR = 'BTC/USDT';

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoInternet();
    }

    private function skipIfNoInternet(): void
    {
        try {
            $request = Http::withOptions([
                'verify' => true,
            ])
                ->timeout(15)
                ->connectTimeout(5)
                ->retry(0, 0)
                ->get('https://data-api.binance.vision/api/v3/time');

            if (!$request->ok()) {
                $this->markTestSkipped('Probe not OK: ' . $request->status());
            }

            $json = $request->json();
            if (!is_array($json) || !isset($json['serverTime'])) {
                $this->markTestSkipped('Probe unexpected response');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('No network доступний з контейнера: ' . $e->getMessage());
        }
    }

    public function test_clients_quotes_for_pairs_return_bid_ask(): void
    {
        $clients = [
            BinanceClient::class,
            BybitClient::class,
            WhitebitClient::class,
            PoloniexClient::class,
        ];

        foreach ($clients as $client) {
            $c = app($client);

            $quotes = $c->quotesForPairs([self::PAIR]);

            $this->assertIsArray($quotes, $client . ': quotesForPairs must return array');
            $this->assertArrayHasKey(self::PAIR, $quotes, $client . ': missing ' . self::PAIR);

            $this->assertIsArray($quotes[self::PAIR], $client . ': quote must be array');
            $this->assertArrayHasKey('bid', $quotes[self::PAIR], $client . ': missing bid');
            $this->assertArrayHasKey('ask', $quotes[self::PAIR], $client . ': missing ask');

            $this->assertIsNumeric($quotes[self::PAIR]['bid'], $client . ': bid not numeric');
            $this->assertIsNumeric($quotes[self::PAIR]['ask'], $client . ': ask not numeric');

            $this->assertGreaterThan(0, (float)$quotes[self::PAIR]['bid'], $client . ': bid <= 0');
            $this->assertGreaterThan(0, (float)$quotes[self::PAIR]['ask'], $client . ': ask <= 0');
        }
    }

    public function test_clients_list_pairs_returns_non_empty(): void
    {
        $clients = [
            BinanceClient::class,
            BybitClient::class,
            WhitebitClient::class,
            PoloniexClient::class,
        ];

        foreach ($clients as $client) {
            $c = app($client);

            $pairs = $c->listPairs();

            $this->assertIsArray($pairs, $client . ': listPairs must return array');
            $this->assertNotEmpty($pairs, $client . ': listPairs is empty');

            $this->assertTrue(
                (bool) preg_match('~^[A-Z0-9]+\/[A-Z0-9]+$~', (string) $pairs[0]),
                $client . ': unexpected pair format, example=' . (string) $pairs[0]
            );
        }
    }

    public function test_aggregator_common_pairs_contains_btc_usdt(): void
    {
        $a = app(CryptoAggregator::class);

        $pairs = $a->commonPairs();

        $this->assertIsArray($pairs);
        $this->assertNotEmpty($pairs);
        $this->assertContains(self::PAIR, $pairs, 'commonPairs missing BTC/USDT');
    }

    public function test_best_rate_command_runs_for_btc_usdt(): void
    {
        $code = Artisan::call('app:crypto-best-rate-command', ['pair' => self::PAIR]);

        $this->assertSame(0, $code, 'best-rate command failed');

        $out = Artisan::output();

        $this->assertNotEmpty(trim($out));
    }

    public function test_arbitrage_command_runs(): void
    {
        $code = Artisan::call('app:crypto-arbitrage-command', [
            '--min-profit' => 0,
            '--limit' => 20,
        ]);

        $this->assertSame(0, $code, 'arbitrage command failed');

        $out = Artisan::output();
        $this->assertNotEmpty(trim($out));
    }
}

