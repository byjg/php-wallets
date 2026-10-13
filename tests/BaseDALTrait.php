<?php

namespace Tests;

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
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\Wallets\Entity\WalletTypeEntity;
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\TransactionException;
use ByJG\Wallets\Exception\WalletException;
use ByJG\Wallets\Exception\WalletTypeException;
use ByJG\Wallets\Repository\OutboxRepository;
use ByJG\Wallets\Repository\TransactionRepository;
use ByJG\Wallets\Repository\WalletRepository;
use ByJG\Wallets\Repository\WalletTypeRepository;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Service\WalletTypeService;
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
     * @var OutboxRepository|null
     */
    protected ?OutboxRepository $outboxRepository = null;

    /**
     * @throws ReflectionException
     * @throws OrmModelInvalidException
     */
    public function prepareObjects($walletEntity = WalletEntity::class, $walletTypeEntity = WalletTypeEntity::class, $transactionEntity = TransactionEntity::class, ?OutboxRepository $outboxRepository = null): void
    {
        $walletRepository = new WalletRepository($this->dbExecutor, $walletEntity);
        $walletTypeRepository = new WalletTypeRepository($this->dbExecutor, $walletTypeEntity);
        $transactionRepository = new TransactionRepository($this->dbExecutor, $transactionEntity);

        $this->outboxRepository = $outboxRepository;
        $this->walletTypeService = new WalletTypeService($walletTypeRepository);
        $this->transactionService = new TransactionService($transactionRepository, $walletRepository, null, $outboxRepository);
        $this->walletService = new WalletService($walletRepository, $this->walletTypeService, $this->transactionService);
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
        $uriMySqlTest = getenv('MYSQL_TEST_URI') ? getenv('MYSQL_TEST_URI') : "mysql://root:password@127.0.0.1/wallettest";
        $this->uri = new Uri($uriMySqlTest);

        Migration::registerDatabase(MySqlDatabase::class);

        $migration = new Migration($this->uri, __DIR__ . "/../db");
        $migration->prepareEnvironment();
        $migration->reset();

        $dbDriver = $migration->getDbDriver();
        $this->dbExecutor = DatabaseExecutor::using($dbDriver);

        // Drop the CHECK constraints so the negative amount can be validated
        /** @psalm-suppress InternalMethod */
        if (str_contains($this->name(), "Allow_Negativ")) {
            $this->dbExecutor->execute("ALTER TABLE transaction DROP CONSTRAINT transaction_chk_value_nonnegative");
            $this->dbExecutor->execute("ALTER TABLE wallet DROP CONSTRAINT wallet_chk_value_nonnegative");
        }

        $this->dbExecutor->execute("CREATE TABLE transaction_extended LIKE transaction");
        $this->dbExecutor->execute("alter table transaction_extended add extra_property varchar(100) null;");
    }

    protected function dbClear(): void
    {
        // Outbox entries reference transactions (FK), so they go first
        $this->dbExecutor->execute(
            'DELETE outbox FROM outbox ' .
            'INNER JOIN transaction ON outbox.transactionid = transaction.transactionid ' .
            'INNER JOIN wallet ON wallet.walletid = transaction.walletid ' .
            "WHERE wallet.userid like '___TESTUSER-%'"
        );

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

        $this->walletTypeService->update($dto1);
        $this->walletTypeService->update($dto2);
        $this->walletTypeService->update($dto3);
        $this->walletTypeService->update($dto4);

        $this->walletService->createWallet('BRLTEST', '___TESTUSER-1', 1000, 1);
    }
}
