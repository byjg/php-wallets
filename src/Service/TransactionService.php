<?php

namespace ByJG\AccountTransactions\Service;

use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\AccountEntity;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Exception\AccountException;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\AccountTransactions\Repository\AccountRepository;
use ByJG\AccountTransactions\Repository\TransactionRepository;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Enum\ObserverEvent;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\InsertSelectQuery;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\ORMSubject;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\UpdateQuery;
use ByJG\Serializer\Exception\InvalidArgumentException;
use Exception;

class TransactionService
{
    /**
     * @var TransactionRepository
     */
    protected TransactionRepository $transactionRepository;

    /**
     * @var AccountRepository
     */
    protected AccountRepository $accountRepository;

    /**
     * TransactionService constructor.
     * @param TransactionRepository $transactionRepository
     * @param AccountRepository $accountRepository
     */
    public function __construct(TransactionRepository $transactionRepository, AccountRepository $accountRepository)
    {
        $this->transactionRepository = $transactionRepository;
        $this->accountRepository = $accountRepository;
    }

    /**
     * Get a Transaction By ID.
     *
     * @param int|string $transactionId Optional. empty, return all ids.
     * @return mixed
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(int|string $transactionId): mixed
    {
        return $this->transactionRepository->getById($transactionId);
    }

    protected function validateTransactionDto(TransactionDTO $dto): void
    {
        if (!$dto->hasAccount()) {
            throw new TransactionException('Account is required');
        }
        if ($dto->getAmount() < 0) {
            throw new AmountException('Amount needs to be greater than zero');
        }

        $dto->setUuid($dto->calculateUuid($this->transactionRepository->getExecutor()));
    }

    /**
     * Central method to apply a balance-changing operation.
     * - Creates a new transaction row reflecting the post-operation balances
     * - Updates the account with the same balances and the last transaction id
     *
     * @param string $operation One of TransactionEntity::DEPOSIT, WITHDRAW, DEPOSIT_BLOCKED, WITHDRAW_BLOCKED
     * @param TransactionDTO $dto Input data (account, amount, description, etc.)
     * @param bool $capAtZero When true and operation is WITHDRAW, caps the withdrawal so net balance never goes below zero
     * @return TransactionEntity
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    protected function updateFunds(string $operation, TransactionDTO $dto, bool $capAtZero = false): TransactionEntity
    {
        $this->validateTransactionDto($dto);

        // 1) Compute numeric deltas for balances (used for notifications) and the SQL expressions (used for insert/select)
        [$balanceDelta, $reservedDelta, $availableDelta] = $this->computeBalanceDeltas($operation, $dto->getAmount());
        [$exprAmount, $exprBalance, $exprAvailable] = $this->buildAmountAndExpressions($operation, $dto->getAmount(), $capAtZero);

        // 2) Build the insert-select for the transaction and the account update based on the new transaction
        $transactionInsert = $this->getInsertTransactionQuery(
            $operation,
            $dto,
            $exprBalance,
            $exprAvailable,
            $exprAmount,
            (string)$reservedDelta
        );

        $accountUpdate = $this->getAccountUpdateQuery($dto);

        // 3) Execute both queries atomically
        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, allowJoin: true);
        try {
            $this->getRepository()->bulkExecute([
                $transactionInsert,
                $accountUpdate,
            ]);

            // 4) Load the account just updated
            /** @var AccountEntity $account */
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (empty($account)) {
                throw new AccountException('Transaction Failed: Account not found');
            }
            if (empty($account->getLastUuid())) {
                throw new AccountException('Transaction Failed: Account last_uuid is empty');
            }
            if (HexUuidLiteral::getFormattedUuid($account->getLastUuid()) !== HexUuidLiteral::getFormattedUuid($dto->getUuid())) {
                throw new AccountException('Transaction Failed: Account last_uuid does not match the DTO');
            }

            // 5) Load the transaction just created
            $transaction = $this->transactionRepository->getByUuid($dto->getUuid());
            if (empty($transaction)) {
                throw new TransactionException('Transaction Failed: Transaction not found');
            }

            // Validate that the persisted transaction matches the DTO intent (allowing capped withdraw amount)
            $mismatches = [];
            if ((int)$transaction->getAccountId() !== (int)$dto->getAccountId()) { $mismatches[] = 'accountId'; }
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
                if ($fieldMap && $fieldMap->isSyncWithDb()) {
                    $fieldName = "get" . $fieldMap->getPropertyName();
                    if ($transaction->$fieldName() !== $propertyValue) {
                        $mismatches[] = $fieldName;
                    }
                }
            }
            if (!empty($mismatches)) {
                throw new TransactionException('Persisted transaction does not match the DTO fields: ' . implode(', ', $mismatches));
            }

            $this->getRepository()->getExecutor()->commitTransaction();
        } catch (Exception $ex) {
            if ($this->getRepository()->getExecutor()->hasActiveTransaction()) {
                $this->getRepository()->getExecutor()->rollbackTransaction();
            }
            if ($ex instanceof \PDOException && strpos($ex->getMessage(), 'chk_value_nonnegative') !== false) {
                throw new AmountException('Cannot withdraw above the account balance');
            }
            throw $ex;
        }

        // 6) Notify observers of account change, providing an oldAccount with pre-change balances
        $oldAccount = clone $account;
        $oldAccount->setBalance($oldAccount->getBalance() - $balanceDelta);
        $oldAccount->setReserved($oldAccount->getReserved() - $reservedDelta);
        $oldAccount->setAvailable($oldAccount->getAvailable() - $availableDelta);

        ORMSubject::getInstance()->notify(
            $this->accountRepository->getMapper()->getTable(),
            ObserverEvent::Update,
            $account,
            $oldAccount
        );

        // 7) Notify observers of transaction insert
        ORMSubject::getInstance()->notify(
            $this->transactionRepository->getMapper()->getTable(),
            ObserverEvent::Insert,
            $transaction,
            null
        );

        // If capping occurred on withdraw, the actual amount may differ from the DTO amount
        $dto->setAmount(intval($transaction->getAmount()));

        return $transaction;
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
     * When capping at zero (withdraw), it ensures the amount is reduced to avoid negative net balance.
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
            if ($fieldMap && $fieldMap->isSyncWithDb()) {
                $fieldName = $fieldMap->getFieldName();
                if (!in_array($fieldName, $targetColumns, true)) {
                    $targetColumns[] = $fieldName;
                    $selectFields[] = !is_null($propertyValue) ? "'" . $propertyValue . "'" : 'null';
                }
            }
        }
    }

    protected function getInsertTransactionQuery(
        string $operation,
        TransactionDTO $dto,
        string $expressionSumBalance,
        string $expressionSumAvailable,
        string $expressionAmount,
        string $sumReserved
    ): InsertSelectQuery
    {
        // Build base target columns and select fields
        $targetColumns = [
            'accountid',
            'accounttypeid',
            'balance',
            'available',
            'reserved',
            'price',
            'amount',
            'description',
            'code',
            'referenceid',
            'referencesource',
            'typeid',
            'date',
            'transactionparentid',
            'uuid'
        ];

        $selectFields = [
            'accountid',
            'accounttypeid',
            $expressionSumBalance,
            $expressionSumAvailable,
            "reserved + $sumReserved",
            'price',
            $expressionAmount,
            ':description',
            ':code',
            ':referenceid',
            ':referencesource',
            ':operation',
            $this->transactionRepository->getExecutor()->getHelper()->sqlDate('Y-m-d H:i:s'),
            'null',
            ':uuid'
        ];

        // Append any extra mapped fields provided via DTO properties (for extended entities)
        $this->appendExtraMappedFields($dto, $targetColumns, $selectFields);

        $transactionQuery = Query::getInstance()
            ->table('account')
            ->fields($selectFields)
            ->where('accountid = :accid2', [
                'accid2' => $dto->getAccountId(),
                'description' => $dto->getDescription(),
                'code' => $dto->getCode(),
                'referenceid' => $dto->getReferenceId(),
                'referencesource' => $dto->getReferenceSource(),
                'operation' => $operation,
                'uuid' => $dto->getUuid()
            ])
            ->forUpdate();

        return InsertSelectQuery::getInstance(
            $this->transactionRepository->getMapper()->getTable(),
            $targetColumns
        )->fromQuery($transactionQuery);
    }

    public function getAccountUpdateQuery(TransactionDTO $dto): UpdateQuery
    {
        $uuid = new HexUuidLiteral($dto->getUuid());

        return UpdateQuery::getInstance()
            ->table('account')
            ->setLiteral('account.balance', 'st.balance')
            ->setLiteral('account.reserved', 'st.reserved')
            ->setLiteral('account.available', 'st.available')
            ->setLiteral('account.last_uuid', $uuid)
            ->where('account.accountid = :accid', ['accid' => $dto->getAccountId()])
            ->join($this->transactionRepository->getMapper()->getTable(), 'st.accountid = account.accountid and st.uuid = ' . $uuid, 'st');
    }

    /**
     * Add funds to an account
     *
     * @param TransactionDTO $dto
     * @return TransactionEntity Newly created transaction entity
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function addFunds(TransactionDTO $dto): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::DEPOSIT, $dto);
    }

    /**
     * Withdraw funds from an account
     *
     * @param TransactionDTO $dto
     * @param bool $capAtZero
     * @return TransactionEntity Transaction ID
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
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
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
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
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function reserveFundsForDeposit(TransactionDTO $dto): TransactionEntity
    {
        return $this->updateFunds(TransactionEntity::DEPOSIT_BLOCKED, $dto);
    }

    /**
     * Accept a reserved fund and update gross balance
     *
     * @param int $transactionId
     * @param TransactionDTO|null $transactionDto
     * @return int Transaction ID
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptFundsById(int $transactionId, ?TransactionDTO $transactionDto = null): int
    {
        if (is_null($transactionDto)) {
            $transactionDto = TransactionDTO::createEmpty();
        }

        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            /** @var TransactionEntity $transaction */
            $transaction = $this->transactionRepository->getById($transactionId);
            if (is_null($transaction)) {
                throw new TransactionException('acceptFundsById: Transaction not found');
            }

            // Validate if transaction can be accepted.
            if ($transaction->getTypeId() != TransactionEntity::WITHDRAW_BLOCKED && $transaction->getTypeId() != TransactionEntity::DEPOSIT_BLOCKED) {
                throw new TransactionException("The transaction id doesn't belongs to a reserved fund.");
            }

            // Validate if the transaction has been already accepted.
            if ($this->transactionRepository->getByParentId($transactionId) != null) {
                throw new TransactionException('The transaction has been accepted already');
            }

            if ($transactionDto->hasAccount() && $transactionDto->getAccountId() != $transaction->getAccountId()) {
                throw new TransactionException('The transaction account is different from the informed account in the DTO. Try createEmpty().');
            }

            // Get values and apply the updates
            $signal = $transaction->getTypeId() == TransactionEntity::DEPOSIT_BLOCKED ? 1 : -1;

            $account = $this->accountRepository->getById($transaction->getAccountId());
            $account->setReserved($account->getReserved() + ($transaction->getAmount() * $signal));
            $account->setBalance($account->getBalance() + ($transaction->getAmount() * $signal));
            $account->setEntryDate(null);
            $this->accountRepository->save($account);

            // Update data
            $transaction->setTransactionParentId($transaction->getTransactionId());
            $transaction->setTransactionId(null); // Poder criar um novo registro
            $transaction->setDate(null);
            $transaction->setTypeId($transaction->getTypeId() == TransactionEntity::WITHDRAW_BLOCKED ? TransactionEntity::WITHDRAW : TransactionEntity::DEPOSIT);
            $transaction->attachAccount($account);
            $transactionDto->setUuid($transactionDto->calculateUuid($this->transactionRepository->getExecutor()));
            $transactionDto->setToTransaction($transaction);
            $result = $this->transactionRepository->save($transaction);

            $this->getRepository()->getExecutor()->commitTransaction();

            return $result->getTransactionId();
        } catch (Exception $ex) {
            $this->getRepository()->getExecutor()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param int $transactionId
     * @param TransactionDTO $transactionDtoWithdraw
     * @param TransactionDTO $transactionDtoRefund
     * @return TransactionEntity
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
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

            $transactionDtoWithdraw->setAccountId($transaction->getAccountId());

            $finalDebitTransaction = $this->withdrawFunds($transactionDtoWithdraw);

            $this->getRepository()->getExecutor()->commitTransaction();

            return $finalDebitTransaction;

        } catch (Exception $ex) {
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
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function rejectFundsById(int $transactionId, ?TransactionDTO $transactionDto = null): int
    {
        if (is_null($transactionDto)) {
            $transactionDto = TransactionDTO::createEmpty();
        }

        $this->getRepository()->getExecutor()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $transaction = $this->transactionRepository->getById($transactionId);
            if (is_null($transaction)) {
                throw new TransactionException('rejectFundsById: Transaction not found');
            }

            // Validate if transaction can be accepted.
            if ($transaction->getTypeId() != TransactionEntity::WITHDRAW_BLOCKED && $transaction->getTypeId() != TransactionEntity::DEPOSIT_BLOCKED) {
                throw new TransactionException("The transaction id doesn't belongs to a reserved fund.");
            }

            // Validate if the transaction has been already accepted.
            if ($this->transactionRepository->getByParentId($transactionId) != null) {
                throw new TransactionException('The transaction has been accepted already');
            }

            if ($transactionDto->hasAccount() && $transactionDto->getAccountId() != $transaction->getAccountId()) {
                throw new TransactionException('The transaction account is different from the informed account in the DTO. Try createEmpty().');
            }

            // Update Account
            $signal = $transaction->getTypeId() == TransactionEntity::DEPOSIT_BLOCKED ? -1 : +1;

            $account = $this->accountRepository->getById($transaction->getAccountId());
            $account->setReserved($account->getReserved() - ($transaction->getAmount() * $signal));
            $account->setAvailable($account->getAvailable() + ($transaction->getAmount() * $signal));
            $account->setEntryDate(null);
            $this->accountRepository->save($account);

            // Update Transaction
            $transaction->setTransactionParentId($transaction->getTransactionId());
            $transaction->setTransactionId(null); // Poder criar um novo registro
            $transaction->setDate(null);
            $transaction->setTypeId(TransactionEntity::REJECT);
            $transaction->attachAccount($account);
            $transactionDto->setUuid($transactionDto->calculateUuid($this->transactionRepository->getExecutor()));
            $transactionDto->setToTransaction($transaction);
            $result = $this->transactionRepository->save($transaction);

            $this->getRepository()->getExecutor()->commitTransaction();

            return $result->getTransactionId();
        } catch (Exception $ex) {
            $this->getRepository()->getExecutor()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Update all blocked (reserved) transactions
     *
     * @param int|null $accountId
     * @return TransactionEntity[]
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function getReservedTransactions(?int $accountId = null): array
    {
        return $this->transactionRepository->getReservedTransactions($accountId);
    }

    /**
     * @param int $accountId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDate(int $accountId, string $startDate, string $endDate): array
    {
        return $this->transactionRepository->getByDate($accountId, $startDate, $endDate);
    }

    /**
     * This transaction is blocked (reserved)
     *
     * @param int|null $transactionId
     * @return bool
     */
    public function isTransactionReserved(?int $transactionId = null): bool
    {
        return null === $this->transactionRepository->getByParentId($transactionId, true);
    }

    /**
     * @return TransactionRepository
     */
    public function getRepository(): TransactionRepository
    {
        return $this->transactionRepository;
    }
}
