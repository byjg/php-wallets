<?php

namespace Tests\Database;

use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Entity\WalletEntity;
use ByJG\AccountTransactions\Entity\WalletTypeEntity;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\WalletException;
use ByJG\AccountTransactions\Exception\WalletTypeException;
use ByJG\AccountTransactions\Service\TransactionService;
use ByJG\AccountTransactions\Service\WalletService;
use ByJG\AccountTransactions\Service\WalletTypeService;
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
use Tests\Classes\TransactionExtended;
use Tests\Classes\TransactionRepositoryExtended;
use Tests\Classes\WalletRepositoryExtended;


class WalletTransactionsTest extends TestCase
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
        $walletTypeRepo = $this->accountTypeService->getRepository();
        $list = $walletTypeRepo->getAll(null, null, null, [["wallettypeid like '___TEST'", []]]);

        $this->assertEquals(4, count($list));

        $this->assertEquals(
            [
                [
                    'wallettypeid' => 'ABCTEST',
                    'name' => 'Test 3'
                ],
                [
                    'wallettypeid' => 'BRLTEST',
                    'name' => 'Test 2'
                ],
                [
                    'wallettypeid' => 'NEGTEST',
                    'name' => 'Test 4'
                ],
                [
                    'wallettypeid' => 'USDTEST',
                    'name' => 'Test 1'
                ],
            ],
            Serialize::from($list)->toArray()
        );

        $dto = $this->accountTypeService->getById('USDTEST');
        $this->assertEquals('Test 1', $dto->getName());
        $this->assertEquals('USDTEST', $dto->getWalletTypeId());
    }

    public function testGetById(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('10.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance('990.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('990.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia');
        $transaction->setReferenceSource('Source');
        $transaction->setCode('XYZ');
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testGetById_Zero(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 0);
        $dto = TransactionDTO::create($walletId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionService->addFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('10.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance('10.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable('10.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia');
        $transaction->setReferenceSource('Source');
        $transaction->setCode('XYZ');
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testGetById_NotFound(): void
    {
        // Executar teste
        $this->assertEquals($this->transactionService->getById(2), null);
    }

    public function testGetAll(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transactionResult = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
        );
        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 50)
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
        $transaction[0]->setWalletId($walletId);
        $transaction[0]->setTransactionId(2);
        $transaction[0]->setTypeId('D');
        $transaction[0]->setAvailable('1000.00');
        $transaction[0]->setPrice('1.00');
        $transaction[0]->setReserved('0.00');
        $transaction[0]->setReferenceId('');
        $transaction[0]->setReferenceSource('');
        $transaction[0]->setWalletTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[1]->setAmount('10.00');
        $transaction[1]->setDate('2015-01-24');
        $transaction[1]->setDescription('Test');
        $transaction[1]->setBalance('990.00');
        $transaction[1]->setWalletId($walletId);
        $transaction[1]->setTransactionId($transactionResult->getTransactionId());
        $transaction[1]->setTypeId('W');
        $transaction[1]->setAvailable('990.00');
        $transaction[1]->setPrice('1.00');
        $transaction[1]->setReserved('0.00');
        $transaction[1]->setReferenceId('Referencia');
        $transaction[1]->setReferenceSource('Source');
        $transaction[1]->setWalletTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[2]->setAmount('50.00');
        $transaction[2]->setDate('2015-01-24');
        $transaction[2]->setDescription('Test');
        $transaction[2]->setBalance('940.00');
        $transaction[2]->setWalletId($walletId);
        $transaction[2]->setTransactionId(4);
        $transaction[2]->setTypeId('W');
        $transaction[2]->setAvailable('940.00');
        $transaction[2]->setPrice('1.00');
        $transaction[2]->setReserved('0.00');
        $transaction[2]->setReferenceId('Referencia');
        $transaction[2]->setReferenceSource('Source');
        $transaction[2]->setWalletTypeId('USDTEST');

        $listAll = $this->transactionService->getRepository()->getAll(null, null, null, [["wallettypeid = :id", ["id" => 'USDTEST']]]);

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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $actual = $this->transactionService->addFunds($dto);

        // Check
        $transaction = new TransactionEntity;
        $transaction->setAmount('250.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance('1250.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('D');
        $transaction->setAvailable('1250.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Add Funds');
        $transaction->setReferenceSource('Source Add Funds');
        $transaction->setWalletTypeId('USDTEST');
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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        // Check;
        $this->transactionService->addFunds(TransactionDTO::create($walletId, -15));
    }

    public function testWithdrawFunds(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('350.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance('650.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('650.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setWalletTypeId('USDTEST');
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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, -15));
    }

    public function testWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $dto = TransactionDTO::create($walletId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('1150.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance('-150.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable('-150.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setWalletTypeId('NEGTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testWithdrawFunds_Allow_Negative2(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('NEGTEST', "___TESTUSER-1", 1000, 1, -400);
        $transaction = $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 1400)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertEquals(-400, $transaction->getAvailable());
        $this->assertEquals(1400, $transaction->getAmount());
    }


    public function testWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000, 1, -400);
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 1401)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
    }

    /**
     * @return void
     * @throws WalletException
     * @throws WalletTypeException
     * @throws AmountException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function testGetAccountByUserId()
    {
        $walletId = $this->accountService->createWallet(
            'USDTEST',
            "___TESTUSER-10",
            1000,
            1,
            0,
            'Extra Information'
        );

        $wallet = $this->accountService->getByUserId("___TESTUSER-10");
        $wallet[0]->setEntryDate(null);

        $walletEntity = $this->accountService->getRepository()->getMapper()->getEntity([
            "walletid" => $walletId,
            "wallettypeid" => "USDTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00",
            "last_uuid" => $wallet[0]->getLastUuid(),
        ]);

        $this->assertNotNull($wallet[0]->getLastUuid());

        $this->assertEquals([
            $walletEntity
        ], $wallet);
    }

    /**
     * @throws AmountException
     * @throws WalletException
     * @throws WalletTypeException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws TransactionException
     * @throws \ByJG\Serializer\Exception\InvalidArgumentException
     */
    public function testGetAccountByAccountType(): void
    {
        $walletId = $this->accountService->createWallet(
            'ABCTEST',
            "___TESTUSER-10",
            1000,
            1,
            0,
            'Extra Information'
        );

        $wallet = $this->accountService->getByWalletTypeId('ABCTEST');
        $wallet[0]->setEntryDate(null);

        $walletEntity = $this->accountService->getRepository()->getMapper()->getEntity([
            "walletid" => $walletId,
            "wallettypeid" => "ABCTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            "price" => 1,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => "0.00",
            "last_uuid" => $wallet[0]->getLastUuid(),
        ]);

        $this->assertNotNull($wallet[0]->getLastUuid());

        $this->assertEquals([
            $walletEntity
        ], $wallet);
    }

    public function testOverrideFunds(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $transactionId = $this->accountService->overrideBalance($walletId, 650);
        $wallet = $this->accountService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        $transaction = $this->transactionService->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance' => '650.00',
            'reserved' => '0.00',
            'available' => '650.00',
            'price' => '1.00',
            'extra' => '',
            'minvalue' => '0.00',
            "lastUuid" => $transaction["uuid"],
        ],
            $wallet
        );

        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $transactionPartial = $this->accountService->partialBalance($walletId, 650);
        $wallet = $this->accountService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        // Executar teste
        $this->assertEquals(
            [
                'walletid' => $walletId,
                'wallettypeid' => 'USDTEST',
                'userid' => "___TESTUSER-1",
                'balance' => '650.00',
                'reserved' => '0.00',
                'available' => '650.00',
                'price' => '1.00',
                'extra' => '',
                'minvalue' => '0.00',
                "lastUuid" => $transactionPartial->getUuid(),
            ],
            $wallet
        );

        $transaction = Serialize::from($transactionPartial)->toArray();
        unset($transaction["date"]);
        unset($transaction["uuid"]);

        $this->assertEquals(
            [
                'walletid' => $walletId,
                'wallettypeid' => 'USDTEST',
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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 200));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $transactionId = $this->accountService->closeWallet($walletId);

        $wallet = $this->accountService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        $transaction = $this->transactionService->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance' => '0.00',
            'reserved' => '0.00',
            'available' => '0.00',
            'price' => '0.00',
            'extra' => '',
            'minvalue' => '0.00',
            "lastUuid" => $transaction["uuid"],
        ],
            $wallet
        );

        $this->assertEquals(
            [
                'walletid' => $walletId,
                'wallettypeid' => 'USDTEST',
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
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->accountService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $startDate = date('Y') . "/" . date('m') . "/01";
        $endDate = (intval(date('Y')) + (date('m') == 12 ? 1 : 0)) . "/" . (date('m') == 12 ? 1 : intval(date('m')) + 1) . "/01";

        $transactionList = $this->transactionService->getByDate($walletId, $startDate, $endDate);

        // Executar teste
        $this->assertEquals(
            [
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
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
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
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
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
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

        $transactionList = $this->transactionService->getByDate($walletId, '1900/01/01', '1900/02/01');

        $this->assertEquals([], $transactionList);

    }

    public function testGetByTransactionId(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->accountService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $walletRepo = $this->accountService->getRepository();

        $walletResult = $walletRepo->getByTransactionId($transaction->getTransactionId());;
        $walletExpected = $walletRepo->getById($walletId);

        // Executar teste$this->transactionService
        $this->assertEquals($walletExpected, $walletResult);
    }

    public function testGetByTransactionIdNotFound(): void
    {
        $walletRepo = $this->accountService->getRepository();
        $walletResult = $walletRepo->getByTransactionId(12345); // Dont exists
        $this->assertNull($walletResult);
    }

    public function testTransactionsByCode(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400)->setCode('TEST'));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->accountService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionService->getRepository()->getByCode($walletId, 'TEST');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
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


        $transactionList = $this->transactionService->getRepository()->getByCode($walletId, 'NOTFOUND');

        $this->assertEquals([], $transactionList);

    }

    public function testGetTransactionsByReferenceId(): void
    {
        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));

        $ignore = $this->accountService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this account
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionService->getRepository()->getByReferenceId($walletId, 'REFSRC', 'REFID2');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
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
        $walletBrlId = $this->accountService->getByWalletTypeId('BRLTEST')[0]->getWalletId();
        $walletUsdId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        [$transactionSource, $transactionTarget] = $this->accountService->transferFunds($walletBrlId, $walletUsdId, 300);

        $walletSource = $this->accountService->getById($transactionSource->getWalletId());
        $walletTarget = $this->accountService->getById($transactionTarget->getWalletId());

        $this->assertEquals(700, $walletSource->getAvailable());
        $this->assertEquals(1300, $walletTarget->getAvailable());
    }

    public function testTransferFundsFail(): void
    {
        $walletBrlId = $this->accountService->getByWalletTypeId('BRLTEST')[0]->getWalletId();
        $walletUsdId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        $this->accountService->transferFunds($walletBrlId, $walletUsdId, 1100);
    }

    public function testJoinTransactionAndCommit(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->commitTransaction();

        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertNotNull($transaction);
    }

    public function testJoinTransactionAndRollback(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 10)
                ->setDescription('Test')
                ->setReferenceId('Referencia')
                ->setReferenceSource('Source')
                ->setCode('XYZ')
        );

        // Needs to commit inside the context
        $this->dbExecutor->rollbackTransaction();

        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertNull($transaction);
    }

    public function testJoinTransactionDifferentIsolationLevel(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::READ_UNCOMMITTED);

        $this->expectException(TransactionStartedException::class);
        $this->expectExceptionMessage('You cannot join a transaction with a different isolation level');

        try {
            $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
            $this->transactionService->withdrawFunds(
                TransactionDTO::create($walletId, 10)
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
        $this->prepareObjects(walletEntity: WalletEntity::class, walletTypeEntity: WalletTypeEntity::class, transactionEntity: TransactionExtended::class);

        // Populate Data!
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds')
            ->setProperty('extraProperty', 'Extra');
        $actual = $this->transactionService->addFunds($dto);

        // Check
        $transaction = new TransactionExtended();
        $transaction->setAmount('250.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance('1250.00');
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable('1250.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Add Funds');
        $transaction->setReferenceSource('Source Add Funds');
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setExtraProperty('Extra');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($transaction, $actual);
    }

    public function testAddFundAccountNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionService->addFunds(TransactionDTO::create(1023, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
    }

    public function testWithdrawFundAccountNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionService->withdrawFunds(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveWithdrawFundAccountNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveDepositFundAccountNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Account not found');
        $this->transactionService->reserveFundsForDeposit(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testTransactionObserver(): void
    {
        $walletRepository = new WalletRepositoryExtended($this->dbExecutor, WalletEntity::class);
        $transactionRepository = new TransactionRepositoryExtended($this->dbExecutor, TransactionEntity::class);

        // Recreate Service instances with the extended repositories that have observers
        $walletTypeService = new WalletTypeService($this->accountTypeService->getRepository());
        $this->transactionService = new TransactionService($transactionRepository, $walletRepository);
        $walletService = new WalletService($walletRepository, $walletTypeService, $this->transactionService);

        // Sanity Check
        $this->assertFalse($walletRepository->getReach());
        $this->assertFalse($transactionRepository->getReach());

        $walletId = $walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        // I don´t need to test the values, because it is tested before.
        // I just need to check if the observer was called.
        // And inside the observer, I will check the values.
        $this->assertTrue($walletRepository->getReach());
        $this->assertTrue($transactionRepository->getReach());
    }

    public function testCapAtZeroFalse(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the account balance');

        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 1250)
                ->setDescription('Test Add Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds'),
            capAtZero: false
        );
    }

    public function testCapAtZeroTrue(): void
    {
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($walletId, 1100)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionService->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $wallet = $this->accountService->getById($walletId);
        $this->assertEquals(0, $wallet->getBalance());
        $this->assertEquals(0, $wallet->getReserved());
        $this->assertEquals(0, $wallet->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertEquals(1000, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(1000, $dto->getAmount());;
    }

    public function testCapAtZeroTruePartial(): void
    {
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($walletId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionService->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $wallet = $this->accountService->getById($walletId);
        $this->assertEquals(200, $wallet->getBalance());
        $this->assertEquals(0, $wallet->getReserved());
        $this->assertEquals(200, $wallet->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertEquals(800, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(800, $dto->getAmount());;
    }

    public function testCapAtZeroTrueReserved(): void
    {
        $walletId = $this->accountService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 250)
                ->setDescription('Test Reserve Funds')
                ->setReferenceId('Referencia Add Funds')
                ->setReferenceSource('Source Add Funds')
        );

        $dto = TransactionDTO::create($walletId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $withdraw = $this->transactionService->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $wallet = $this->accountService->getById($walletId);
        $this->assertEquals(250, $wallet->getBalance());
        $this->assertEquals(250, $wallet->getReserved());
        $this->assertEquals(0, $wallet->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionService->getById($withdraw->getTransactionId());
        $this->assertEquals(750, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(750, $dto->getAmount());;
    }
}