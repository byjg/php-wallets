<?php

namespace Tests\Database;

use ByJG\AccountStatements\Bll\AccountBLL;
use ByJG\AccountStatements\Bll\AccountTypeBLL;
use ByJG\AccountStatements\Bll\StatementBLL;
use ByJG\AccountStatements\DTO\StatementDTO;
use ByJG\AccountStatements\Entity\AccountEntity;
use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AccountStatements\Exception\AccountException;
use ByJG\AccountStatements\Exception\AccountTypeException;
use ByJG\AccountStatements\Exception\AmountException;
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
use Tests\Classes\StatementExtended;
use Tests\Classes\StatementRepositoryExtended;


class AccountStatementsTest extends TestCase
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
        $dto = StatementDTO::create($accountId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->statementBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('10.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test');
        $statement->setBalance('990.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('W');
        $statement->setAvailable('990.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia');
        $statement->setReferenceSource('Source');
        $statement->setCode('XYZ');
        $statement->setAccountTypeId('USDTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testGetById_Zero(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 0);
        $dto = StatementDTO::create($accountId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->statementBLL->addFunds($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('10.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test');
        $statement->setBalance('10.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());;
        $statement->setTypeId('D');
        $statement->setAvailable('10.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia');
        $statement->setReferenceSource('Source');
        $statement->setCode('XYZ');
        $statement->setAccountTypeId('USDTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testGetById_NotFound(): void
    {
        // Executar teste
        $this->assertEquals($this->statementBLL->getById(2), null);
    }

    public function testGetAll(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementResult = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
        );
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 50)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
        );

        $statement = [];

        // Objetos que são esperados
        $statement[] = new StatementEntity;
        $statement[0]->setAmount('1000.00');
        $statement[0]->setDate('2015-01-24');
        $statement[0]->setDescription('Opening Balance');
        $statement[0]->setCode('BAL');
        $statement[0]->setBalance('1000.00');
        $statement[0]->setAccountId($accountId);
        $statement[0]->setStatementId(2);
        $statement[0]->setTypeId('D');
        $statement[0]->setAvailable('1000.00');
        $statement[0]->setPrice('1.00');
        $statement[0]->setReserved('0.00');
        $statement[0]->setReferenceId('');
        $statement[0]->setReferenceSource('');
        $statement[0]->setAccountTypeId('USDTEST');

        $statement[] = new StatementEntity;
        $statement[1]->setAmount('10.00');
        $statement[1]->setDate('2015-01-24');
        $statement[1]->setDescription('Test');
        $statement[1]->setBalance('990.00');
        $statement[1]->setAccountId($accountId);
        $statement[1]->setStatementId($statementResult->getStatementId());
        $statement[1]->setTypeId('W');
        $statement[1]->setAvailable('990.00');
        $statement[1]->setPrice('1.00');
        $statement[1]->setReserved('0.00');
        $statement[1]->setReferenceId('Referencia');
        $statement[1]->setReferenceSource('Source');
        $statement[1]->setAccountTypeId('USDTEST');

        $statement[] = new StatementEntity;
        $statement[2]->setAmount('50.00');
        $statement[2]->setDate('2015-01-24');
        $statement[2]->setDescription('Test');
        $statement[2]->setBalance('940.00');
        $statement[2]->setAccountId($accountId);
        $statement[2]->setStatementId(4);
        $statement[2]->setTypeId('W');
        $statement[2]->setAvailable('940.00');
        $statement[2]->setPrice('1.00');
        $statement[2]->setReserved('0.00');
        $statement[2]->setReferenceId('Referencia');
        $statement[2]->setReferenceSource('Source');
        $statement[2]->setAccountTypeId('USDTEST');

        $listAll = $this->statementBLL->getRepository()->getAll(null, null, null, [["accounttypeid = :id", ["id" => 'USDTEST']]]);

        /** @psalm-suppress InvalidArrayOffset */
        for ($i = 0; $i < count($statement); $i++) {
            $statement[$i]->setDate(null);
            $statement[$i]->setStatementId(null);
            $statement[$i]->setUuid(null);
            $listAll[$i]->setDate(null);
            $listAll[$i]->setStatementId(null);
            $listAll[$i]->setUuid(null);
        }

        // Testar método
        $this->assertEquals(
            $statement,
            $listAll
        );
    }

    public function testAddFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = StatementDTO::create($accountId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $actual = $this->statementBLL->addFunds($dto);

        // Check
        $statement = new StatementEntity;
        $statement->setAmount('250.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Add Funds');
        $statement->setBalance('1250.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('D');
        $statement->setAvailable('1250.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Add Funds');
        $statement->setReferenceSource('Source Add Funds');
        $statement->setAccountTypeId('USDTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($statement->toArray(), $actual->toArray());
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
        $this->statementBLL->addFunds(StatementDTO::create($accountId, -15));
    }

    public function testWithdrawFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = StatementDTO::create($accountId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->statementBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('650.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('W');
        $statement->setAvailable('650.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('USDTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, -15));
    }

    public function testWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $dto = StatementDTO::create($accountId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->statementBLL->withdrawFunds($dto);

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('1150.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setBalance('-150.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());
        $statement->setTypeId('W');
        $statement->setAvailable('-150.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('NEGTEST');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Allow_Negative2(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $statement = $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 1400)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        $statement = $this->statementBLL->getById($statement->getStatementId());
        $this->assertEquals(-400, $statement->getAvailable());
        $this->assertEquals(1400, $statement->getAmount());
    }


    public function testWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000, 1, -400);
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 1401)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
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
            "laststatementid" => 2,
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

        $statementId = $this->accountBLL->overrideBalance($accountId, 650);
        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        $statement = $this->statementBLL->getById($statementId)->toArray();
        unset($statement["date"]);

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
            "lastUuid" => $statement["uuid"],
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
            'statementid' => $statementId,
            'typeid' => 'B',
            'amount' => '650.00',
            'description' => 'Reset Balance',
            'statementparentid' => '',
            'code' => 'BAL',
            'referenceid' => '',
            'referencesource' => '',
            'uuid' => $statement["uuid"],
        ],
            $statement
        );
    }

    public function testPartialFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $statementPartial = $this->accountBLL->partialBalance($accountId, 650);
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
                "lastUuid" => $statementPartial->getUuid(),
            ],
            $account
        );

        $statement = Serialize::from($statementPartial)->toArray();
        unset($statement["date"]);
        unset($statement["uuid"]);

        $this->assertEquals(
            [
                'accountid' => $accountId,
                'accounttypeid' => 'USDTEST',
                'balance' => '650.00',
                'reserved' => '0.00',
                'available' => '650.00',
                'price' => '1.00',
                'statementid' => $statementPartial->getStatementId(),
                'typeid' => 'W',
                'amount' => '350.00',
                'description' => 'Partial Balance',
                'statementparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => ''
            ],
            $statement
        );

    }

    public function testCloseAccount(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->addFunds(StatementDTO::create($accountId, 400));
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 200));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $statementId = $this->accountBLL->closeAccount($accountId);

        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        $statement = $this->statementBLL->getById($statementId)->toArray();
        unset($statement["date"]);

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
            "lastUuid" => $statement["uuid"],
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
                'statementid' => $statementId,
                'typeid' => 'B',
                'amount' => '0.00',
                'description' => 'Reset Balance',
                'statementparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => 'BAL',
                "uuid" => $statement["uuid"],
            ],
            $statement
        );

    }

    public function testGetByDate(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 400));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->statementBLL->addFunds(StatementDTO::create($ignore, 200));

        $startDate = date('Y') . "/" . date('m') . "/01";
        $endDate = (intval(date('Y')) + (date('m') == 12 ? 1 : 0)) . "/" . (date('m') == 12 ? 1 : intval(date('m')) + 1) . "/01";

        $statementList = $this->statementBLL->getByDate($accountId, $startDate, $endDate);

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
                    'statementid' => '2',
                    'typeid' => 'D',
                    'amount' => '1000.00',
                    'description' => 'Opening Balance',
                    'referenceid' => '',
                    'referencesource' => '',
                    'statementparentid' => '',
                    'code' => 'BAL'
                ],
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1400.00',
                    'reserved' => '0.00',
                    'available' => '1400.00',
                    'price' => '1.00',
                    'statementid' => '3',
                    'typeid' => 'D',
                    'amount' => '400.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'statementparentid' => '',
                    'code' => ''
                ],
                [
                    'accountid' => $accountId,
                    'accounttypeid' => 'USDTEST',
                    'balance' => '1100.00',
                    'reserved' => '0.00',
                    'available' => '1100.00',
                    'price' => '1.00',
                    'statementid' => '4',
                    'typeid' => 'W',
                    'amount' => '300.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'statementparentid' => '',
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
                $statementList
            )
        );

        $statementList = $this->statementBLL->getByDate($accountId, '1900/01/01', '1900/02/01');

        $this->assertEquals([], $statementList);

    }

    public function testGetByStatementId(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->addFunds(StatementDTO::create($accountId, 400));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->statementBLL->addFunds(StatementDTO::create($ignore, 200));

        $accountRepo = $this->accountBLL->getRepository();

        $accountResult = $accountRepo->getByStatementId($statement->getStatementId());;
        $accountExpected = $accountRepo->getById($accountId);

        // Executar testestatementBLL
        $this->assertEquals($accountExpected, $accountResult);
    }

    public function testGetByStatementIdNotFound(): void
    {
        $accountRepo = $this->accountBLL->getRepository();
        $accountResult = $accountRepo->getByStatementId(12345); // Dont exists
        $this->assertNull($accountResult);
    }

    public function testGetStatementsByCode(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 400)->setCode('TEST'));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->statementBLL->addFunds(StatementDTO::create($ignore, 200));

        $statementList = $this->statementBLL->getRepository()->getByCode($accountId, 'TEST');

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
                    'statementid' => '3',
                    'typeid' => 'D',
                    'amount' => '400.00',
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'statementparentid' => '',
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
                $statementList
            )
        );


        $statementList = $this->statementBLL->getRepository()->getByCode($accountId, 'NOTFOUND');

        $this->assertEquals([], $statementList);

    }

    public function testGetStatementsByReferenceId(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->statementBLL->addFunds(StatementDTO::create($ignore, 200));

        $statementList = $this->statementBLL->getRepository()->getByReferenceId($accountId, 'REFSRC', 'REFID2');

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
                    'statementid' => '4',
                    'typeid' => 'W',
                    'amount' => '300.00',
                    'description' => '',
                    'referenceid' => 'REFID2',
                    'referencesource' => 'REFSRC',
                    'statementparentid' => '',
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
                $statementList
            )
        );
    }

    public function testTransferFunds(): void
    {
        $accountBrlId = $this->accountBLL->getByAccountTypeId('BRLTEST')[0]->getAccountId();
        $accountUsdId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        [$statementSource, $statementTarget] = $this->accountBLL->transferFunds($accountBrlId, $accountUsdId, 300);

        $accountSource = $this->accountBLL->getById($statementSource->getAccountId());
        $accountTarget = $this->accountBLL->getById($statementTarget->getAccountId());

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
        // This transaction starts outside the Statement Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->commitTransaction();

        $statement = $this->statementBLL->getById($statement->getStatementId());
        $this->assertNotNull($statement);
    }

    public function testJoinTransactionAndRollback(): void
    {
        // This transaction starts outside the Statement Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statement = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->rollbackTransaction();

        $statement = $this->statementBLL->getById($statement->getStatementId());
        $this->assertNull($statement);
    }

    public function testJoinTransactionDifferentIsolationLevel(): void
    {
        // This transaction starts outside the Statement Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);

        $this->expectException(TransactionStartedException::class);
        $this->expectExceptionMessage('You cannot join a transaction with a different isolation level');

        try {
            $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
            $this->statementBLL->withdrawFunds(
                StatementDTO::create($accountId, 10)
                    ->setDescription('Test')
                    ->setReferenceId('Referencia')
                    ->setReferenceSource('Source')
                    ->setCode('XYZ')
            );
        } finally {
            $this->dbExecutor->rollbackTransaction();
        }

    }

    public function testAddFundsExtendedStatement(): void
    {
        $this->prepareObjects(statementEntity: StatementExtended::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = StatementDTO::create($accountId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds')
            ->setProperty('extraProperty', 'Extra');
        $actual = $this->statementBLL->addFunds($dto);

        // Check
        $statement = new StatementExtended();
        $statement->setAmount('250.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Add Funds');
        $statement->setBalance('1250.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($actual->getStatementId());;
        $statement->setTypeId('D');
        $statement->setAvailable('1250.00');
        $statement->setPrice('1.00');
        $statement->setReserved('0.00');
        $statement->setReferenceId('Referencia Add Funds');
        $statement->setReferenceSource('Source Add Funds');
        $statement->setAccountTypeId('USDTEST');
        $statement->setExtraProperty('Extra');
        $statement->setDate($actual->getDate());
        $statement->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($statement, $actual);
    }

    public function testAddFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->addFunds(StatementDTO::create(1023, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
    }

    public function testWithdrawFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->withdrawFunds(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveWithdrawFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->reserveFundsForWithdraw(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveDepositFundAccountNotFound(): void
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->reserveFundsForDeposit(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testStatementObserver(): void
    {
        $accountRepository = new AccountRepositoryExtended($this->dbExecutor, AccountEntity::class);
        $statementRepository = new StatementRepositoryExtended($this->dbExecutor, StatementEntity::class);

        // Recreate BLL instances with the extended repositories that have observers
        $accountTypeBLL = new AccountTypeBLL($this->accountTypeBLL->getRepository());
        $statementBLL = new StatementBLL($statementRepository, $accountRepository);
        $accountBLL = new AccountBLL($accountRepository, $accountTypeBLL, $statementBLL);

        // Sanity Check
        $this->assertFalse($accountRepository->getReach());
        $this->assertFalse($statementRepository->getReach());

        $accountId = $accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementBLL->addFunds(
            StatementDTO::create($accountId, 250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        // I don´t need to test the values, because it is tested before.
        // I just need to check if the observer was called.
        // And inside the observer, I will check the values.
        $this->assertTrue($accountRepository->getReach());
        $this->assertTrue($statementRepository->getReach());
    }

    public function testCapAtZeroFalse(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 1250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds'),
            capAtZero: false
        );
    }

    public function testCapAtZeroTrue(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $dto = StatementDTO::create($accountId, 1100)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $statement = $this->statementBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(0, $account->getBalance());
        $this->assertEquals(0, $account->getReserved());
        $this->assertEquals(0, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $statement = $this->statementBLL->getById($statement->getStatementId());
        $this->assertEquals(1000, $statement->getAmount());

        // The DTO should be the same
        $this->assertEquals(1000, $dto->getAmount());;
    }

    public function testCapAtZeroTruePartial(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $dto = StatementDTO::create($accountId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $statement = $this->statementBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(200, $account->getBalance());
        $this->assertEquals(0, $account->getReserved());
        $this->assertEquals(200, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $statement = $this->statementBLL->getById($statement->getStatementId());
        $this->assertEquals(800, $statement->getAmount());

        // The DTO should be the same
        $this->assertEquals(800, $dto->getAmount());;
    }

    public function testCapAtZeroTrueUncleared(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->reserveFundsForWithdraw(
            StatementDTO::create($accountId, 250)
                ->setDescription('Test Reserve Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        $dto = StatementDTO::create($accountId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $withdraw = $this->statementBLL->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $account = $this->accountBLL->getById($accountId);
        $this->assertEquals(250, $account->getBalance());
        $this->assertEquals(250, $account->getReserved());
        $this->assertEquals(0, $account->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $statement = $this->statementBLL->getById($withdraw->getStatementId());
        $this->assertEquals(750, $statement->getAmount());

        // The DTO should be the same
        $this->assertEquals(750, $dto->getAmount());;
    }
}