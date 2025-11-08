---
sidebar_position: 1
---

# Getting Started

## Installation

Install the package via Composer:

```bash
composer require byjg/wallets
```

## Database Setup

This library requires a MySQL database. You can run the database schema using the provided SQL file:

```bash
mysql -u root -p your_database < vendor/byjg/wallets/db/base.sql
```

Or use [ByJG Migration](https://opensource.byjg.com/docs/php/migration) for database migrations:

```bash
vendor/bin/migrate up mysql://user:pass@localhost/dbname -path=vendor/byjg/wallets/db
```

## Quick Start Example

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

// Create database connection
$dbDriver = Factory::getDbInstance('mysql://user:pass@localhost/dbname');

// Initialize repositories
$walletTypeRepo = new WalletTypeRepository($dbDriver);
$transactionRepo = new TransactionRepository($dbDriver);
$walletRepo = new WalletRepository($dbDriver);

// Initialize services
$walletTypeService = new WalletTypeService($walletTypeRepo);
$transactionService = new TransactionService($transactionRepo, $walletRepo);
$walletService = new WalletService($walletRepo, $walletTypeService, $transactionService);

// Create a wallet type (e.g., USD)
$walletType = new WalletTypeEntity();
$walletType->setWalletTypeId('USD');
$walletType->setName('US Dollar Wallet');
$walletTypeService->update($walletType);

// Create a wallet for a user with an initial balance of $100.00 (10000 cents)
$walletId = $walletService->createWallet('USD', 'user-123', 10000, 2);

// Add funds: $50.00 (5000 cents)
$dto = TransactionDTO::create($walletId, 5000)
    ->setDescription('Deposit from bank account')
    ->setCode('DEP');
$transactionService->addFunds($dto);

// Withdraw funds: $30.00 (3000 cents)
$dto = TransactionDTO::create($walletId, 3000)
    ->setDescription('Purchase payment')
    ->setCode('PMT');
$transactionService->withdrawFunds($dto);

// Get wallet balance
$wallet = $walletService->getById($walletId);
echo "Available balance: " . ($wallet->getAvailable() / 100) . " USD\n";
// Output: Available balance: 120 USD
```

## Core Concepts

### Scale and Integer Storage

All monetary amounts are stored as **integers** (BIGINT) representing the smallest currency unit (e.g., cents for USD). The `scale` parameter determines how many decimal places:

- `scale = 2`: Values in cents (1 USD = 100 cents)
- `scale = 0`: Values in whole units (cryptocurrencies)
- `scale = 3`: Values in thousandths (some currencies)

**Example:**
```php
// Create wallet with scale=2 (cents)
$walletId = $walletService->createWallet('USD', 'user-123', 10000, 2);
// 10000 represents $100.00

// Create crypto wallet with scale=8
$cryptoWalletId = $walletService->createWallet('BTC', 'user-123', 100000000, 8);
// 100000000 represents 1.00000000 BTC
```

### Balance Components

Each wallet maintains three balance components:

- **balance**: Total funds in the wallet
- **reserved**: Funds held for pending transactions (blocked/reserved)
- **available**: Funds available for use (`balance - reserved`)

### Transaction Types

- **B** (Balance): Initial balance or reset
- **D** (Deposit): Add funds immediately
- **W** (Withdraw): Remove funds immediately
- **DB** (Deposit Blocked): Reserve funds for future deposit (pending)
- **WB** (Withdraw Blocked): Reserve funds for future withdrawal (pending)
- **R** (Reject): Reject/reverse a reserved transaction

## Next Steps

- [Wallet Management](wallet-management.md) - Learn how to create and manage wallets
- [Transaction Operations](transaction-operations.md) - Understanding transaction types
- [Reserved Funds](reserved-funds.md) - Working with pending transactions
- [Extending Entities](extending-entities.md) - Add custom fields to wallets and transactions
