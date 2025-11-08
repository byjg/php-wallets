# PHP Wallets

[![Build Status](https://github.com/byjg/php-wallets/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-wallets/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](http://opensource.byjg.com)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-wallets/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-wallets.svg)](https://opensource.byjg.com/opensource/licensing.html)
[![GitHub release](https://img.shields.io/github/release/byjg/php-wallets.svg)](https://github.com/byjg/php-wallets/releases/)

A robust PHP library for managing digital wallets and financial transactions with full audit trails, transaction chain integrity, and support for reserved funds.

## Features

- **Multiple wallets per user** - Each user can have multiple wallets for different currencies
- **Multiple currencies** - Support for any currency type (fiat, crypto, points, etc.)
- **Reserved funds** - Pre-authorize and block funds for pending transactions
- **Transaction chain integrity** - Immutable transaction history with UUID linking and checksums
- **Atomic operations** - All balance updates are atomic and transactional
- **Flexible scale** - Support for different decimal places (cents, satoshis, whole units)
- **Overdraft support** - Optional negative balances with configurable minimum values
- **Audit trail** - Complete transaction history with balance snapshots
- **Idempotent operations** - UUID-based transaction deduplication
- **Extensible** - Easily extend wallets and transactions with custom fields

## Installation

```bash
composer require byjg/wallets
```

## Quick Start

```php
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Service\WalletTypeService;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Entity\WalletTypeEntity;
use ByJG\Wallets\Repository\WalletRepository;
use ByJG\Wallets\Repository\WalletTypeRepository;
use ByJG\Wallets\Repository\TransactionRepository;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\AnyDataset\Db\Factory;

// Initialize database connection
$dbDriver = Factory::getDbInstance('mysql://user:pass@localhost/dbname');

// Initialize repositories
$walletTypeRepo = new WalletTypeRepository($dbDriver);
$transactionRepo = new TransactionRepository($dbDriver);
$walletRepo = new WalletRepository($dbDriver);

// Initialize services
$walletTypeService = new WalletTypeService($walletTypeRepo);
$transactionService = new TransactionService($transactionRepo, $walletRepo);
$walletService = new WalletService($walletRepo, $walletTypeService, $transactionService);

// Create a wallet type
$walletType = new WalletTypeEntity();
$walletType->setWalletTypeId('USD');
$walletType->setName('US Dollar');
$walletTypeService->update($walletType);

// Create a wallet with $100.00 initial balance (10000 cents)
$walletId = $walletService->createWallet('USD', 'user-123', 10000, 2);

// Add funds: $50.00
$transaction = $transactionService->addFunds(
    TransactionDTO::create($walletId, 5000)
        ->setDescription('Deposit from bank account')
);

// Withdraw funds: $30.00
$transaction = $transactionService->withdrawFunds(
    TransactionDTO::create($walletId, 3000)
        ->setDescription('Purchase payment')
);

// Reserve funds for pending withdrawal: $20.00
$reserve = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 2000)
        ->setDescription('Pre-authorization')
);

// Accept the reservation
$transactionService->acceptFundsById($reserve->getTransactionId());

// Get wallet balance
$wallet = $walletService->getById($walletId);
echo "Available: " . ($wallet->getAvailable() / 100) . " USD\n";
```

## Documentation

- [Getting Started](docs/getting-started.md) - Installation, database setup, and quick start guide
- [Wallet Management](docs/wallet-management.md) - Create, retrieve, and manage wallets
- [Transaction Operations](docs/transaction-operations.md) - Add, withdraw, and query transactions
- [Reserved Funds](docs/reserved-funds.md) - Pre-authorize and manage pending transactions
- [Extending Entities](docs/extending-entities.md) - Add custom fields to wallets and transactions
- [Database Schema](docs/database-schema.md) - Complete database schema documentation

Full documentation is available at [https://opensource.byjg.com/docs/php/wallets](https://opensource.byjg.com/docs/php/wallets)

## Core Concepts

### Integer Storage with Scale

All monetary amounts are stored as **integers** (BIGINT) representing the smallest currency unit:

```php
// USD with scale=2 (cents)
$walletId = $walletService->createWallet('USD', 'user-123', 10000, 2);
// 10000 represents $100.00

// Bitcoin with scale=8 (satoshis)
$btcWalletId = $walletService->createWallet('BTC', 'user-123', 100000000, 8);
// 100000000 represents 1.00000000 BTC

// Loyalty points with scale=0 (whole units)
$pointsWalletId = $walletService->createWallet('POINTS', 'user-123', 1000, 0);
// 1000 represents 1000 points
```

### Balance Components

Each wallet maintains three balance components:

- **balance** - Total funds (reserved + available)
- **reserved** - Funds held for pending transactions
- **available** - Funds available for immediate use

```php
$wallet = $walletService->getById($walletId);
echo "Balance: " . $wallet->getBalance();      // 10000 (total)
echo "Reserved: " . $wallet->getReserved();    // 2000 (blocked)
echo "Available: " . $wallet->getAvailable();  // 8000 (usable)
```

### Transaction Types

| Type             | Code | Description                    |
|------------------|------|--------------------------------|
| Balance          | B    | Initial balance or reset       |
| Deposit          | D    | Add funds immediately          |
| Withdraw         | W    | Remove funds immediately       |
| Deposit Blocked  | DB   | Reserve for incoming funds     |
| Withdraw Blocked | WB   | Reserve funds for withdrawal   |
| Reject           | R    | Reverse a reserved transaction |

### Transaction Chain Integrity

Every transaction links to the previous via `uuid` and `previousuuid`, creating an immutable audit trail:

```php
$tx1 = $transactionService->addFunds($dto1);  // previousuuid = null
$tx2 = $transactionService->addFunds($dto2);  // previousuuid = $tx1->uuid
$tx3 = $transactionService->withdrawFunds($dto3);  // previousuuid = $tx2->uuid
```

Each transaction includes a SHA-256 checksum for data integrity verification.

## Use Cases

### E-commerce Platform

```php
// Reserve funds when order is placed
$reserve = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 9999)
        ->setDescription('Order #12345')
        ->setReferenceSource('ecommerce')
        ->setReferenceId('order-12345')
);

// Capture payment when order ships
$transactionService->acceptFundsById($reserve->getTransactionId());

// Or cancel and release funds if order is cancelled
// $transactionService->rejectFundsById($reserve->getTransactionId());
```

### Multi-Currency Wallets

```php
// Create multiple wallets for same user
$usdWallet = $walletService->createWallet('USD', 'user-123', 100000, 2);
$eurWallet = $walletService->createWallet('EUR', 'user-123', 50000, 2);
$btcWallet = $walletService->createWallet('BTC', 'user-123', 5000000, 8);

// Transfer between wallets (with exchange rate logic in your app)
$walletService->transferFunds($usdWallet, $eurWallet, 10000);
```

### Gaming/Betting Platform

```php
// Reserve bet amount
$betReserve = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 5000)
        ->setDescription('Bet on Game #789')
);

// User wins - reject withdrawal and add winnings
$transactionService->rejectFundsById($betReserve->getTransactionId());
$transactionService->addFunds(
    TransactionDTO::create($walletId, 10000)
        ->setDescription('Bet winnings')
);

// User loses - accept withdrawal
// $transactionService->acceptFundsById($betReserve->getTransactionId());
```

## Testing

```bash
# Start MySQL container
docker run --name mysql-container --rm \
  -e MYSQL_ROOT_PASSWORD=password \
  -p 3306:3306 -d mysql:8.0

# Run migrations
vendor/bin/migrate up mysql://root:password@localhost/test -path=db

# Run tests
vendor/bin/phpunit
```

## API Reference

### WalletService

- `createWallet(string $walletTypeId, string $userId, int $balance, int $scale = 2, int $minValue = 0, ?string $extra = null): int`
- `getById(int $walletId): WalletEntity`
- `getByUserId(string $userId, string $walletType = ""): array`
- `getByWalletTypeId(string $walletTypeId): array`
- `overrideBalance(int $walletId, int $newBalance, int $newScale = 2, int $newMinValue = 0, string $description = "Reset Balance"): ?int`
- `partialBalance(int $walletId, int $balance, string $description = "Partial Balance"): TransactionEntity`
- `closeWallet(int $walletId): ?int`
- `transferFunds(int $walletSource, int $walletTarget, int $amount): array`

### TransactionService

- `addFunds(TransactionDTO $dto): TransactionEntity`
- `withdrawFunds(TransactionDTO $dto): TransactionEntity`
- `reserveFundsForWithdraw(TransactionDTO $dto): TransactionEntity`
- `reserveFundsForDeposit(TransactionDTO $dto): TransactionEntity`
- `acceptFundsById(int $transactionId): int`
- `acceptFundsByUuid(string $uuid): int`
- `rejectFundsById(int $transactionId): int`
- `rejectFundsByUuid(string $uuid): int`
- `acceptPartialFundsById(int $transactionId, TransactionDTO $transactionDTO, TransactionDTO $transactionRefundDTO): TransactionEntity`
- `getById(int $transactionId): TransactionEntity`
- `getByWallet(int $walletId, int $limit = null, int $offset = null): array`
- `getByDate(int $walletId, string $startDate, string $endDate, int $limit = null, int $offset = null): array`
- `getByReference(string $referenceSource, string $referenceId): array`
- `getByUuid(string $uuid): ?TransactionEntity`
- `existsTransactionByUuid(string $uuid): bool`
- `getReservedTransactions(int $walletId): array`

## Dependencies

```mermaid
flowchart TD
    byjg/wallets --> ext-pdo
    byjg/wallets --> ext-openssl
    byjg/wallets --> byjg/micro-orm
```

----
[Open source ByJG](http://opensource.byjg.com)
