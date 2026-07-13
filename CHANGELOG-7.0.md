# CHANGELOG - Version 7.0

## Overview

Version 7.0 upgrades the library to `byjg/micro-orm` 7.0 (and `byjg/migration` 7.0 for development),
which rewired the observer system on top of the per-connection `DatabaseExecutor` observer and
removed the global `ORMSubject` singleton. Starting with this release, the library version is
aligned with the `byjg/micro-orm` major version.

The public API of the Wallets library (services, repositories, DTOs, entities) is unchanged.
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
is registered on a repository whose connection performs the writes you want to observe.

### Step 4: Test Your Application

```bash
vendor/bin/phpunit
```

No database schema changes are required for this release.

## Support

For questions or issues with the migration:
- Documentation: https://opensource.byjg.com/docs/php/wallets
- Issues: https://github.com/byjg/php-wallets/issues
- Source: https://github.com/byjg/php-wallets
