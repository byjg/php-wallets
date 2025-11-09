---
sidebar_position: 5
---

# Extending Entities

You can extend the `TransactionEntity` and `WalletEntity` classes to add custom fields to your transactions and wallets. This is useful for storing additional metadata specific to your application.

## Extending Transaction Entity

### 1. Create Database Table

First, create a new table that extends the base `transaction` table:

```sql
CREATE TABLE transaction_extended (
    transactionid INT(11) NOT NULL,
    extra_property VARCHAR(255) DEFAULT NULL,
    custom_field INT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    PRIMARY KEY (transactionid),
    CONSTRAINT fk_transaction_extended_transaction
        FOREIGN KEY (transactionid)
        REFERENCES transaction (transactionid)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
```

:::tip Table Design
- Use same primary key as parent table (`transactionid`)
- Add foreign key constraint with `ON DELETE CASCADE`
- Only add your custom fields - inherited fields come from parent
:::

### 2. Create Extended Entity Class

```php
<?php

namespace App\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Wallets\Entity\TransactionEntity;

#[TableAttribute('transaction_extended')]
class TransactionExtended extends TransactionEntity
{
    #[FieldAttribute(fieldName: 'extra_property')]
    protected ?string $extraProperty = null;

    #[FieldAttribute(fieldName: 'custom_field')]
    protected ?int $customField = null;

    #[FieldAttribute(fieldName: 'metadata')]
    protected ?string $metadata = null;

    public function getExtraProperty(): ?string
    {
        return $this->extraProperty;
    }

    public function setExtraProperty(?string $extraProperty): void
    {
        $this->extraProperty = $extraProperty;
    }

    public function getCustomField(): ?int
    {
        return $this->customField;
    }

    public function setCustomField(?int $customField): void
    {
        $this->customField = $customField;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): void
    {
        $this->metadata = $metadata;
    }
}
```

### 3. Create Extended Repository

```php
<?php

namespace App\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Wallets\Repository\TransactionRepository;

class TransactionRepositoryExtended extends TransactionRepository
{
    public function __construct(
        DatabaseExecutor $dbExecutor,
        string $transactionEntity = \App\Entity\TransactionExtended::class,
        array $fieldMappingList = []
    ) {
        parent::__construct($dbExecutor, $transactionEntity, $fieldMappingList);
    }

    /**
     * Custom method example: Get transactions by custom field
     */
    public function getByCustomField(int $customFieldValue): array
    {
        return $this->getRepository()->getByQuery(
            "SELECT * FROM transaction_extended
             WHERE custom_field = :value",
            ['value' => $customFieldValue]
        );
    }
}
```

### 4. Use Extended Repository with Services

```php
use App\Repository\TransactionRepositoryExtended;
use App\Entity\TransactionExtended;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Repository\WalletRepository;
use ByJG\AnyDataset\Db\Factory;

// Create database connection
$dbDriver = Factory::getDbInstance('mysql://user:pass@localhost/dbname');

// Initialize with extended repository
$transactionRepo = new TransactionRepositoryExtended($dbDriver);
$walletRepo = new WalletRepository($dbDriver);

$transactionService = new TransactionService($transactionRepo, $walletRepo);

// Now transactions will be TransactionExtended instances
$transaction = $transactionService->addFunds($dto);

// Access custom fields
if ($transaction instanceof TransactionExtended) {
    $transaction->setExtraProperty('custom value');
    $transaction->setCustomField(12345);
    $transactionService->getRepository()->save($transaction);
}
```

### 5. Set Custom Fields During Transaction

You can also set custom fields when creating transactions by using observers or by extending the service:

```php
class TransactionServiceExtended extends TransactionService
{
    public function addFundsWithMetadata(
        TransactionDTO $dto,
        string $metadata
    ): TransactionExtended {
        $transaction = parent::addFunds($dto);

        if ($transaction instanceof TransactionExtended) {
            $transaction->setMetadata($metadata);
            $this->getRepository()->save($transaction);
        }

        return $transaction;
    }
}
```

## Extending Wallet Entity

The process for extending wallets is similar:

### 1. Create Extended Wallet Table

```sql
CREATE TABLE wallet_extended (
    walletid INT(11) NOT NULL,
    loyalty_points INT DEFAULT 0,
    tier VARCHAR(20) DEFAULT 'bronze',
    preferences JSON DEFAULT NULL,
    PRIMARY KEY (walletid),
    CONSTRAINT fk_wallet_extended_wallet
        FOREIGN KEY (walletid)
        REFERENCES wallet (walletid)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
```

### 2. Create Extended Wallet Entity

```php
<?php

namespace App\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Wallets\Entity\WalletEntity;

#[TableAttribute('wallet_extended')]
class WalletExtended extends WalletEntity
{
    #[FieldAttribute(fieldName: 'loyalty_points')]
    protected int $loyaltyPoints = 0;

    #[FieldAttribute(fieldName: 'tier')]
    protected string $tier = 'bronze';

    #[FieldAttribute(fieldName: 'preferences')]
    protected ?string $preferences = null;

    public function getLoyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function setLoyaltyPoints(int $loyaltyPoints): void
    {
        $this->loyaltyPoints = $loyaltyPoints;
    }

    public function getTier(): string
    {
        return $this->tier;
    }

    public function setTier(string $tier): void
    {
        $this->tier = $tier;
    }

    public function getPreferences(): ?string
    {
        return $this->preferences;
    }

    public function setPreferences(?string $preferences): void
    {
        $this->preferences = $preferences;
    }
}
```

### 3. Create Extended Wallet Repository

```php
<?php

namespace App\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Wallets\Repository\WalletRepository;

class WalletRepositoryExtended extends WalletRepository
{
    public function __construct(
        DatabaseExecutor $dbExecutor,
        string $walletEntity = \App\Entity\WalletExtended::class,
        array $fieldMappingList = []
    ) {
        parent::__construct($dbExecutor, $walletEntity, $fieldMappingList);
    }

    /**
     * Get all wallets by tier
     */
    public function getByTier(string $tier): array
    {
        return $this->getRepository()->getByQuery(
            "SELECT * FROM wallet_extended WHERE tier = :tier",
            ['tier' => $tier]
        );
    }

    /**
     * Update loyalty points
     */
    public function addLoyaltyPoints(int $walletId, int $points): void
    {
        $wallet = $this->getById($walletId);
        if ($wallet instanceof \App\Entity\WalletExtended) {
            $wallet->setLoyaltyPoints($wallet->getLoyaltyPoints() + $points);
            $this->save($wallet);
        }
    }
}
```

### 4. Use Extended Wallet Repository

```php
use App\Repository\WalletRepositoryExtended;
use ByJG\Wallets\Service\WalletService;

$walletRepo = new WalletRepositoryExtended($dbDriver);
$walletService = new WalletService($walletRepo, $walletTypeService, $transactionService);

// Create wallet - custom fields can be set after creation
$walletId = $walletService->createWallet('USD', 'user-123', 10000);

$wallet = $walletService->getById($walletId);
if ($wallet instanceof \App\Entity\WalletExtended) {
    $wallet->setTier('gold');
    $wallet->setLoyaltyPoints(1000);
    $wallet->setPreferences('{"notifications": true}');
    $walletRepo->save($wallet);
}
```

## Using Observers for Auto-Population

You can use MicroORM observers to automatically populate custom fields:

### Create Observer

```php
<?php

namespace App\Observer;

use ByJG\MicroOrm\Observer\ObserverInterface;
use App\Entity\TransactionExtended;

class TransactionObserver implements ObserverInterface
{
    public function beforeInsert(object $instance): void
    {
        if ($instance instanceof TransactionExtended) {
            // Auto-populate metadata on insert
            if (empty($instance->getMetadata())) {
                $instance->setMetadata(json_encode([
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'timestamp' => time(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]));
            }
        }
    }

    public function afterInsert(object $instance): void
    {
        // Called after insert
    }

    public function beforeUpdate(object $instance): void
    {
        // Called before update
    }

    public function afterUpdate(object $instance): void
    {
        // Called after update
    }

    public function beforeDelete(object $instance): void
    {
        // Called before delete
    }

    public function afterDelete(object $instance): void
    {
        // Called after delete
    }
}
```

### Register Observer

```php
class TransactionRepositoryExtended extends TransactionRepository
{
    public function __construct(DatabaseExecutor $dbExecutor)
    {
        parent::__construct($dbExecutor, TransactionExtended::class);

        // Register observer
        $this->getRepository()->addObserver(new \App\Observer\TransactionObserver());
    }
}
```

## Field Mapping

If your database column names don't match your property names, use field mapping:

```php
use ByJG\MicroOrm\FieldMapping;

$fieldMappingList = [
    FieldMapping::create('extraProperty')
        ->withFieldName('extra_property')
        ->withUpdateFunction(fn($value) => strtoupper($value)),

    FieldMapping::create('metadata')
        ->withFieldName('metadata')
        ->withSelectFunction(fn($value) => json_decode($value, true))
        ->withUpdateFunction(fn($value) => json_encode($value))
];

$repository = new TransactionRepositoryExtended(
    $dbExecutor,
    TransactionExtended::class,
    $fieldMappingList
);
```

## Complete Example

Here's a complete example combining wallets and transactions with custom fields:

```php
<?php

// 1. Setup
$dbDriver = Factory::getDbInstance('mysql://user:pass@localhost/dbname');

$walletRepo = new WalletRepositoryExtended($dbDriver);
$transactionRepo = new TransactionRepositoryExtended($dbDriver);

$walletTypeService = new WalletTypeService(new WalletTypeRepository($dbDriver));
$transactionService = new TransactionService($transactionRepo, $walletRepo);
$walletService = new WalletService($walletRepo, $walletTypeService, $transactionService);

// 2. Create wallet with custom fields
$walletId = $walletService->createWallet('USD', 'user-123', 10000);

$wallet = $walletService->getById($walletId);
$wallet->setTier('platinum');
$wallet->setLoyaltyPoints(5000);
$wallet->setPreferences('{"theme": "dark", "alerts": true}');
$walletRepo->save($wallet);

// 3. Create transaction with custom fields
$dto = TransactionDTO::create($walletId, 2500)
    ->setDescription('VIP purchase')
    ->setCode('VIP');

$transaction = $transactionService->addFunds($dto);

$transaction->setExtraProperty('VIP member discount applied');
$transaction->setCustomField(42);
$transaction->setMetadata('{"discount": 0.15, "campaign": "summer2024"}');
$transactionRepo->save($transaction);

// 4. Query by custom fields
$goldWallets = $walletRepo->getByTier('gold');
$transactions = $transactionRepo->getByCustomField(42);

// 5. Award loyalty points on transaction
$walletRepo->addLoyaltyPoints($walletId, 25);  // Add 25 points

echo "Transaction {$transaction->getTransactionId()} created\n";
echo "Extra property: {$transaction->getExtraProperty()}\n";
echo "Loyalty points: {$wallet->getLoyaltyPoints()}\n";
```

## Best Practices

1. **Use proper foreign key constraints**
   ```sql
   CONSTRAINT fk_transaction_extended_transaction
       FOREIGN KEY (transactionid)
       REFERENCES transaction (transactionid)
       ON DELETE CASCADE
   ```

2. **Keep base tables clean**
   - Don't modify `transaction` or `wallet` tables directly
   - All customizations go in extended tables

3. **Use JSON for flexible metadata**
   ```php
   $transaction->setMetadata(json_encode([
       'ip' => $ipAddress,
       'session_id' => $sessionId,
       'device_type' => $deviceType
   ]));
   ```

4. **Type-hint properly**
   ```php
   public function processVIPTransaction(TransactionExtended $transaction): void
   {
       $metadata = json_decode($transaction->getMetadata(), true);
       // ...
   }
   ```

5. **Document custom fields**
   ```php
   /**
    * @property string|null $extraProperty Additional transaction info
    * @property int|null $customField Application-specific ID
    * @property string|null $metadata JSON-encoded metadata
    */
   class TransactionExtended extends TransactionEntity
   ```

6. **Test extended functionality**
   ```php
   public function testExtendedTransaction(): void
   {
       $dto = TransactionDTO::create($this->walletId, 1000);
       $tx = $this->transactionService->addFunds($dto);

       $this->assertInstanceOf(TransactionExtended::class, $tx);

       $tx->setExtraProperty('test');
       $this->transactionRepo->save($tx);

       $loaded = $this->transactionService->getById($tx->getTransactionId());
       $this->assertEquals('test', $loaded->getExtraProperty());
   }
   ```
