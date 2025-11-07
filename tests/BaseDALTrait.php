<?php

namespace Tests;

use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Entity\WalletEntity;
use ByJG\AccountTransactions\Entity\WalletTypeEntity;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\AccountTransactions\Exception\WalletException;
use ByJG\AccountTransactions\Exception\WalletTypeException;
use ByJG\AccountTransactions\Repository\TransactionRepository;
use ByJG\AccountTransactions\Repository\WalletRepository;
use ByJG\AccountTransactions\Repository\WalletTypeRepository;
use ByJG\AccountTransactions\Service\TransactionService;
use ByJG\AccountTransactions\Service\WalletService;
use ByJG\AccountTransactions\Service\WalletTypeService;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\DbMigration\Database\MySqlDatabase;
use ByJG\DbMigration\Migration;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\Util\Uri;
use ReflectionException;

trait BaseDALTrait
{

    /**
     * @var WalletService
     */
    protected WalletService $walletService;

    /**
     * @var WalletTypeService
     */
    protected WalletTypeService $walletTypeService;

    /**
     * @var TransactionService
     */
    protected TransactionService $transactionService;

    /**
     * @throws ReflectionException
     * @throws OrmModelInvalidException
     */
    public function prepareObjects($walletEntity = WalletEntity::class, $walletTypeEntity = WalletTypeEntity::class, $transactionEntity = TransactionEntity::class): void
    {
        $walletRepository = new WalletRepository($this->dbExecutor, $walletEntity);
        $walletTypeRepository = new WalletTypeRepository($this->dbExecutor, $walletTypeEntity);
        $transactionRepository = new TransactionRepository($this->dbExecutor, $transactionEntity);

        $this->accountTypeService = new WalletTypeService($walletTypeRepository);
        $this->transactionService = new TransactionService($transactionRepository, $walletRepository);
        $this->accountService = new WalletService($walletRepository, $this->accountTypeService, $this->transactionService);
    }

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var DatabaseExecutor
     */
    protected $dbExecutor;

    public function dbSetUp(): void
    {
        $uriMySqlTest = getenv('MYSQL_TEST_URI') ? getenv('MYSQL_TEST_URI') : "mysql://root:password@127.0.0.1/accounttest";
        $this->uri = new Uri($uriMySqlTest);

        Migration::registerDatabase(MySqlDatabase::class);

        $migration = new Migration($this->uri, __DIR__ . "/../db");
        $migration->prepareEnvironment();
        // This will delete the constraint to validate the negative amount
        $maxVersion = null;
        /** @psalm-suppress InternalMethod */
        if (str_contains($this->name(), "Allow_Negativ")) {
            $maxVersion = 0;
        }
        $migration->reset($maxVersion);

        $dbDriver = $migration->getDbDriver();
        $this->dbExecutor = DatabaseExecutor::using($dbDriver);

        $this->dbExecutor->execute("CREATE TABLE transaction_extended LIKE transaction");
        $this->dbExecutor->execute("alter table transaction_extended add extra_property varchar(100) null;");
    }

    protected function dbClear(): void
    {
        $this->dbExecutor->execute(
            'DELETE transaction FROM `wallet` INNER JOIN transaction ' .
            "WHERE wallet.walletid = transaction.walletid and wallet.userid like '___TESTUSER-%' and transactionparentid is not null;"
        );

        $this->dbExecutor->execute(
            'DELETE transaction FROM `wallet` INNER JOIN transaction ' .
            "WHERE wallet.walletid = transaction.walletid and wallet.userid like '___TESTUSER-%'"
        );

        $this->dbExecutor->execute("DELETE FROM `wallet` where wallet.userid like '___TESTUSER-%'");

        $this->dbExecutor->execute("DELETE FROM `wallettype` WHERE wallettypeid like '___TEST'");
    }

    /**
     * @throws WalletException
     * @throws WalletTypeException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    protected function createDummyData(): void
    {
        $dto1 = new WalletTypeEntity();
        $dto1->setWalletTypeId('USDTEST');
        $dto1->setName('Test 1');

        $dto2 = new WalletTypeEntity();
        $dto2->setWalletTypeId('BRLTEST');
        $dto2->setName('Test 2');

        $dto3 = new WalletTypeEntity();
        $dto3->setWalletTypeId('ABCTEST');
        $dto3->setName('Test 3');

        $dto4 = new WalletTypeEntity();
        $dto4->setWalletTypeId('NEGTEST');
        $dto4->setName('Test 4');

        $this->accountTypeService->update($dto1);
        $this->accountTypeService->update($dto2);
        $this->accountTypeService->update($dto3);
        $this->accountTypeService->update($dto4);

        $this->accountService->createWallet('BRLTEST', '___TESTUSER-1', 1000, 1);
    }
}
