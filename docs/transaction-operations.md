---
sidebar_position: 3
---

# Transaction Operations

## Transaction DTO

All transaction operations use `TransactionDTO` to pass data:

```php
use ByJG\Wallets\DTO\TransactionDTO;

// Create with wallet ID and amount
$dto = TransactionDTO::create($walletId, 5000);

// Create empty (for partial operations)
$dto = TransactionDTO::createEmpty();

// Set optional properties
$dto->setDescription('Purchase payment')
    ->setCode('PMT')
    ->setReferenceId('order-12345')
    ->setReferenceSource('ecommerce')
    ->setUuid($customUuid);  // Optional: auto-generated if not set
```

### TransactionDTO Properties

| Property          | Type   | Description                                        |
|-------------------|--------|----------------------------------------------------|
| `walletId`        | int    | Target wallet ID                                   |
| `amount`          | int    | Transaction amount in smallest unit                |
| `description`     | string | Human-readable description                         |
| `code`            | string | Transaction code for categorization (max 10 chars) |
| `referenceId`     | string | External reference ID                              |
| `referenceSource` | string | Source system name                                 |
| `uuid`            | string | Idempotency key (auto-generated when not supplied) |

## Add Funds (Deposit)

Add funds immediately to a wallet:

```php
$transaction = $transactionService->addFunds(
    TransactionDTO::create($walletId, 10000)
        ->setDescription('Bank deposit')
        ->setCode('DEP')
        ->setReferenceId('bank-tx-456')
        ->setReferenceSource('bank-api')
);

echo "New balance: " . ($transaction->getBalance() / 100);
echo "Transaction ID: " . $transaction->getTransactionId();
```

Creates a **Deposit (D)** transaction that:
- Increases `balance` by amount
- Increases `available` by amount
- Does not affect `reserved`

## Withdraw Funds

Remove funds immediately from a wallet:

```php
$transaction = $transactionService->withdrawFunds(
    TransactionDTO::create($walletId, 5000)
        ->setDescription('ATM withdrawal')
        ->setCode('ATM')
);
```

Creates a **Withdraw (W)** transaction that:
- Decreases `balance` by amount
- Decreases `available` by amount
- Validates that `available >= amount`
- Validates that `available - amount >= minValue`

:::danger Insufficient Funds
Throws `AmountException` if insufficient available funds or would violate `minValue`.
:::

## Transaction Types

### Immediate Transactions

| Type     | Code  | Operation | Description                                         |
|----------|-------|-----------|-----------------------------------------------------|
| Balance  | `B`   | Reset     | Sets wallet to a specific balance, ignoring history |
| Deposit  | `D`   | Add       | Adds funds immediately                              |
| Withdraw | `W`   | Subtract  | Removes funds immediately                           |
| Reject   | `R`   | Reverse   | Reverses a reserved transaction                     |

### Reserved Transactions

| Type              | Code | Operation              | Description                         |
|-------------------|------|------------------------|-------------------------------------|
| Deposit Blocked   | `DB` | Reserve for deposit    | Reserves space for incoming funds   |
| Withdraw Blocked  | `WB` | Reserve for withdrawal | Blocks funds for pending withdrawal |

See [Reserved Funds](reserved-funds.md) for details on pending transactions.

## Retrieving Transactions

### Get Transaction by ID

```php
$transaction = $transactionService->getById($transactionId);

echo "Amount: " . ($transaction->getAmount() / 100);
echo "Type: " . $transaction->getTypeId();
echo "Balance after: " . ($transaction->getBalance() / 100);
```

### Get Transactions by Wallet

```php
// Get all transactions for a wallet
$transactions = $transactionService->getByWallet($walletId);

// Get with limit and offset
$transactions = $transactionService->getByWallet(
    walletId: $walletId,
    limit: 50,
    offset: 0
);
```

### Get Transactions by Date Range

```php
$transactions = $transactionService->getByDate(
    walletId: $walletId,
    startDate: '2024-01-01',
    endDate: '2024-01-31',
    limit: 100,
    offset: 0
);
```

### Get Transactions by Reference

```php
$transactions = $transactionService->getByReference(
    referenceSource: 'ecommerce',
    referenceId: 'order-12345'
);
```

### Get Reserved Transactions

```php
// Get all pending reserved transactions for a wallet
$reserved = $transactionService->getReservedTransactions($walletId);

foreach ($reserved as $tx) {
    echo $tx->getTypeId();  // 'DB' or 'WB'
    echo $tx->getAmount();
}
```

### Get Transaction by UUID

```php
$transaction = $transactionService->getByUuid($uuid);

if ($transaction === null) {
    // No transaction with this UUID
}
```

### Accept or Reject Reserved Funds by UUID

When the caller supplies its own UUIDs (see [Idempotency](#idempotency)), the whole
reserve/accept lifecycle can be driven with identifiers the caller owns - no need
to store the internal transaction id:

```php
$uuid = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';

$transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 5000)->setUuid($uuid)
);

// Later, e.g. when an external callback arrives:
$transactionService->acceptFundsByUuid($uuid);
// or
$transactionService->rejectFundsByUuid($uuid);
```

There is no need to check for duplicates before creating a transaction: calling
`addFunds()`/`withdrawFunds()` again with the same UUID and the same data returns
the original transaction (see [Idempotency](#idempotency)).

## Transaction Entity Properties

### Core Properties

| Property         | Type     | Description                          |
|------------------|----------|--------------------------------------|
| `transactionId`  | int      | Unique transaction identifier        |
| `walletId`       | int      | Wallet this transaction belongs to   |
| `walletTypeId`   | string   | Wallet type (for denormalization)    |
| `typeId`         | string   | Transaction type (B/D/W/DB/WB/R)     |
| `amount`         | int      | Transaction amount (always positive) |
| `scale`          | int      | Decimal scale at time of transaction |
| `date`           | datetime | Transaction timestamp                |

### Balance Snapshots

These represent wallet state **after** this transaction:

| Property    | Type  | Description                        |
|-------------|-------|------------------------------------|
| `balance`   | int   | Total balance after transaction    |
| `reserved`  | int   | Reserved amount after transaction  |
| `available` | int   | Available amount after transaction |

### Metadata

| Property              | Type         | Description                                     |
|-----------------------|--------------|-------------------------------------------------|
| `code`                | string       | Transaction code (e.g., 'DEP', 'PMT')           |
| `description`         | string       | Human-readable description                      |
| `transactionParentId` | int\|null    | Parent transaction for accept/reject operations |
| `referenceId`         | string\|null | External reference identifier                   |
| `referenceSource`     | string\|null | External system name                            |

### Integrity Fields

| Property       | Type             | Description                                    |
|----------------|------------------|------------------------------------------------|
| `uuid`         | binary(16)       | Unique transaction identifier for idempotency  |
| `previousUuid` | binary(16)\|null | UUID of previous transaction (chain integrity) |
| `checksum`     | string(64)       | SHA-256 hash of transaction data               |

## Helper Methods

### Float Conversions

```php
$transaction = $transactionService->getById($transactionId);

// Get values as floats based on scale
$amountFloat = $transaction->getAmountFloat();      // 50.00
$balanceFloat = $transaction->getBalanceFloat();    // 150.75
$reservedFloat = $transaction->getReservedFloat();  // 25.50
$availableFloat = $transaction->getAvailableFloat(); // 125.25
```

### Checksum Validation

```php
// Calculate checksum for a transaction
$checksum = TransactionEntity::calculateChecksum($transaction);

// Validate checksum
$isValid = TransactionEntity::validateChecksum($transaction, $checksum);

if (!$isValid) {
    throw new Exception('Transaction data integrity compromised!');
}
```

The checksum is calculated from:
```
SHA256(amount|balance|reserved|available|uuid|previousuuid)
```

## Transaction Chain Integrity

Every transaction links to the previous transaction via `previousUuid`, creating an immutable chain:

```php
$transaction1 = $transactionService->addFunds(...);  // previousUuid = null
$transaction2 = $transactionService->addFunds(...);  // previousUuid = $transaction1->uuid
$transaction3 = $transactionService->withdrawFunds(...);  // previousUuid = $transaction2->uuid
```

This ensures:
1. **Chronological ordering** of transactions
2. **Tamper detection** - any modification breaks the chain
3. **Auditability** - can verify entire transaction history

### Verifying the Chain

`verifyChain()` walks the chain from the wallet's `last_uuid` back to the genesis
transaction, validating every checksum and confirming the wallet balances match the
head transaction's snapshot. It detects tampered wallet state, tampered transaction
data, deleted rows (broken links), forked/orphan rows, and cycles.

```php
$result = $transactionService->verifyChain($walletId);

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        // e.g. "Checksum mismatch on transaction 42 (UUID ...)"
        alertOperations($error);
    }
}

echo $result->getTransactionsVerified(); // number of transactions walked
```

Run it from a scheduled reconciliation job so a corruption is detected as soon as
it happens, not when a human audits the ledger.

## Idempotency

Supply your own UUID as an idempotency key to prevent duplicate transactions.
A retry with the same UUID and the same data returns the original transaction
instead of creating a new movement:

```php
// The idempotency key is generated by the caller, once per business operation
$uuid = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';

// First attempt - creates the transaction
$transaction = $transactionService->addFunds(
    TransactionDTO::create($walletId, 5000)
        ->setUuid($uuid)
        ->setDescription('Payment')
);

// Retry after a timeout (same UUID, same data) - returns the original transaction
$replay = $transactionService->addFunds(
    TransactionDTO::create($walletId, 5000)
        ->setUuid($uuid)
        ->setDescription('Payment')
);
// $replay->getTransactionId() === $transaction->getTransactionId()
```

Reusing a UUID with **different** data throws a `TransactionException`
(`The UUID was already used by a different transaction`).

When no UUID is supplied, one is generated automatically and every call
creates a new movement.

## Error Handling

### Common Exceptions

```php
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\TransactionException;
use ByJG\Wallets\Exception\WalletException;

try {
    $transaction = $transactionService->withdrawFunds(
        TransactionDTO::create($walletId, 100000)
    );
} catch (AmountException $e) {
    // Insufficient funds or invalid amount
    echo "Amount error: " . $e->getMessage();
} catch (WalletException $e) {
    // Wallet not found or invalid
    echo "Wallet error: " . $e->getMessage();
} catch (TransactionException $e) {
    // Transaction operation failed
    echo "Transaction error: " . $e->getMessage();
}
```

### Amount Validation

```php
// Amount must be positive
TransactionDTO::create($walletId, -100);  // Throws AmountException

// Must respect minValue
$walletService->createWallet('USD', $userId, 1000, 2, 0);
$transactionService->withdrawFunds(
    TransactionDTO::create($walletId, 2000)  // Throws AmountException
);
```

## Best Practices

1. **Always use TransactionDTO**
   ```php
   // Good
   $dto = TransactionDTO::create($walletId, 5000)
       ->setDescription('Purchase')
       ->setCode('PMT');
   $transactionService->addFunds($dto);

   // Bad - don't create TransactionEntity directly
   ```

2. **Set meaningful descriptions and codes**
   ```php
   $dto->setDescription('Monthly subscription payment')
       ->setCode('SUB')
       ->setReferenceId('subscription-789')
       ->setReferenceSource('billing-system');
   ```

3. **Use reference fields for linking**
   ```php
   // Link to external order
   $dto->setReferenceSource('ecommerce')
       ->setReferenceId('order-12345');

   // Later, find all transactions for an order
   $txs = $transactionService->getByReference('ecommerce', 'order-12345');
   ```

4. **Handle idempotency for external operations**
   ```php
   $externalId = 'payment-provider-tx-123';

   // Check if already processed
   $existing = $transactionService->getByReference('payment-provider', $externalId);
   if (!empty($existing)) {
       return $existing[0];  // Already processed
   }

   // Process new transaction
   $dto = TransactionDTO::create($walletId, $amount)
       ->setReferenceSource('payment-provider')
       ->setReferenceId($externalId);
   return $transactionService->addFunds($dto);
   ```

5. **Use database transactions for complex operations**
   ```php
   $dbExecutor = $transactionService->getRepository()->getExecutor();

   $dbExecutor->beginTransaction();
   try {
       $tx1 = $transactionService->withdrawFunds($dto1);
       $tx2 = $transactionService->addFunds($dto2);
       $dbExecutor->commitTransaction();
   } catch (Exception $e) {
       $dbExecutor->rollbackTransaction();
       throw $e;
   }
   ```
