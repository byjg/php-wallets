# CHANGELOG - Version 6.0

## Overview

Version 6.0 is a **major release** with significant breaking changes. This version represents a complete rebrand and architectural refactor of the library from "Account Statements" to "Wallets", with improved terminology, enhanced data integrity, and better performance through integer-based financial calculations.

## New Features

### Transaction Chain Integrity
- **UUID-based transaction linking**: Every transaction now has a unique UUID and references the previous transaction's UUID, creating an immutable audit trail
- **Checksum validation**: Each transaction includes a SHA-256 checksum for data integrity verification
- **Immutable transaction history**: The UUID chain ensures transactions cannot be modified without breaking the integrity chain

### Integer-Based Financial Storage
- **BIGINT storage**: All financial amounts now stored as integers (BIGINT) instead of DECIMAL for better precision and performance
- **Configurable scale**: Support for different decimal places per wallet (e.g., cents=2, satoshis=8, whole units=0)
- **Eliminated floating-point errors**: Integer arithmetic ensures accurate financial calculations

### Enhanced Balance Tracking
- **Three-component balance system**: Each wallet maintains `balance` (total), `reserved` (blocked funds), and `available` (usable funds)
- **Non-negative balance constraints**: Database-level constraints prevent negative reserved balances
- **Last UUID tracking**: Wallets track the last transaction UUID for quick integrity verification

### Improved API Design
- **Service layer architecture**: Introduction of dedicated Service classes (`WalletService`, `TransactionService`, `WalletTypeService`)
- **DTO pattern**: New `TransactionDTO` for flexible transaction creation with fluent interface
- **Observer support**: Enhanced observer pattern for bulk operations
- **Idempotent operations**: UUID-based transaction deduplication prevents duplicate transactions

### Comprehensive Documentation
- Complete documentation suite covering all aspects of the library
- Step-by-step guides for common use cases
- Database schema documentation with entity-relationship diagrams
- API reference for all public methods

## Bug Fixes

- Fixed balance calculation inconsistencies through integer-based arithmetic
- Improved transaction parent-child relationship handling
- Enhanced reference tracking with separate `referencesource` and `referenceid` fields
- Added validation to prevent invalid transaction states

## Breaking Changes

### Project Naming and Namespacing

| Before (5.x) | After (6.0) | Description |
|--------------|-------------|-------------|
| `php-account-statements` | `php-wallets` | Package renamed |
| `ByJG\AccountStatements` | `ByJG\Wallets` | Namespace changed |
| "Account Statements" | "Wallets" | Project branding updated |

### Class Renaming

| Before (5.x) | After (6.0) | Description |
|--------------|-------------|-------------|
| `AccountBLL` | `WalletService` | Business logic renamed to Service |
| `AccountTypeBLL` | `WalletTypeService` | Business logic renamed to Service |
| `StatementBLL` | `TransactionService` | Business logic renamed to Service |
| `AccountEntity` | `WalletEntity` | Account renamed to Wallet |
| `AccountTypeEntity` | `WalletTypeEntity` | Account type renamed to Wallet type |
| `StatementEntity` | `TransactionEntity` | Statement renamed to Transaction |
| `StatementDTO` | `TransactionDTO` | Statement DTO renamed to Transaction DTO |
| `AccountRepository` | `WalletRepository` | Repository renamed |
| `AccountTypeRepository` | `WalletTypeRepository` | Repository renamed |
| `StatementRepository` | `TransactionRepository` | Repository renamed |
| `AccountException` | `WalletException` | Exception renamed |
| `AccountTypeException` | `WalletTypeException` | Exception renamed |
| `StatementException` | `TransactionException` | Exception renamed |

### Database Schema Changes

#### Table Names
| Before (5.x) | After (6.0) | Description |
|--------------|-------------|-------------|
| `account` | `wallet` | Table renamed |
| `accounttype` | `wallettype` | Table renamed |
| `statement` | `transaction` | Table renamed |

#### Column Names - Wallet Table
| Before (5.x) | After (6.0) | Type Change | Description |
|--------------|-------------|-------------|-------------|
| `accountid` | `walletid` | - | Primary key renamed |
| `accounttypeid` | `wallettypeid` | - | Foreign key renamed |
| `grossbalance` | `balance` | DECIMAL → BIGINT | Total balance (reserved + available) |
| `uncleared` | `reserved` | DECIMAL → BIGINT | Reserved/blocked funds |
| `netbalance` | `available` | DECIMAL → BIGINT | Available funds for use |
| `price` | `scale` | DECIMAL → BIGINT | Decimal places (renamed for clarity) |
| `minvalue` | `minvalue` | DECIMAL → BIGINT | Minimum allowed balance |
| - | `last_uuid` | NEW (binary(16)) | UUID of last transaction |

#### Column Names - Transaction Table
| Before (5.x) | After (6.0) | Type Change | Description |
|--------------|-------------|-------------|-------------|
| `statementid` | `transactionid` | - | Primary key renamed |
| `accountid` | `walletid` | - | Foreign key renamed |
| `accounttypeid` | `wallettypeid` | - | Foreign key renamed |
| `amount` | `amount` | DECIMAL → BIGINT | Transaction amount |
| `price` | `scale` | DECIMAL → BIGINT | Decimal places |
| `grossbalance` | `balance` | DECIMAL → BIGINT | Balance snapshot |
| `uncleared` | `reserved` | DECIMAL → BIGINT | Reserved funds snapshot |
| `netbalance` | `available` | DECIMAL → BIGINT | Available funds snapshot |
| `statementparentid` | `transactionparentid` | - | Parent transaction ID renamed |
| `reference` | `referenceid` | - | Reference ID (now paired with source) |
| - | `referencesource` | NEW (VARCHAR(50)) | Source system identifier |
| - | `uuid` | NEW (binary(16)) | Unique transaction identifier |
| - | `previousuuid` | NEW (binary(16)) | Previous transaction UUID |
| - | `checksum` | NEW (VARCHAR(64)) | SHA-256 integrity checksum |
| - | `code` | NEW (CHAR(10)) | Transaction code/reference |

### Method Signature Changes

#### Creating Wallets
```php
// Before (5.x)
$accountBLL->createAccount('USD', 'user-123', 100.00, 1.00, 0.00);

// After (6.0)
$walletService->createWallet('USD', 'user-123', 10000, 2, 0);
// Note: 10000 = $100.00 with scale=2 (cents)
```

#### Adding Funds
```php
// Before (5.x)
$statementDTO = new StatementDTO();
$statementDTO->setAccountId($accountId);
$statementDTO->setAmount(50.00);
$statementDTO->setDescription('Deposit');
$statementBLL->addFunds($statementDTO);

// After (6.0)
$transactionService->addFunds(
    TransactionDTO::create($walletId, 5000)
        ->setDescription('Deposit')
);
// Note: 5000 = $50.00 with scale=2
```

#### Withdrawing Funds
```php
// Before (5.x)
$statementDTO = new StatementDTO();
$statementDTO->setAccountId($accountId);
$statementDTO->setAmount(30.00);
$statementBLL->withdrawFunds($statementDTO);

// After (6.0)
$transactionService->withdrawFunds(
    TransactionDTO::create($walletId, 3000)
        ->setDescription('Withdrawal')
);
```

#### Reserved Funds
```php
// Before (5.x)
$reserve = $statementBLL->reserveFundsForWithdraw($statementDTO);
$statementBLL->acceptFundsById($reserve->getStatementId());

// After (6.0)
$reserve = $transactionService->reserveFundsForWithdraw($transactionDTO);
$transactionService->acceptFundsById($reserve->getTransactionId());
```

#### Getting Balance
```php
// Before (5.x)
$account = $accountBLL->getById($accountId);
$balance = $account->getGrossBalance();  // DECIMAL
$available = $account->getNetBalance();  // DECIMAL

// After (6.0)
$wallet = $walletService->getById($walletId);
$balance = $wallet->getBalance();        // BIGINT (cents)
$available = $wallet->getAvailable();    // BIGINT (cents)
// Convert to currency: $balance / 100 = dollars
```

### Configuration Changes

#### Composer Package
```json
// Before (5.x)
"require": {
    "php": ">=8.1",
    "byjg/micro-orm": "^5.0"
}

// After (6.0)
"require": {
    "php": ">=8.3 <8.6",
    "byjg/micro-orm": "^6.0",
    "ext-pdo": "*",
    "ext-openssl": "*"
}
```

## Path to Upgrade from 5.x to 6.0

### Step 1: Backup Your Data
```bash
# Backup your database before starting the migration
mysqldump -u root -p database_name > backup_5.x.sql
```

### Step 2: Update Composer Dependencies
```bash
# Update your composer.json
composer require byjg/wallets:^6.0

# This will also require updating PHP to 8.3+
# and byjg/micro-orm to ^6.0
```

### Step 3: Update Your Code - Class Names

Replace all class references in your codebase:

```php
// Update use statements
use ByJG\Wallets\Service\WalletService;           // was AccountBLL
use ByJG\Wallets\Service\WalletTypeService;       // was AccountTypeBLL
use ByJG\Wallets\Service\TransactionService;      // was StatementBLL
use ByJG\Wallets\Entity\WalletEntity;             // was AccountEntity
use ByJG\Wallets\Entity\WalletTypeEntity;         // was AccountTypeEntity
use ByJG\Wallets\Entity\TransactionEntity;        // was StatementEntity
use ByJG\Wallets\DTO\TransactionDTO;              // was StatementDTO
use ByJG\Wallets\Repository\WalletRepository;     // was AccountRepository
use ByJG\Wallets\Repository\TransactionRepository; // was StatementRepository
```

### Step 4: Update Method Calls

Replace all method names and property accessors:

```php
// Balance properties
$wallet->getBalance();      // was getGrossBalance()
$wallet->getReserved();     // was getUncleared()
$wallet->getAvailable();    // was getNetBalance()

// ID properties
$wallet->getWalletId();     // was getAccountId()
$wallet->getWalletTypeId(); // was getAccountTypeId()
$transaction->getTransactionId(); // was getStatementId()
$transaction->getWalletId();      // was getAccountId()

// Service instantiation
$walletService = new WalletService($walletRepo, $walletTypeService, $transactionService);
// was: $accountBLL = new AccountBLL($accountRepo, $accountTypeBLL, $statementBLL);
```

### Step 5: Migrate Database Schema

Run the migration scripts to update your database:

```bash
# Using byjg/migration package
vendor/bin/migrate up mysql://user:pass@localhost/dbname -path=db
```

This will:
1. Rename tables: `account` → `wallet`, `statement` → `transaction`, `accounttype` → `wallettype`
2. Rename columns to new terminology (grossbalance → balance, uncleared → reserved, etc.)
3. Convert DECIMAL columns to BIGINT (multiply values by 100 for scale=2)
4. Add new UUID and checksum columns
5. Add database constraints for data integrity

### Step 6: Update Amount Handling

All amounts must now be integers representing the smallest currency unit:

```php
// Before (5.x): Working with dollars (DECIMAL)
$account->setGrossBalance(100.50);  // $100.50

// After (6.0): Working with cents (BIGINT with scale=2)
$wallet->setBalance(10050);  // $100.50 = 10050 cents

// Helper conversion functions
function dollarsToCents(float $dollars): int {
    return (int) round($dollars * 100);
}

function centsToDollars(int $cents): float {
    return $cents / 100;
}

// Example usage
$amountInCents = dollarsToCents(100.50);  // 10050
$transactionService->addFunds(
    TransactionDTO::create($walletId, $amountInCents)
);
```

### Step 7: Update Transaction References

The reference system has been split into two fields:

```php
// Before (5.x)
$dto->setReference('order-12345');

// After (6.0)
$dto->setReferenceSource('ecommerce');
$dto->setReferenceId('order-12345');

// Or using fluent interface
TransactionDTO::create($walletId, 5000)
    ->setReferenceSource('ecommerce')
    ->setReferenceId('order-12345');
```

### Step 8: Test Your Application

After migration:
1. Verify all balances are correct (remember they're now in cents)
2. Test transaction creation and retrieval
3. Verify reserved funds functionality
4. Test balance calculations
5. Validate UUID chain integrity

### Step 9: Update Custom Extensions

If you extended the base entities:

```php
// Update your custom entity class names
class MyCustomWalletEntity extends WalletEntity {  // was AccountEntity
    // Update property names
    protected int $customField;

    // Update method overrides if any
}

// Update observer implementations
class MyWalletObserver implements ObserverInterface {
    public function handle(array $params) {
        $wallet = $params['wallet'];  // was 'account'
        $transaction = $params['transaction'];  // was 'statement'
    }
}
```

### Step 10: Update Tests

Update all test cases:
- Replace class names
- Update assertions for integer amounts
- Update property names in assertions
- Test UUID generation and chain integrity

## Migration Checklist

- [ ] Backup database
- [ ] Update PHP to 8.3 or higher
- [ ] Update composer.json dependencies
- [ ] Run `composer update`
- [ ] Update all `use` statements (AccountBLL → WalletService, etc.)
- [ ] Update all class instantiations
- [ ] Update method calls (getGrossBalance → getBalance, etc.)
- [ ] Update amount calculations (DECIMAL → BIGINT)
- [ ] Update reference handling (reference → referencesource + referenceid)
- [ ] Run database migrations
- [ ] Update custom entities if any
- [ ] Update tests
- [ ] Run full test suite
- [ ] Verify balances in database
- [ ] Test in staging environment
- [ ] Deploy to production

## Support

For questions or issues with the migration:
- Documentation: https://opensource.byjg.com/docs/php/wallets
- Issues: https://github.com/byjg/php-wallets/issues
- Source: https://github.com/byjg/php-wallets

## Credits

This major version represents months of careful refactoring to improve the library's usability, performance, and data integrity. Thank you to all contributors and users who provided feedback.
