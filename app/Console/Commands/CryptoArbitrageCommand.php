<?php

namespace App\Console\Commands;

use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CryptoArbitrageCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:crypto-arbitrage-command
                            {--min-profit=0 : Minimum profit percentage required}
                            {--limit=20 : Maximum number of opportunities to show}';

    /**
     * @var string
     */
    protected $description = 'Show arbitrage opportunities between exchanges';

    /**
     * @param CryptoAggregator $aggregator
     * @return int
     */
    public function handle(CryptoAggregator $aggregator): int
    {
        $validator = Validator::make([
            'limit' => $this->option('limit'),
            'min-profit' => $this->option('min-profit'),
        ], [
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
            'min-profit' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            $this->error('Invalid options:');

            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $minProfit = (float) $this->option('min-profit');

        try {
            $opportunities = $aggregator->arbitrage($limit, $minProfit);

            if (empty($opportunities)) {
                $this->warn('No arbitrage opportunities found');

                return self::SUCCESS;
            }

            $this->table(
                ['PAIR', 'BUY', 'BUY ASK', 'SELL', 'SELL BID', 'PROFIT %'],
                array_map(fn ($opp) => [
                    $opp->pair,
                    $opp->buyExchange,
                    number_format($opp->buyPrice, 2),
                    $opp->sellExchange,
                    number_format($opp->sellPrice, 2),
                    number_format($opp->profitPercent, 4),
                ], $opportunities)
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('An error occurred: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
