<?php

namespace ByJG\AccountStatements\Bll;

use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\AccountEntity;
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
use ByJG\Serializer\Exception\InvalidArgumentException;
use Exception;
use KingPandaApi\Model\StatementCodes;

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
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws StatementException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function addFunds(StatementDTO $dto): ?int
    {
        // Validations
        $this->validateStatementDto($dto);

        // Get an Account
        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (is_null($account) || $account->getAccountId() == "") {
                throw new AccountException("addFunds: Account " . $dto->getAccountId() . " not found");
            }

            $result = $this->updateFunds(StatementEntity::DEPOSIT, $account, $dto);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
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
     * @throws RepositoryReadOnlyException
     * @throws InvalidArgumentException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws UpdateConstraintException
     * @throws OrmBeforeInvalidException
     */
    protected function updateFunds(string $operation, AccountEntity $account, StatementDTO $dto): StatementEntity
    {
        $sumGrossBalance = $dto->getAmount() * match($operation) {
            StatementEntity::DEPOSIT => 1,
            StatementEntity::WITHDRAW => -1,
            default => 0,
        };
        $sumUnCleared = $dto->getAmount() * match($operation) {
            StatementEntity::DEPOSIT_BLOCKED => -1,
            StatementEntity::WITHDRAW_BLOCKED => 1,
            default => 0,
        };
        $sumNetBalance = $dto->getAmount() * match($operation) {
            StatementEntity::DEPOSIT, StatementEntity::DEPOSIT_BLOCKED => 1,
            StatementEntity::WITHDRAW, StatementEntity::WITHDRAW_BLOCKED => -1,
            default => 0,
        };

        // Update Values in an account
        $account->setGrossBalance($account->getGrossBalance() + $sumGrossBalance);
        $account->setUncleared($account->getUncleared() + $sumUnCleared);
        $account->setNetBalance($account->getNetBalance() + $sumNetBalance);
        $this->accountRepository->save($account);

        // Add the new line
        /** @var StatementEntity $statement */
        $statement = $this->statementRepository->getRepository()->entity([]);
        $dto->setToStatement($statement);
        $statement->setTypeId($operation);
        $statement->attachAccount($account);

        // Save to DB
        return $this->statementRepository->save($statement);
    }

    /**
     * Withdraw funds from an account
     *
     * @param StatementDTO $dto
     * @param bool $allowZeroNoBalance
     * @return int|null Statement ID
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
    public function withdrawFunds(StatementDTO $dto, bool $allowZeroNoBalance = false): ?int
    {
        // Validations
        $this->validateStatementDto($dto);

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (is_null($account)) {
                throw new AccountException('addFunds: Account not found');
            }

            // Cannot withdraw above the account balance.
            $newBalance = $account->getNetBalance() - $dto->getAmount();
            if ($newBalance < $account->getMinValue()) {
                if (!$allowZeroNoBalance) {
                    throw new AmountException('Cannot withdraw above the account balance.');
                }
                $dto->setAmount($account->getNetBalance() - $account->getMinValue());
            }

            $result = $this->updateFunds(StatementEntity::WITHDRAW, $account, $dto);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Reserve funds to future withdrawn. It affects the net balance but not the gross balance
     *
     * @param StatementDTO $dto
     * @return int|null Statement ID
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
    public function reserveFundsForWithdraw(StatementDTO $dto): ?int
    {
        // Validations
        $this->validateStatementDto($dto);

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (is_null($account)) {
                throw new AccountException('reserveFundsForWithdraw: Account not found');
            }

            // Cannot withdraw above the account balance.
            if ($account->getNetBalance() - $dto->getAmount() < $account->getMinValue()) {
                throw new AmountException('Cannot withdraw above the account balance.');
            }

            $result = $this->updateFunds(StatementEntity::WITHDRAW_BLOCKED, $account, $dto);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Reserve funds to future deposit. Update net balance but not gross balance.
     *
     * @param StatementDTO $dto
     * @return int|null Statement ID
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
    public function reserveFundsForDeposit(StatementDTO $dto): ?int
    {
        // Validações
        $this->validateStatementDto($dto);

        $this->getRepository()->getDbDriver()->beginTransaction(IsolationLevelEnum::SERIALIZABLE, true);
        try {
            $account = $this->accountRepository->getById($dto->getAccountId());
            if (is_null($account)) {
                throw new AccountException('reserveFundsForDeposit: Account not found');
            }

            $result = $this->updateFunds(StatementEntity::DEPOSIT_BLOCKED, $account, $dto);

            $this->getRepository()->getDbDriver()->commitTransaction();

            return $result->getStatementId();
        } catch (Exception $ex) {
            $this->getRepository()->getDbDriver()->rollbackTransaction();

            throw $ex;
        }
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
