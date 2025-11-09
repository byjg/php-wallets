---
sidebar_position: 6
---

# Database Schema

## Overview

The wallet system uses three main tables:
- `wallettype` - Defines currency types (USD, EUR, BTC, etc.)
- `wallet` - User wallets for each currency
- `transaction` - All wallet transactions with full history

## Tables

### wallettype

Defines the types of wallets/currencies available in the system.

```sql
CREATE TABLE `wallettype` (
  `wallettypeid` VARCHAR(20) NOT NULL,
  `name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`wallettypeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
```

| Column         | Type        | Description                                        |
|----------------|-------------|----------------------------------------------------|
| `wallettypeid` | VARCHAR(20) | Unique identifier (e.g., 'USD', 'EUR', 'BTC')      |
| `name`         | VARCHAR(45) | Human-readable name (e.g., 'US Dollar', 'Bitcoin') |

**Examples:**
```sql
INSERT INTO wallettype VALUES ('USD', 'US Dollar');
INSERT INTO wallettype VALUES ('EUR', 'Euro');
INSERT INTO wallettype VALUES ('BTC', 'Bitcoin');
INSERT INTO wallettype VALUES ('POINTS', 'Loyalty Points');
```

### wallet

Stores user wallets with current balance state.

```sql
CREATE TABLE `wallet` (
  `walletid` INT(11) NOT NULL AUTO_INCREMENT,
  `wallettypeid` VARCHAR(20) NOT NULL,
  `userid` VARCHAR(50) DEFAULT NULL,
  `balance` BIGINT DEFAULT 0,
  `reserved` BIGINT DEFAULT 0,
  `available` BIGINT DEFAULT 0,
  `scale` BIGINT NOT NULL DEFAULT 2,
  `extra` TEXT,
  `minvalue` BIGINT NOT NULL DEFAULT 0,
  `last_uuid` BINARY(16) DEFAULT NULL,
  `entrydate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`walletid`),
  UNIQUE KEY `unique_userid_type` (`userid`,`wallettypeid`),
  KEY `fk_wallet_wallettype_idx` (`wallettypeid`),
  CONSTRAINT `fk_wallet_wallettype` FOREIGN KEY (`wallettypeid`)
      REFERENCES `wallettype` (`wallettypeid`)
      ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `wallet_chk_value_nonnegative` CHECK (`available` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
```

| Column         | Type        | Default           | Description                                             |
|----------------|-------------|-------------------|---------------------------------------------------------|
| `walletid`     | INT(11)     | AUTO_INCREMENT    | Unique wallet identifier                                |
| `wallettypeid` | VARCHAR(20) | -                 | Type of wallet (references `wallettype`)                |
| `userid`       | VARCHAR(50) | NULL              | User who owns this wallet                               |
| `balance`      | BIGINT      | 0                 | Total balance in smallest unit (e.g., cents)            |
| `reserved`     | BIGINT      | 0                 | Funds held for pending transactions                     |
| `available`    | BIGINT      | 0                 | Funds available for use (balance - reserved)            |
| `scale`        | BIGINT      | 2                 | Number of decimal places                                |
| `extra`        | TEXT        | NULL              | Custom JSON metadata                                    |
| `minvalue`     | BIGINT      | 0                 | Minimum allowed balance (can be negative for overdraft) |
| `last_uuid`    | BINARY(16)  | NULL              | UUID of last transaction (chain integrity)              |
| `entrydate`    | TIMESTAMP   | CURRENT_TIMESTAMP | Last update timestamp                                   |

**Constraints:**
- One wallet per user per currency (`unique_userid_type`)
- `available` must be non-negative (enforced by CHECK constraint)
- `wallettypeid` must exist in `wallettype` table

**Example:**
```sql
-- User '123' has $125.50 USD with $25.50 reserved
INSERT INTO wallet (wallettypeid, userid, balance, reserved, available, scale, minvalue)
VALUES ('USD', '123', 12550, 2550, 10000, 2, 0);
```

### transaction

Stores all wallet transactions with full audit history.

```sql
CREATE TABLE `transaction` (
  `transactionid` INT(11) NOT NULL AUTO_INCREMENT,
  `walletid` INT(11) NOT NULL,
  `wallettypeid` VARCHAR(20) NOT NULL,
  `typeid` ENUM('B','D','W','DB','WB','R') NOT NULL,
  `amount` BIGINT NOT NULL,
  `scale` BIGINT DEFAULT 2,
  `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `balance` BIGINT DEFAULT NULL,
  `reserved` BIGINT DEFAULT NULL,
  `available` BIGINT DEFAULT NULL,
  `code` CHAR(10) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `transactionparentid` INT(11) DEFAULT NULL,
  `referenceid` VARCHAR(100) DEFAULT NULL,
  `referencesource` VARCHAR(50) DEFAULT NULL,
  `uuid` BINARY(16) DEFAULT NULL,
  `previousuuid` BINARY(16) DEFAULT NULL,
  `checksum` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`transactionid`),
  UNIQUE KEY `idx_transaction_uuid` (`uuid`),
  KEY `idx_transaction_previous_uuid` (`previousuuid`),
  KEY `fk_transaction_wallet1_idx` (`walletid`),
  KEY `fk_transaction_transaction1_idx` (`transactionparentid`),
  KEY `idx_transaction_typeid_date` (`typeid`,`date`),
  KEY `fk_transaction_referenceid_idx` (`referencesource`, `referenceid`),
  CONSTRAINT `fk_transaction_wallettype` FOREIGN KEY (`wallettypeid`)
      REFERENCES `wallettype` (`wallettypeid`)
      ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transaction_wallet1` FOREIGN KEY (`walletid`)
      REFERENCES `wallet` (`walletid`)
      ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transaction_transaction1` FOREIGN KEY (`transactionparentid`)
      REFERENCES `transaction` (`transactionid`)
      ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
```

| Column                | Type         | Description                                 |
|-----------------------|--------------|---------------------------------------------|
| `transactionid`       | INT(11)      | Unique transaction identifier               |
| `walletid`            | INT(11)      | Wallet this transaction belongs to          |
| `wallettypeid`        | VARCHAR(20)  | Wallet type (denormalized for performance)  |
| `typeid`              | ENUM         | Transaction type: B, D, W, DB, WB, R        |
| `amount`              | BIGINT       | Transaction amount (always positive)        |
| `scale`               | BIGINT       | Decimal scale at time of transaction        |
| `date`                | TIMESTAMP    | Transaction timestamp                       |
| `balance`             | BIGINT       | Wallet balance **after** this transaction   |
| `reserved`            | BIGINT       | Wallet reserved **after** this transaction  |
| `available`           | BIGINT       | Wallet available **after** this transaction |
| `code`                | CHAR(10)     | Application-defined transaction code        |
| `description`         | VARCHAR(255) | Human-readable description                  |
| `transactionparentid` | INT(11)      | Parent transaction (for accept/reject)      |
| `referenceid`         | VARCHAR(100) | External reference ID                       |
| `referencesource`     | VARCHAR(50)  | External system name                        |
| `uuid`                | BINARY(16)   | Unique identifier for idempotency           |
| `previousuuid`        | BINARY(16)   | UUID of previous transaction (chain)        |
| `checksum`            | VARCHAR(64)  | SHA-256 hash for data integrity             |

#### Transaction Types

| Type  | Name             | Description                                              |
|-------|------------------|----------------------------------------------------------|
| `B`   | Balance          | Initial balance or reset - ignores previous transactions |
| `D`   | Deposit          | Add funds immediately                                    |
| `W`   | Withdraw         | Remove funds immediately                                 |
| `DB`  | Deposit Blocked  | Reserve for incoming funds (pending deposit)             |
| `WB`  | Withdraw Blocked | Reserve funds for pending withdrawal                     |
| `R`   | Reject           | Reverse/cancel a reserved transaction                    |

#### Indexes

- `PRIMARY KEY (transactionid)` - Fast lookup by ID
- `UNIQUE KEY idx_transaction_uuid (uuid)` - Ensures idempotency
- `KEY idx_transaction_previous_uuid (previousuuid)` - Chain integrity queries
- `KEY fk_transaction_wallet1_idx (walletid)` - Get all transactions for wallet
- `KEY idx_transaction_typeid_date (typeid, date)` - Filter by type and sort by date
- `KEY fk_transaction_referenceid_idx (referencesource, referenceid)` - External references

## Data Integrity

### Transaction Chain

Every transaction links to the previous transaction via `previousuuid`:

```
Transaction 1: uuid=A, previousuuid=NULL
Transaction 2: uuid=B, previousuuid=A
Transaction 3: uuid=C, previousuuid=B
```

This creates an **immutable audit trail** where:
- Any modification breaks the chain
- Full transaction history is verifiable
- Chronological order is guaranteed

### Checksum

Each transaction includes a SHA-256 checksum calculated from:

```
SHA256(amount|balance|reserved|available|uuid|previousuuid)
```

This ensures:
- Data integrity - detects any tampering
- Verification - can validate historical transactions
- Consistency - database values match checksum

### UUID Format

UUIDs are stored as `BINARY(16)` for efficiency:

```php
// Generate UUID
$uuid = \ByJG\MicroOrm\Literal\HexUuidLiteral::uuid();

// Store in database (automatic conversion to binary)
$transaction->setUuid($uuid);

// Retrieve as formatted string
$uuidString = $transaction->getUuid();  // 'A1B2C3D4-E5F6-...'
```

## Balance Accounting

### Balance Components

For every wallet:
```
balance = reserved + available
```

- **balance**: Total funds (positive or negative if overdraft allowed)
- **reserved**: Funds held for pending transactions
  - Positive = funds reserved for withdrawal
  - Negative = space reserved for deposit
- **available**: Funds that can be used immediately

### Transaction Balance Effects

| Type                  | balance         | reserved       | available          |
|-----------------------|-----------------|----------------|--------------------|
| B (Balance)           | Set to amount   | Set explicitly | balance - reserved |
| D (Deposit)           | +amount         | unchanged      | +amount            |
| W (Withdraw)          | -amount         | unchanged      | -amount            |
| DB (Deposit Blocked)  | unchanged       | -amount        | +amount            |
| WB (Withdraw Blocked) | unchanged       | +amount        | -amount            |
| R (Reject)            | Reverse reserve | Return to 0    | Restore            |

**Example: Withdraw flow**

```sql
-- Initial state
balance=10000, reserved=0, available=10000

-- Reserve funds (WB)
balance=10000, reserved=5000, available=5000

-- Accept withdrawal (W)
balance=5000, reserved=0, available=5000
```

## Scale and Integer Storage

All monetary amounts are stored as **BIGINT** (integers) representing the smallest currency unit:

| Scale | Unit        | Example                             |
|-------|-------------|-------------------------------------|
| 0     | Whole units | Points, tokens: 100 = 100 points    |
| 2     | Cents       | USD: 10050 = $100.50                |
| 3     | Thousandths | Some currencies: 10000 = 10.000     |
| 8     | Satoshis    | Bitcoin: 100000000 = 1.00000000 BTC |

**Conversion:**
```php
// Store $100.50 as integer
$cents = 10050;
$scale = 2;

// Convert to float
$dollars = $cents / pow(10, $scale);  // 100.50

// Convert from float
$cents = (int)round($dollars * pow(10, $scale));  // 10050
```

## Migrations

The schema is managed with migrations in `db/migrations/`:

```bash
# Apply all migrations
vendor/bin/migrate up mysql://user:pass@localhost/dbname -path=vendor/byjg/wallets/db

# Rollback last migration
vendor/bin/migrate down mysql://user:pass@localhost/dbname -path=vendor/byjg/wallets/db
```

See [ByJG Migration documentation](https://opensource.byjg.com/docs/php/migration) for details.

## Example Queries

### Get wallet with all transactions

```sql
SELECT
    w.walletid,
    w.userid,
    w.balance,
    w.available,
    w.reserved,
    t.transactionid,
    t.typeid,
    t.amount,
    t.description,
    t.date
FROM wallet w
LEFT JOIN transaction t ON w.walletid = t.walletid
WHERE w.walletid = ?
ORDER BY t.date DESC;
```

### Get pending reserved transactions

```sql
SELECT *
FROM transaction
WHERE walletid = ?
  AND typeid IN ('WB', 'DB')
  AND transactionparentid IS NULL;
```

### Get transactions by reference

```sql
SELECT *
FROM transaction
WHERE referencesource = ?
  AND referenceid = ?
ORDER BY date DESC;
```

### Verify transaction chain

```sql
SELECT
    t1.transactionid,
    t1.uuid,
    t1.previousuuid,
    t2.uuid as previous_tx_uuid,
    t1.checksum
FROM transaction t1
LEFT JOIN transaction t2 ON t1.previousuuid = t2.uuid
WHERE t1.walletid = ?
ORDER BY t1.date ASC;
```

### Calculate total deposited/withdrawn

```sql
SELECT
    SUM(CASE WHEN typeid = 'D' THEN amount ELSE 0 END) as total_deposited,
    SUM(CASE WHEN typeid = 'W' THEN amount ELSE 0 END) as total_withdrawn
FROM transaction
WHERE walletid = ?;
```

## Performance Considerations

1. **Indexes are crucial** - Query by wallet, date, type, and reference
2. **Denormalization** - `wallettypeid` in `transaction` avoids joins
3. **Balance snapshots** - No need to sum all transactions
4. **BIGINT for amounts** - Faster than DECIMAL, no rounding errors
5. **BINARY(16) for UUIDs** - More efficient than CHAR(36)

## Best Practices

1. **Never modify historical transactions** - They're part of the audit chain
2. **Always use transactions** - Wrap wallet updates in database transactions
3. **Verify checksums** - Regularly validate transaction integrity
4. **Monitor reserved funds** - Clean up stale reservations
5. **Index custom queries** - Add indexes for your specific access patterns
6. **Backup regularly** - Financial data is critical
7. **Test migrations** - Always test on copy of production data first
