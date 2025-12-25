# Crypto Prices Aggregator

A Laravel application that aggregates cryptocurrency prices from multiple exchanges, calculates best rates, and identifies arbitrage opportunities.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [How It Works](#how-it-works)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Development](#development)

## 🎯 Overview

This service fetches real-time cryptocurrency prices from multiple exchanges (Binance, Bybit, WhiteBIT, Poloniex) and provides:

1. **Best Rate Finder**: Find the best buy and sell prices for any trading pair across all exchanges
2. **Arbitrage Scanner**: Identify profitable arbitrage opportunities between exchanges

### Supported Exchanges

- ✅ **Binance** 
- ✅ **Bybit**
- ✅ **WhiteBIT**
- ✅ **Poloniex**
- ⚠️ **Jbex** - Currently disabled (can be enabled in config)

## ✨ Features

- 🔄 **Real-time Price Aggregation**: Fetches live prices from multiple exchanges
- 📊 **Best Rate Calculation**: Finds optimal buy/sell prices across exchanges
- 💰 **Arbitrage Detection**: Identifies profitable trading opportunities
- 🚀 **High Performance**: Caching and optimized HTTP requests
- 🛡️ **Type Safety**: Value Objects and DTOs for type-safe operations
- 🏗️ **Clean Architecture**: Domain-driven design with clear separation of concerns
- 🔌 **Extensible**: Easy to add new exchanges via interface implementation

## 🏗️ Architecture

### Domain-Driven Design

The application follows Domain-Driven Design principles with clear layer separation:

```
app/Domain/Crypto/
├── Clients/              # Exchange API clients
│   ├── BinanceClient.php
│   ├── BybitClient.php
│   ├── WhitebitClient.php
│   ├── PoloniexClient.php
│   ├── HttpClientHelper.php  # HTTP utilities (composition)
│   └── Traits/
│       └── ClientTools.php   # Shared pair utilities
├── Contracts/            # Interfaces
│   └── ExchangeClient.php    # Exchange client contract
├── Services/             # Business logic
│   └── CryptoAggregator.php  # Main aggregation service
├── ValueObjects/         # Type-safe data structures
│   ├── Quote.php
│   ├── ExchangeQuote.php
│   ├── BestRateResult.php
│   └── ArbitrageOpportunity.php
├── Exceptions/           # Domain exceptions
│   ├── ExchangeClientException.php
│   ├── PairNotFoundException.php
│   └── InsufficientQuotesException.php
└── Support/             # Utilities
    ├── Pair.php             # Pair normalization
    ├── CacheKeys.php        # Cache key management
    └── Constants.php        # Domain constants
```

## 🔄 How It Works

### Flow Diagram

```
┌─────────────────┐
│  User Command   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Command Class  │  Validates input, formats output
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│CryptoAggregator │  Orchestrates exchange clients
└────────┬────────┘
         │
         ├─────────────────┬─────────────────┬──────────────┐
         ▼                 ▼                 ▼              ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│BinanceClient │  │ BybitClient  │  │WhitebitClient│  │PoloniexClient│
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                  │                  │                  │
       └──────────────────┴──────────────────┴──────────────────┘
                          │
                          ▼
                 ┌─────────────────┐
                 │ HttpClientHelper │  Makes HTTP requests
                 └─────────────────┘
                          │
                          ▼
                 ┌─────────────────┐
                 │ Exchange APIs    │  External exchange APIs
                 └─────────────────┘
```

### Detailed Flow

#### 1. **Best Rate Command Flow**

```
User: php artisan app:crypto-best-rate-command BTC/USDT
  │
  ├─> Command validates input
  │
  ├─> Normalizes pair (BTC/USDT)
  │
  ├─> Calls CryptoAggregator::bestRate()
  │   │
  │   ├─> Gets common pairs (cached)
  │   │
  │   ├─> Validates pair exists
  │   │
  │   ├─> Iterates through all exchange clients
  │   │   │
  │   │   ├─> BinanceClient::quotesForPairs(['BTC/USDT'])
  │   │   │   └─> HTTP GET /api/v3/ticker/bookTicker
  │   │   │
  │   │   ├─> BybitClient::quotesForPairs(['BTC/USDT'])
  │   │   │   └─> HTTP GET /v5/market/tickers
  │   │   │
  │   │   └─> ... (other exchanges)
  │   │
  │   ├─> Finds minimum ASK (best buy price)
  │   │
  │   ├─> Finds maximum BID (best sell price)
  │   │
  │   └─> Returns BestRateResult (Value Object)
  │
  └─> Command formats and displays results
```

#### 2. **Arbitrage Command Flow**

```
User: php artisan app:crypto-arbitrage-command --min-profit=0.1 --limit=10
  │
  ├─> Command validates options
  │
  ├─> Calls CryptoAggregator::arbitrage()
  │   │
  │   ├─> Gets all common pairs (cached)
  │   │
  │   ├─> For each exchange:
  │   │   └─> Fetches quotes for all pairs
  │   │
  │   ├─> For each pair:
  │   │   ├─> Finds minimum ASK across exchanges
  │   │   ├─> Finds maximum BID across exchanges
  │   │   ├─> Calculates profit percentage
  │   │   └─> Filters by min-profit threshold
  │   │
  │   ├─> Sorts by profit (descending)
  │   │
  │   └─> Returns array of ArbitrageOpportunity
  │
  └─> Command displays table of opportunities
```

### Caching Strategy

- **Common Pairs**: Cached for 600 seconds (10 minutes)
  - Key: `crypto:commonPairs`
  - Invalidated when exchange list changes

- **Price Data**: Cached for 3 seconds
  - Key: `crypto:prices:{exchange}`
  - Short TTL for near real-time data

- **Pair Lists**: Cached per exchange
  - Key: `crypto:pairs:{exchange}`
  - TTL: 600 seconds

## 📦 Installation

### Prerequisites

- PHP 8.2+
- Composer
- Docker & Docker Compose (for Laravel Sail)
- Or: MySQL/PostgreSQL, Redis (optional)

### Quick Start with Laravel Sail

```bash
# Clone repository
git clone <repository-url>
cd crypto-prices

# Copy environment file
cp .env.example .env

# Start Docker containers
./vendor/bin/sail up -d

# Install dependencies
./vendor/bin/sail composer install

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations (if using database cache)
./vendor/bin/sail artisan migrate

# Set cache driver (if Redis not available)
# Edit .env: CACHE_STORE=file
```

### Manual Installation

```bash
# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure cache (choose one):
# - File: CACHE_STORE=file
# - Database: CACHE_STORE=database && php artisan migrate
# - Redis: CACHE_STORE=redis (requires Redis)
```

## ⚙️ Configuration

### Environment Variables

```env
# Cache Configuration
CACHE_STORE=file  # Options: file, database, redis, array

# Application
APP_NAME="Crypto Prices"
APP_ENV=local
```

### Crypto Configuration (`config/crypto.php`)

```php
return [
    // Cache TTLs (in seconds)
    'cache' => [
        'pairs_ttl' => 600,    // Common pairs cache
        'prices_ttl' => 3,     // Price data cache
    ],

    // Require all exchanges to be available
    'require_all_exchanges' => false,

    // Arbitrage settings
    'arbitrage' => [
        'max_profit_percent' => 50.0,  // Filter unrealistic profits
        'use_bid_ask' => true,
    ],

    // HTTP client settings
    'http' => [
        'timeout' => 12,           // Request timeout (seconds)
        'connect_timeout' => 7,    // Connection timeout (seconds)
        'retry_times' => 2,        // Number of retries
        'retry_sleep_ms' => 200,   // Delay between retries (ms)
    ],

    // Supported quote currencies
    'quote_currencies' => [
        'USDT', 'USDC', 'FDUSD', 'TUSD', 'BUSD',
        'BTC', 'ETH', 'BNB',
        'EUR', 'GBP', 'JPY', 'TRY', 'BRL', 'UAH', 'AUD', 'RUB',
    ],

    // Exchange configurations
    'exchanges' => [
        'binance' => [
            'enabled' => true,
            'base_url' => 'https://data-api.binance.vision',
        ],
        // ... other exchanges
    ],
];
```

## 🚀 Usage

### Best Rate Command

Find the best buy and sell prices for a trading pair:

```bash
# Using slash format
php artisan app:crypto-best-rate-command BTC/USDT

# Using concatenated format
php artisan app:crypto-best-rate-command BTCUSDT

# Other examples
php artisan app:crypto-best-rate-command ETH/USDT
php artisan app:crypto-best-rate-command SOL/USDT
```

**Output:**
```
PAIR: BTC/USDT
+----------------+----------+----------+
| TYPE           | EXCHANGE | PRICE    |
+----------------+----------+----------+
| BUY (MIN ASK)  | Poloniex | 87807.55 |
| SELL (MAX BID) | Binance  | 87822.96 |
+----------------+----------+----------+
All quotes:
+----------+----------+----------+
| EXCHANGE | BID      | ASK      |
+----------+----------+----------+
| Binance  | 87822.96 | 87822.97 |
| Bybit    | 87821.1  | 87821.2  |
...
```

### Arbitrage Command

Find arbitrage opportunities:

```bash
# Default (min-profit=0, limit=20)
php artisan app:crypto-arbitrage-command

# With minimum profit filter
php artisan app:crypto-arbitrage-command --min-profit=0.1 --limit=10

# Find only high-profit opportunities
php artisan app:crypto-arbitrage-command --min-profit=1.0 --limit=5
```

**Output:**
```
+--------+----------+----------+----------+----------+-----------+
| PAIR   | BUY      | BUY ASK  | SELL     | SELL BID | PROFIT %  |
+--------+----------+----------+----------+----------+-----------+
| BTC/USDT| Poloniex| 87807.55 | Binance  | 87822.96 | 0.0175    |
| ETH/USDT| WhiteBIT| 2450.12  | Bybit    | 2452.45  | 0.0951    |
...
```

### Using in Code

```php
use App\Domain\Crypto\Services\CryptoAggregator;
use App\Domain\Crypto\Exceptions\PairNotFoundException;

// Get best rate
try {
    $result = app(CryptoAggregator::class)->bestRate('BTC/USDT');
    
    echo "Best buy: {$result->buy->exchange} at {$result->buy->price}\n";
    echo "Best sell: {$result->sell->exchange} at {$result->sell->price}\n";
    
    foreach ($result->quotes as $quote) {
        echo "{$quote->exchange}: bid={$quote->bid}, ask={$quote->ask}\n";
    }
} catch (PairNotFoundException $e) {
    echo "Pair not found: " . $e->getMessage();
}

// Find arbitrage opportunities
$opportunities = app(CryptoAggregator::class)->arbitrage(
    limit: 10,
    minProfit: 0.1
);

foreach ($opportunities as $opp) {
    echo "{$opp->pair}: Buy on {$opp->buyExchange} at {$opp->buyPrice}, ";
    echo "Sell on {$opp->sellExchange} at {$opp->sellPrice}, ";
    echo "Profit: {$opp->profitPercent}%\n";
}
```

## 📚 API Reference

### ExchangeClient Interface

All exchange clients implement this interface:

```php
interface ExchangeClient
{
    public function code(): string;
    public function name(): string;
    public function listPairs(): array;
    public function pricesForPairs(array $pairs): array;
    public function quotesForPairs(array $pairs): array;
}
```

### CryptoAggregator Service

#### `commonPairs(): array`

Returns array of trading pairs available on all enabled exchanges.

```php
$pairs = $aggregator->commonPairs();
// Returns: ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', ...]
```

#### `bestRate(string $pair): BestRateResult`

Finds best buy and sell rates for a pair.

**Parameters:**
- `$pair` (string): Normalized trading pair (e.g., 'BTC/USDT')

**Returns:** `BestRateResult` value object

**Throws:**
- `PairNotFoundException`: If pair not available
- `InsufficientQuotesException`: If not enough quotes

#### `arbitrage(int $limit, float $minProfit): array`

Finds arbitrage opportunities.

**Parameters:**
- `$limit` (int): Maximum opportunities to return (default: 30)
- `$minProfit` (float): Minimum profit percentage (default: 0.0)

**Returns:** Array of `ArbitrageOpportunity` value objects

### Value Objects

#### Quote

```php
new Quote(
    bid: 87822.96,
    ask: 87822.97,
    pair: 'BTC/USDT',
    exchange: 'binance'
);
```

#### BestRateResult

```php
new BestRateResult(
    pair: 'BTC/USDT',
    buy: new ExchangeQuote('poloniex', 87807.55),
    sell: new ExchangeQuote('binance', 87822.96),
    quotes: [...]
);
```

#### ArbitrageOpportunity

```php
new ArbitrageOpportunity(
    pair: 'BTC/USDT',
    buyExchange: 'poloniex',
    buyPrice: 87807.55,
    sellExchange: 'binance',
    sellPrice: 87822.96,
    profitPercent: 0.0175
);
```

## 🧪 Development

### Running Tests

```bash
# All tests
./vendor/bin/sail test

# Specific test
./vendor/bin/sail test --filter=CryptoChainIntegrationTest

# With coverage
./vendor/bin/sail test --coverage
```

### Adding a New Exchange

1. **Create Client Class:**

```php
final class NewExchangeClient implements ExchangeClient
{
    use ClientTools;

    private const EXCHANGE_CODE = 'newexchange';
    private const EXCHANGE_NAME = 'New Exchange';

    public function __construct(
        private readonly HttpClientHelper $http
    ) {
    }

    public function code(): string
    {
        return self::EXCHANGE_CODE;
    }

    public function name(): string
    {
        return self::EXCHANGE_NAME;
    }

    private function baseUrl(): string
    {
        return (string) config('crypto.exchanges.newexchange.base_url');
    }

    public function listPairs(): array
    {
        // Implement API call to get pairs
    }

    public function pricesForPairs(array $pairs): array
    {
        // Implement API call to get prices
    }

    public function quotesForPairs(array $pairs): array
    {
        // Implement API call to get bid/ask quotes
    }
}
```

2. **Register in Service Provider:**

```php
// app/Providers/CryptoServiceProvider.php
$exchangeMap = [
    // ... existing exchanges
    'newexchange' => NewExchangeClient::class,
];
```

3. **Add Configuration:**

```php
// config/crypto.php
'exchanges' => [
    // ... existing exchanges
    'newexchange' => [
        'enabled' => true,
        'base_url' => 'https://api.newexchange.com',
    ],
],
```

### Code Quality

```bash
# Run Laravel Pint (code formatter)
./vendor/bin/sail pint

# Run static analysis (if configured)
./vendor/bin/sail phpstan
```

## 🔍 Troubleshooting

### Cache Issues

If you see Redis errors:

```bash
# Use file cache instead
# Edit .env: CACHE_STORE=file
```

### Exchange API Errors

- Check exchange API status
- Verify base URLs in config
- Check rate limits
- Review logs: `storage/logs/laravel.log`

### No Arbitrage Opportunities

- Normal - real arbitrage is rare
- Try lowering `--min-profit` to 0
- Check if exchanges are responding
- Verify pairs are common across exchanges

## 📝 License

MIT License

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📧 Support

For issues and questions, please open an issue on GitHub.

---

**Built with Laravel 12** 🚀
