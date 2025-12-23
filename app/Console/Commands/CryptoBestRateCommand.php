<?php

namespace App\Console\Commands;

use App\Domain\Crypto\Services\CryptoAggregator;
use Illuminate\Console\Command;

class CryptoBestRateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crypto-best-rate-command {pair : Example BTC/USDT}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show min and max price for selected pair across all exchanges';

    /**
     * Execute the console command.
     */
    public function handle(CryptoAggregator $aggregator): int
    {
        $raw = (string) $this->argument('pair');
        $pair = $this->normalizePair($raw);
        $result = $aggregator->bestRate($pair);

        if (isset($result['error'])) {
            $this->error("Error: {$result['error']} ({$result['pair']})");
            $this->line('Examples: BTC/USDT, ETH/USDT, SOL/USDT');

            return self::FAILURE;
        }

        $this->info("PAIR: {$result['pair']}");

        $this->table(
            ['TYPE', 'EXCHANGE', 'PRICE'],
            [
                ['BUY (MIN ASK)', $result['buy']['exchange'], $result['buy']['price']],
                ['SELL (MAX BID)', $result['sell']['exchange'], $result['sell']['price']],
            ]
        );

        if (!empty($result['quotes'])) {
            $this->line('All quotes:');

            $this->table(
                ['EXCHANGE', 'BID', 'ASK'],
                array_map(
                    fn ($q) => [$q['exchange'], $q['bid'], $q['ask']],
                    $result['quotes']
                )
            );
        }

        return self::SUCCESS;
    }

    /**
     * Normalize pair.
     *
     * user for unification pairs params.
     *
     * @param string $input
     * @return string
     */
    private function normalizePair(string $input): string
    {
        $s = strtoupper(trim($input));
        $s = str_replace(['-', '_', ' '], '/', $s);

        if (str_contains($s, '/')) {
            $parts = array_values(array_filter(explode('/', $s)));

            if (count($parts) >= 2) {
                return $parts[0] . '/' . $parts[1];
            }

            return $s;
        }

        $quotes = ['USDT','USDC','BUSD','FDUSD','BTC','ETH','BNB','EUR','TRY','BRL','UAH','GBP','JPY','AUD','RUB'];

        foreach ($quotes as $q) {
            if (str_ends_with($s, $q) && strlen($s) > strlen($q)) {
                $base = substr($s, 0, -strlen($q));

                return $base . '/' . $q;
            }
        }

        return $s;
    }
}
