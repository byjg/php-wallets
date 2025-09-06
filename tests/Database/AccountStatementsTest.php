<?php

namespace Tests\Database;

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

    public function testGetById()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('10.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test');
        $statement->setGrossBalance('990.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('W');
        $statement->setNetBalance('990.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia');
        $statement->setReferenceSource('Source');
        $statement->setCode('XYZ');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testGetById_Zero()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 0);
        $statementId = $this->statementBLL->addFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('10.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test');
        $statement->setGrossBalance('10.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('D');
        $statement->setNetBalance('10.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia');
        $statement->setReferenceSource('Source');
        $statement->setCode('XYZ');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testGetById_NotFound()
    {
        // Executar teste
        $this->assertEquals($this->statementBLL->getById(2), null);
    }

    public function testGetAll()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
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
        $statement[0]->setGrossBalance('1000.00');
        $statement[0]->setAccountId($accountId);
        $statement[0]->setStatementId(2);
        $statement[0]->setTypeId('D');
        $statement[0]->setNetBalance('1000.00');
        $statement[0]->setPrice('1.00');
        $statement[0]->setUnCleared('0.00');
        $statement[0]->setReferenceId('');
        $statement[0]->setReferenceSource('');
        $statement[0]->setAccountTypeId('USDTEST');

        $statement[] = new StatementEntity;
        $statement[1]->setAmount('10.00');
        $statement[1]->setDate('2015-01-24');
        $statement[1]->setDescription('Test');
        $statement[1]->setGrossBalance('990.00');
        $statement[1]->setAccountId($accountId);
        $statement[1]->setStatementId($statementId);
        $statement[1]->setTypeId('W');
        $statement[1]->setNetBalance('990.00');
        $statement[1]->setPrice('1.00');
        $statement[1]->setUnCleared('0.00');
        $statement[1]->setReferenceId('Referencia');
        $statement[1]->setReferenceSource('Source');
        $statement[1]->setAccountTypeId('USDTEST');

        $statement[] = new StatementEntity;
        $statement[2]->setAmount('50.00');
        $statement[2]->setDate('2015-01-24');
        $statement[2]->setDescription('Test');
        $statement[2]->setGrossBalance('940.00');
        $statement[2]->setAccountId($accountId);
        $statement[2]->setStatementId(4);
        $statement[2]->setTypeId('W');
        $statement[2]->setNetBalance('940.00');
        $statement[2]->setPrice('1.00');
        $statement[2]->setUnCleared('0.00');
        $statement[2]->setReferenceId('Referencia');
        $statement[2]->setReferenceSource('Source');
        $statement[2]->setAccountTypeId('USDTEST');

        $listAll = $this->statementBLL->getRepository()->getAll(null, null, null, [["accounttypeid = :id", ["id" => 'USDTEST']]]);

        /** @psalm-suppress InvalidArrayOffset */
        for ($i = 0; $i < count($statement); $i++) {
            $statement[$i]->setDate(null);
            $statement[$i]->setStatementId(null);
            $listAll[$i]->setDate(null);
            $listAll[$i]->setStatementId(null);
        }

        // Testar método
        $this->assertEquals(
            $statement,
            $listAll
        );
    }

    public function testAddFunds()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->addFunds(
            StatementDTO::create($accountId, 250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        // Check
        $statement = new StatementEntity;
        $statement->setAmount('250.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Add Funds');
        $statement->setGrossBalance('1250.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('D');
        $statement->setNetBalance('1250.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Add Funds');
        $statement->setReferenceSource('Source Add Funds');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function amountDataProvider()
    {

        return [
            [250],
            [250.00],
            [250.01],
            [250.12],
            [250.18],
            [250.19],
            [250.32],
            [250.41],
            [2055.49],
            [10.23456789 - 1.21456789]
        ];
    }

    /**
     * @dataProvider amountDataProvider
     */
    public function testAddFundsDecimal($amount)
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->addFunds(
            StatementDTO::create($accountId, $amount)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        // Check
        $statement = new StatementEntity;
        $statement->setAmount($amount);
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Add Funds');
        $statement->setGrossBalance(1000 + $amount);
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('D');
        $statement->setNetBalance(1000 + $amount);
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Add Funds');
        $statement->setReferenceSource('Source Add Funds');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testAddFunds_Invalid()
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check;
        $this->statementBLL->addFunds(StatementDTO::create($accountId, -15));
    }

    public function testAddFunds_InvalidRound()
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to have two decimal places');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check;
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 0.105));
    }

    public function testWithdrawFunds()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
        );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('350.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('650.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('W');
        $statement->setNetBalance('650.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('USDTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Invalid()
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, -15));
    }

    public function testWithdrawFunds_InvalidRound()
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to have two decimal places');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 10.001));
    }

    public function testWithdrawFunds_Allow_Negative()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 1150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
        );

        // Objeto que é esperado
        $statement = new StatementEntity();
        $statement->setAmount('1150.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Withdraw');
        $statement->setGrossBalance('-150.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('W');
        $statement->setNetBalance('-150.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Withdraw');
        $statement->setReferenceSource('Source Withdraw');
        $statement->setAccountTypeId('NEGTEST');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        // Executar teste
        $this->assertEquals($statement->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Allow_Negative2()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $id = $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 1400)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'), true);

        $statement = $this->statementBLL->getById($id);
        $this->assertEquals(-400, $statement->getNetBalance());
        $this->assertEquals(1400, $statement->getAmount());
    }


    public function testWithdrawFunds_NegativeInvalid()
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
            "grossbalance" => 1000,
            "uncleared" => 0,
            "netbalance" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00"
        ]);

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
    public function testGetAccountByAccountType()
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
            "grossbalance" => 1000,
            "uncleared" => 0,
            "netbalance" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00"
        ]);

        $this->assertEquals([
            $accountEntity
        ], $account);
    }

    public function testOverrideFunds()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $statementId = $this->accountBLL->overrideBalance($accountId, 650);
        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        // Executar teste
        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'grossbalance' => '650.00',
            'uncleared' => '0.00',
            'netbalance' => '650.00',
            'price' => '1.00',
            'extra' => '',
            'minvalue' => '0.00',
        ],
            $account
        );

        $statement = $this->statementBLL->getById($statementId)->toArray();
        unset($statement["date"]);

        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'grossbalance' => '650.00',
            'uncleared' => '0.00',
            'netbalance' => '650.00',
            'price' => '1.00',
            'statementid' => $statementId,
            'typeid' => 'B',
            'amount' => '650.00',
            'description' => 'Reset Balance',
            'statementparentid' => '',
            'code' => 'BAL',
            'referenceid' => '',
            'referencesource' => ''
        ],
            $statement
        );
    }

    public function testPartialFunds()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $statementId = $this->accountBLL->partialBalance($accountId, 650);
        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        // Executar teste
        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'grossbalance' => '650.00',
            'uncleared' => '0.00',
            'netbalance' => '650.00',
            'price' => '1.00',
            'extra' => '',
            'minvalue' => '0.00',
        ],
            $account
        );

        $statement = $this->statementBLL->getById($statementId)->toArray();
        unset($statement["date"]);

        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'grossbalance' => '650.00',
            'uncleared' => '0.00',
            'netbalance' => '650.00',
            'price' => '1.00',
            'statementid' => $statementId,
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

    public function testCloseAccount()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->statementBLL->addFunds(StatementDTO::create($accountId, 400));
        $this->statementBLL->addFunds(StatementDTO::create($accountId, 200));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $statementId = $this->accountBLL->closeAccount($accountId);

        $account = $this->accountBLL->getById($accountId)->toArray();
        unset($account["entrydate"]);

        // Executar teste
        $this->assertEquals([
            'accountid' => $accountId,
            'accounttypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'grossbalance' => '0.00',
            'uncleared' => '0.00',
            'netbalance' => '0.00',
            'price' => '0.00',
            'extra' => '',
            'minvalue' => '0.00',
        ],
            $account
        );

        $statement = $this->statementBLL->getById($statementId)->toArray();
        unset($statement["date"]);

        $this->assertEquals(
            [
                'accountid' => $accountId,
                'accounttypeid' => 'USDTEST',
                'grossbalance' => '0.00',
                'uncleared' => '0.00',
                'netbalance' => '0.00',
                'price' => '0.00',
                'statementid' => $statementId,
                'typeid' => 'B',
                'amount' => '0.00',
                'description' => 'Reset Balance',
                'statementparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => 'BAL'
            ],
            $statement
        );

    }

    public function testGetByDate()
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
                    'grossbalance' => '1000.00',
                    'uncleared' => '0.00',
                    'netbalance' => '1000.00',
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
                    'grossbalance' => '1400.00',
                    'uncleared' => '0.00',
                    'netbalance' => '1400.00',
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
                    'grossbalance' => '1100.00',
                    'uncleared' => '0.00',
                    'netbalance' => '1100.00',
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
                    return $value;
                },
                $statementList
            )
        );

        $statementList = $this->statementBLL->getByDate($accountId, '1900/01/01', '1900/02/01');

        $this->assertEquals([], $statementList);

    }

    public function testGetByStatementId()
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->addFunds(StatementDTO::create($accountId, 400));
        $this->statementBLL->withdrawFunds(StatementDTO::create($accountId, 300));

        $ignore = $this->accountBLL->createAccount('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->statementBLL->addFunds(StatementDTO::create($ignore, 200));

        $accountRepo = $this->accountBLL->getRepository();

        $accountResult = $accountRepo->getByStatementId($statementId);
        $accountExpected = $accountRepo->getById($accountId);

        // Executar testestatementBLL
        $this->assertEquals($accountExpected, $accountResult);
    }

    public function testGetByStatementIdNotFound()
    {
        $accountRepo = $this->accountBLL->getRepository();
        $accountResult = $accountRepo->getByStatementId(12345); // Dont exists
        $this->assertNull($accountResult);
    }

    public function testGetStatementsByCode()
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
                    'grossbalance' => '1400.00',
                    'uncleared' => '0.00',
                    'netbalance' => '1400.00',
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
                    return $value;
                },
                $statementList
            )
        );


        $statementList = $this->statementBLL->getRepository()->getByCode($accountId, 'NOTFOUND');

        $this->assertEquals([], $statementList);

    }

    public function testGetStatementsByReferenceId()
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
                    'grossbalance' => '1100.00',
                    'uncleared' => '0.00',
                    'netbalance' => '1100.00',
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
                    return $value;
                },
                $statementList
            )
        );
    }

    public function testTransferFunds()
    {
        $accountBrlId = $this->accountBLL->getByAccountTypeId('BRLTEST')[0]->getAccountId();
        $accountUsdId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        [$statementSourceId, $statementTargetId] = $this->accountBLL->transferFunds($accountBrlId, $accountUsdId, 300);

        $accountSource = $this->accountBLL->getById($accountBrlId);
        $accountTarget = $this->accountBLL->getById($accountUsdId);

        $this->assertEquals(700, $accountSource->getNetBalance());
        $this->assertEquals(1300, $accountTarget->getNetBalance());
    }

    public function testTransferFundsFail()
    {
        $accountBrlId = $this->accountBLL->getByAccountTypeId('BRLTEST')[0]->getAccountId();
        $accountUsdId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);

        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        [$statementSourceId, $statementTargetId] = $this->accountBLL->transferFunds($accountBrlId, $accountUsdId, 1100);
    }

    public function testJoinTransactionAndCommit()
    {
        // This transaction starts outside the Statement Context
        $this->dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbDriver->commitTransaction();

        $statement = $this->statementBLL->getById($statementId);
        $this->assertNotNull($statement);
    }

    public function testJoinTransactionAndRollback()
    {
        // This transaction starts outside the Statement Context
        $this->dbDriver->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->withdrawFunds(
            StatementDTO::create($accountId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbDriver->rollbackTransaction();

        $statement = $this->statementBLL->getById($statementId);
        $this->assertNull($statement);
    }

    public function testJoinTransactionDifferentIsolationLevel()
    {
        // This transaction starts outside the Statement Context
        $this->dbDriver->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);

        $this->expectException(TransactionStartedException::class);
        $this->expectExceptionMessage('You cannot join a transaction with a different isolation level');

        try {
            $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
            $statementId = $this->statementBLL->withdrawFunds(
                StatementDTO::create($accountId, 10)
                    ->setDescription('Test')
                    ->setReferenceId('Referencia')
                    ->setReferenceSource('Source')
                    ->setCode('XYZ')
            );
        } finally {
            $this->dbDriver->rollbackTransaction();
        }

    }

    public function testAddFundsExtendedStatement()
    {
        $this->prepareObjects(statementEntity: StatementExtended::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->addFunds(
            StatementDTO::create($accountId, 250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
                ->setProperty('extraProperty', 'Extra')
        );

        // Check
        $statement = new StatementExtended();
        $statement->setAmount('250.00');
        $statement->setDate('2015-01-24');
        $statement->setDescription('Test Add Funds');
        $statement->setGrossBalance('1250.00');
        $statement->setAccountId($accountId);
        $statement->setStatementId($statementId);
        $statement->setTypeId('D');
        $statement->setNetBalance('1250.00');
        $statement->setPrice('1.00');
        $statement->setUnCleared('0.00');
        $statement->setReferenceId('Referencia Add Funds');
        $statement->setReferenceSource('Source Add Funds');
        $statement->setAccountTypeId('USDTEST');
        $statement->setExtraProperty('Extra');

        $actual = $this->statementBLL->getById($statementId);
        $statement->setDate($actual->getDate());

        $this->assertEquals($statement, $actual);
    }

    public function testAddFundAccountNotFound()
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->addFunds(StatementDTO::create(1023, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
    }

    public function testWithdrawFundAccountNotFound()
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->withdrawFunds(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveWithdrawFundAccountNotFound()
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->reserveFundsForWithdraw(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveDepositFundAccountNotFound()
    {
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account not found');
        $this->statementBLL->reserveFundsForDeposit(StatementDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testStatementObserver()
    {
        $accountRepository = new AccountRepositoryExtended($this->dbDriver, AccountEntity::class);
        $statementRepository = new StatementRepositoryExtended($this->dbDriver, StatementEntity::class);

        // Sanity Check
        $this->assertFalse($accountRepository->getReach());
        $this->assertFalse($statementRepository->getReach());

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $statementId = $this->statementBLL->addFunds(
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
}