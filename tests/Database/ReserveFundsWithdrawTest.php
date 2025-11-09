<?php

namespace Tests\Database;

use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Exception\AmountException;
use ByJG\Wallets\Exception\TransactionException;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

class ReserveFundsWithdrawTest extends TestCase
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

    public function testReserveForWithdrawFunds(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $dto = TransactionDTO::create($walletId, 350)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->reserveFundsForWithdraw($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(1000);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());;
        $transaction->setTypeId('WB');
        $transaction->setAvailable(650);
        $transaction->setScale(2);
        $transaction->setReserved(350);
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_Invalid(): void
    {
        $this->expectException(AmountException::class);
        $this->expectExceptionMessage('Amount needs to be greater than zero');

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, -50)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw'));
    }

    public function testReserveForWithdrawFunds_Allow_Negative(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('NEGTEST', "___TESTUSER-1", 1000, 2, -400);
        $dto = TransactionDTO::create($walletId, 1150)
            ->setDescription('Test Withdraw')
            ->setReferenceId('Referencia Withdraw')
            ->setReferenceSource('Source Withdraw');
        $actual = $this->transactionService->reserveFundsForWithdraw($dto);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(1150);
        $transaction->setDate('2015-01-24');
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(1000);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actual->getTransactionId());
        $transaction->setTypeId('WB');
        $transaction->setAvailable(-150);
        $transaction->setScale(2);
        $transaction->setReserved(1150);
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setWalletTypeId('NEGTEST');
        $transaction->setDate($actual->getDate());
        $transaction->setUuid(HexUuidLiteral::getFormattedUuid($dto->getUuid()));
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testReserveForWithdrawFunds_NegativeInvalid(): void
    {
        $this->expectException(AmountException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000, 2, -400);
        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 1401)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
    }

    public function testAcceptFundsById_InvalidId(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->acceptFundsById(2);
    }

    public function testAcceptFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 200)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        $this->transactionService->acceptFundsById($transaction->getTransactionId());
    }

    public function testAcceptFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 150)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));
        $transaction = $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create($walletId, 350)->setDescription('Test Withdraw')->setReferenceId('Referencia Withdraw'));

        // Executar ação
        $this->transactionService->acceptFundsById($transaction->getTransactionId());

        // Provar o erro:
        $this->transactionService->acceptFundsById($transaction->getTransactionId());
    }

    public function testAcceptFundsById_OK(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 150)
                ->setDescription( 'Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->transactionService->acceptFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionService->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(500);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('W');
        $transaction->setAvailable(500);
        $transaction->setScale(2);
        $transaction->setReserved(0);
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setDate($actual->getDate());
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

    public function testRejectFundsById_InvalidId(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->rejectFundsById(5);
    }

    public function testRejectFundsById_InvalidType(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 300));

        $this->transactionService->rejectFundsById($transaction->getTransactionId());
    }

    public function testRejectFundsById_HasParentTransation(): void
    {
        $this->expectException(TransactionException::class);

        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $transaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $this->transactionService->rejectFundsById($transaction->getTransactionId());

        // Provocar o erro:
        $this->transactionService->rejectFundsById($transaction->getTransactionId());
    }

    public function testRejectFundsById_OK(): void
    {
        // Populate Data!
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 150)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );
        $reserveTransaction = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 350)
                ->setDescription('Test Withdraw')
                ->setReferenceId('Referencia Withdraw')
                ->setReferenceSource('Source Withdraw')
            );

        // Executar ação
        $actualId = $this->transactionService->rejectFundsById($reserveTransaction->getTransactionId());
        $actual = $this->transactionService->getById($actualId);

        // Objeto que é esperado
        $transaction = new TransactionEntity();
        $transaction->setAmount(350);
        $transaction->setDescription('Test Withdraw');
        $transaction->setBalance(850);
        $transaction->setWalletId($walletId);
        $transaction->setTransactionId($actualId);
        $transaction->setTransactionParentId($reserveTransaction->getTransactionId());
        $transaction->setTypeId('R');
        $transaction->setAvailable(850);
        $transaction->setScale(2);
        $transaction->setReserved(0);
        $transaction->setReferenceId('Referencia Withdraw');
        $transaction->setReferenceSource('Source Withdraw');
        $transaction->setDate($actual->getDate());
        $transaction->setWalletTypeId('USDTEST');
        $transaction->setUuid($actual->getUuid());
        $transaction->setPreviousUuid($actual->getPreviousUuid());
        $transaction->setChecksum(TransactionEntity::calculateChecksum($actual));

        // Executar teste
        $this->assertEquals($transaction->toArray(), $actual->toArray());
    }

}
