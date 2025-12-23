<?php

namespace App\Console\Commands;

use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Console\Command;

class CryptoArbitrageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crypto-arbitrage-command {--min-profit=0} {--limit=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show arbitrage opportunities between exchanges';

    /**
     * Execute the console command.
     */
    public function handle(CryptoAggregator $aggregator): int
    {
        $limit = (int) $this->option('limit');

        if ($limit <= 0) {
            $limit = 20;
        }

        $minProfit = (float) $this->option('min-profit');

        if ($minProfit < 0) {
            $minProfit = 0.0;
        }

        $rows = $aggregator->arbitrage($limit, $minProfit);
        $this->line('Note: In arbitrage mode WhiteBIT uses last price (not orderbook bid/ask), to avoid hundreds of HTTP calls.');

        if (!$rows) {
            $this->warn('No arbitrage opportunities found');

            return self::SUCCESS;
        }

        $this->table(
            ['PAIR', 'BUY', 'BUY ASK', 'SELL', 'SELL BID', 'PROFIT %'],
            array_map(fn ($r) => [
                $r['pair'],
                $r['buy_exchange'],
                $r['buy_price'],
                $r['sell_exchange'],
                $r['sell_price'],
                round($r['profit_percent'], 4),
            ], $rows)
        );

        return self::SUCCESS;
    }
}
