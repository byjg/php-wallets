<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace ByJG\AccountTransactions\Service;

use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Entity\WalletEntity;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\AccountTransactions\Exception\WalletException;
use ByJG\AccountTransactions\Exception\WalletTypeException;
use ByJG\AccountTransactions\Repository\WalletRepository;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use Exception;
use PDOException;

class WalletService
{
    /**
     * @var WalletRepository
     */
    protected WalletRepository $walletRepository;

    /**
     * @var WalletTypeService
     */
    protected WalletTypeService $walletTypeService;

    /**
     * @var TransactionService
     */
    protected TransactionService $transactionService;

    /**
     * AccountService constructor.
     * @param WalletRepository $walletRepository
     * @param WalletTypeService $walletTypeService
     * @param TransactionService $transactionService
     */
    public function __construct(WalletRepository $walletRepository, WalletTypeService $walletTypeService, TransactionService $transactionService)
    {
        $this->walletRepository = $walletRepository;

        $this->walletTypeService = $walletTypeService;
        $this->transactionService = $transactionService;
    }


    /**
     * Get an account by ID.
     *
     * @param int $walletId Optional id empty return all.
     * @return WalletEntity|WalletEntity[]
     * @throws OrmInvalidFieldsException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getById(int $walletId): array|WalletEntity
    {
        
        return $this->walletRepository->getById($walletId);
    }

    /**
     * Return a list of AccountEntity by User ID
     *
     * @param string $userId
     * @param string $walletType Tipo de conta
     * @return WalletEntity[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByUserId(string $userId, string $walletType = ""): array
    {
        

        return $this->walletRepository->getByUserId($userId, $walletType);
    }

    /**
     * Obtém uma lista  AccountEntity pelo Account Type ID
     *
     * @param string $walletTypeId
     * @return WalletEntity[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByWalletTypeId(string $walletTypeId): array
    {
        return $this->walletRepository->getByWalletTypeId($walletTypeId);
    }

    /**
     * Cria uma nova conta no sistema
     *
     * @param string $walletTypeId
     * @param string $userId
     * @param int $balance
     * @param int $price
     * @param int $minValue
     * @param string|null $extra
     * @return int
     * @throws WalletException
     * @throws WalletTypeException
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
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function createWallet(string $walletTypeId, string $userId, int $balance, int $price = 1, int $minValue = 0, ?string $extra = null): int
    {
        // Faz as validações
        if ($this->walletTypeService->getById($walletTypeId) == null) {
            throw new WalletTypeException('AccountTypeId ' . $walletTypeId . ' não existe');
        }

        // Define os dados
        $model = new WalletEntity();
        $model->setWalletTypeId($walletTypeId);
        $model->setUserId($userId);
        $model->setBalance(0);
        $model->setAvailable(0);
        $model->setReserved(0);
        $model->setPrice($price);
        $model->setExtra($extra);
        $model->setMinValue($minValue);

        // Persiste os dados.
        
        try {
            $result = $this->walletRepository->save($model);
            $walletId = $result->getWalletId();
        } catch (PDOException $ex) {
            if (str_contains($ex->getMessage(), "Duplicate entry")) {
                throw new WalletException("Usuário $userId já possui uma conta do tipo $walletTypeId");
            } else {
                throw $ex;
            }
        }

        if ($balance >= 0) {
            $this->transactionService->addFunds(TransactionDTO::create($walletId, $balance)->setDescription("Opening Balance")->setCode('BAL'));
        } else {
            $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, abs($balance))->setDescription("Opening Balance")->setCode('BAL'));
        }

        return $walletId;
    }

    /**
     * Reinicia o balanço
     *
     * @param int $walletId
     * @param int $newBalance
     * @param int $newPrice
     * @param int $newMinValue
     * @param string $description
     * @return int|null
     * @throws WalletException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function overrideBalance(
        int       $walletId,
        int     $newBalance,
        int $newPrice = 1,
        int $newMinValue = 0,
        string $description = "Reset Balance"
    ): ?int
    {
        $wallet = $this->walletRepository->getById($walletId);

        if (empty($wallet)) {
            throw new WalletException('Account Id doesnt exists');
        }

        $dto = TransactionDTO::createEmpty();
        $dto->setUuid($dto->calculateUuid($this->walletRepository->getExecutor()));

        $this->walletRepository->getExecutor()->beginTransaction();
        try {
            // Get total value reserved
            $reservedValues = 0;
            $qtd = 0;
            $object = $this->transactionService->getReservedTransactions($wallet->getWalletId());
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
            $wallet->setBalance($newBalance);
            $wallet->setAvailable($newBalance - $reservedValues);
            $wallet->setReserved($reservedValues);
            $wallet->setPrice($newPrice);
            $wallet->setMinValue($newMinValue);
            $wallet->setLastUuid($dto->getUuid());
            $this->walletRepository->save($wallet);

            // Create a new Transaction
            $transaction = new TransactionEntity();
            $transaction->setAmount($newBalance);
            $transaction->setWalletId($wallet->getWalletId());
            $transaction->setDescription(empty($description) ? "Reset Balance" : $description);
            $transaction->setTypeId(TransactionEntity::BALANCE);
            $transaction->setCode('BAL');
            $transaction->setBalance($newBalance);
            $transaction->setAvailable($newBalance - $reservedValues);
            $transaction->setReserved($reservedValues);
            $transaction->setPrice($newPrice);
            $transaction->setWalletTypeId($wallet->getWalletTypeId());
            $transaction->setUuid($dto->getUuid());
            $this->transactionService->getRepository()->save($transaction);
            $this->walletRepository->getExecutor()->commitTransaction();
        } catch (Exception $ex) {
            $this->walletRepository->getExecutor()->rollbackTransaction();
            throw $ex;
        }

        return $transaction->getTransactionId();
    }

    /**
     * Encerra (Zera) uma conta
     *
     * @param int $walletId
     * @return int|null
     * @throws WalletException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws TransactionException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function closeWallet(int $walletId): ?int
    {
        return $this->overrideBalance($walletId, 0, 0);
    }

    /**
     * @param int $walletId
     * @param int $balance
     * @param string $description
     * @return TransactionEntity
     * @throws WalletException
     * @throws AmountException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function partialBalance(int $walletId, int $balance, string $description = "Partial Balance"): TransactionEntity
    {
        $wallet = $this->getById($walletId);

        $amount = $balance - $wallet->getAvailable();

        if ($amount >= 0) {
            $transaction = $this->transactionService->addFunds(TransactionDTO::create($walletId, $amount)->setDescription($description));
        } else {
            $transaction = $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, abs($amount))->setDescription($description));
        }

        return $transaction;
    }

    /**
     * @param int $walletSource
     * @param int $walletTarget
     * @param int $amount
     * @return array
     * @throws WalletException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function transferFunds(int $walletSource, int $walletTarget, int $amount): array
    {
        $refSource = bin2hex(openssl_random_pseudo_bytes(16));

        $transactionSourceDTO = TransactionDTO::createEmpty();
        $transactionSourceDTO->setWalletId($walletSource);
        $transactionSourceDTO->setAmount($amount);
        $transactionSourceDTO->setCode('T_TO');
        $transactionSourceDTO->setReferenceSource('transfer_to');
        $transactionSourceDTO->setReferenceId($refSource);
        $transactionSourceDTO->setDescription('Transfer to account id ' . $walletTarget);

        $transactionTargetDTO = TransactionDTO::createEmpty();
        $transactionTargetDTO->setWalletId($walletTarget);
        $transactionTargetDTO->setAmount($amount);
        $transactionTargetDTO->setCode('T_FROM');
        $transactionTargetDTO->setReferenceSource('transfer_from');
        $transactionTargetDTO->setReferenceId($refSource);
        $transactionTargetDTO->setDescription('Transfer from account id ' . $walletSource);

        $transactionSource = $this->transactionService->withdrawFunds($transactionSourceDTO);
        $transactionTarget = $this->transactionService->addFunds($transactionTargetDTO);

        return [ $transactionSource, $transactionTarget ];
    }

    public function getRepository(): WalletRepository
    {
        return $this->walletRepository;
    }
}
