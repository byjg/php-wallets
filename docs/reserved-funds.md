---
sidebar_position: 4
---

# Reserved Funds (Pending Transactions)

Reserved funds allow you to hold (block) funds for pending operations that may be accepted or rejected later. This is commonly used for:

- **Pre-authorization** on credit cards
- **Pending deposits** from external sources
- **Escrow transactions**
- **Bet/wager reservations**
- **Multi-step payment flows**

## Reserve for Withdrawal

Block funds in a wallet for a future withdrawal:

```php
$reserveTransaction = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 5000)
        ->setDescription('Pre-authorization for purchase')
        ->setCode('PREAUTH')
        ->setReferenceId('order-12345')
        ->setReferenceSource('ecommerce')
);

// Transaction type is 'WB' (Withdraw Blocked)
echo $reserveTransaction->getTypeId();  // 'WB'
echo $reserveTransaction->getTransactionId();  // Save this for later accept/reject
```

**Effect on wallet:**
- `balance` remains unchanged
- `reserved` increases by amount
- `available` decreases by amount

**Example:**
```
Before: balance=10000, reserved=0,    available=10000
After:  balance=10000, reserved=5000, available=5000
```

## Reserve for Deposit

Reserve space for incoming funds that are pending:

```php
$reserveTransaction = $transactionService->reserveFundsForDeposit(
    TransactionDTO::create($walletId, 3000)
        ->setDescription('Pending bank deposit')
        ->setCode('PENDING')
);

// Transaction type is 'DB' (Deposit Blocked)
echo $reserveTransaction->getTypeId();  // 'DB'
```

**Effect on wallet:**
- `balance` remains unchanged
- `reserved` decreases by amount (becomes negative)
- `available` increases by amount

**Example:**
```
Before: balance=10000, reserved=0,     available=10000
After:  balance=10000, reserved=-3000, available=13000
```

:::info Why Negative Reserved?
Negative `reserved` indicates **expected incoming funds**. The `available` balance increases because the user can spend against the expected deposit, but `balance` stays the same until funds actually arrive.
:::

## Accept Reserved Funds

Complete a reserved transaction:

### Accept by Transaction ID

```php
// Previously created reserve
$reserveId = $reserveTransaction->getTransactionId();

// Accept the reservation
$finalTransactionId = $transactionService->acceptFundsById($reserveId);
$finalTransaction = $transactionService->getById($finalTransactionId);

echo $finalTransaction->getTypeId();  // 'W' or 'D' (depending on reserve type)
echo $finalTransaction->getTransactionParentId();  // Points to $reserveId
```

**For Withdraw Blocked (WB) → Withdraw (W):**
```
Reserve:  balance=10000, reserved=5000,  available=5000
Accept:   balance=5000,  reserved=0,     available=5000
```

**For Deposit Blocked (DB) → Deposit (D):**
```
Reserve:  balance=10000, reserved=-3000, available=13000
Accept:   balance=13000, reserved=0,     available=13000
```

### Accept by UUID

```php
$uuid = $reserveTransaction->getUuid();
$finalTransactionId = $transactionService->acceptFundsByUuid($uuid);
```

## Reject Reserved Funds

Cancel a reserved transaction and restore funds:

### Reject by Transaction ID

```php
$rejectTransactionId = $transactionService->rejectFundsById($reserveId);
$rejectTransaction = $transactionService->getById($rejectTransactionId);

echo $rejectTransaction->getTypeId();  // 'R' (Reject)
echo $rejectTransaction->getTransactionParentId();  // Points to $reserveId
```

**For Withdraw Blocked (WB) → Reject (R):**
```
Reserve:  balance=10000, reserved=5000,  available=5000
Reject:   balance=10000, reserved=0,     available=10000  (restored)
```

**For Deposit Blocked (DB) → Reject (R):**
```
Reserve:  balance=10000, reserved=-3000, available=13000
Reject:   balance=10000, reserved=0,     available=10000  (removed)
```

### Reject by UUID

```php
$uuid = $reserveTransaction->getUuid();
$rejectTransactionId = $transactionService->rejectFundsByUuid($uuid);
```

## Partial Accept

Accept only part of a reserved withdrawal and reject the remainder:

```php
// Reserve $100 for a bet
$reserveTransaction = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 10000)
        ->setDescription('Bet reservation')
);

// User actually only bet $80, return $20
$withdrawDto = TransactionDTO::createEmpty()
    ->setAmount(8000)
    ->setDescription('Final bet amount');

$refundDto = TransactionDTO::createEmpty()
    ->setDescription('Bet refund');

$finalTransaction = $transactionService->acceptPartialFundsById(
    transactionId: $reserveTransaction->getTransactionId(),
    transactionDTO: $withdrawDto,
    transactionRefundDTO: $refundDto
);

// Creates two child transactions:
// 1. Withdraw (W) for $80 (actual bet)
// 2. Reject (R) for $100 (releasing the full reservation)
```

**Balance flow:**
```
Initial:          balance=20000, reserved=0,     available=20000
After reserve:    balance=20000, reserved=10000, available=10000
After partial:    balance=12000, reserved=0,     available=12000
```

:::tip Partial Accept Use Cases
- **Partial fulfillment** - order reserved $100, only $80 available
- **Price changes** - reserved $100, actual charge is $95
- **Bet/wager** - reserved max amount, actual bet is less
- **Refunds** - reserved full amount, partial refund needed
:::

## Get Reserved Transactions

Retrieve all pending reserved transactions for a wallet:

```php
$reservedTransactions = $transactionService->getReservedTransactions($walletId);

foreach ($reservedTransactions as $tx) {
    echo "Type: " . $tx->getTypeId();  // 'WB' or 'DB'
    echo "Amount: " . ($tx->getAmount() / 100);
    echo "Description: " . $tx->getDescription();

    // Check if expired or should be auto-rejected
    $age = time() - strtotime($tx->getDate());
    if ($age > 86400 * 7) {  // 7 days old
        $transactionService->rejectFundsById($tx->getTransactionId());
    }
}
```

## Error Handling

### Amount Validation

```php
use ByJG\Wallets\Exception\AmountException;

try {
    // Amount must be positive
    $transactionService->reserveFundsForWithdraw(
        TransactionDTO::create($walletId, -100)
    );
} catch (AmountException $e) {
    echo "Amount must be greater than zero";
}

try {
    // Must have sufficient available funds
    $transactionService->reserveFundsForWithdraw(
        TransactionDTO::create($walletId, 999999)
    );
} catch (AmountException $e) {
    echo "Insufficient available funds";
}
```

### Transaction Validation

```php
use ByJG\Wallets\Exception\TransactionException;

try {
    // Can only accept/reject WB or DB transactions
    $normalTx = $transactionService->addFunds($dto);
    $transactionService->acceptFundsById($normalTx->getTransactionId());
} catch (TransactionException $e) {
    echo "Can only accept blocked transactions";
}

try {
    // Cannot accept/reject same transaction twice
    $transactionService->acceptFundsById($reserveId);
    $transactionService->acceptFundsById($reserveId);  // Already has child
} catch (TransactionException $e) {
    echo "Transaction already accepted/rejected";
}

try {
    // Transaction must exist
    $transactionService->acceptFundsById(99999);
} catch (TransactionException $e) {
    echo "Transaction not found";
}
```

## Use Case Examples

### Credit Card Pre-Authorization

```php
// 1. Pre-authorize $100 on card
$preauth = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($walletId, 10000)
        ->setDescription('Card pre-authorization')
        ->setCode('PREAUTH')
        ->setReferenceId('card-auth-123')
        ->setReferenceSource('payment-gateway')
);

// 2. Customer orders $75 worth of items
$finalAmount = 7500;

// 3. Capture only what was ordered
$captureDto = TransactionDTO::createEmpty()
    ->setAmount($finalAmount)
    ->setDescription('Purchase charge')
    ->setCode('CAPTURE');

$refundDto = TransactionDTO::createEmpty()
    ->setDescription('Pre-auth release');

$charge = $transactionService->acceptPartialFundsById(
    $preauth->getTransactionId(),
    $captureDto,
    $refundDto
);

// Result: Charged $75, released $25 hold
```

### Pending Bank Deposit

```php
// 1. Bank notifies of incoming $500 transfer (pending)
$pending = $transactionService->reserveFundsForDeposit(
    TransactionDTO::create($walletId, 50000)
        ->setDescription('Pending bank transfer')
        ->setCode('PENDING')
        ->setReferenceId('bank-transfer-456')
        ->setReferenceSource('bank-api')
);

// User can now spend against the pending deposit
// available = balance + 50000 (but balance hasn't changed yet)

// 2. Bank confirms transfer cleared
$deposit = $transactionService->acceptFundsById($pending->getTransactionId());

// Now balance increases by 50000, reserved returns to 0

// 2. Alternative: Bank rejects transfer
// $reject = $transactionService->rejectFundsById($pending->getTransactionId());
// available returns to original amount
```

### Escrow Transaction

```php
// 1. Buyer reserves funds for purchase
$escrow = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($buyerWalletId, 100000)
        ->setDescription('Escrow for purchase')
        ->setCode('ESCROW')
        ->setReferenceId('escrow-789')
);

// Funds are locked (reserved) but not yet transferred

// 2a. Purchase confirmed - complete the withdrawal
$withdrawal = $transactionService->acceptFundsById($escrow->getTransactionId());

// Transfer to seller
$transactionService->addFunds(
    TransactionDTO::create($sellerWalletId, 100000)
        ->setDescription('Payment received')
        ->setReferenceId('escrow-789')
);

// 2b. Alternative: Purchase cancelled - release funds
// $release = $transactionService->rejectFundsById($escrow->getTransactionId());
```

### Betting/Gaming

```php
// 1. User places bet - reserve funds
$betReserve = $transactionService->reserveFundsForWithdraw(
    TransactionDTO::create($userWalletId, 5000)
        ->setDescription('Bet on Game #123')
        ->setCode('BET')
        ->setReferenceId('bet-12345')
);

// 2a. User wins - reject the withdrawal, add winnings
$transactionService->rejectFundsById($betReserve->getTransactionId());
$transactionService->addFunds(
    TransactionDTO::create($userWalletId, 10000)
        ->setDescription('Bet winnings')
        ->setCode('WIN')
        ->setReferenceId('bet-12345')
);

// 2b. User loses - accept the withdrawal
// $loss = $transactionService->acceptFundsById($betReserve->getTransactionId());
```

## Best Practices

1. **Always clean up reserved transactions**
   ```php
   // Implement timeout for old reservations
   $reserved = $transactionService->getReservedTransactions($walletId);
   foreach ($reserved as $tx) {
       $hours = (time() - strtotime($tx->getDate())) / 3600;
       if ($hours > 72) {  // 3 days
           $transactionService->rejectFundsById($tx->getTransactionId());
       }
   }
   ```

2. **Use meaningful descriptions**
   ```php
   // Good
   ->setDescription('Pre-auth for Order #12345')

   // Bad
   ->setDescription('Reserved funds')
   ```

3. **Link with reference fields**
   ```php
   $reserve = $transactionService->reserveFundsForWithdraw($dto);

   // Later, find the reservation by external ID
   $txs = $transactionService->getByReference('ecommerce', 'order-12345');
   $reserve = $txs[0];
   $transactionService->acceptFundsById($reserve->getTransactionId());
   ```

4. **Handle accept/reject idempotently**
   ```php
   function acceptReservation($reserveId) {
       $reserve = $transactionService->getById($reserveId);

       // Check if already processed
       if ($reserve->getTransactionParentId() !== null) {
           // Already accepted/rejected, find the child transaction
           return $transactionService->getByParentId($reserveId);
       }

       return $transactionService->acceptFundsById($reserveId);
   }
   ```

5. **Validate reserved amount constraints**
   ```php
   // Don't allow over-reservation
   $wallet = $walletService->getById($walletId);
   $currentReserved = $transactionService->getReservedTransactions($walletId);

   $totalReserved = array_sum(array_map(
       fn($tx) => $tx->getTypeId() === 'WB' ? $tx->getAmount() : 0,
       $currentReserved
   ));

   if ($totalReserved + $newReserveAmount > $wallet->getAvailable()) {
       throw new AmountException('Cannot reserve more than available');
   }
   ```
