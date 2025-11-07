<?php

namespace Tests\Database;

use ByJG\AccountTransactions\Bll\AccountBLL;
use ByJG\AccountTransactions\Bll\AccountTypeBLL;
use ByJG\AccountTransactions\Bll\TransactionBLL;
use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\AccountEntity;
use ByJG\AccountTransactions\Entity\AccountTypeEntity;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Exception\AccountException;
use ByJG\AccountTransactions\Exception\AccountTypeException;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AnyDataset\Db\Exception\TransactionStartedException;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\TransactionException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Serializer\Serialize;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;
use Tests\Classes\AccountRepositoryExtended;
use Tests\Classes\TransactionExtended;
use Tests\Classes\TransactionRepositoryExtended;


class AccountTransactionsTest extends TestCase
{
    use BaseDALTrait;


    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    #[\Override]
    protected function setUp(): void
    {
        $this->dbSetUp();
        $this->prepareObjects();
        $this->createDummyData();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->dbClear();
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetAccountType()
    {
        $accountTypeRepo = $this->accountTypeBLL->getRepository();
        $list = $accountTypeRepo->getAll(null, null, null, [["accounttypeid like '___TEST'", []]]);

        $this->assertEquals(4, count($list));

        $this->assertEquals(
            [
                [
                    'accounttypeid' => 'ABCTEST',
                    'name' => 'Test 3'
                ],
                [
                    'accounttypeid' => 'BRLTEST',
                    'name' => 'Test 2'
                ],
                [
                    'accounttypeid' => 'NEGTEST',
                    'name' => 'Test 4'
                ],
                [
                    'accounttypeid' => 'USDTEST',
                    'name' => 'Test 1'
                ],
            ],
            Serialize::from($list)->toArray()
        );

        $dto = $this->accountTypeBLL->getById('USDTEST');
        $this->assertEquals('Test 1', $dto->getName());
        $this->assertEquals('USDTEST', $dto->getAccountTypeId());
    }

    public function testGetById(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($accountId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('10.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance('990.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('990.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia');
        $transaction->setReferenceSource('Source');
        $transaction->setCode('XYZ');
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testGetById_Zero(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 0);
        $dto = TransactionDTO::create($accountId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionBLL->addFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('10.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance('10.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable('10.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia');
        $transaction->setReferenceSource('Source');
        $transaction->setCode('XYZ');
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testGetById_NotFound(): void
    {
        // Executar teste
        $this->assertEquals($this->transactionBLL->getById(2), null);
    }

    public function testGetAll(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transactionResult = $this->transactionBLL->withdrawFunds(
            TransactionDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
        );
        $this->transactionBLL->withdrawFunds(
            TransactionDTO::create($accountId, 50)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
        );

        $transaction = [];

        // Objetos que são esperados
        $transaction[] = new TransactionEntity;
        $transaction[0]->setAmount('1000.00');
        $transaction[0]->setDate('2015-01-24');
        $transaction[0]->setDescription('Opening Balance');
        $transaction[0]->setCode('BAL');
        $transaction[0]->setBalance('1000.00');
        $transaction[0]->setAccountId($accountId);
        $transaction[0]->setTransactionId(2);
        $transaction[0]->setTypeId('D');
        $transaction[0]->setAvailable('1000.00');
        $transaction[0]->setPrice('1.00');
        $transaction[0]->setReserved('0.00');
        $transaction[0]->setReferenceId('');
        $transaction[0]->setReferenceSource('');
        $transaction[0]->setAccountTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[1]->setAmount('10.00');
        $transaction[1]->setDate('2015-01-24');
        $transaction[1]->setDescription('Test');
        $transaction[1]->setBalance('990.00');
        $transaction[1]->setAccountId($accountId);
        $transaction[1]->setTransactionId($transactionResult->getTransactionId());
        $transaction[1]->setTypeId('W');
        $transaction[1]->setAvailable('990.00');
        $transaction[1]->setPrice('1.00');
        $transaction[1]->setReserved('0.00');
        $transaction[1]->setReferenceId('Referencia');
        $transaction[1]->setReferenceSource('Source');
        $transaction[1]->setAccountTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[2]->setAmount('50.00');
        $transaction[2]->setDate('2015-01-24');
        $transaction[2]->setDescription('Test');
        $transaction[2]->setBalance('940.00');
        $transaction[2]->setAccountId($accountId);
        $transaction[2]->setTransactionId(4);
        $transaction[2]->setTypeId('W');
        $transaction[2]->setAvailable('940.00');
        $transaction[2]->setPrice('1.00');
        $transaction[2]->setReserved('0.00');
        $transaction[2]->setReferenceId('Referencia');
        $transaction[2]->setReferenceSource('Source');
        $transaction[2]->setAccountTypeId('USDTEST');

        $listAll = $this->transactionBLL->getRepository()->getAll(null, null, null, [["accounttypeid = :id", ["id" => 'USDTEST']]]);

        /** @psalm-suppress InvalidArrayOffset */
        for ($i = 0; $i < count($transaction); $i++) {
            $transaction[$i]->setDate(null);
            $transaction[$i]->setTransactionId(null);
            $transaction[$i]->setUuid(null);
            $listAll[$i]->setDate(null);
            $listAll[$i]->setTransactionId(null);
            $listAll[$i]->setUuid(null);
        }

        // Testar método
        $this->assertEquals(
            $transaction,
            $listAll
        );
    }

    public function testAddFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($accountId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $actual = $this->transactionBLL->addFunds($dto);

        // Check
        $transaction = new TransactionEntity;
        $transaction->setAmount('250.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance('1250.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('D');
        $transaction->setAvailable('1250.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Add Funds');
        $transaction->setReferenceSource('Source Add Funds');
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    /**
     * @return (float|int)[][]
     *
     * @psalm-return list{list{250}, list{float}, list{float}, list{float}, list{float}, list{float}, list{float}, list{float}, list{float}, list{float}}
     */

    public function testAddFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check;
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, -15));
    }

    public function testWithdrawFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($accountId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('350.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance('650.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('650.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, -15));
    }

    public function testWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $dto = TransactionDTO::create($accountId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('1150.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance('-150.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('-150.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setAccountTypeId('NEGTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Allow_Negative2(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $transaction = $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 1400)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        $transaction = $this->transactionBLL->getById($transaction->getTransactionId());
        $this->assertEquals(-400, $transaction->getAvailable());
        $this->assertEquals(1400, $transaction->getAmount());
    }


    public function testWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000, 1, -400);
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 1401)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
    }

    /**
     * @return void
     * @throws AccountException
     * @throws AccountTypeException
     * @throws AmountException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function testGetAccountByUserId()
    {
        $accountId = $this->accountBLL->createAccount(
            'USDTEST',
            "___TESTUSER-10",
            1000,
            1,
            0,
            'Extra Information'
        );

        $account = $this->accountBLL->getByUserId("___TESTUSER-10");
        $account[0]->setEntryDate(null);

        $accountEntity = $this->accountBLL->getRepository()->getMapper()->getEntity([
            "accountid" => $accountId,
            "accounttypeid" => "USDTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00",
            "last_uuid" => $account[0]->getLastUuid(),
        ]);

        $this->assertNotNull($account[0]->getLastUuid());

        $this->assertEquals([
            $accountEntity
        ], $account);
    }

    /**
     * @throws AmountException
     * @throws AccountException
     * @throws AccountTypeException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function testGetAccountByAccountType(): void
    {
        $accountId = $this->accountBLL->createAccount(
            'ABCTEST',
            "___TESTUSER-10",
            1000,
            1,
            0,
            'Extra Information'
        );

        $account = $this->accountBLL->getByAccountTypeId('ABCTEST');
        $account[0]->setEntryDate(null);

        $accountEntity = $this->accountBLL->getRepository()->getMapper()->getEntity([
            "accountid" => $accountId,
            "accounttypeid" => "ABCTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00",
            "last_uuid" => $account[0]->getLastUuid(),
        ]);

        $this->assertNotNull($account[0]->getLastUuid());

        $this->assertEquals([
            $accountEntity
        ], $account);
    }

    public function testOverrideFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $transactionId = $this->accountBLL->overrideBalance($accountId, 650);
        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        $transaction = $this->transactionBLL->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance' => '650.00',
            'reserved' => '0.00',
            'available' => '650.00',
            'price' => '1.00',
            'extra' => '',
            'minvalue' => '0.00',
            "lastUuid" => $transaction["uuid"],
        ],
            $account
        );

        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'balance' => '650.00',
            'reserved' => '0.00',
            'available' => '650.00',
            'price' => '1.00',
            'transactionid' => $transactionId,
            'typeid' => 'B',
            'amount' => '650.00',
            'description' => 'Reset Balance',
            'transactionparentid' => '',
            'code' => 'BAL',
            'referenceid' => '',
            'referencesource' => '',
            'uuid' => $transaction["uuid"],
        ],
            $transaction
        );
    }

    public function testPartialFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $transactionPartial = $this->accountBLL->partialBalance($accountId, 650);
        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        // Executar teste
        $this->assertEquals(
            [
                'accountid' => $accountId,
                'accounttypeid' => 'USDTEST',
                'userid' => "___TESTUSER-1",
                'balance' => '650.00',
                'reserved' => '0.00',
                'available' => '650.00',
                'price' => '1.00',
                'extra' => '',
                'minvalue' => '0.00',
                "lastUuid" => $transactionPartial->getUuid(),
            ],
            $account
        );

        $transaction = Serialize::from($transactionPartial)->toArray();
        unset($transaction["date"]);
        unset($transaction["uuid"]);

        $this->assertEquals(
            [
                'accountid' => $accountId,
                'accounttypeid' => 'USDTEST',
                'balance' => '650.00',
                'reserved' => '0.00',
                'available' => '650.00',
                'price' => '1.00',
                'transactionid' => $transactionPartial->getTransactionId(),
                'typeid' => 'W',
                'amount' => '350.00',
                'description' => 'Partial Balance',
                'transactionparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => ''
            ],
            $transaction
        );

    }

    public function testCloseAccount(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 400));
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 200));
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 300));

        $transactionId = $this->accountBLL->closeAccount($accountId);

        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        $transaction = $this->transactionBLL->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance' => '0.00',
            'reserved' => '0.00',
            'available' => '0.00',
            'price' => '0.00',
            'extra' => '',
            'minvalue' => '0.00',
            "lastUuid" => $transaction["uuid"],
        ],
            $account
        );

        $this->assertEquals(
            [
                'accountid' => $accountId,
                'accounttypeid' => 'USDTEST',
                'balance' => '0.00',
                'reserved' => '0.00',
                'available' => '0.00',
                'price' => '0.00',
                'transactionid' => $transactionId,
                'typeid' => 'B',
                'amount' => '0.00',
                'description' => 'Reset Balance',
                'transactionparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => 'BAL',
                "uuid" => $transaction["uuid"],
            ],
            $transaction
        );

    }

    public function testGetByDate(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 400));
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionBLL->addFunds(TransactionDTO::create($ignore, 200));

        $startDate = date('Y') . "/" . date('m') . "/01";
        $endDate = (intval(date('Y')) + (date('m') == 12 ? 1 : 0)) . "/" . (date('m') == 12 ? 1 : intval(date('m')) + 1) . "/01";

        $transactionList = $this->transactionBLL->getByDate($accountId, $startDate, $endDate);

        // Executar teste
        $this->assertEquals(
            [
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1000.00',
                    'reserved' => '0.00',
                    'available' => '1000.00',
                    'price' => '1.00',
                    'transactionid' => '2',
                    'typeid' => 'D',
                    'amount' => '1000.00',
                    'description' => 'Opening Balance',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => 'BAL'
                ],
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1400.00',
                    'reserved' => '0.00',
                    'available' => '1400.00',
                    'price' => '1.00',
                    'transactionid' => '3',
                    'typeid' => 'D',
                    'amount' => '400.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => ''
                ],
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1100.00',
                    'reserved' => '0.00',
                    'available' => '1100.00',
                    'price' => '1.00',
                    'transactionid' => '4',
                    'typeid' => 'W',
                    'amount' => '300.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => ''
                ],
            ],
            array_map(
                function ($value) {
                    $value = $value->toArray();
                    unset($value["date"]);
                    unset($value["uuid"]);
                    return $value;
                },
                $transactionList
            )
        );

        $transactionList = $this->transactionBLL->getByDate($accountId, '1900/01/01', '1900/02/01');

        $this->assertEquals([], $transactionList);

    }

    public function testGetByTransactionId(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 400));
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionBLL->addFunds(TransactionDTO::create($ignore, 200));

        $accountRepo = $this->accountBLL->getRepository();

        $accountResult = $accountRepo->getByTransactionId($transaction->getTransactionId());;
        $accountExpected = $accountRepo->getById($accountId);

        // Executar teste$this->transactionBLL
        $this->assertEquals($accountExpected, $accountResult);
    }

    public function testGetByTransactionIdNotFound(): void
    {
        $accountRepo = $this->accountBLL->getRepository();
        $accountResult = $accountRepo->getByTransactionId(12345); // Dont exists
        $this->assertNull($accountResult);
    }

    public function testTransactionsByCode(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 400)->setCode('TEST'));
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionBLL->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionBLL->getRepository()->getByCode($accountId, 'TEST');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1400.00',
                    'reserved' => '0.00',
                    'available' => '1400.00',
                    'price' => '1.00',
                    'transactionid' => '3',
                    'typeid' => 'D',
                    'amount' => '400.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => 'TEST'
                ],
            ],
            array_map(
                function ($value) {
                    $value = $value->toArray();
                    unset($value["date"]);
                    unset($value["uuid"]);
                    return $value;
                },
                $transactionList
            )
        );


        $transactionList = $this->transactionBLL->getRepository()->getByCode($accountId, 'NOTFOUND');

        $this->assertEquals([], $transactionList);

    }

    public function testGetTransactionsByReferenceId(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
        $this->transactionBLL->withdrawFunds(TransactionDTO::create($accountId, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionBLL->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionBLL->getRepository()->getByReferenceId($accountId, 'REFSRC', 'REFID2');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1100.00',
                    'reserved' => '0.00',
                    'available' => '1100.00',
                    'price' => '1.00',
                    'transactionid' => '4',
                    'typeid' => 'W',
                    'amount' => '300.00',
                    'description' => '',
                    'referenceid' => 'REFID2',
                    'referencesource' => 'REFSRC',
                    'transactionparentid' => '',
                    'code' => ''
                ],
            ],
            array_map(
                function ($value) {
                    $value = $value->toArray();
                    unset($value["date"]);
                    unset($value["uuid"]);
                    return $value;
                },
                $transactionList
            )
        );
    }

    public function testTransferFunds(): void
    {
        $accountBrlId = $this->accountBLL->getByAccountTypeId('BRLTEST')[0]->getAccountId();
        $accountUsdId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        [$transactionSource, $transactionTarget] = $this->accountBLL->transferFunds($accountBrlId, $accountUsdId, 300);

        $accountSource = $this->accountBLL->getById($transactionSource->getAccountId());
        $accountTarget = $this->accountBLL->getById($transactionTarget->getAccountId());

        $this->assertEquals(700, $accountSource->getAvailable());
        $this->assertEquals(1300, $accountTarget->getAvailable());
    }

    public function testTransferFundsFail(): void
    {
        $accountBrlId = $this->accountBLL->getByAccountTypeId('BRLTEST')[0]->getAccountId();
        $accountUsdId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        $this->accountBLL->transferFunds($accountBrlId, $accountUsdId, 1100);
    }

    public function testJoinTransactionAndCommit(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionBLL->withdrawFunds(
            TransactionDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->commitTransaction();

        $transaction = $this->transactionBLL->getById($transaction->getTransactionId());
        $this->assertNotNull($transaction);
    }

    public function testJoinTransactionAndRollback(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionBLL->withdrawFunds(
            TransactionDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->rollbackTransaction();

        $transaction = $this->transactionBLL->getById($transaction->getTransactionId());
        $this->assertNull($transaction);
    }

    public function testJoinTransactionDifferentIsolationLevel(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);

        $this->expectException(TransactionStartedException::class);
        $this->expectExceptionMessage('You cannot join a transaction with a different isolation level');

        try {
            $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
            $this->transactionBLL->withdrawFunds(
                TransactionDTO::create($accountId, 10)
                    ->setDescription('Test')
                    ->setReferenceId('Referencia')
                    ->setReferenceSource('Source')
                    ->setCode('XYZ')
            );
        } finally {
            $this->dbExecutor->rollbackTransaction();
        }

    }

    public function testAddFundsExtendedTransaction(): void
    {
        $this->prepareObjects(accountEntity: AccountEntity::class, accountTypeEntity: AccountTypeEntity::class, transactionEntity: TransactionExtended::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($accountId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds')
            ->setProperty('extraProperty', 'Extra');
        $actual = $this->transactionBLL->addFunds($dto);

        // Check
        $transaction = new TransactionExtended();
        $transaction->setAmount('250.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance('1250.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable('1250.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Add Funds');
        $transaction->setReferenceSource('Source Add Funds');
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setExtraProperty('Extra');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($transaction, $actual);
    }

    public function testAddFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionBLL->addFunds(TransactionDTO::create(1023, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
    }

    public function testWithdrawFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionBLL->withdrawFunds(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveWithdrawFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionBLL->reserveFundsForWithdraw(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveDepositFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionBLL->reserveFundsForDeposit(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testTransactionObserver(): void
    {
        $accountRepository = new AccountRepositoryExtended($this->dbExecutor, AccountEntity::class);
        $transactionRepository = new TransactionRepositoryExtended($this->dbExecutor, TransactionEntity::class);

        // Recreate BLL instances with the extended repositories that have observers
        $accountTypeBLL = new AccountTypeBLL($this->accountTypeBLL->getRepository());
        $this->transactionBLL = new TransactionBLL($transactionRepository, $accountRepository);
        $accountBLL = new AccountBLL($accountRepository, $accountTypeBLL, $this->transactionBLL);

        // Sanity Check
        $this->assertFalse($accountRepository->getReach());
        $this->assertFalse($transactionRepository->getReach());

        $accountId = $accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(
            TransactionDTO::create($accountId, 250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        // I don´t need to test the values, because it is tested before.
        // I just need to check if the observer was called.
        // And inside the observer, I will check the values.
        $this->assertTrue($accountRepository->getReach());
        $this->assertTrue($transactionRepository->getReach());
    }

    public function testCapAtZeroFalse(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->withdrawFunds(
            TransactionDTO::create($accountId, 1250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds'),
            capAtZero: false
        );
    }

    public function testCapAtZeroTrue(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($accountId, 1100)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(0, $account->getBalance());
        $this->assertEquals(0, $account->getReserved());
        $this->assertEquals(0, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionBLL->getById($transaction->getTransactionId());
        $this->assertEquals(1000, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(1000, $dto->getAmount());;
    }

    public function testCapAtZeroTruePartial(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($accountId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(200, $account->getBalance());
        $this->assertEquals(0, $account->getReserved());
        $this->assertEquals(200, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionBLL->getById($transaction->getTransactionId());
        $this->assertEquals(800, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(800, $dto->getAmount());;
    }

    public function testCapAtZeroTrueReserved(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionBLL->reserveFundsForWithdraw(
            TransactionDTO::create($accountId, 250)
                ->setDescription('Test Reserve Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        $dto = TransactionDTO::create($accountId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $withdraw = $this->transactionBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(250, $account->getBalance());
        $this->assertEquals(250, $account->getReserved());
        $this->assertEquals(0, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionBLL->getById($withdraw->getTransactionId());
        $this->assertEquals(750, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(750, $dto->getAmount());;
    }
}