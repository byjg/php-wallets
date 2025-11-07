<?php

namespace Tests;

use ByJG\AccountStatements\Bll\AccountBLL;
use ByJG\AccountStatements\Bll\AccountTypeBLL;
use ByJG\AccountStatements\Bll\StatementBLL;
use ByJG\AccountStatements\Entity\AccountEntity;
use ByJG\AccountStatements\Entity\AccountTypeEntity;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AccountException;
use ByJG\AccountStatements\Exception\AccountTypeException;
use ByJG\AccountStatements\Exception\AmountException;
use ByJG\AccountStatements\Exception\StatementException;
use ByJG\AccountStatements\Repository\AccountRepository;
use ByJG\AccountStatements\Repository\AccountTypeRepository;
use ByJG\AccountStatements\Repository\StatementRepository;
use ByJG\AnyDataset\Db\DbDriverInterface;
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
     * @var AccountBLL
     */
    protected AccountBLL $accountBLL;

    /**
     * @var AccountTypeBLL
     */
    protected AccountTypeBLL $accountTypeBLL;

    /**
     * @var StatementBLL
     */
    protected StatementBLL $statementBLL;

    /**
     * @throws ReflectionException
     * @throws OrmModelInvalidException
     */
    public function prepareObjects($accountEntity = AccountEntity::class, $accountTypeEntity = AccountTypeEntity::class, $statementEntity = StatementEntity::class): void
    {
        $accountRepository = new AccountRepository($this->dbDriver, $accountEntity);
        $accountTypeRepository = new AccountTypeRepository($this->dbDriver, $accountTypeEntity);
        $statementRepository = new StatementRepository($this->dbDriver, $statementEntity);

        $this->accountTypeBLL = new AccountTypeBLL($accountTypeRepository);
        $this->statementBLL = new StatementBLL($statementRepository, $accountRepository);
        $this->accountBLL = new AccountBLL($accountRepository, $this->accountTypeBLL, $this->statementBLL);
    }

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var DbDriverInterface
     */
    protected $dbDriver;

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
        if (strpos($this->getName(), "Allow_Negativ") !== false) {
            $maxVersion = 3;
        }
        $migration->reset($maxVersion);

        $migration->getDbDriver()->execute("CREATE TABLE statement_extended LIKE statement");
        $migration->getDbDriver()->execute("alter table statement_extended add extra_property varchar(100) null;");

        $this->dbDriver = $migration->getDbDriver();
    }

    protected function dbClear(): void
    {
        $this->dbDriver->execute(
            'DELETE statement FROM `account` INNER JOIN statement ' .
            "WHERE account.accountid = statement.accountid and account.userid like '___TESTUSER-%' and statementparentid is not null;"
        );

        $this->dbDriver->execute(
            'DELETE statement FROM `account` INNER JOIN statement ' .
            "WHERE account.accountid = statement.accountid and account.userid like '___TESTUSER-%'"
        );

        $this->dbDriver->execute("DELETE FROM `account` where account.userid like '___TESTUSER-%'");

        $this->dbDriver->execute("DELETE FROM `accounttype` WHERE accounttypeid like '___TEST'");
    }

    /**
     * @throws AccountException
     * @throws AccountTypeException
     * @throws AmountException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws StatementException
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

        $this->accountTypeBLL->update($dto1);
        $this->accountTypeBLL->update($dto2);
        $this->accountTypeBLL->update($dto3);
        $this->accountTypeBLL->update($dto4);

        $this->accountBLL->createAccount('BRLTEST', '___TESTUSER-1', 1000, 1);
    }
}
