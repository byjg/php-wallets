<?php

namespace Tests\Database;

use ByJG\AccountTransactions\DTO\TransactionDTO;
use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AccountTransactions\Exception\AmountException;
use ByJG\AccountTransactions\Exception\TransactionException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

class ReserveFundsDepositTest extends TestCase
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

    public function testReserveForDepositFunds(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($accountId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->transactionBLL->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $expectedTransaction = new TransactionEntity();
        $expectedTransaction->setAmount('350.00');
        $expectedTransaction->setDate('2015-01-24');
        $expectedTransaction->setDescription('Test Deposit');
        $expectedTransaction->setBalance('1000.00');
        $expectedTransaction->setAccountId($accountId);
        $expectedTransaction->setTransactionId($actual->getTransactionId());
        $expectedTransaction->setTypeId('DB');
        $expectedTransaction->setAvailable('1350.00');
        $expectedTransaction->setPrice('1.00');
        $expectedTransaction->setReserved('-350.00');
        $expectedTransaction->setReferenceId('Referencia Deposit');
        $expectedTransaction->setReferenceSource('Source Deposit');
        $expectedTransaction->setAccountTypeId('USDTEST');
        $expectedTransaction->setTransactionParentId(null);
        $expectedTransaction->setDate($actual->getDate());
        $expectedTransaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($expectedTransaction->toArray(), $actual->toArray());
    }

    public function testReserveForDepositFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->reserveFundsForDeposit(TransactionDTO::create($accountId, -50)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
    }

    public function testReserveForDepositFunds_Allow_Negative(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('NEGTEST', "___TESTUSER-1", -200, 1, -400);
        $dto = TransactionDTO::create($accountId, 300)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->transactionBLL->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('300.00');
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Deposit');
        $transaction->setBalance('-200.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('DB');
        $transaction->setAvailable('100.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('-300.00');
        $transaction->setReferenceId('Referencia Deposit');
        $transaction->setReferenceSource('Source Deposit');
        $transaction->setAccountTypeId('NEGTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }
//
//    /**
//     * @expectedException OutOfRangeException
//     */
//    public function testAcceptFundsById_InvalidId()
//    {
//        // Populate Data!
//        $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
//
//        $this->$this->transactionBLL->acceptFundsById(2);
//    }
//

public function testAcceptFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionBLL->addFunds(
            TransactionDTO::create($accountId, 200)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );

        $this->transactionBLL->acceptFundsById($transaction->getTransactionId());;
    }

    public function testAcceptFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $transaction = $this->transactionBLL->reserveFundsForDeposit(TransactionDTO::create($accountId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->transactionBLL->acceptFundsById($transaction->getTransactionId());;

        // Provar o erro: try to accept the same transaction again
        $this->transactionBLL->acceptFundsById($transaction->getTransactionId());;
    }

    public function testAcceptFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(
            TransactionDTO::create($accountId, 150)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );
        $reserveDto = TransactionDTO::create($accountId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $reserveTransaction = $this->transactionBLL->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->transactionBLL->acceptFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionBLL->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('350.00');
        $transaction->setDescription('Test Deposit');
        $transaction->setBalance('1500.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('D');
        $transaction->setAvailable('1500.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Deposit');
        $transaction->setReferenceSource('Source Deposit');
        $transaction->setDate($actual->getDate());
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testAcceptPartialFundsById_PartialAmountZero(): void
    {
        $this->expectException(AmountException::class);

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionBLL->reserveFundsForWithdraw(
            TransactionDTO::create($accountId, 100)
        );

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $transactionDTO = TransactionDTO::createEmpty()->setAmount(0);
        $this->transactionBLL->acceptPartialFundsById($reserveTransaction->getTransactionId(), $transactionDTO, $transactionRefundDto);
    }

    public function testAcceptPartialFundsById_AmountMoreThanWithdrawBlocked(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Partial amount must be greater than zero and less than the original reserved amount.');

        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionBLL->reserveFundsForWithdraw(
            TransactionDTO::create($accountId, 100)
        );

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $transactionDTO = TransactionDTO::createEmpty()->setAmount(101);
        $this->transactionBLL->acceptPartialFundsById($reserveTransaction->getTransactionId(), $transactionDTO, $transactionRefundDto);
    }

    public function testAcceptPartialFundsById_OK(): void
    {
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionBLL->reserveFundsForWithdraw(
            TransactionDTO::create($accountId, 100)->setDescription('Reserva para Aposta')
        );

        $transactionWithdrawDto = TransactionDTO::createEmpty()
            ->setAmount(80.00)
            ->setDescription("Deposit")
            ->setReferenceSource("test-source");

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $finalDebitTransaction = $this->transactionBLL->acceptPartialFundsById(
            $reserveTransaction->getTransactionId(),
            $transactionWithdrawDto,
            $transactionRefundDto
        );

        $accountAfter = $this->accountBLL->getById($accountId);
        $this->assertEquals('920.00', $accountAfter->getBalance());
        $this->assertEquals('920.00', $accountAfter->getAvailable());
        $this->assertEquals('0.00', $accountAfter->getReserved());

        $rejectedTransaction = $this->transactionBLL->getRepository()->getByParentId($reserveTransaction->getTransactionId());
        $this->assertNotNull($rejectedTransaction);
        $this->assertEquals(TransactionEntity::REJECT, $rejectedTransaction->getTypeId());
        $this->assertEquals('100.00', $rejectedTransaction->getAmount());

        /** @var TransactionEntity $finalDebitTransaction */
        $finalDebitTransaction = $this->transactionBLL->getById($finalDebitTransaction->getTransactionId());
        $this->assertEquals('80.00', $finalDebitTransaction->getAmount());
        $this->assertEquals(TransactionEntity::WITHDRAW, $finalDebitTransaction->getTypeId());
        $this->assertEquals("Deposit", $finalDebitTransaction->getDescription());
    }

    public function testRejectFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 300));

        $this->transactionBLL->rejectFundsById($transaction->getTransactionId());
    }

    public function testRejectFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionBLL->addFunds(TransactionDTO::create($accountId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $reserveTransaction = $this->transactionBLL->reserveFundsForDeposit(TransactionDTO::create($accountId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->transactionBLL->rejectFundsById($reserveTransaction->getTransactionId());

        // Provocar o erro: try to reject the same transaction again
        $this->transactionBLL->rejectFundsById($reserveTransaction->getTransactionId());
    }

    public function testRejectFundsById_OK(): void
    {
        // Populate Data!
        $accountId = $this->accountBLL->createAccount('USDTEST', "___TESTUSER-1", 1000);
        $addDto = TransactionDTO::create($accountId, 150)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add')
            ->setReferenceSource('Source Add');
        $this->transactionBLL->addFunds($addDto);
        
        $reserveDto = TransactionDTO::create($accountId, 350)
            ->setDescription('Test Reserve Deposit')
            ->setReferenceId('Referencia Reserve')
            ->setReferenceSource('Source Reserve');
        $reserveTransaction = $this->transactionBLL->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->transactionBLL->rejectFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionBLL->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount('350.00');
        $transaction->setDescription('Test Reserve Deposit');
        $transaction->setBalance('1150.00');
        $transaction->setAccountId($accountId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('R');
        $transaction->setAvailable('1150.00');
        $transaction->setPrice('1.00');
        $transaction->setReserved('0.00');
        $transaction->setReferenceId('Referencia Reserve');
        $transaction->setReferenceSource('Source Reserve');
        $transaction->setDate($actual->getDate());
        $transaction->setAccountTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

}
