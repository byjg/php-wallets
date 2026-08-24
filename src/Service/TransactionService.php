<?php

namespace ByJG\Wallets\Service;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\InsertSelectQuery;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\UpdateQuery;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\Wallets\Checksum\ChecksumFactory;
use ByJG\Wallets\Checksum\ChecksumV2;
use ByJG\Wallets\DTO\ChainVerificationResult;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\TransactionException;
use ByJG\Wallets\Exception\WalletException;
use ByJG\Wallets\Repository\OutboxRepository;
use ByJG\Wallets\Repository\TransactionRepository;
use ByJG\Wallets\Repository\WalletRepository;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use PDOException;
use Throwable;

class TransactionService
{
    /**
     * @var TransactionRepository
     */
    protected TransactionRepository $transactionRepository;

    /**
     * @var WalletRepository
     */
    protected WalletRepository $walletRepository;

    /**
     * Secret appended to every checksum (empty string when not configured).
     */
    protected string $checksumSecret;

    /**
     * Transactional outbox (disabled when null).
     */
    protected ?OutboxRepository $outboxRepository;

    /**
     * TransactionService constructor.
     * @param TransactionRepository $transactionRepository
     * @param WalletRepository $walletRepository
     * @param string|null $checksumSecret Optional installation secret mixed into every
     *        transaction checksum, so an attacker with database access only cannot
     *        recompute valid checksums. Once transactions are created with a secret,
     *        the same secret must always be provided; verification fails otherwise.
     * @param OutboxRepository|null $outboxRepository Optional transactional outbox: when
     *        provided, every created ledger transaction also writes an outbox entry in
     *        the same database transaction, for guaranteed delivery to a message broker
     *        via OutboxService::dispatch().
     */
    public function __construct(TransactionRepository $transactionRepository, WalletRepository $walletRepository, ?string $checksumSecret = null, ?OutboxRepository $outboxRepository = null)
    {
        $this->transactionRepository = $transactionRepository;
        $this->walletRepository = $walletRepository;
        $this->checksumSecret = $checksumSecret ?? '';
        $this->outboxRepository = $outboxRepository;
    }

    /**
     * Record a transactional-outbox event for a created ledger transaction.
     * No-op when the outbox was not enabled (constructor param). Must be called
     * inside the same database transaction that created the ledger row, so the
     * event exists if and only if the transaction committed.
     *
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function recordOutbox(TransactionEntity $transaction): void
    {
        if ($this->outboxRepository === null) {
            return;
        }

        $entry = new OutboxEntity();
        $entry->setTransactionId($transaction->getTransactionId());
        $entry->setUuid($transaction->getUuid());
        $entry->setEvent(OutboxEntity::EVENT_TRANSACTION_CREATED);
        $entry->setStatus(OutboxEntity::STATUS_PENDING);
        $entry->setAttempts(0);
        $this->outboxRepository->save($entry);
    }

    /**
     * The secret used to calculate transaction checksums (empty string when not configured).
     * Shared with WalletService so both services produce identical checksums.
     */
    public function getChecksumSecret(): string
    {
        return $this->checksumSecret;
    }

    /**
     * Get a Transaction By ID.
     *
     * @param int|string $transactionId Optional. empty, return all ids.
     * @return mixed
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmInvalidFieldsException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(int|string $transactionId): mixed
    {
        return $this->transactionRepository->getById($transactionId);
    }

    /**
     * Get a Transaction by its UUID (e.g., a caller-supplied idempotency key).
     *
     * @param Literal|string $uuid
     * @return TransactionEntity|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByUuid(Literal|string $uuid): ?TransactionEntity
    {
        return $this->transactionRepository->getByUuid($uuid);
    }

    /**
     * @throws AmountException
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws TransactionException
     */
    protected function validateTransactionDto(TransactionDTO $dto): void
    {
        if (!$dto->hasWallet()) {
            throw new TransactionException('Wallet is required');
        }
        if ($dto->getAmount() < 0) {
            throw new AmountException('Amount needs to be greater than zero');
        }

        // A caller-supplied UUID acts as an idempotency key: a retry with the same UUID
        // will not create a duplicate transaction (enforced by the unique index on uuid)
        $uuid = $dto->getUuid();
        if (empty($uuid)) {
            $dto->setUuid($dto->calculateUuid($this->transactionRepository->getExecutor()));
        } elseif (!($uuid instanceof Literal)) {
            $dto->setUuid(new HexUuidLiteral($uuid));
        }
    }

    /**
     * Central method to apply a balance-changing operation.
     * - Creates a new transaction row reflecting the post-operation balances
     * - Updates the wallet with the same balances and the last transaction id
     *
     * @param string $operation One of TransactionEntity::DEPOSIT, WITHDRAW, DEPOSIT_BLOCKED, WITHDRAW_BLOCKED
     * @param TransactionDTO $dto Input data (wallet, amount, description, etc.)
     * @param bool $capAtZero When true and operation is WITHDRAW, caps the withdrawal so the net balance never goes below zero
     * @return TransactionEntity
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    protected function updateFunds(string $operation, TransactionDTO $dto, bool $capAtZero = false): TransactionEntity
    {
        $this->validateTransactionDto($dto);

        // 1) Compute numeric deltas for balances (used for notifications) and the SQL expressions (used for insert/select)
        /** @psalm-suppress PossiblyNullArgument - validated by validateTransactionDto */
        [$balanceDelta, $reservedDelta, $availableDelta] = $this->computeBalanceDeltas($operation, $dto->getAmount());
        /** @psalm-suppress PossiblyNullArgument - validated by validateTransactionDto */
        [$exprAmount, $exprBalance, $exprAvailable] = $this->buildAmountAndExpressions($operation, $dto->getAmount(), $capAtZero);

        // 2) Build the insert-select for the transaction and the wallet update based on the new transaction
        $transactionInsert = $this->getInsertTransactionQuery(
            $operation,
            $dto,
            $exprBalance,
            $exprAvailable,
            $exprAmount,
            (string)$reservedDelta
        );

        $walletUpdate = $this->getWalletUpdateQuery($dto);

        // 3) Build the statements upfront and defer their observer notification: the entities are
        // attached after commit, so observers receive them instead of the raw SQL parameters
        $executor = $this->getRepository()->getExecutor();
        $transactionInsertStmt = $transactionInsert->build($executor->getDriver());
        $transactionInsertStmt->getOrmContext()->defer();
        $walletUpdateStmt = $walletUpdate->build($executor->getDriver());
        $walletUpdateStmt->getOrmContext()->defer();

        // 4) Execute both queries atomically
        $executor->beginTransaction(IsolationLevelEnum::SERIALIZABLE, allowJoin: true);
        try {
            $executor->execute($transactionInsertStmt);
            $executor->execute($walletUpdateStmt);

            // 5) Load the wallet just updated
            /** @var WalletEntity $wallet */
            /** @psalm-suppress PossiblyNullArgument - validated by validateTransactionDto */
            $wallet = $this->walletRepository->getById($dto->getWalletId());
            if (empty($wallet)) {
                throw new WalletException('Transaction Failed: Wallet not found');
            }
            if (empty($wallet->getLastUuid())) {
                throw new WalletException('Transaction Failed: Wallet last_uuid is empty');
            }
            if (HexUuidLiteral::getFormattedUuid($wallet->getLastUuid()) !== HexUuidLiteral::getFormattedUuid($dto->getUuid())) {
                throw new WalletException('Transaction Failed: Wallet last_uuid does not match the DTO');
            }

            // 6) Load the transaction just created
            /** @psalm-suppress PossiblyNullArgument - UUID set by validateTransactionDto */
            $transaction = $this->transactionRepository->getByUuid($dto->getUuid());
            if (empty($transaction)) {
                throw new TransactionException('Transaction Failed: Transaction not found');
            }

            // Validate that the persisted transaction matches the DTO intent (allowing capped withdraw amount)
            $mismatches = $this->getTransactionDtoMismatches($transaction, $dto, $operation, $capAtZero);
            if (!empty($mismatches)) {
                throw new TransactionException('Persisted transaction does not match the DTO fields: ' . implode(', ', $mismatches));
            }

            $this->recordOutbox($transaction);

            $this->getRepository()->getExecutor()->commitTransaction();
        } catch (Throwable $ex) {
            if ($this->getRepository()->getExecutor()->hasActiveTransaction()) {
                $this->getRepository()->getExecutor()->rollbackTransaction();
            }
            if ($ex instanceof PDOException && str_contains($ex->getMessage(), 'chk_value_nonnegative')) {
                throw new AmountException('Cannot withdraw above the wallet balance');
            }
            if ($ex instanceof PDOException && str_contains($ex->getMessage(), 'idx_transaction_uuid')) {
                // Idempotent replay: the caller-supplied UUID was already processed.
                // Return the original transaction when it matches the DTO; otherwise fail loudly.
                /** @psalm-suppress PossiblyNullArgument - UUID set by validateTransactionDto */
                $existing = $this->transactionRepository->getByUuid($dto->getUuid());
                if (!empty($existing) && empty($this->getTransactionDtoMismatches($existing, $dto, $operation, $capAtZero))) {
                    $dto->setAmount(intval($existing->getAmount()));
                    return $existing;
                }
                throw new TransactionException('The UUID was already used by a different transaction');
            }
            throw $ex;
        }

        // 7) Notify observers of wallet change, providing an oldWallet with pre-change balances
        $oldWallet = clone $wallet;
        $oldWallet->setBalance($oldWallet->getBalance() - $balanceDelta);
        $oldWallet->setReserved($oldWallet->getReserved() - $reservedDelta);
        $oldWallet->setAvailable($oldWallet->getAvailable() - $availableDelta);

        $walletContext = $walletUpdateStmt->getOrmContext();
        $walletContext->setEntities($wallet, $oldWallet);
        foreach ($walletContext->drainPendingBridges() as $bridge) {
            $bridge->notifyStatement($walletUpdateStmt);
        }

        // 8) Notify observers of transaction insert
        $transactionContext = $transactionInsertStmt->getOrmContext();
        $transactionContext->setEntities($transaction, null);
        foreach ($transactionContext->drainPendingBridges() as $bridge) {
            $bridge->notifyStatement($transactionInsertStmt);
        }

        // If capping occurred on withdrawal, the actual amount may differ from the DTO amount
        $dto->setAmount(intval($transaction->getAmount()));

        return $transaction;
    }

    /**
     * Compare a persisted transaction against the DTO intent.
     * Returns the list of fields that do not match (allowing a capped withdraw amount).
     *
     * @return string[]
     */
    private function getTransactionDtoMismatches(TransactionEntity $transaction, TransactionDTO $dto, string $operation, bool $capAtZero): array
    {
        $mismatches = [];
        if ((int)$transaction->getWalletId() !== (int)$dto->getWalletId()) { $mismatches[] = 'walletId'; }
        if ($transaction->getDescription() !== $dto->getDescription()) { $mismatches[] = 'description'; }
        if ($transaction->getCode() !== $dto->getCode()) { $mismatches[] = 'code'; }
        if ($transaction->getReferenceId() !== $dto->getReferenceId()) { $mismatches[] = 'referenceId'; }
        if ($transaction->getReferenceSource() !== $dto->getReferenceSource()) { $mismatches[] = 'referenceSource'; }
        if ($transaction->getTypeId() !== $operation) { $mismatches[] = 'typeId'; }
        $amountMatches =
            ($transaction->getAmount() === $dto->getAmount()) ||
            ($capAtZero && $operation === TransactionEntity::WITHDRAW && $transaction->getAmount() <= $dto->getAmount());
        if (!$amountMatches) { $mismatches[] = 'amount'; }
        foreach ($dto->getProperties() as $propertyName => $propertyValue) {
            $fieldMap = $this->transactionRepository->getMapper()->getFieldMap($propertyName);
            /** @psalm-suppress PossiblyInvalidMethodCall - getFieldMap returns FieldMapping when property name provided */
            if ($fieldMap && $fieldMap->isSyncWithDb()) {
                /** @psalm-suppress PossiblyInvalidMethodCall - getFieldMap returns FieldMapping when property name provided */
                $fieldName = "get" . $fieldMap->getPropertyName();
                if ($transaction->$fieldName() !== $propertyValue) {
                    $mismatches[] = $fieldName;
                }
            }
        }
        return $mismatches;
    }

    // ---- Helpers: computations and query building ---------------------------------------------------------------

    /**
     * Compute numeric deltas for balances according to the operation and amount.
     * Returns [grossDelta, reservedDelta, netDelta].
     */
    private function computeBalanceDeltas(string $operation, int $amount): array
    {
        $balanceDelta = $amount * match ($operation) {
            TransactionEntity::DEPOSIT => 1,
            TransactionEntity::WITHDRAW => -1,
            default => 0,
        };

        $reservedDelta = $amount * match ($operation) {
            TransactionEntity::DEPOSIT_BLOCKED => -1,
            TransactionEntity::WITHDRAW_BLOCKED => 1,
            default => 0,
        };

        $availableDelta = $amount * match ($operation) {
            TransactionEntity::DEPOSIT, TransactionEntity::DEPOSIT_BLOCKED => 1,
            TransactionEntity::WITHDRAW, TransactionEntity::WITHDRAW_BLOCKED => -1,
            default => 0,
        };

        return [$balanceDelta, $reservedDelta, $availableDelta];
    }

    /**
     * Build SQL literal expressions for amount, gross and net balances.
     * When capping at zero (withdraw), it ensures the amount is reduced to avoid a negative net balance.
     * Returns [exprAmount, exprGross, exprNet].
     */
    private function buildAmountAndExpressions(string $operation, int $amount, bool $capAtZero): array
    {
        $exprBalance = "balance + " . ($amount * match ($operation) {
            TransactionEntity::DEPOSIT => 1,
            TransactionEntity::WITHDRAW => -1,
            default => 0,
        });
        $exprAvailable = "available + " . ($amount * match ($operation) {
            TransactionEntity::DEPOSIT, TransactionEntity::DEPOSIT_BLOCKED => 1,
            TransactionEntity::WITHDRAW, TransactionEntity::WITHDRAW_BLOCKED => -1,
            default => 0,
        });
        $exprAmount = (string)$amount;

        if ($capAtZero && $operation === TransactionEntity::WITHDRAW) {
            // Cap withdraw so available never goes below zero
            $exprAmount = "case when available - {$amount} < 0 then {$amount} + (available - {$amount}) else {$amount} end";
            $exprBalance = "balance - $exprAmount";
            $exprAvailable = "available - $exprAmount";
        }

        return [$exprAmount, $exprBalance, $exprAvailable];
    }

    /**
     * Append extra mapped fields from the DTO properties (extended entities) into the target/select lists.
     */
    private function appendExtraMappedFields(TransactionDTO $dto, array &$targetColumns, array &$selectFields): void
    {
        $mapper = $this->transactionRepository->getMapper();
        foreach ($dto->getProperties() as $propertyName => $propertyValue) {
            $fieldMap = $mapper->getFieldMap($propertyName);
            /** @psalm-suppress PossiblyInvalidMethodCall - getFieldMap returns FieldMapping when property name provided */
            if ($fieldMap && $fieldMap->isSyncWithDb()) {
                /** @psalm-suppress PossiblyInvalidMethodCall - getFieldMap returns FieldMapping when property name provided */
                $fieldName = $fieldMap->getFieldName();
                if (!in_array($fieldName, $targetColumns, true)) {
                    $targetColumns[] = $fieldName;
                    $selectFields[] = !is_null($propertyValue) ? "'" . $propertyValue . "'" : 'null';
                }
            }
        }
    }

    /**
     * Builds an INSERT-SELECT query that atomically creates a transaction record from wallet data.
     *
     * This method generates a single SQL query that:
     * 1. Reads the current wallet state (with FOR UPDATE lock)
     * 2. Calculates new balance/available/reserved values based on the operation
     * 3. Inserts a new transaction record with the calculated values
     *
     * Using INSERT-SELECT ensures atomicity - the wallet snapshot and transaction creation
     * happen in a single database operation, preventing race conditions.
     *
     * @param string $operation The transaction type (D=Deposit, W=Withdraw, DB=Deposit Blocked, WB=Withdraw Blocked, etc.)
     * @param TransactionDTO $dto The transaction data transfer object containing wallet ID, amount, description, etc.
     * @param string $expressionSumBalance SQL expression to calculate new balance (e.g., "balance + :amount" or "balance - :amount")
     * @param string $expressionSumAvailable SQL expression to calculate new available amount (e.g., "available + :amount")
     * @param string $expressionAmount SQL expression for the transaction amount (e.g., ":amount" or "-:amount")
     * @param string $sumReserved Amount to add/subtract from reserved funds (e.g., ":amount" or "-:amount")
     * @return InsertSelectQuery The query that will insert a transaction record by selecting from the wallet table
     * @throws InvalidArgumentException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    protected function getInsertTransactionQuery(
        string $operation,
        TransactionDTO $dto,
        string $expressionSumBalance,
        string $expressionSumAvailable,
        string $expressionAmount,
        string $sumReserved
    ): InsertSelectQuery
    {
        // Define the target columns in the transaction table that will receive the data
        $targetColumns = [
            'walletid',           // Wallet this transaction belongs to
            'wallettypeid',       // Type of wallet (USD, BRL, etc.)
            'balance',            // Wallet balance snapshot after this transaction
            'available',          // Available funds snapshot after this transaction
            'reserved',           // Reserved funds snapshot after this transaction
            'scale',              // Decimal scale (e.g., 2 for cents, 0 for whole units)
            'amount',             // Transaction amount (positive or negative)
            'description',        // Human-readable description
            'code',               // Transaction code for categorization
            'referenceid',        // External reference ID
            'referencesource',    // Source system for the reference
            'typeid',             // Transaction type (D, W, DB, WB, B, R)
            'date',               // Transaction timestamp
            'transactionparentid',// Parent transaction ID (for accept/reject operations)
            'uuid',               // Unique identifier for idempotency
            'previousuuid',       // Previous transaction UUID (from wallet's last_uuid) for chain integrity
            'checksum',           // SHA-256 checksum (v2) of every business field + previous checksum + secret
            'previouschecksum',   // Previous transaction checksum, chaining the hashes
            'checksumversion'     // Algorithm that produced the checksum (see ChecksumInterface::getVersion())
        ];

        // The checksum expressions are built by the current algorithm so the SQL hash
        // stays in sync with the PHP-side calculation
        $transactionTable = $this->transactionRepository->getMapper()->getTable();
        $checksumAlgorithm = ChecksumFactory::current();
        $previousChecksumExpression = $checksumAlgorithm->buildPreviousChecksumSqlExpression($transactionTable);
        $checksumExpression = $checksumAlgorithm->buildSqlExpression(
            $expressionAmount,
            $expressionSumBalance,
            "reserved + $sumReserved",
            $expressionSumAvailable,
            $transactionTable
        );

        // Define the SELECT fields that will provide values for the target columns
        // These are calculated from the current wallet state
        $selectFields = [
            'walletid',                                 // Copy wallet ID from the wallet table
            'wallettypeid',                             // Copy wallet type from the wallet table
            $expressionSumBalance,                      // Calculate new balance (e.g., balance + amount)
            $expressionSumAvailable,                    // Calculate new available (e.g., available + amount)
            "reserved + $sumReserved",                  // Calculate new reserved (e.g., reserved + amount)
            'scale',                                    // Copy scale from the wallet table
            $expressionAmount,                          // Transaction amount (from parameter binding)
            ':description',                             // From DTO parameter
            ':code',                                    // From DTO parameter
            ':referenceid',                             // From DTO parameter
            ':referencesource',                         // From DTO parameter
            ':operation',                               // Transaction type from parameter
            $this->transactionRepository->getExecutor()->getHelper()->sqlDate('Y-m-d H:i:s'), // Current timestamp
            'null',                                     // No parent transaction (NULL)
            ':uuid',                                    // From DTO parameter
            'last_uuid',                                // Previous transaction UUID from wallet's last_uuid
            $checksumExpression,                        // Calculate SHA-256 checksum (v2) in the database
            $previousChecksumExpression,                // Previous transaction checksum from the wallet head
            (string)$checksumAlgorithm->getVersion()    // Checksum algorithm version
        ];

        // Append any extra mapped fields provided via DTO properties (for extended entities)
        // This allows custom transaction entities to add additional fields
        $this->appendExtraMappedFields($dto, $targetColumns, $selectFields);

        // Build the SELECT query that reads from the wallet table
        // This provides the source data for the INSERT
        $transactionQuery = Query::getInstance()
            ->table('wallet')
            ->fields($selectFields)                     // Select the calculated fields
            ->where('walletid = :accid2', [             // Filter to a specific wallet
                'accid2' => $dto->getWalletId(),        // Wallet ID to read from
                'description' => $dto->getDescription(),// Bind description parameter
                'code' => $dto->getCode(),              // Bind code parameter
                'referenceid' => $dto->getReferenceId(),// Bind reference ID parameter
                'referencesource' => $dto->getReferenceSource(), // Bind reference source parameter
                'operation' => $operation,              // Bind operation type parameter
                'uuid' => $dto->getUuid(),              // Bind UUID parameter
                'checksumsecret' => $this->checksumSecret // Bind checksum secret parameter
            ])
            ->forUpdate();                              // Lock the wallet row to prevent concurrent modifications

        // Create the INSERT-SELECT query that combines the target table with the SELECT query
        return InsertSelectQuery::getInstance(
            $this->transactionRepository->getMapper()->getTable(), // Target table (transaction)
            $targetColumns                                         // Target columns
        )->fromQuery($transactionQuery);                          // Source query (SELECT from wallet)
    }

    /**
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getWalletUpdateQuery(TransactionDTO $dto): UpdateQuery
    {
        /** @psalm-suppress PossiblyNullArgument - UUID set by validateTransactionDto */
        $uuid = new HexUuidLiteral($dto->getUuid());

        return UpdateQuery::getInstance()
            ->table('wallet')
            ->setLiteral('wallet.balance', 'st.balance')
            ->setLiteral('wallet.reserved', 'st.reserved')
            ->setLiteral('wallet.available', 'st.available')
            ->setLiteral('wallet.last_uuid', $uuid)
            ->where('wallet.walletid = :accid', ['accid' => $dto->getWalletId()])
            ->join($this->transactionRepository->getMapper()->getTable(), 'st.walletid = wallet.walletid and st.uuid = ' . (string)$uuid, 'st');
    }

    /**
     * Add funds to a wallet
     *
     * @param TransactionDTO $dto
     * @return TransactionEntity Newly created transaction entity
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function addFunds(TransactionDTO $dto): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::DEPOSIT, $dto);
    }

    /**
     * Withdraw funds from a wallet
     *
     * @param TransactionDTO $dto
     * @param bool $capAtZero
     * @return TransactionEntity Transaction ID
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function withdrawFunds(TransactionDTO $dto, bool $capAtZero = false): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::WITHDRAW, $dto, $capAtZero);
    }

    /**
     * Reserve funds to future withdrawn. It affects the net balance but not the gross balance
     *
     * @param TransactionDTO $dto
     * @return TransactionEntity Transaction ID
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function reserveFundsForWithdraw(TransactionDTO $dto): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::WITHDRAW_BLOCKED, $dto);
    }

    /**
     * Reserve funds to future deposit. Update net balance but not gross balance.
     *
     * @param TransactionDTO $dto
     * @return TransactionEntity Transaction ID
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function reserveFundsForDeposit(TransactionDTO $dto): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::DEPOSIT_BLOCKED, $dto);
    }

    /**
     * Validate that a reserved transaction can be processed (accepted or rejected)
     *
     * @param int $transactionId
     * @param TransactionDTO $transactionDto
     * @return TransactionEntity
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    private function validateReservedTransaction(int $transactionId, TransactionDTO $transactionDto): TransactionEntity
    {
        $transaction = $this->transactionRepository->getById($transactionId);
        if (is_null($transaction)) {
            throw new TransactionException('Transaction not found');
        }

        // Validate if the transaction can be processed
        if ($transaction->getTypeId() != TransactionEntity::WITHDRAW_BLOCKED && $transaction->getTypeId() != TransactionEntity::DEPOSIT_BLOCKED) {
            throw new TransactionException("The transaction id doesn't belongs to a reserved fund.");
        }

        // Validate if the transaction has been already processed
        if ($this->transactionRepository->getByParentId($transactionId) != null) {
            throw new TransactionException('The transaction has been processed already');
        }

        if ($transactionDto->hasWallet() && $transactionDto->getWalletId() != $transaction->getWalletId()) {
            throw new TransactionException('The transaction wallet is different from the informed wallet in the DTO. Try createEmpty().');
        }

        return $transaction;
    }

    /**
     * Create a new transaction from a reserved transaction
     *
     * @param TransactionEntity $originalTransaction
     * @param WalletEntity $wallet
     * @param TransactionDTO $transactionDto
     * @param string $newTypeId
     * @return TransactionEntity
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    private function createTransactionFromReserved(
        TransactionEntity $originalTransaction,
        WalletEntity $wallet,
        TransactionDTO $transactionDto,
        string $newTypeId
    ): TransactionEntity {
        $originalTransaction->setTransactionParentId($originalTransaction->getTransactionId());
        $originalTransaction->setTransactionId(null); // Allow creating a new record
        $originalTransaction->setDate(null);
        $originalTransaction->setTypeId($newTypeId);
        $originalTransaction->attachWallet($wallet);
        $uuid = $transactionDto->getUuid();
        if (empty($uuid)) {
            $transactionDto->setUuid($transactionDto->calculateUuid($this->transactionRepository->getExecutor()));
        } elseif (!($uuid instanceof Literal)) {
            $transactionDto->setUuid(new HexUuidLiteral($uuid));
        }
        $transactionDto->setToTransaction($originalTransaction);

        // Set previousuuid from wallet's last_uuid to maintain chain integrity
        $originalTransaction->setPreviousUuid($wallet->getLastUuid());

        // Chain the checksums: the new checksum covers the previous transaction's checksum
        $lastUuid = $wallet->getLastUuid();
        $previousTransaction = empty($lastUuid) ? null : $this->transactionRepository->getByUuid($lastUuid);
        $originalTransaction->setPreviousChecksum($previousTransaction?->getChecksum());

        // Calculate and set checksum
        $checksumAlgorithm = ChecksumFactory::current();
        $originalTransaction->setChecksumVersion($checksumAlgorithm->getVersion());
        $originalTransaction->setChecksum($checksumAlgorithm->calculate($originalTransaction, $this->checksumSecret));

        return $originalTransaction;
    }

    /**
     * Accept a reserved fund and update the gross balance
     *
     * @param int $transactionId
     * @param TransactionDTO|null $transactionDto
     * @return int Transaction ID
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptFundsById(int $transactionId, ?TransactionDTO $transactionDto = null): int
    {
        if (is_null($transactionDto)) {
            $transactionDto = TransactionDTO::createEmpty();
        }

        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $transaction = $this->validateReservedTransaction($transactionId, $transactionDto);

            // Get values and apply the updates
            $signal = $transaction->getTypeId() == TransactionEntity::DEPOSIT_BLOCKED ? 1 : -1;

            /** @psalm-suppress PossiblyNullArgument - transaction validated by validateReservedTransaction */
            $wallet = $this->walletRepository->getById($transaction->getWalletId());
            $wallet->setReserved($wallet->getReserved() + ($transaction->getAmount() * $signal));
            $wallet->setBalance($wallet->getBalance() + ($transaction->getAmount() * $signal));
            $wallet->setEntryDate(null);

            // Create a new transaction (accept) - previousuuid comes from the wallet's current last_uuid
            $newTypeId = $transaction->getTypeId() == TransactionEntity::WITHDRAW_BLOCKED
                ? TransactionEntity::WITHDRAW
                : TransactionEntity::DEPOSIT;

            $newTransaction = $this->createTransactionFromReserved($transaction, $wallet, $transactionDto, $newTypeId);
            $result = $this->transactionRepository->save($newTransaction);
            $this->recordOutbox($result);

            // Persist the balance changes and the new last_uuid in a single save
            $wallet->setLastUuid($result->getUuid());
            $this->walletRepository->save($wallet);

            $this->getRepository()->getExecutor()->commitTransaction();

            return intval($result->getTransactionId());
        } catch (Throwable $ex) {
            $this->getRepository()->getExecutor()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Accept a reserved fund identified by its UUID (e.g., a caller-supplied idempotency key).
     *
     * @param Literal|string $uuid UUID of the reserved (WB/DB) transaction
     * @param TransactionDTO|null $transactionDto
     * @return int Transaction ID of the accept transaction
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptFundsByUuid(Literal|string $uuid, ?TransactionDTO $transactionDto = null): int
    {
        $transaction = $this->transactionRepository->getByUuid($uuid);
        if (is_null($transaction)) {
            throw new TransactionException('Transaction not found');
        }

        /** @psalm-suppress PossiblyNullArgument - a persisted transaction always has an id */
        return $this->acceptFundsById($transaction->getTransactionId(), $transactionDto);
    }

    /**
     * Reject a reserved fund identified by its UUID (e.g., a caller-supplied idempotency key).
     *
     * @param Literal|string $uuid UUID of the reserved (WB/DB) transaction
     * @param TransactionDTO|null $transactionDto
     * @return int Transaction ID of the reject transaction
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function rejectFundsByUuid(Literal|string $uuid, ?TransactionDTO $transactionDto = null): int
    {
        $transaction = $this->transactionRepository->getByUuid($uuid);
        if (is_null($transaction)) {
            throw new TransactionException('Transaction not found');
        }

        /** @psalm-suppress PossiblyNullArgument - a persisted transaction always has an id */
        return $this->rejectFundsById($transaction->getTransactionId(), $transactionDto);
    }

    /**
     * @param int $transactionId
     * @param TransactionDTO $transactionDtoWithdraw
     * @param TransactionDTO $transactionDtoRefund
     * @return TransactionEntity
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptPartialFundsById(int $transactionId, TransactionDTO $transactionDtoWithdraw, TransactionDTO $transactionDtoRefund): TransactionEntity
    {
        $partialAmount = $transactionDtoWithdraw->getAmount();

        if ($partialAmount <= 0) {
            throw new AmountException('Partial amount must be greater than zero.');
        }

        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $transaction = $this->transactionRepository->getById($transactionId);
            if (is_null($transaction)) {
                throw new TransactionException('acceptPartialFundsById: Transaction not found');
            }
            if ($transaction->getTypeId() != TransactionEntity::WITHDRAW_BLOCKED) {
                throw new TransactionException("The transaction id doesn't belong to a reserved withdraw fund.");
            }
            if ($this->transactionRepository->getByParentId($transactionId) != null) {
                throw new TransactionException('The transaction has been processed already');
            }

            $originalAmount = $transaction->getAmount();
            if ($partialAmount <= 0 || $partialAmount >= $originalAmount) {
                throw new AmountException(
                    'Partial amount must be greater than zero and less than the original reserved amount.'
                );
            }

            $this->rejectFundsById($transactionId, $transactionDtoRefund);

            $transactionDtoWithdraw->setWalletId($transaction->getWalletId());

            $finalDebitTransaction = $this->withdrawFunds($transactionDtoWithdraw);

            $this->getRepository()->getExecutor()->commitTransaction();

            return $finalDebitTransaction;

        } catch (Throwable $ex) {
            $this->getRepository()->getExecutor()->rollbackTransaction();
            throw $ex;
        }
    }

    /**
     * Reject a reserved fund and return the net balance
     *
     * @param int $transactionId
     * @param TransactionDTO|null $transactionDto
     * @return int Transaction ID
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function rejectFundsById(int $transactionId, ?TransactionDTO $transactionDto = null): int
    {
        if (is_null($transactionDto)) {
            $transactionDto = TransactionDTO::createEmpty();
        }

        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $transaction = $this->validateReservedTransaction($transactionId, $transactionDto);

            // Update Wallet - reverse the reservation
            $signal = $transaction->getTypeId() == TransactionEntity::DEPOSIT_BLOCKED ? -1 : +1;

            /** @psalm-suppress PossiblyNullArgument - transaction validated by validateReservedTransaction */
            $wallet = $this->walletRepository->getById($transaction->getWalletId());
            $wallet->setReserved($wallet->getReserved() - ($transaction->getAmount() * $signal));
            $wallet->setAvailable($wallet->getAvailable() + ($transaction->getAmount() * $signal));
            $wallet->setEntryDate(null);

            // Create a new transaction (reject) - previousuuid comes from the wallet's current last_uuid
            $newTransaction = $this->createTransactionFromReserved(
                $transaction,
                $wallet,
                $transactionDto,
                TransactionEntity::REJECT
            );
            $result = $this->transactionRepository->save($newTransaction);
            $this->recordOutbox($result);

            // Persist the balance changes and the new last_uuid in a single save
            $wallet->setLastUuid($result->getUuid());
            $this->walletRepository->save($wallet);

            $this->getRepository()->getExecutor()->commitTransaction();

            return intval($result->getTransactionId());
        } catch (Throwable $ex) {
            $this->getRepository()->getExecutor()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Update all blocked (reserved) transactions
     *
     * @param int|null $walletId
     * @return TransactionEntity[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getReservedTransactions(?int $walletId = null): array
    {
        return $this->transactionRepository->getReservedTransactions($walletId);
    }

    /**
     * @param int $walletId
     * @param string $startDate
     * @param string $endDate
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByDate(int $walletId, string $startDate, string $endDate): array
    {
        return $this->transactionRepository->getByDate($walletId, $startDate, $endDate);
    }

    /**
     * This transaction is blocked (reserved)
     *
     * @param int|null $transactionId
     * @return bool
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function isTransactionReserved(?int $transactionId = null): bool
    {
        if ($transactionId === null) {
            return false;
        }
        return null === $this->transactionRepository->getByParentId($transactionId, true);
    }

    /**
     * Verify the integrity of a wallet's transaction chain.
     *
     * Walks the chain from wallet.last_uuid back through each transaction's previousuuid,
     * validating every checksum, confirming the wallet balances match the head transaction's
     * snapshot, and detecting broken links, cycles and orphan rows (rows not reachable from
     * the head). Intended for scheduled reconciliation jobs.
     *
     * @param int $walletId
     * @return ChainVerificationResult
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmInvalidFieldsException
     * @throws WalletException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function verifyChain(int $walletId): ChainVerificationResult
    {
        $wallet = $this->walletRepository->getById($walletId);
        if (empty($wallet)) {
            throw new WalletException('Wallet not found');
        }

        $errors = [];

        // Index every transaction of the wallet by its formatted UUID
        $transactions = $this->transactionRepository->getAllByWalletId($walletId);
        $byUuid = [];
        foreach ($transactions as $transaction) {
            $uuid = HexUuidLiteral::getFormattedUuid($transaction->getUuid(), throwErrorIfInvalid: false);
            if ($uuid === null) {
                $errors[] = "Transaction {$transaction->getTransactionId()} has no UUID";
                continue;
            }
            $byUuid[$uuid] = $transaction;
        }

        $headUuid = HexUuidLiteral::getFormattedUuid($wallet->getLastUuid(), throwErrorIfInvalid: false);
        if ($headUuid === null) {
            if (!empty($transactions)) {
                $errors[] = 'Wallet last_uuid is empty but the wallet has transactions';
            }
            return new ChainVerificationResult($walletId, $errors, 0);
        }

        $head = $byUuid[$headUuid] ?? null;
        if ($head === null) {
            $errors[] = "Wallet last_uuid $headUuid does not match any transaction of the wallet";
            return new ChainVerificationResult($walletId, $errors, 0);
        }

        // The wallet state must equal the head transaction's snapshot
        if ($wallet->getBalance() !== $head->getBalance()
            || $wallet->getReserved() !== $head->getReserved()
            || $wallet->getAvailable() !== $head->getAvailable()
        ) {
            $errors[] = sprintf(
                'Wallet state (balance=%d, reserved=%d, available=%d) does not match the head transaction %d snapshot (balance=%d, reserved=%d, available=%d)',
                (int)$wallet->getBalance(), (int)$wallet->getReserved(), (int)$wallet->getAvailable(),
                (int)$head->getTransactionId(), (int)$head->getBalance(), (int)$head->getReserved(), (int)$head->getAvailable()
            );
        }

        // Walk the chain from the head back to the genesis transaction
        $verified = 0;
        $legacyChecksums = 0;
        $visited = [];
        $current = $head;
        $currentUuid = $headUuid;
        while ($current !== null) {
            if (isset($visited[$currentUuid])) {
                $errors[] = "Chain cycle detected at transaction UUID $currentUuid";
                break;
            }
            $visited[$currentUuid] = true;

            $currentVersion = $current->getChecksumVersion() ?? 1;
            if ($currentVersion < ChecksumV2::VERSION) {
                $legacyChecksums++;
            }

            try {
                $checksumAlgorithm = ChecksumFactory::get($current->getChecksumVersion());
                if (!$checksumAlgorithm->validate($current, (string)$current->getChecksum(), $this->checksumSecret)) {
                    $errors[] = "Checksum mismatch on transaction {$current->getTransactionId()} (UUID $currentUuid)";
                }
            } catch (TransactionException) {
                $errors[] = "Unknown checksum version $currentVersion on transaction {$current->getTransactionId()} (UUID $currentUuid)";
            }
            $verified++;

            $previousUuid = HexUuidLiteral::getFormattedUuid($current->getPreviousUuid(), throwErrorIfInvalid: false);
            if ($previousUuid === null) {
                // Reached the genesis transaction
                $current = null;
            } else {
                $next = $byUuid[$previousUuid] ?? null;
                if ($next === null) {
                    $errors[] = "Broken chain: transaction UUID $currentUuid references previous UUID $previousUuid which does not exist in wallet $walletId";
                } else {
                    // The stored copy of the previous checksum must match the previous row
                    if ($currentVersion >= ChecksumV2::VERSION
                        && $current->getPreviousChecksum() !== $next->getChecksum()
                    ) {
                        $errors[] = "Previous checksum mismatch: transaction {$current->getTransactionId()} does not chain to the checksum of transaction {$next->getTransactionId()}";
                    }
                    // In a healthy ledger the checksum version never decreases over time
                    if (($next->getChecksumVersion() ?? 1) > $currentVersion) {
                        $errors[] = "Checksum version downgrade on transaction {$current->getTransactionId()}: version $currentVersion is older than version {$next->getChecksumVersion()} of the previous transaction {$next->getTransactionId()}";
                    }
                }
                $current = $next;
                $currentUuid = $previousUuid;
            }
        }

        // Every transaction of the wallet must be reachable from the head
        if ($verified !== count($byUuid)) {
            $orphans = count($byUuid) - $verified;
            $errors[] = "$orphans transaction(s) of wallet $walletId are not reachable from the wallet head (orphan or forked rows)";
        }

        return new ChainVerificationResult($walletId, $errors, $verified, $legacyChecksums);
    }

    /**
     * @return TransactionRepository
     */
    public function getRepository(): TransactionRepository
    {
        return $this->transactionRepository;
    }
}
