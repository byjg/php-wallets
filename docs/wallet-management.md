---
sidebar_position: 2
---

# Wallet Management

## Creating Wallets

### Basic Wallet Creation

```php
$walletId = $walletService->createWallet(
    walletTypeId: 'USD',
    userId: 'user-123',
    balance: 10000,      // $100.00 in cents
    scale: 2,            // 2 decimal places
    minValue: 0,         // Cannot go below $0.00
    extra: null          // Optional JSON metadata
);
```

### Parameters

- **walletTypeId** (string, required): The currency/wallet type (must exist in `wallettype` table)
- **userId** (string, required): User identifier who owns this wallet
- **balance** (int, required): Initial balance in smallest unit (e.g., cents)
- **scale** (int, optional, default=2): Number of decimal places
- **minValue** (int, optional, default=0): Minimum allowed balance
- **extra** (string|null, optional): JSON string for custom metadata

### Wallets with Negative Balance

You can allow negative balances (overdraft) by setting a negative `minValue`:

```php
// Allow overdraft up to -$50.00
$walletId = $walletService->createWallet(
    walletTypeId: 'USD',
    userId: 'user-123',
    balance: 0,
    scale: 2,
    minValue: -5000  // Can go down to -$50.00
);
```

## Retrieving Wallets

### Get Wallet by ID

```php
$wallet = $walletService->getById($walletId);

echo "Balance: " . ($wallet->getBalance() / 100) . "\n";
echo "Available: " . ($wallet->getAvailable() / 100) . "\n";
echo "Reserved: " . ($wallet->getReserved() / 100) . "\n";
echo "Scale: " . $wallet->getScale() . "\n";
```

### Get Wallets by User ID

```php
// Get all wallets for a user
$wallets = $walletService->getByUserId('user-123');

// Get specific wallet type for a user
$usdWallets = $walletService->getByUserId('user-123', 'USD');
```

### Get Wallets by Type

```php
// Get all wallets of a specific type
$allUsdWallets = $walletService->getByWalletTypeId('USD');
```

## Balance Operations

### Override Balance

Reset a wallet to a specific balance, ignoring previous transactions:

```php
$transactionId = $walletService->overrideBalance(
    walletId: $walletId,
    newBalance: 50000,           // $500.00
    newScale: 2,
    newMinValue: 0,
    description: 'Balance correction'
);
```

:::warning
`overrideBalance()` creates a **Balance (B)** transaction that resets the wallet. This operation:
- Maintains existing reserved funds
- Validates that new balance can accommodate reserved amounts
- Updates scale and minValue
:::

### Partial Balance Adjustment

Adjust balance to a target available amount:

```php
// Adjust wallet to have $75.00 available
$transaction = $walletService->partialBalance(
    walletId: $walletId,
    balance: 7500,  // Target available balance
    description: 'Balance adjustment'
);
```

This method:
- Calculates the difference between current available and target
- Adds funds if target > current available
- Withdraws funds if target < current available

### Close Wallet

Set wallet balance to zero:

```php
$transactionId = $walletService->closeWallet($walletId);
```

:::caution
Cannot close wallet if there are pending reserved transactions.
:::

## Transfer Between Wallets

Transfer funds from one wallet to another:

```php
[$sourceTransaction, $targetTransaction] = $walletService->transferFunds(
    walletSource: $sourceWalletId,
    walletTarget: $targetWalletId,
    amount: 5000  // $50.00
);

// Both transactions share the same reference
echo $sourceTransaction->getReferenceId();  // e.g., "a1b2c3d4..."
echo $targetTransaction->getReferenceId();  // Same value
```

Transfer creates:
- **Withdrawal (W)** in source wallet with code `T_TO`
- **Deposit (D)** in target wallet with code `T_FROM`
- Linked via `referenceid` and `referencesource`

## Wallet Properties

### WalletEntity Properties

| Property       | Type         | Description                                    |
|----------------|--------------|------------------------------------------------|
| `walletId`     | int          | Unique wallet identifier                       |
| `walletTypeId` | string       | Currency/type code (e.g., 'USD', 'EUR')        |
| `userId`       | string       | User who owns this wallet                      |
| `balance`      | int          | Total balance (reserved + available)           |
| `reserved`     | int          | Funds held for pending transactions            |
| `available`    | int          | Funds available for use                        |
| `scale`        | int          | Decimal places for this wallet                 |
| `minValue`     | int          | Minimum allowed balance                        |
| `extra`        | string\|null | Custom JSON metadata                           |
| `lastUuid`     | binary       | UUID of last transaction (for chain integrity) |
| `entryDate`    | datetime     | Last update timestamp                          |

### Helper Methods

```php
// Get values as floats
$balanceFloat = $wallet->getBalanceFloat();      // 100.50
$availableFloat = $wallet->getAvailableFloat();  // 75.25
$reservedFloat = $wallet->getReservedFloat();    // 25.25

// Convert array representation
$walletArray = $wallet->toArray();
```

## Wallet Types

### Creating Wallet Types

Before creating wallets, you must define wallet types:

```php
$walletType = new WalletTypeEntity();
$walletType->setWalletTypeId('EUR');
$walletType->setName('Euro Wallet');
$walletTypeService->update($walletType);
```

### Common Wallet Types

```php
// Fiat currencies
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('USD')->setName('US Dollar'));
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('EUR')->setName('Euro'));
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('GBP')->setName('British Pound'));

// Cryptocurrencies (use scale=8)
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('BTC')->setName('Bitcoin'));
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('ETH')->setName('Ethereum'));

// Points/Credits (use scale=0)
$walletTypeService->update((new WalletTypeEntity())
    ->setWalletTypeId('POINTS')->setName('Loyalty Points'));
```

## Best Practices

1. **Always use scale appropriately**
   - Fiat currencies: `scale=2` (cents)
   - Cryptocurrencies: `scale=8` (satoshis)
   - Points/tokens: `scale=0` (whole units)

2. **Validate wallet ownership**
   ```php
   $wallet = $walletService->getById($walletId);
   if ($wallet->getUserId() !== $currentUserId) {
       throw new UnauthorizedException('Not your wallet');
   }
   ```

3. **Use minValue for overdraft protection**
   ```php
   // No overdraft allowed
   $walletService->createWallet('USD', $userId, 0, 2, 0);

   // Allow up to $100 overdraft
   $walletService->createWallet('USD', $userId, 0, 2, -10000);
   ```

4. **Handle concurrent access**
   - The library uses database transactions internally
   - UUID chain ensures transaction integrity
   - Checksum validates transaction data integrity
