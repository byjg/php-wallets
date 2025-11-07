<?php

namespace Tests;

use ByJG\AccountTransactions\Entity\AccountEntity;
use ByJG\AccountTransactions\Entity\AccountTypeEntity;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Exception\AccountException;
use ByJG\AccountTransactions\Exception\AccountTypeException;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\AccountTransactions\Repository\AccountRepository;
use ByJG\AccountTransactions\Repository\AccountTypeRepository;
use ByJG\AccountTransactions\Repository\TransactionRepository;
use ByJG\AccountTransactions\Service\AccountService;
use ByJG\AccountTransactions\Service\AccountTypeService;
use ByJG\AccountTransactions\Service\TransactionService;
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
     * @var AccountService
     */
    protected AccountService $accountService;

    /**
     * @var AccountTypeService
     */
    protected AccountTypeService $accountTypeService;

    /**
     * @var TransactionService
     */
    protected TransactionService $transactionService;

    /**
     * @throws ReflectionException
     * @throws OrmModelInvalidException
     */
    public function prepareObjects($accountEntity = AccountEntity::class, $accountTypeEntity = AccountTypeEntity::class, $transactionEntity = TransactionEntity::class): void
    {
        $accountRepository = new AccountRepository($this->dbExecutor, $accountEntity);
        $accountTypeRepository = new AccountTypeRepository($this->dbExecutor, $accountTypeEntity);
        $transactionRepository = new TransactionRepository($this->dbExecutor, $transactionEntity);

        $this->accountTypeService = new AccountTypeService($accountTypeRepository);
        $this->transactionService = new TransactionService($transactionRepository, $accountRepository);
        $this->accountService = new AccountService($accountRepository, $this->accountTypeService, $this->transactionService);
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
            'DELETE transaction FROM `account` INNER JOIN transaction ' .
            "WHERE account.accountid = transaction.accountid and account.userid like '___TESTUSER-%' and transactionparentid is not null;"
        );

        $this->dbExecutor->execute(
            'DELETE transaction FROM `account` INNER JOIN transaction ' .
            "WHERE account.accountid = transaction.accountid and account.userid like '___TESTUSER-%'"
        );

        $this->dbExecutor->execute("DELETE FROM `account` where account.userid like '___TESTUSER-%'");

        $this->dbExecutor->execute("DELETE FROM `accounttype` WHERE accounttypeid like '___TEST'");
    }

    /**
     * @throws AccountException
     * @throws AccountTypeException
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
        $dto1 = new AccountTypeEntity();
        $dto1->setAccountTypeId('USDTEST');
        $dto1->setName('Test 1');

        $dto2 = new AccountTypeEntity();
        $dto2->setAccountTypeId('BRLTEST');
        $dto2->setName('Test 2');

        $dto3 = new AccountTypeEntity();
        $dto3->setAccountTypeId('ABCTEST');
        $dto3->setName('Test 3');

        $dto4 = new AccountTypeEntity();
        $dto4->setAccountTypeId('NEGTEST');
        $dto4->setName('Test 4');

        $this->accountTypeService->update($dto1);
        $this->accountTypeService->update($dto2);
        $this->accountTypeService->update($dto3);
        $this->accountTypeService->update($dto4);

        $this->accountService->createAccount('BRLTEST', '___TESTUSER-1', 1000, 1);
    }
}
