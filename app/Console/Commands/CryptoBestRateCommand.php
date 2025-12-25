<?php

namespace App\Console\Commands;

use App\Domain\Crypto\Exceptions\InsufficientQuotesException;
use App\Domain\Crypto\Exceptions\PairNotFoundException;
use App\Domain\Crypto\Services\CryptoAggregator;
use App\Domain\Crypto\Support\Pair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CryptoBestRateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:crypto-best-rate-command {pair : Trading pair (e.g., BTC/USDT, BTCUSDT)}';

    /**
     * @var string
     */
    protected $description = 'Show min and max price for selected pair across all exchanges';

    /**
     * @param CryptoAggregator $aggregator
     * @return int
     */
    public function handle(CryptoAggregator $aggregator): int
    {
        $raw = (string) $this->argument('pair');

        $validator = Validator::make(['pair' => $raw], [
            'pair' => ['required', 'string', 'min:3', 'max:20'],
        ]);

        if ($validator->fails()) {
            $this->error('Invalid pair format');
            $this->line('Examples: BTC/USDT, ETH/USDT, BTCUSDT');

            return self::FAILURE;
        }

        $pair = Pair::normalize($raw);

        try {
            $result = $aggregator->bestRate($pair);
            $data = $result->toArray();

            $this->info("PAIR: {$data['pair']}");

            $this->table(
                ['TYPE', 'EXCHANGE', 'PRICE'],
                [
                    ['BUY (MIN ASK)', $data['buy']['exchange'], number_format($data['buy']['price'], 2)],
                    ['SELL (MAX BID)', $data['sell']['exchange'], number_format($data['sell']['price'], 2)],
                ]
            );

            if (!empty($data['quotes'])) {
                $this->line('All quotes:');

                $this->table(
                    ['EXCHANGE', 'BID', 'ASK'],
                    array_map(
                        fn ($q) => [
                            $q['exchange'],
                            number_format($q['bid'], 2),
                            number_format($q['ask'], 2),
                        ],
                        $data['quotes']
                    )
                );
            }

            return self::SUCCESS;
        } catch (PairNotFoundException $e) {
            $this->error($e->getMessage());
            $this->line('Examples: BTC/USDT, ETH/USDT, SOL/USDT');

            return self::FAILURE;
        } catch (InsufficientQuotesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('An error occurred: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
