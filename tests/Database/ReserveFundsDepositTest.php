<?php

namespace Tests\Database;

use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\TransactionException;
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
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->transactionService->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $expectedTransaction = new TransactionEntity();
        $expectedTransaction->setAmount(350);
        $expectedTransaction->setDate('2015-01-24');
        $expectedTransaction->setDescription('Test Deposit');
        $expectedTransaction->setBalance(1000);
        $expectedTransaction->setWalletId($walletId);
        $expectedTransaction->setTransactionId($actual->getTransactionId());
        $expectedTransaction->setTypeId('DB');
        $expectedTransaction->setAvailable(1350);
        $expectedTransaction->setScale(2);
        $expectedTransaction->setReserved(-350);
        $expectedTransaction->setReferenceId('Referencia Deposit');
        $expectedTransaction->setReferenceSource('Source Deposit');
        $expectedTransaction->setWalletTypeId('USDTEST');
        $expectedTransaction->setTransactionParentId(null);
        $expectedTransaction->setDate($actual->getDate());
        $expectedTransaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));
        $expectedTransaction->setPreviousUuid($actual->getPreviousUuid());
        $expectedTransaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($expectedTransaction->toArray(), $actual->toArray());
    }

    public function testReserveForDepositFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->reserveFundsForDeposit(TransactionDTO::create($walletId, -50)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
    }

    public function testReserveForDepositFunds_Allow_Negative(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('NEGTEST', "___TESTUSER-1", -200, 2, -400);
        $dto = TransactionDTO::create($walletId, 300)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $actual = $this->transactionService->reserveFundsForDeposit($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(300);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Deposit');
        $transaction->setBalance(-200);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('DB');
        $transaction->setAvailable(100);
        $transaction->setScale(2);
        $transaction->setReserved(-300);
        $transaction->setReferenceId('Referencia Deposit');
        $transaction->setReferenceSource('Source Deposit');
        $transaction->setWalletTypeId('NEGTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

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
//        $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
//
//        $this->$this->transactionService->acceptFundsById(2);
//    }
//

public function testAcceptFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 200)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );

        $this->transactionService->acceptFundsById($transaction->getTransactionId());;
    }

    public function testAcceptFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $transaction = $this->transactionService->reserveFundsForDeposit(TransactionDTO::create($walletId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->transactionService->acceptFundsById($transaction->getTransactionId());;

        // Provar o erro: try to accept the same transaction again
        $this->transactionService->acceptFundsById($transaction->getTransactionId());;
    }

    public function testAcceptFundsById_OK(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 150)
                ->setDescription('Test Deposit')
                ->setReferenceId('Referencia Deposit')
                ->setReferenceSource('Source Deposit')
            );
        $reserveDto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Deposit')
            ->setReferenceId('Referencia Deposit')
            ->setReferenceSource('Source Deposit');
        $reserveTransaction = $this->transactionService->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->transactionService->acceptFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionService->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDescription('Test Deposit');
        $transaction->setBalance(1500);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('D');
        $transaction->setAvailable(1500);
        $transaction->setScale(2);
        $transaction->setReserved(0);
        $transaction->setReferenceId('Referencia Deposit');
        $transaction->setReferenceSource('Source Deposit');
        $transaction->setDate($actual->getDate());
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testAcceptPartialFundsById_PartialAmountZero(): void
    {
        $this->expectException(AmountException::class);

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 100)
        );

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $transactionDTO = TransactionDTO::createEmpty()->setAmount(0);
        $this->transactionService->acceptPartialFundsById($reserveTransaction->getTransactionId(), $transactionDTO, $transactionRefundDto);
    }

    public function testAcceptPartialFundsById_AmountMoreThanWithdrawBlocked(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Partial amount must be greater than zero and less than the original reserved amount.');

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 100)
        );

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $transactionDTO = TransactionDTO::createEmpty()->setAmount(101);
        $this->transactionService->acceptPartialFundsById($reserveTransaction->getTransactionId(), $transactionDTO, $transactionRefundDto);
    }

    public function testAcceptPartialFundsById_OK(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 100)->setDescription('Reserva para Aposta')
        );

        $transactionWithdrawDto = TransactionDTO::createEmpty()
            ->setAmount(80)
            ->setDescription("Deposit")
            ->setReferenceSource("test-source");

        $transactionRefundDto = TransactionDTO::createEmpty()
            ->setDescription("Refund")
            ->setReferenceSource("test-source");

        $finalDebitTransaction = $this->transactionService->acceptPartialFundsById(
            $reserveTransaction->getTransactionId(),
            $transactionWithdrawDto,
            $transactionRefundDto
        );

        $walletAfter = $this->walletService->getById($walletId);
        $this->assertEquals(920, $walletAfter->getBalance());
        $this->assertEquals(920, $walletAfter->getAvailable());
        $this->assertEquals(0, $walletAfter->getReserved());

        $rejectedTransaction = $this->transactionService->getRepository()->getByParentId($reserveTransaction->getTransactionId());
        $this->assertNotNull($rejectedTransaction);
        $this->assertEquals(TransactionEntity::REJECT, $rejectedTransaction->getTypeId());
        $this->assertEquals(100, $rejectedTransaction->getAmount());

        /** @var TransactionEntity $finalDebitTransaction */
        $finalDebitTransaction = $this->transactionService->getById($finalDebitTransaction->getTransactionId());
        $this->assertEquals(80, $finalDebitTransaction->getAmount());
        $this->assertEquals(TransactionEntity::WITHDRAW, $finalDebitTransaction->getTypeId());
        $this->assertEquals("Deposit", $finalDebitTransaction->getDescription());
    }

    public function testRejectFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(TransactionDTO::create($walletId, 300));

        $this->transactionService->rejectFundsById($transaction->getTransactionId());
    }

    public function testRejectFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 150)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));
        $reserveTransaction = $this->transactionService->reserveFundsForDeposit(TransactionDTO::create($walletId, 350)->setDescription('Test Deposit')->setReferenceId('Referencia Deposit'));

        // Executar ação
        $this->transactionService->rejectFundsById($reserveTransaction->getTransactionId());

        // Provocar o erro: try to reject the same transaction again
        $this->transactionService->rejectFundsById($reserveTransaction->getTransactionId());
    }

    public function testRejectFundsById_OK(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $addDto = TransactionDTO::create($walletId, 150)
            ->setDescription('Test Add Funds')
            ->setReferenceId('Referencia Add')
            ->setReferenceSource('Source Add');
        $this->transactionService->addFunds($addDto);
        
        $reserveDto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Reserve Deposit')
            ->setReferenceId('Referencia Reserve')
            ->setReferenceSource('Source Reserve');
        $reserveTransaction = $this->transactionService->reserveFundsForDeposit($reserveDto);

        // Executar ação
        $actualId = $this->transactionService->rejectFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionService->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDescription('Test Reserve Deposit');
        $transaction->setBalance(1150);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('R');
        $transaction->setAvailable(1150);
        $transaction->setScale(2);
        $transaction->setReserved(0);
        $transaction->setReferenceId('Referencia Reserve');
        $transaction->setReferenceSource('Source Reserve');
        $transaction->setDate($actual->getDate());
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

}
