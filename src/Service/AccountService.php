<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ByJG\AccountTransactions\Service;

use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\AccountEntity;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Exception\AccountException;
use ByJG\AccountTransactions\Exception\AccountTypeException;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\AccountTransactions\Repository\AccountRepository;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\Serializer\Exception\InvalidArgumentException;
use PDOException;

class AccountService
{
    /**
     * @var AccountRepository
     */
    protected AccountRepository $accountRepository;

    /**
     * @var AccountTypeService
     */
    protected AccountTypeService $accountTypeService;

    /**
     * @var TransactionService
     */
    protected TransactionService $transactionService;

    /**
     * AccountService constructor.
     * @param AccountRepository $accountRepository
     * @param AccountTypeService $accountTypeService
     * @param TransactionService $transactionService
     */
    public function __construct(AccountRepository $accountRepository, AccountTypeService $accountTypeService, TransactionService $transactionService)
    {
        $this->accountRepository = $accountRepository;

        $this->accountTypeService = $accountTypeService;
        $this->transactionService = $transactionService;
    }


    /**
     * Get an account by ID.
     *
     * @param int $accountId Optional id empty return all. 
     * @return AccountEntity|AccountEntity[]
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(int $accountId): array|AccountEntity
    {
        
        return $this->accountRepository->getById($accountId);
    }

    /**
     * Obtém uma lista AccountEntity pelo Id do Usuário
     *
     * @param string $userId
     * @param string $accountType Tipo de conta
     * @return AccountEntity[]
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function getByUserId(string $userId, string $accountType = ""): array
    {
        

        return $this->accountRepository->getByUserId($userId, $accountType);
    }

    /**
     * Obtém uma lista  AccountEntity pelo Account Type ID
     *
     * @param string $accountTypeId
     * @return AccountEntity[]
     * @throws InvalidArgumentException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getByAccountTypeId(string $accountTypeId): array
    {
        return $this->accountRepository->getByAccountTypeId($accountTypeId);
    }

    /**
     * Cria uma nova conta no sistema
     *
     * @param string $accountTypeId
     * @param string $userId
     * @param int $balance
     * @param int $price
     * @param int $minValue
     * @param string|null $extra
     * @return int
     * @throws AccountException
     * @throws AccountTypeException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function createAccount(string $accountTypeId, string $userId, int $balance, int $price = 1, int $minValue = 0, ?string $extra = null): int
    {
        // Faz as validações
        if ($this->accountTypeService->getById($accountTypeId) == null) {
            throw new AccountTypeException('AccountTypeId ' . $accountTypeId . ' não existe');
        }

        // Define os dados
        $model = new AccountEntity();
        $model->setAccountTypeId($accountTypeId);
        $model->setUserId($userId);
        $model->setBalance(0);
        $model->setAvailable(0);
        $model->setReserved(0);
        $model->setPrice($price);
        $model->setExtra($extra);
        $model->setMinValue($minValue);

        // Persiste os dados.
        
        try {
            $result = $this->accountRepository->save($model);
            $accountId = $result->getAccountId();
        } catch (PDOException $ex) {
            if (str_contains($ex->getMessage(), "Duplicate entry")) {
                throw new AccountException("Usuário $userId já possui uma conta do tipo $accountTypeId");
            } else {
                throw $ex;
            }
        }

        if ($balance >= 0) {
            $this->transactionService->addFunds(TransactionDTO::create($accountId, $balance)->setDescription("Opening Balance")->setCode('BAL'));
        } else {
            $this->transactionService->withdrawFunds(TransactionDTO::create($accountId, abs($balance))->setDescription("Opening Balance")->setCode('BAL'));
        }

        return $accountId;
    }

    /**
     * Reinicia o balanço
     *
     * @param int $accountId
     * @param int $newBalance
     * @param int $newPrice
     * @param int $newMinValue
     * @param string $description
     * @return int|null
     * @throws AccountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function overrideBalance(
        int       $accountId,
        int     $newBalance,
        int $newPrice = 1,
        int $newMinValue = 0,
        string $description = "Reset Balance"
    ): ?int
    {
        $account = $this->accountRepository->getById($accountId);

        if (empty($account)) {
            throw new AccountException('Account Id doesnt exists');
        }

        $dto = TransactionDTO::createEmpty();
        $dto->setUuid($dto->calculateUuid($this->accountRepository->getExecutor()));;

        $this->accountRepository->getExecutor()->beginTransaction();
        try {
            // Get total value reserved
            $reservedValues = 0;
            $qtd = 0;
            $object = $this->transactionService->getReservedTransactions($account->getAccountId());
            foreach ($object as $stmt) {
                $qtd++;
                $reservedValues += $stmt->getAmount();
            }

            if ($newBalance - $reservedValues < $newMinValue) {
                throw new TransactionException(
                    "Can't override balance because there is $qtd pending transactions with the amount of $reservedValues"
                );
            }

            // Update object Account
            $account->setBalance($newBalance);
            $account->setAvailable($newBalance - $reservedValues);
            $account->setReserved($reservedValues);
            $account->setPrice($newPrice);
            $account->setMinValue($newMinValue);
            $account->setLastUuid($dto->getUuid());
            $this->accountRepository->save($account);

            // Create new Transaction
            $transaction = new TransactionEntity();
            $transaction->setAmount($newBalance);
            $transaction->setAccountId($account->getAccountId());
            $transaction->setDescription(empty($description) ? "Reset Balance" : $description);
            $transaction->setTypeId(TransactionEntity::BALANCE);
            $transaction->setCode('BAL');
            $transaction->setBalance($newBalance);
            $transaction->setAvailable($newBalance - $reservedValues);
            $transaction->setReserved($reservedValues);
            $transaction->setPrice($newPrice);
            $transaction->setAccountTypeId($account->getAccountTypeId());
            $transaction->setUuid($dto->getUuid());
            $this->transactionService->getRepository()->save($transaction);
            $this->accountRepository->getExecutor()->commitTransaction();
        } catch (\Exception $ex) {
            $this->accountRepository->getExecutor()->rollbackTransaction();
            throw $ex;
        }

        return $transaction->getTransactionId();
    }

    /**
     * Encerra (Zera) uma conta
     *
     * @param int $accountId
     * @return int|null
     * @throws AccountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function closeAccount(int $accountId): ?int
    {
        return $this->overrideBalance($accountId, 0, 0);
    }

    /**
     * @param int $accountId
     * @param int $balance
     * @param string $description
     * @return TransactionEntity
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function partialBalance(int $accountId, int $balance, string $description = "Partial Balance"): TransactionEntity
    {
        $account = $this->getById($accountId);

        $amount = $balance - $account->getAvailable();

        if ($amount >= 0) {
            $transaction = $this->transactionService->addFunds(TransactionDTO::create($accountId, $amount)->setDescription($description));
        } else {
            $transaction = $this->transactionService->withdrawFunds(TransactionDTO::create($accountId, abs($amount))->setDescription($description));
        }

        return $transaction;
    }

    /**
     * @param int $accountSource
     * @param int $accountTarget
     * @param int $amount
     * @return array
     * @throws AccountException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function transferFunds(int $accountSource, int $accountTarget, int $amount): array
    {
        $refSource = bin2hex(openssl_random_pseudo_bytes(16));

        $transactionSourceDTO = TransactionDTO::createEmpty();
        $transactionSourceDTO->setAccountId($accountSource);
        $transactionSourceDTO->setAmount($amount);
        $transactionSourceDTO->setCode('T_TO');
        $transactionSourceDTO->setReferenceSource('transfer_to');
        $transactionSourceDTO->setReferenceId($refSource);
        $transactionSourceDTO->setDescription('Transfer to account id ' . $accountTarget);

        $transactionTargetDTO = TransactionDTO::createEmpty();
        $transactionTargetDTO->setAccountId($accountTarget);
        $transactionTargetDTO->setAmount($amount);
        $transactionTargetDTO->setCode('T_FROM');
        $transactionTargetDTO->setReferenceSource('transfer_from');
        $transactionTargetDTO->setReferenceId($refSource);
        $transactionTargetDTO->setDescription('Transfer from account id ' . $accountSource);

        $transactionSource = $this->transactionService->withdrawFunds($transactionSourceDTO);
        $transactionTarget = $this->transactionService->addFunds($transactionTargetDTO);

        return [ $transactionSource, $transactionTarget ];
    }

    public function getRepository(): AccountRepository
    {
        return $this->accountRepository;
    }
}
