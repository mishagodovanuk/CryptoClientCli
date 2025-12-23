# Crypto Prices (Laravel + Sail)

Сервіс для отримання пар/котировок з бірж, розрахунку best-rate та пошуку арбітражу.

## Requirements
- Docker + Docker Compose
- (Опційно) GNU Make

## Setup (Laravel Sail)

1) Clone + install dependencies
```bash
git clone <repo>
cd crypto-prices
cp .env.example .env
Build & start containers

./vendor/bin/sail up -d


Install PHP deps (if vendor not present)
./vendor/bin/sail composer install



3)Generate app key


./vendor/bin/sail artisan key:generate


Run migrations (if used)
./vendor/bin/sail artisan migrate

Usage
1) Перевірка quotesForPairs (tinker)

./vendor/bin/sail artisan tinker --execute="\$pairs=['BTC/USDT']; foreach([\App\Domain\Crypto\Clients\BinanceClient::class,\App\Domain\Crypto\Clients\BybitClient::class,\App\Domain\Crypto\Clients\WhitebitClient::class,\App\Domain\Crypto\Clients\PoloniexClient::class] as \$cls){ \$c=app(\$cls); dump([\$c->code()=>\$c->quotesForPairs(\$pairs)]); }"

2) listPairs

./vendor/bin/sail artisan tinker --execute="foreach([\App\Domain\Crypto\Clients\BinanceClient::class,\App\Domain\Crypto\Clients\BybitClient::class,\App\Domain\Crypto\Clients\WhitebitClient::class,\App\Domain\Crypto\Clients\PoloniexClient::class] as \$cls){ \$c=app(\$cls); \$pairs=\$c->listPairs(); dump([\$c->code()=>count(\$pairs)]); }"

3) Best rate

./vendor/bin/sail artisan app:crypto-best-rate-command BTC/USDT
./vendor/bin/sail artisan app:crypto-best-rate-command btcusdt

4) Arbitrage

./vendor/bin/sail artisan app:crypto-arbitrage-command --min-profit=0 --limit=20
./vendor/bin/sail artisan app:crypto-arbitrage-command --min-profit=1 --limit=20

Run all tests

./vendor/bin/sail test


Run crypto chain integration test

./vendor/bin/sail test --filter=CryptoChainIntegrationTest

Troubleshooting

Clear caches
./vendor/bin/sail artisan optimize:clear
```
