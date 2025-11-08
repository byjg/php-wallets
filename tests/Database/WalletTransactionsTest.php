<?php

namespace Tests\Database;

use ByJG\AnyDataset\Db\Exception\TransactionStartedException;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\TransactionException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Serializer\Serialize;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\Wallets\Entity\WalletTypeEntity;
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\WalletException;
use ByJG\Wallets\Exception\WalletTypeException;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Service\WalletTypeService;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testGetWalletType()
    {
        $walletTypeRepo = $this->walletTypeService->getRepository();
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

        $dto = $this->walletTypeService->getById('USDTEST');
        $this->assertEquals('Test 1', $dto->getName());
        $this->assertEquals('USDTEST', $dto->getWalletTypeId());
    }

    public function testGetById(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(10);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance(990);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable(990);
        $transaction->setScale(2);
        $transaction->setReserved(0);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 0);
        $dto = TransactionDTO::create($walletId, 10)
            ->setDescription('Test')
            ->setReferenceId('Referencia')
            ->setReferenceSource('Source')
            ->setCode('XYZ');
        $actual = $this->transactionService->addFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(10);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test');
        $transaction->setBalance(10);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable(10);
        $transaction->setScale(2);
        $transaction->setReserved(0);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
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
        $transaction[0]->setAmount(1000);
        $transaction[0]->setDate('2015-01-24');
        $transaction[0]->setDescription('Opening Balance');
        $transaction[0]->setCode('BAL');
        $transaction[0]->setBalance(1000);
        $transaction[0]->setWalletId($walletId);
        $transaction[0]->setTransactionId(2);
        $transaction[0]->setTypeId('D');
        $transaction[0]->setAvailable(1000);
        $transaction[0]->setScale(2);
        $transaction[0]->setReserved(0);
        $transaction[0]->setReferenceId('');
        $transaction[0]->setReferenceSource('');
        $transaction[0]->setWalletTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[1]->setAmount(10);
        $transaction[1]->setDate('2015-01-24');
        $transaction[1]->setDescription('Test');
        $transaction[1]->setBalance(990);
        $transaction[1]->setWalletId($walletId);
        $transaction[1]->setTransactionId($transactionResult->getTransactionId());
        $transaction[1]->setTypeId('W');
        $transaction[1]->setAvailable(990);
        $transaction[1]->setScale(2);
        $transaction[1]->setReserved(0);
        $transaction[1]->setReferenceId('Referencia');
        $transaction[1]->setReferenceSource('Source');
        $transaction[1]->setWalletTypeId('USDTEST');

        $transaction[] = new TransactionEntity;
        $transaction[2]->setAmount(50);
        $transaction[2]->setDate('2015-01-24');
        $transaction[2]->setDescription('Test');
        $transaction[2]->setBalance(940);
        $transaction[2]->setWalletId($walletId);
        $transaction[2]->setTransactionId(4);
        $transaction[2]->setTypeId('W');
        $transaction[2]->setAvailable(940);
        $transaction[2]->setScale(2);
        $transaction[2]->setReserved(0);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $actual = $this->transactionService->addFunds($dto);

        // Check
        $transaction = new TransactionEntity;
        $transaction->setAmount(250);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance(1250);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('D');
        $transaction->setAvailable(1250);
        $transaction->setScale(2);
        $transaction->setReserved(0);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        // Check;
        $this->transactionService->addFunds(TransactionDTO::create($walletId, -15));
    }

    public function testWithdrawFunds(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(650);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable(650);
        $transaction->setScale(2);
        $transaction->setReserved(0);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        // Check
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, -15));
    }

    public function testWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('NEGTEST', "___TESTUSER-1", 1000, 2, -400);
        $dto = TransactionDTO::create($walletId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->withdrawFunds($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(1150);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(-150);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable(-150);
        $transaction->setScale(2);
        $transaction->setReserved(0);
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
        $walletId = $this->walletService->createWallet('NEGTEST', "___TESTUSER-1", 1000, 2, -400);
        $transaction = $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 1400)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        $transaction = $this->transactionService->getById($transaction->getTransactionId());
        $this->assertEquals(-400, $transaction->getAvailable());
        $this->assertEquals(1400, $transaction->getAmount());
    }


    public function testWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000, 2, -400);
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
    public function testGetWalletByUserId()
    {
        $walletId = $this->walletService->createWallet(
            'USDTEST',
            "___TESTUSER-10",
            1000,
            2,
            0,
            'Extra Information'
        );

        $wallet = $this->walletService->getByUserId("___TESTUSER-10");
        $wallet[0]->setEntryDate(null);

        $walletEntity = $this->walletService->getRepository()->getMapper()->getEntity([
            "walletid" => $walletId,
            "wallettypeid" => "USDTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            'scale' => 2,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => 0,
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
    public function testGetWalletByWalletType(): void
    {
        $walletId = $this->walletService->createWallet(
            'ABCTEST',
            "___TESTUSER-10",
            1000,
            2,
            0,
            'Extra Information'
        );

        $wallet = $this->walletService->getByWalletTypeId('ABCTEST');
        $wallet[0]->setEntryDate(null);

        $walletEntity = $this->walletService->getRepository()->getMapper()->getEntity([
            "walletid" => $walletId,
            "wallettypeid" => "ABCTEST",
            "userid" => "___TESTUSER-10",
            "balance" => 1000,
            "reserved" => 0,
            "available" => 1000,
            'scale' => 2,
            "extra" => "Extra Information",
            "entrydate" => null,
            "minvalue" => 0,
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $transactionId = $this->walletService->overrideBalance($walletId, 650);
        $wallet = $this->walletService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        $transaction = $this->transactionService->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance'=> 650,
            'reserved'=> 0,
            'available'=> 650,
            'scale' => 2,
            'extra' => null,
            'minvalue'=> 0,
            "lastUuid" => $transaction["uuid"],
        ],
            $wallet
        );

        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
            'balance'=> 650,
            'reserved'=> 0,
            'available'=> 650,
            'scale' => 2,
            'transactionid' => $transactionId,
            'typeid' => 'B',
            'amount'=> 650,
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $transactionPartial = $this->walletService->partialBalance($walletId, 650);
        $wallet = $this->walletService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        // Executar teste
        $this->assertEquals(
            [
                'walletid' => $walletId,
                'wallettypeid' => 'USDTEST',
                'userid' => "___TESTUSER-1",
                'balance'=> 650,
                'reserved'=> 0,
                'available'=> 650,
                'scale' => 2,
                'extra' => null,
                'minvalue'=> 0,
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
                'balance'=> 650,
                'reserved'=> 0,
                'available'=> 650,
                'scale' => 2,
                'transactionid' => $transactionPartial->getTransactionId(),
                'typeid' => 'W',
                'amount'=> 350,
                'description' => 'Partial Balance',
                'transactionparentid' => '',
                'referenceid' => '',
                'referencesource' => '',
                'code' => ''
            ],
            $transaction
        );

    }

    public function testCloseWallet(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 200));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $transactionId = $this->walletService->closeWallet($walletId);

        $wallet = $this->walletService->getById($walletId)->toArray();
        unset($wallet["entrydate"]);

        $transaction = $this->transactionService->getById($transactionId)->toArray();
        unset($transaction["date"]);

        // Executar teste
        $this->assertEquals([
            'walletid' => $walletId,
            'wallettypeid' => 'USDTEST',
            'userid' => "___TESTUSER-1",
            'balance'=> 0,
            'reserved'=> 0,
            'available'=> 0,
            'scale' => 0,
            'extra' => null,
            'minvalue'=> 0,
            "lastUuid" => $transaction["uuid"],
        ],
            $wallet
        );

        $this->assertEquals(
            [
                'walletid' => $walletId,
                'wallettypeid' => 'USDTEST',
                'balance'=> 0,
                'reserved'=> 0,
                'available'=> 0,
                'scale' => 0,
                'transactionid' => $transactionId,
                'typeid' => 'B',
                'amount'=> 0,
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->walletService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this wallet
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
                    'balance'=> 1000,
                    'reserved'=> 0,
                    'available'=> 1000,
                    'scale' => 2,
                    'transactionid' => '2',
                    'typeid' => 'D',
                    'amount'=> 1000,
                    'description' => 'Opening Balance',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => 'BAL'
                ],
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
                    'balance'=> 1400,
                    'reserved'=> 0,
                    'available'=> 1400,
                    'scale' => 2,
                    'transactionid' => '3',
                    'typeid' => 'D',
                    'amount'=> 400,
                    'description' => '',
                    'referenceid' => '',
                    'referencesource' => '',
                    'transactionparentid' => '',
                    'code' => ''
                ],
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
                    'balance'=> 1100,
                    'reserved'=> 0,
                    'available'=> 1100,
                    'scale' => 2,
                    'transactionid' => '4',
                    'typeid' => 'W',
                    'amount'=> 300,
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(TransactionDTO::create($walletId, 400));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->walletService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this wallet
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $walletRepo = $this->walletService->getRepository();

        $walletResult = $walletRepo->getByTransactionId($transaction->getTransactionId());;
        $walletExpected = $walletRepo->getById($walletId);

        // Executar teste$this->transactionService
        $this->assertEquals($walletExpected, $walletResult);
    }

    public function testGetByTransactionIdNotFound(): void
    {
        $walletRepo = $this->walletService->getRepository();
        $walletResult = $walletRepo->getByTransactionId(12345); // Dont exists
        $this->assertNull($walletResult);
    }

    public function testTransactionsByCode(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400)->setCode('TEST'));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $ignore = $this->walletService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this wallet
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionService->getRepository()->getByCode($walletId, 'TEST');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
                    'balance'=> 1400,
                    'reserved'=> 0,
                    'available'=> 1400,
                    'scale' => 2,
                    'transactionid' => '3',
                    'typeid' => 'D',
                    'amount'=> 400,
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));

        $ignore = $this->walletService->createWallet('BRLTEST', "___TESTUSER-999", 1000); // I dont want this wallet
        $this->transactionService->addFunds(TransactionDTO::create($ignore, 200));

        $transactionList = $this->transactionService->getRepository()->getByReferenceId($walletId, 'REFSRC', 'REFID2');

        // Executar teste
        $this->assertEquals(
            [
                [
                    'walletid' => $walletId,
                    'wallettypeid' => 'USDTEST',
                    'balance'=> 1100,
                    'reserved'=> 0,
                    'available'=> 1100,
                    'scale' => 2,
                    'transactionid' => '4',
                    'typeid' => 'W',
                    'amount'=> 300,
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
        $walletBrlId = $this->walletService->getByWalletTypeId('BRLTEST')[0]->getWalletId();
        $walletUsdId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        [$transactionSource, $transactionTarget] = $this->walletService->transferFunds($walletBrlId, $walletUsdId, 300);

        $walletSource = $this->walletService->getById($transactionSource->getWalletId());
        $walletTarget = $this->walletService->getById($transactionTarget->getWalletId());

        $this->assertEquals(700, $walletSource->getAvailable());
        $this->assertEquals(1300, $walletTarget->getAvailable());
    }

    public function testTransferFundsFail(): void
    {
        $walletBrlId = $this->walletService->getByWalletTypeId('BRLTEST')[0]->getWalletId();
        $walletUsdId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Cannot withdraw above the wallet balance');

        $this->walletService->transferFunds($walletBrlId, $walletUsdId, 1100);
    }

    public function testJoinTransactionAndCommit(): void
    {
        // This transaction starts outside the Transaction Context
        $this->dbExecutor->beginTransaction(IsolationLevelEnum::SERIALIZABLE);

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
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

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
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
            $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 250)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds')
            ->setProperty('extraProperty', 'Extra');
        $actual = $this->transactionService->addFunds($dto);

        // Check
        $transaction = new TransactionExtended();
        $transaction->setAmount(250);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Add Funds');
        $transaction->setBalance(1250);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('D');
        $transaction->setAvailable(1250);
        $transaction->setScale(2);
        $transaction->setReserved(0);
        $transaction->setReferenceId('Referencia Add Funds');
        $transaction->setReferenceSource('Source Add Funds');
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setExtraProperty('Extra');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        $this->assertEquals($transaction, $actual);
    }

    public function testAddFundWalletNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Wallet not found');
        $this->transactionService->addFunds(TransactionDTO::create(1023, 400)->setReferenceId('REFID')->setReferenceSource('REFSRC'));
    }

    public function testWithdrawFundWalletNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Wallet not found');
        $this->transactionService->withdrawFunds(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveWithdrawFundWalletNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Wallet not found');
        $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testReserveDepositFundWalletNotFound(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Wallet not found');
        $this->transactionService->reserveFundsForDeposit(TransactionDTO::create(1023, 300)->setReferenceId('REFID2')->setReferenceSource('REFSRC'));
    }

    public function testTransactionObserver(): void
    {
        $walletRepository = new WalletRepositoryExtended($this->dbExecutor, WalletEntity::class);
        $transactionRepository = new TransactionRepositoryExtended($this->dbExecutor, TransactionEntity::class);

        // Recreate Service instances with the extended repositories that have observers
        $walletTypeService = new WalletTypeService($this->walletTypeService->getRepository());
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
        $this->expectExceptionMessage('Cannot withdraw above the wallet balance');

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($walletId, 1100)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionService->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $wallet = $this->walletService->getById($walletId);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $dto = TransactionDTO::create($walletId, 800)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add Funds')
            ->setReferenceSource('Source Add Funds');
        $transaction = $this->transactionService->withdrawFunds(
            $dto,
            capAtZero: true
        );

        // Should be zero, because allow cap at zero
        $wallet = $this->walletService->getById($walletId);
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

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
        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(250, $wallet->getBalance());
        $this->assertEquals(250, $wallet->getReserved());
        $this->assertEquals(0, $wallet->getAvailable());

        // Needs to be adjusted to the new balance - 750
        $transaction = $this->transactionService->getById($withdraw->getTransactionId());
        $this->assertEquals(750, $transaction->getAmount());

        // The DTO should be the same
        $this->assertEquals(750, $dto->getAmount());;
    }

    public function testWalletWithDefaultScale(): void
    {
        // Test that default scale is 2 when not specified
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-SCALE-1", 5000);

        $wallet = $this->walletService->getById($walletId);

        $this->assertEquals(2, $wallet->getScale());
        $this->assertEquals(5000, $wallet->getBalance());
        $this->assertEquals(5000, $wallet->getAvailable());

        // Test float conversion: 5000 with scale=2 = 50.00
        $this->assertEquals(50.0, $wallet->getBalanceFloat());
        $this->assertEquals(50.0, $wallet->getAvailableFloat());
        $this->assertEquals(0.0, $wallet->getReservedFloat());

        // Test using TransactionDTO with setAmountFloat() and setScale()
        $dto = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(25.50, 2) // Should convert to 2550
            ->setDescription('Test setAmountFloat');

        $transaction = $this->transactionService->addFunds($dto);

        $this->assertEquals(2550, $transaction->getAmount());
        $this->assertEquals(25.5, $transaction->getAmountFloat());
        $this->assertEquals(7550, $transaction->getBalance());
        $this->assertEquals(75.5, $transaction->getBalanceFloat());
    }

    public function testWalletWithCustomScale0(): void
    {
        // Test scale=0 (no decimal places, like Japanese Yen)
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-SCALE-0", 1000, 0);

        $wallet = $this->walletService->getById($walletId);

        $this->assertEquals(0, $wallet->getScale());
        $this->assertEquals(1000, $wallet->getBalance());

        // Test float conversion: with scale=0, integers and floats are the same
        $this->assertEquals(1000.0, $wallet->getBalanceFloat());
        $this->assertEquals(1000.0, $wallet->getAvailableFloat());

        // Add funds using setAmountFloat() with scale=0
        $dto = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(500.0, 0) // With scale=0, 500.0 = 500
            ->setDescription('Add funds with scale 0');

        $transaction = $this->transactionService->addFunds($dto);

        $this->assertEquals(0, $transaction->getScale());
        $this->assertEquals(1500, $transaction->getBalance());
        $this->assertEquals(500, $transaction->getAmount());

        // Test transaction float conversion
        $this->assertEquals(500.0, $transaction->getAmountFloat());
        $this->assertEquals(1500.0, $transaction->getBalanceFloat());
        $this->assertEquals(1500.0, $transaction->getAvailableFloat());

        // Test that decimal values get truncated with scale=0
        $dto2 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(123.99, 0) // Should become 123 (truncated)
            ->setDescription('Test truncation');

        $transaction2 = $this->transactionService->addFunds($dto2);
        $this->assertEquals(124, $transaction2->getAmount());
        $this->assertEquals(124.0, $transaction2->getAmountFloat());
    }

    public function testWalletWithCustomScale3(): void
    {
        // Test scale=3 (three decimal places, like cryptocurrencies or fine measurements)
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-SCALE-3", 10000, 3);

        $wallet = $this->walletService->getById($walletId);

        $this->assertEquals(3, $wallet->getScale());
        $this->assertEquals(10000, $wallet->getBalance());

        // Test float conversion: 10000 with scale=3 = 10.000
        $this->assertEquals(10.0, $wallet->getBalanceFloat());
        $this->assertEquals(10.0, $wallet->getAvailableFloat());

        // Withdraw funds using setAmountFloat() with scale=3
        $dto = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(2.567, 3) // With scale=3, 2.567 = 2567
            ->setDescription('Withdraw with scale 3');

        $transaction = $this->transactionService->withdrawFunds($dto);

        $this->assertEquals(3, $transaction->getScale());
        $this->assertEquals(7433, $transaction->getBalance()); // 10000 - 2567
        $this->assertEquals(2567, $transaction->getAmount());

        // Test transaction float conversion: 2567 with scale=3 = 2.567
        $this->assertEquals(2.567, $transaction->getAmountFloat());
        $this->assertEquals(7.433, $transaction->getBalanceFloat());
        $this->assertEquals(7.433, $transaction->getAvailableFloat());

        // Test precision with scale=3
        $dto2 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(1.2345, 3) // Should become 1234 (truncated at 3 decimals = 1.234)
            ->setDescription('Test precision');

        $transaction2 = $this->transactionService->addFunds($dto2);
        $this->assertEquals(1235, $transaction2->getAmount());
        $this->assertEquals(1.235, $transaction2->getAmountFloat());
    }

    public function testWalletScalePreservedInReserveOperations(): void
    {
        // Test that scale is preserved during reserve operations
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-SCALE-RESERVE", 5000, 1);

        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(1, $wallet->getScale());

        // Test float conversion: 5000 with scale=1 = 500.0
        $this->assertEquals(500.0, $wallet->getBalanceFloat());

        // Reserve funds using setAmountFloat()
        $dto = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(100.0, 1) // With scale=1, 100.0 = 1000
            ->setDescription('Reserve with scale 1');

        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw($dto);

        $this->assertEquals(1, $reserveTransaction->getScale());
        $this->assertEquals(5000, $reserveTransaction->getBalance());
        $this->assertEquals(1000, $reserveTransaction->getReserved());
        $this->assertEquals(1000, $reserveTransaction->getAmount());

        // Test float conversion: 1000 with scale=1 = 100.0
        $this->assertEquals(100.0, $reserveTransaction->getReservedFloat());
        $this->assertEquals(500.0, $reserveTransaction->getBalanceFloat());
        $this->assertEquals(400.0, $reserveTransaction->getAvailableFloat()); // 5000-1000 = 4000 with scale=1 = 400.0

        // Accept the reserved funds
        $acceptId = $this->transactionService->acceptFundsById($reserveTransaction->getTransactionId());
        $acceptTransaction = $this->transactionService->getById($acceptId);

        $this->assertEquals(1, $acceptTransaction->getScale());
        $this->assertEquals(4000, $acceptTransaction->getBalance());

        // Test float conversion: 4000 with scale=1 = 400.0
        $this->assertEquals(400.0, $acceptTransaction->getBalanceFloat());
        $this->assertEquals(400.0, $acceptTransaction->getAvailableFloat());
    }

    public function testWalletScaleInTransferOperations(): void
    {
        // Test scale preservation when transferring between wallets with different scales
        $walletScale2 = $this->walletService->createWallet('USDTEST', "___TESTUSER-TRANSFER-2", 10000, 2);
        $walletScale0 = $this->walletService->createWallet('BRLTEST', "___TESTUSER-TRANSFER-0", 5000, 0);

        $wallet2 = $this->walletService->getById($walletScale2);
        $wallet0 = $this->walletService->getById($walletScale0);

        $this->assertEquals(2, $wallet2->getScale());
        $this->assertEquals(0, $wallet0->getScale());

        // Test float conversion before transfer
        $this->assertEquals(100.0, $wallet2->getBalanceFloat()); // 10000 with scale=2 = 100.00
        $this->assertEquals(5000.0, $wallet0->getBalanceFloat()); // 5000 with scale=0 = 5000.0

        // Transfer from scale=2 wallet to scale=0 wallet
        // Note: transferFunds uses integer amount, demonstrating the same value has different meanings
        [$transactionSource, $transactionTarget] = $this->walletService->transferFunds($walletScale2, $walletScale0, 3000);

        // Verify source transaction preserves scale=2
        $this->assertEquals(2, $transactionSource->getScale());
        $this->assertEquals(7000, $transactionSource->getBalance());
        $this->assertEquals(3000, $transactionSource->getAmount());

        // Test source float conversion: 3000 with scale=2 = 30.00
        $this->assertEquals(30.0, $transactionSource->getAmountFloat());
        $this->assertEquals(70.0, $transactionSource->getBalanceFloat());

        // Verify target transaction preserves scale=0
        $this->assertEquals(0, $transactionTarget->getScale());
        $this->assertEquals(8000, $transactionTarget->getBalance());
        $this->assertEquals(3000, $transactionTarget->getAmount());

        // Test target float conversion: 3000 with scale=0 = 3000.0
        $this->assertEquals(3000.0, $transactionTarget->getAmountFloat());
        $this->assertEquals(8000.0, $transactionTarget->getBalanceFloat());
    }

    public function testTransactionDTOSetAmountFloatWithDifferentScales(): void
    {
        // Create wallets with different scales
        $walletScale2 = $this->walletService->createWallet('USDTEST', "___TESTUSER-DTO-2", 10000, 2);
        $walletScale4 = $this->walletService->createWallet('BRLTEST', "___TESTUSER-DTO-4", 100000, 4);

        // Test with scale=2: $12.34 = 1234 cents
        $dto2 = TransactionDTO::create($walletScale2, 0)
            ->setAmountFloat(12.34, 2)
            ->setDescription('Test DTO with scale 2');

        $this->assertEquals(1234, $dto2->getAmount());

        $transaction2 = $this->transactionService->addFunds($dto2);
        $this->assertEquals(1234, $transaction2->getAmount());
        $this->assertEquals(12.34, $transaction2->getAmountFloat());

        // Test with scale=4: 12.3456 = 123456
        $dto4 = TransactionDTO::create($walletScale4, 0)
            ->setAmountFloat(12.3456, 4)
            ->setDescription('Test DTO with scale 4');

        $this->assertEquals(123456, $dto4->getAmount());

        $transaction4 = $this->transactionService->addFunds($dto4);
        $this->assertEquals(123456, $transaction4->getAmount());
        $this->assertEquals(12.3456, $transaction4->getAmountFloat());
    }

    public function testTransactionDTOGetScale(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-GETSCALE", 5000, 3);

        // Test that getScale() returns the set scale
        $dto = TransactionDTO::create($walletId, 1234)
            ->setScale(3)
            ->setDescription('Test getScale');

        $this->assertEquals(3, $dto->getScale());

        // Test changing scale
        $dto->setScale(5);
        $this->assertEquals(5, $dto->getScale());

        // Test with setAmountFloat
        $dto2 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(99.99, 2);

        $this->assertEquals(2, $dto2->getScale());
        $this->assertEquals(9999, $dto2->getAmount());
    }

    public function testSetAmountFloatEdgeCases(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-EDGE", 1000000, 2);

        // Test very small amounts
        $dto1 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(0.01, 2); // 1 cent

        $transaction1 = $this->transactionService->addFunds($dto1);
        $this->assertEquals(1, $transaction1->getAmount());
        $this->assertEquals(0.01, $transaction1->getAmountFloat());

        // Test larger amounts
        $dto2 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(1234.56, 2);

        $transaction2 = $this->transactionService->addFunds($dto2);
        $this->assertEquals(123456, $transaction2->getAmount());
        $this->assertEquals(1234.56, $transaction2->getAmountFloat());

        // Test zero
        $dto3 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(0.0, 2);

        $this->assertEquals(0, $dto3->getAmount());

        // Test rounding with high precision input
        $dto4 = TransactionDTO::create($walletId, 0)
            ->setAmountFloat(99.999, 2); // Should round/truncate to 99.99 = 9999

        $this->assertEquals(10000, $dto4->getAmount());
    }

    public static function amountDataProvider(): array
    {
        return [
            [250, 25000],
            [250.00, 25000],
            [250.01, 25001],
            [250.12, 25012],
            [250.18, 25018],
            [250.19, 25019],
            [250.32, 25032],
            [250.41, 25041],
            [2055.49, 205549],
            [10.23456789 - 1.21456789, 902], // 9.02 = 902
        ];
    }

    #[DataProvider('amountDataProvider')]
    public function testSetAmountFloatWithDataProvider(float $floatValue, int $expectedAmount): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-DATAPROVIDER", 1000000, 2);

        $dto = TransactionDTO::create($walletId, 0)
            ->setAmountFloat($floatValue, 2);

        $this->assertEquals($expectedAmount, $dto->getAmount(),
            "Float value {$floatValue} with scale=2 should convert to {$expectedAmount}");

        // Also verify it works when actually creating a transaction
        $transaction = $this->transactionService->addFunds($dto);
        $this->assertEquals($expectedAmount, $transaction->getAmount());
    }
}