# CHANGELOG - Version 7.0

## Overview

Version 7.0 upgrades the library to `byjg/micro-orm` 7.0 (and `byjg/migration` 7.0 for development),
which rewired the observer system on top of the per-connection `DatabaseExecutor` observer and
removed the global `ORMSubject` singleton. Starting with this release, the library version is
aligned with the `byjg/micro-orm` major version.

This release also hardens the ledger reliability guarantees: transfers between wallets are now
atomic, caller-supplied UUIDs act as idempotency keys, reserved transactions can be
accepted/rejected by UUID, transaction rows are immutable at the database level, a reserved
transaction can be processed only once (enforced by a unique index), and transaction checksums
are now chained hashes covering every business field, optionally keyed with an installation
secret.

The public API of the Wallets library is backward compatible with 6.x; new methods were added.
Observers registered with `Repository::addObserver()` keep receiving the same payloads as in 6.x:
the wallet `Update` event still carries the updated `WalletEntity` plus the pre-change wallet as
old data, and the transaction `Insert` event still carries the rehydrated `TransactionEntity`.

## Breaking Changes

| Component | Before (6.x) | After (7.0) | Description |
|-----------|--------------|-------------|-------------|
| **byjg/micro-orm** | `^6.0` | `^7.0` | `ORMSubject` removed; observers dispatched per database connection |
| **byjg/migration** (dev) | `^6.0` | `^7.0` | Query execution moved from the driver to `DatabaseExecutor` |
| **Observer scope** | Global (`ORMSubject` singleton) | Per database connection | Observers fire only for writes flowing through the connection of the repository where they were registered |
| **`ORMSubject` usage** | `ORMSubject::getInstance()` | Removed | Any direct userland usage (e.g. `clearObservers()` in test suites) must be removed |
| **Ledger immutability** | `UPDATE` on `transaction` rows allowed | Blocked by a database trigger (migration 00002) | Corrections must be made with new transactions, never by editing history |
| **Reserved transaction processing** | Single processing enforced by application logic only | Also enforced by a unique index on `transactionparentid` (migration 00002) | Direct SQL writes can no longer double-process a reservation |
| **Wallet observer events on accept/reject** | Two `Update` notifications (balance save + `last_uuid` save) | One `Update` notification | `acceptFundsById()`/`rejectFundsById()` now persist the wallet with a single save |
| **`transferFunds()` failure behavior** | Withdraw could commit even when the deposit failed | All-or-nothing | Both movements run inside a single database transaction |
| **Checksum API** | `TransactionEntity::calculateChecksum()` / `validateChecksum()` static methods | `ByJG\Wallets\Checksum` classes (`ChecksumInterface`, `ChecksumV1`, `ChecksumV2`, `ChecksumFactory`) | The entity statics were removed; resolve the algorithm with `ChecksumFactory::get($row->getChecksumVersion())` or `ChecksumFactory::current()` |
| **Checksum algorithm** | Hash of `amount\|balance\|reserved\|available\|uuid\|previousuuid` | Chained hash of every business field + previous checksum + optional secret (migration 00003) | Old rows keep `checksumversion = 1` and validate with the legacy algorithm |

## New Features

### Idempotency keys (caller-supplied UUIDs)

`TransactionDTO::setUuid()` is now honored instead of being overwritten: the UUID acts as an
idempotency key. Retrying `addFunds()`/`withdrawFunds()`/`reserveFunds*()` with the same UUID and
the same data returns the original transaction instead of creating a duplicate movement (enforced
by the unique index on `uuid`). Reusing a UUID with different data throws a `TransactionException`.
When no UUID is supplied, one is generated automatically, as before.

### UUID-based operations

- `TransactionService::getByUuid($uuid): ?TransactionEntity` - look up a transaction by its UUID
- `TransactionService::acceptFundsByUuid($uuid, ?TransactionDTO $dto = null): int`
- `TransactionService::rejectFundsByUuid($uuid, ?TransactionDTO $dto = null): int`

Combined with idempotency keys, the whole reserve/accept lifecycle can be driven with identifiers
the caller generated and owns, without storing the internal transaction ids.

### Chain verification for reconciliation jobs

`TransactionService::verifyChain(int $walletId): ChainVerificationResult` walks the transaction
chain from `wallet.last_uuid` back to the genesis transaction, validating every checksum,
confirming the wallet balances equal the head transaction's snapshot, and detecting broken links,
cycles and orphan rows. Run it from a scheduled job to detect ledger corruption as soon as it
happens.

### Chained, versioned checksums with an optional secret (checksum v2)

The transaction checksum now covers every business field (wallet, type, amount, scale,
balances, code, description, references, parent id, UUIDs) and is chained to the previous
transaction's checksum (stored in the new `previouschecksum` column): tampering with any row
invalidates every subsequent checksum, so rewriting history requires recomputing the whole
chain forward.

Optionally, pass an installation secret to the `TransactionService` constructor to turn the
checksum into a keyed hash that cannot be recomputed by an attacker with database access only:

```php
$transactionService = new TransactionService(
    $transactionRepository,
    $walletRepository,
    getenv('WALLET_CHECKSUM_SECRET')
);
```

Checksum algorithms are classes in the `ByJG\Wallets\Checksum` namespace implementing
`ChecksumInterface`. Each row records the algorithm that produced it in the new
`checksumversion` column, so rows created before the upgrade (version 1) keep validating with
the legacy algorithm. `verifyChain()` validates each row with its own algorithm, checks the
previous-checksum links, flags checksum version downgrades, and reports the number of legacy
rows via `ChainVerificationResult::getLegacyChecksums()` (a number that must never grow).

### Atomic transfers

`WalletService::transferFunds()` now runs the withdrawal and the deposit inside a single
SERIALIZABLE database transaction, locking both wallets in a consistent order (preventing
deadlocks between opposite concurrent transfers) and validating that both wallets exist before any
money moves. Transfers to the same wallet are rejected with a `WalletException`.

## Reliability Fixes

- **`transferFunds()` was not atomic**: the withdraw and the deposit committed independently, so a
  failure between them lost money from the source wallet. Both now roll back together.
- **Rollback guards catch `Throwable`**: all transactional operations
  (`updateFunds`, `acceptFundsById`, `acceptPartialFundsById`, `rejectFundsById`,
  `overrideBalance`) previously caught only `Exception`; a PHP `Error` escaped without an explicit
  rollback. They now catch `Throwable`, guaranteeing the rollback-and-rethrow contract for every
  abnormal exit.
- **`acceptFundsById()`/`rejectFundsById()` saved the wallet twice** (balance change, then
  `last_uuid`); consolidated into a single save inside the same transaction.
- **`TransactionDTO::setToTransaction()` bug**: a stray `if (!empty($this->get))` statement made
  the custom-properties loop dead code, silently dropping extended-entity properties in the
  accept/reject flows. Fixed.

## Database Schema Changes (migrations 00002 and 00003)

```sql
-- Migration 00002
ALTER TABLE `transaction`
    ADD CONSTRAINT `idx_transaction_parentid_unique` UNIQUE (`transactionparentid`);

CREATE TRIGGER `trg_transaction_no_update` BEFORE UPDATE ON `transaction`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger transactions are immutable and cannot be updated';

-- Migration 00003
ALTER TABLE `transaction`
    ADD COLUMN `previouschecksum` varchar(64) NULL AFTER `checksum`,
    ADD COLUMN `checksumversion` tinyint NOT NULL DEFAULT 1 AFTER `previouschecksum`;
```

- The unique index guarantees at the database level that a reserved transaction is accepted or
  rejected only once, even if the application logic is bypassed.
- The trigger rejects any `UPDATE` on the transaction ledger. `DELETE` is not blocked by the
  schema; restrict it with database grants in production.
- `previouschecksum` chains each checksum to the previous transaction's checksum, and
  `checksumversion` records the algorithm that produced the row (existing rows default to the
  legacy version 1).

### Observer scope: global → per connection

In 6.x an observer registered on any repository was notified globally. In 7.0 the observer is
attached to the `DatabaseExecutor` of the repository where it was registered. Since
`WalletRepository` and `TransactionRepository` must share the same connection for the atomic
wallet/transaction writes, this is transparent for the common setup where both repositories are
created from the same `DatabaseExecutor`. If you observe from a repository on a different
connection, register the observer on a repository of the connection that performs the writes.

## Internal Changes

- `TransactionService::updateFunds()` no longer uses `ORMSubject::getInstance()->notify()`. It now
  builds the transaction insert and wallet update statements upfront, defers their observer
  notification, executes them inside the SERIALIZABLE transaction, and after commit attaches the
  entities and flushes the notifications through the micro-orm 7 `ObserverBridge`. Observers keep
  receiving entity payloads, after commit, in the same order as before (wallet `Update`, then
  transaction `Insert`). Nothing is notified on rollback.
- The two statements are executed directly through the repository executor instead of
  `bulkExecute()`, within the same transaction, preserving atomicity.

## Path to Upgrade from 6.x to 7.0

### Step 1: Update Composer Dependencies

```bash
composer require byjg/wallets:^7.0
```

This will also update `byjg/micro-orm` to `^7.0` and `byjg/anydataset-db` to `^7.0`.

### Step 2: Remove ORMSubject Usage (If Applicable)

If your code (typically test suites) calls `ORMSubject::getInstance()->clearObservers()` or
notifies observers manually, remove those calls. Observers die with their executors; there is no
global state to clear. To remove a specific observer, use `Repository::removeObserver()`.

### Step 3: Review Observer Registration (If Applicable)

`ObserverProcessorInterface` implementations require no code change. Just make sure each observer
is registered on a repository whose connection performs the writes you want to observe. Note that
`acceptFundsById()`/`rejectFundsById()` now emit a single wallet `Update` notification instead of
two; adjust observers (or tests) that counted on the second event.

### Step 4: Apply the Database Migrations

Migration 00002 adds the unique index on `transactionparentid` and the immutability trigger;
migration 00003 adds the `previouschecksum` and `checksumversion` columns:

```bash
migrate update
```

Before applying, make sure no existing data violates the new unique index (each
`transactionparentid` value must appear at most once) and that nothing in your application issues
`UPDATE` statements against the `transaction` table. Existing rows keep `checksumversion = 1`
and continue validating with the legacy algorithm; new rows are created with the chained v2
checksum automatically.

### Step 5: Update Checksum API Usage (If Applicable)

If your code called `TransactionEntity::calculateChecksum()` or
`TransactionEntity::validateChecksum()`, use the `ByJG\Wallets\Checksum` classes instead:

```php
use ByJG\Wallets\Checksum\ChecksumFactory;

$checksum = ChecksumFactory::current()->calculate($transaction);
$isValid = ChecksumFactory::get($transaction->getChecksumVersion())
    ->validate($transaction, $transaction->getChecksum());
```

Optionally configure a checksum secret (see the New Features section) — recommended for
production, since without it an attacker with database access can recompute the chain.

### Step 6: Test Your Application

```bash
vendor/bin/phpunit
```

## Support

For questions or issues with the migration:
- Documentation: https://opensource.byjg.com/docs/php/wallets
- Issues: https://github.com/byjg/php-wallets/issues
- Source: https://github.com/byjg/php-wallets
