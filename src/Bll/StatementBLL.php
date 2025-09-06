<?php

namespace ByJG\AccountStatements\Bll;

use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AccountException;
use ByJG\AccountStatements\Exception\AmountException;
use ByJG\AccountStatements\Exception\StatementException;
use ByJG\AccountStatements\Repository\AccountRepository;
use ByJG\AccountStatements\Repository\StatementRepository;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\InsertSelectQuery;
use ByJG\MicroOrm\ORMSubject;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\UpdateQuery;
use ByJG\Serializer\Exception\InvalidArgumentException;
use Exception;

class StatementBLL
{
    /**
     * @var StatementRepository
     */
    protected StatementRepository $statementRepository;

    /**
     * @var AccountRepository
     */
    protected AccountRepository $accountRepository;

    /**
     * StatementBLL constructor.
     * @param StatementRepository $statementRepository
     * @param AccountRepository $accountRepository
     */
    public function __construct(StatementRepository $statementRepository, AccountRepository $accountRepository)
    {
        $this->statementRepository = $statementRepository;
        $this->accountRepository = $accountRepository;
    }

    /**
     * Get a Statement By ID.
     *
     * @param int|string $statementId Optional. empty, return all ids.
     * @return mixed
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(int|string $statementId): mixed
    {
        return $this->statementRepository->getById($statementId);
    }

    /**
     * Add funds to an account
     *
     * @param StatementDTO $dto
     * @return int|null Statement ID
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws StatementException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function addFunds(StatementDTO $dto): ?int
    {
        return $this->updateFunds(StatementEntity::DEPOSIT, $dto)->getStatementId();
    }

    protected function validateStatementDto(StatementDTO $dto): void
    {
        if (!$dto->hasAccount()) {
            throw new StatementException('Account is required');
        }
        if ($dto->getAmount() < 0) {
            throw new AmountException('Amount needs to be greater than zero');
        }

        if (round($dto->getAmount()*100)/100 != $dto->getAmount()) {
            throw new AmountException('Amount needs to have two decimal places');
        }
    }

    /**
     * Central method to apply a balance-changing operation.
     * - Creates a new statement row reflecting the post-operation balances
     * - Updates the account with the same balances and the last statement id
     *
     * @param string $operation One of StatementEntity::DEPOSIT, WITHDRAW, DEPOSIT_BLOCKED, WITHDRAW_BLOCKED
     * @param StatementDTO $dto Input data (account, amount, description, etc.)
     * @param bool $capAtZero When true and operation is WITHDRAW, caps the withdrawal so net balance never goes below zero
     * @return StatementEntity
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws StatementException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    protected function updateFunds(string $operation, StatementDTO $dto, bool $capAtZero = false): StatementEntity
    {
        $this->validateStatementDto($dto);

        // 1) Compute numeric deltas for balances (used for notifications) and the SQL expressions (used for insert/select)
        [$grossDelta, $unclearedDelta, $netDelta] = $this->computeBalanceDeltas($operation, $dto->getAmount());
        [$exprAmount, $exprGross, $exprNet] = $this->buildAmountAndExpressions($operation, $dto->getAmount(), $capAtZero);

        // 2) Build the insert-select for the statement and the account update based on the new statement
        $statementInsert = $this->getInsertStatementQuery(
            $operation,
            $dto,
            $exprGross,
            $exprNet,
            $exprAmount,
            (string)$unclearedDelta
        );

        $accountUpdate = $this->getAccountUpdateQuery($dto);

        // 3) Execute both queries atomically
        try {
            $this->getRepository()->bulkExecute([
                $statementInsert,
                $accountUpdate,
            ])->toArray();

            // 4) Notify observers of account change, providing an oldAccount with pre-change balances
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (empty($account)) {
                throw new AccountException('Account not found');
            }

            $oldAccount = clone $account;
            $oldAccount->setGrossbalance($oldAccount->getGrossbalance() - $grossDelta);
            $oldAccount->setUncleared($oldAccount->getUncleared() - $unclearedDelta);
            $oldAccount->setNetbalance($oldAccount->getNetbalance() - $netDelta);

            ORMSubject::getInstance()->notify(
                $this->accountRepository->getMapper()->getTable(),
                ORMSubject::EVENT_UPDATE,
                $account,
                $oldAccount
            );

            // 5) Load the statement just created to notify and return
            $statement = $this->statementRepository->getById($account->getLastStatementId());

            ORMSubject::getInstance()->notify(
                $this->statementRepository->getMapper()->getTable(),
                ORMSubject::EVENT_INSERT,
                $statement,
                null
            );

            // If capping occurred on withdraw, the actual amount may differ from the DTO amount
            $dto->setAmount($statement->getAmount());

            return $statement;
        } catch (\PDOException $ex) {
            if (strpos($ex->getMessage(), 'chk_value_nonnegative') !== false) {
                throw new AmountException('Cannot withdraw above the account balance');
            }
            throw $ex;
        }
    }

    // ---- Helpers: computations and query building ---------------------------------------------------------------

    /**
     * Compute numeric deltas for balances according to the operation and amount.
     * Returns [grossDelta, unclearedDelta, netDelta].
     */
    private function computeBalanceDeltas(string $operation, float $amount): array
    {
        $grossDelta = $amount * match ($operation) {
            StatementEntity::DEPOSIT => 1,
            StatementEntity::WITHDRAW => -1,
            default => 0,
        };

        $unclearedDelta = $amount * match ($operation) {
            StatementEntity::DEPOSIT_BLOCKED => -1,
            StatementEntity::WITHDRAW_BLOCKED => 1,
            default => 0,
        };

        $netDelta = $amount * match ($operation) {
            StatementEntity::DEPOSIT, StatementEntity::DEPOSIT_BLOCKED => 1,
            StatementEntity::WITHDRAW, StatementEntity::WITHDRAW_BLOCKED => -1,
            default => 0,
        };

        return [$grossDelta, $unclearedDelta, $netDelta];
    }

    /**
     * Build SQL literal expressions for amount, gross and net balances.
     * When capping at zero (withdraw), it ensures the amount is reduced to avoid negative net balance.
     * Returns [exprAmount, exprGross, exprNet].
     */
    private function buildAmountAndExpressions(string $operation, float $amount, bool $capAtZero): array
    {
        $exprGross = "grossbalance + " . ($amount * match ($operation) {
            StatementEntity::DEPOSIT => 1,
            StatementEntity::WITHDRAW => -1,
            default => 0,
        });
        $exprNet = "netbalance + " . ($amount * match ($operation) {
            StatementEntity::DEPOSIT, StatementEntity::DEPOSIT_BLOCKED => 1,
            StatementEntity::WITHDRAW, StatementEntity::WITHDRAW_BLOCKED => -1,
            default => 0,
        });
        $exprAmount = (string)$amount;

        if ($capAtZero && $operation === StatementEntity::WITHDRAW) {
            // Cap withdraw so netbalance never goes below zero
            $exprAmount = "case when netbalance - {$amount} < 0 then {$amount} + (netbalance - {$amount}) else {$amount} end";
            $exprGross = "grossbalance - $exprAmount";
            $exprNet = "netbalance - $exprAmount";
        }

        return [$exprAmount, $exprGross, $exprNet];
    }

    /**
     * Append extra mapped fields from the DTO properties (extended entities) into the target/select lists.
     */
    private function appendExtraMappedFields(StatementDTO $dto, array &$targetColumns, array &$selectFields): void
    {
        $mapper = $this->statementRepository->getMapper();
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

    /**
     * Subquery selecting the last inserted statement to update account fields with.
     */
    private function buildLastInsertedStatementSnapshotQuery(): Query
    {
        return Query::getInstance()
            ->table($this->statementRepository->getMapper()->getTable())
            ->fields(['statementid', 'accountid', 'grossbalance', 'netbalance', 'uncleared'])
            ->where('statementid = (' . $this->statementRepository->getDbDriver()->getDbHelper()->getSqlLastInsertId() . ')');
    }

    protected function getInsertStatementQuery(
        string $operation,
        StatementDTO $dto,
        string $expressionSumGrossBalance,
        string $expressionSumNetBalance,
        string $expressionAmount,
        string $sumUnCleared
    ): InsertSelectQuery
    {
        // Build base target columns and select fields
        $targetColumns = [
            'accountid',
            'accounttypeid',
            'grossbalance',
            'netbalance',
            'uncleared',
            'price',
            'amount',
            'description',
            'code',
            'referenceid',
            'referencesource',
            'typeid',
            'date',
            'statementparentid',
        ];

        $selectFields = [
            'accountid',
            'accounttypeid',
            $expressionSumGrossBalance,
            $expressionSumNetBalance,
            "uncleared + $sumUnCleared",
            'price',
            $expressionAmount,
            ':description',
            ':code',
            ':referenceid',
            ':referencesource',
            ':operation',
            $this->statementRepository->getDbDriver()->getDbHelper()->sqlDate('Y-m-d H:i:s'),
            'null',
        ];

        // Append any extra mapped fields provided via DTO properties (for extended entities)
        $this->appendExtraMappedFields($dto, $targetColumns, $selectFields);

        $statementQuery = Query::getInstance()
            ->table('account')
            ->fields($selectFields)
            ->where('accountid = :accid2', [
                'accid2' => $dto->getAccountId(),
                'description' => $dto->getDescription(),
                'code' => $dto->getCode(),
                'referenceid' => $dto->getReferenceId(),
                'referencesource' => $dto->getReferenceSource(),
                'operation' => $operation,
            ]);

        return InsertSelectQuery::getInstance(
            $this->statementRepository->getMapper()->getTable(),
            $targetColumns
        )->fromQuery($statementQuery);
    }

    public function getAccountUpdateQuery(StatementDTO $dto): UpdateQuery
    {
        $statementSnapshot = $this->buildLastInsertedStatementSnapshotQuery();

        return UpdateQuery::getInstance()
            ->table('account')
            ->setLiteral('account.grossbalance', 'st.grossbalance')
            ->setLiteral('account.uncleared', 'st.uncleared')
            ->setLiteral('account.netbalance', 'st.netbalance')
            ->setLiteral('account.laststatementid', 'st.statementid')
            ->where('account.accountid = :accid', ['accid' => $dto->getAccountId()])
            ->join($statementSnapshot, 'st.accountid = account.accountid', 'st');
    }

    /**
     * Withdraw funds from an account
     *
     * @param StatementDTO $dto
     * @param bool $capAtZero
     * @return int|null Statement ID
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws StatementException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function withdrawFunds(StatementDTO $dto, bool $capAtZero = false): ?int
    {
        return $this->updateFunds(StatementEntity::WITHDRAW, $dto, $capAtZero)->getStatementId();
    }

    /**
     * Reserve funds to future withdrawn. It affects the net balance but not the gross balance
     *
     * @param StatementDTO $dto
     * @return int|null Statement ID
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws StatementException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function reserveFundsForWithdraw(StatementDTO $dto): ?int
    {
        return $this->updateFunds(StatementEntity::WITHDRAW_BLOCKED, $dto)->getStatementId();
    }

    /**
     * Reserve funds to future deposit. Update net balance but not gross balance.
     *
     * @param StatementDTO $dto
     * @return int|null Statement ID
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws StatementException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function reserveFundsForDeposit(StatementDTO $dto): ?int
    {
        return $this->updateFunds(StatementEntity::DEPOSIT_BLOCKED, $dto)->getStatementId();
    }

    /**
     * Accept a reserved fund and update gross balance
     *
     * @param int $statementId
     * @param StatementDTO|null $statementDto
     * @return int Statement ID
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws StatementException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptFundsById(int $statementId, ?StatementDTO $statementDto = null): int
    {
        if (is_null($statementDto)) {
            $statementDto = StatementDTO::createEmpty();
        }

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $statement = $this->statementRepository->getById($statementId);
            if (is_null($statement)) {
                throw new StatementException('acceptFundsById: Statement not found');
            }

            // Validate if statement can be accepted.
            if ($statement->getTypeId() != StatementEntity::WITHDRAW_BLOCKED && $statement->getTypeId() != StatementEntity::DEPOSIT_BLOCKED) {
                throw new StatementException("The statement id doesn't belongs to a reserved fund.");
            }

            // Validate if the statement has been already accepted.
            if ($this->statementRepository->getByParentId($statementId) != null) {
                throw new StatementException('The statement has been accepted already');
            }

            if ($statementDto->hasAccount() && $statementDto->getAccountId() != $statement->getAccountId()) {
                throw new StatementException('The statement account is different from the informed account in the DTO. Try createEmpty().');
            }

            // Get values and apply the updates
            $signal = $statement->getTypeId() == StatementEntity::DEPOSIT_BLOCKED ? 1 : -1;

            $account = $this->accountRepository->getById($statement->getAccountId());
            $account->setUnCleared($account->getUnCleared() + ($statement->getAmount() * $signal));
            $account->setGrossBalance($account->getGrossBalance() + ($statement->getAmount() * $signal));
            $account->setEntryDate(null);
            $this->accountRepository->save($account);

            // Update data
            $statement->setStatementParentId($statement->getStatementId());
            $statement->setStatementId(null); // Poder criar um novo registro
            $statement->setDate(null);
            $statement->setTypeId($statement->getTypeId() == StatementEntity::WITHDRAW_BLOCKED ? StatementEntity::WITHDRAW : StatementEntity::DEPOSIT);
            $statement->attachAccount($account);
            $statementDto->setToStatement($statement);
            $result = $this->statementRepository->save($statement);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param int $statementId
     * @param StatementDTO $statementDtoWithdraw
     * @param StatementDTO $statementDtoRefund
     * @return int|null
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws StatementException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function acceptPartialFundsById(int $statementId, StatementDTO $statementDtoWithdraw, StatementDTO $statementDtoRefund): ?int
    {
        $partialAmount = $statementDtoWithdraw->getAmount();

        if ($partialAmount <= 0) {
            throw new AmountException('Partial amount must be greater than zero.');
        }

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $statement = $this->statementRepository->getById($statementId);
            if (is_null($statement)) {
                throw new StatementException('acceptPartialFundsById: Statement not found');
            }
            if ($statement->getTypeId() != StatementEntity::WITHDRAW_BLOCKED) {
                throw new StatementException("The statement id doesn't belong to a reserved withdraw fund.");
            }
            if ($this->statementRepository->getByParentId($statementId) != null) {
                throw new StatementException('The statement has been processed already');
            }

            $originalAmount = $statement->getAmount();
            if ($partialAmount <= 0 || $partialAmount >= $originalAmount) {
                throw new AmountException(
                    'Partial amount must be greater than zero and less than the original reserved amount.'
                );
            }

            $this->rejectFundsById($statementId, $statementDtoRefund);

            $statementDtoWithdraw->setAccountId($statement->getAccountId());

            $finalDebitStatementId = $this->withdrawFunds($statementDtoWithdraw);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $finalDebitStatementId;

        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();
            throw $ex;
        }
    }

    /**
     * Reject a reserved fund and return the net balance
     *
     * @param int $statementId
     * @param StatementDTO|null $statementDto
     * @return int Statement ID
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws StatementException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function rejectFundsById(int $statementId, ?StatementDTO $statementDto = null): int
    {
        if (is_null($statementDto)) {
            $statementDto = StatementDTO::createEmpty();
        }

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $statement = $this->statementRepository->getById($statementId);
            if (is_null($statement)) {
                throw new StatementException('rejectFundsById: Statement not found');
            }

            // Validate if statement can be accepted.
            if ($statement->getTypeId() != StatementEntity::WITHDRAW_BLOCKED && $statement->getTypeId() != StatementEntity::DEPOSIT_BLOCKED) {
                throw new StatementException("The statement id doesn't belongs to a reserved fund.");
            }

            // Validate if the statement has been already accepted.
            if ($this->statementRepository->getByParentId($statementId) != null) {
                throw new StatementException('The statement has been accepted already');
            }

            if ($statementDto->hasAccount() && $statementDto->getAccountId() != $statement->getAccountId()) {
                throw new StatementException('The statement account is different from the informed account in the DTO. Try createEmpty().');
            }

            // Update Account
            $signal = $statement->getTypeId() == StatementEntity::DEPOSIT_BLOCKED ? -1 : +1;

            $account = $this->accountRepository->getById($statement->getAccountId());
            $account->setUnCleared($account->getUnCleared() - ($statement->getAmount() * $signal));
            $account->setNetBalance($account->getNetBalance() + ($statement->getAmount() * $signal));
            $account->setEntryDate(null);
            $this->accountRepository->save($account);

            // Update Statement
            $statement->setStatementParentId($statement->getStatementId());
            $statement->setStatementId(null); // Poder criar um novo registro
            $statement->setDate(null);
            $statement->setTypeId(StatementEntity::REJECT);
            $statement->attachAccount($account);
            $statementDto->setToStatement($statement);
            $result = $this->statementRepository->save($statement);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Update all blocked (reserved) transactions
     *
     * @param int|null $accountId
     * @return StatementEntity[]
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function getUnclearedStatements(int $accountId = null): array
    {
        return $this->statementRepository->getUnclearedStatements($accountId);
    }

    /**
     * @param int $accountId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDate(int $accountId, string $startDate, string $endDate): array
    {
        return $this->statementRepository->getByDate($accountId, $startDate, $endDate);
    }

    /**
     * This statement is blocked (reserved)
     *
     * @param int|null $statementId
     * @return bool
     */
    public function isStatementUncleared(int $statementId = null): bool
    {
        return null === $this->statementRepository->getByParentId($statementId, true);
    }

    /**
     * @return StatementRepository
     */
    public function getRepository(): StatementRepository
    {
        return $this->statementRepository;
    }
}
