<?php

namespace Tests\Database;

use ByJG\MicroOrm\Enum\ObserverEvent;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Entity\WalletEntity;
use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\BaseDALTrait;
use Tests\Classes\ObserverRecorder;

class ObserverTest extends TestCase
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
     * Attach fresh recorders to the wallet and transaction tables.
     * Called after the wallet is created so the recorders only capture
     * the events of the operation under test.
     *
     * @return ObserverRecorder[] [walletRecorder, transactionRecorder]
     */
    private function attachRecorders(?Closure $walletCallback = null, ?Closure $transactionCallback = null): array
    {
        $walletRecorder = new ObserverRecorder('wallet', $walletCallback);
        $transactionRecorder = new ObserverRecorder('transaction', $transactionCallback);

        $this->walletService->getRepository()->getRepository()->addObserver($walletRecorder);
        $this->transactionService->getRepository()->getRepository()->addObserver($transactionRecorder);

        return [$walletRecorder, $transactionRecorder];
    }

    public function testWithdrawFundsNotifiesObservers(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders();

        $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 300)->setDescription('Test Withdraw')
        );

        $this->assertCount(1, $transactionRecorder->processed);
        $transactionData = $transactionRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Insert, $transactionData->getEvent());
        /** @var TransactionEntity $transaction */
        $transaction = $transactionData->getData();
        $this->assertInstanceOf(TransactionEntity::class, $transaction);
        $this->assertEquals(TransactionEntity::WITHDRAW, $transaction->getTypeId());
        $this->assertEquals(300, $transaction->getAmount());
        $this->assertEquals(700, $transaction->getBalance());
        $this->assertEquals(700, $transaction->getAvailable());
        $this->assertEquals(0, $transaction->getReserved());
        $this->assertNull($transactionData->getOldData());

        $this->assertCount(1, $walletRecorder->processed);
        $walletData = $walletRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Update, $walletData->getEvent());
        /** @var WalletEntity $wallet */
        $wallet = $walletData->getData();
        $this->assertInstanceOf(WalletEntity::class, $wallet);
        $this->assertEquals(700, $wallet->getBalance());
        $this->assertEquals(700, $wallet->getAvailable());
        $this->assertEquals(0, $wallet->getReserved());
        /** @var WalletEntity $oldWallet */
        $oldWallet = $walletData->getOldData();
        $this->assertInstanceOf(WalletEntity::class, $oldWallet);
        $this->assertEquals(1000, $oldWallet->getBalance());
        $this->assertEquals(1000, $oldWallet->getAvailable());
        $this->assertEquals(0, $oldWallet->getReserved());
    }

    public function testReserveFundsForWithdrawNotifiesObservers(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders();

        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Test Reserve Withdraw')
        );

        $this->assertCount(1, $transactionRecorder->processed);
        $transactionData = $transactionRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Insert, $transactionData->getEvent());
        /** @var TransactionEntity $transaction */
        $transaction = $transactionData->getData();
        $this->assertEquals(TransactionEntity::WITHDRAW_BLOCKED, $transaction->getTypeId());
        $this->assertEquals(300, $transaction->getAmount());
        $this->assertEquals(1000, $transaction->getBalance());
        $this->assertEquals(700, $transaction->getAvailable());
        $this->assertEquals(300, $transaction->getReserved());

        $this->assertCount(1, $walletRecorder->processed);
        $walletData = $walletRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Update, $walletData->getEvent());
        /** @var WalletEntity $wallet */
        $wallet = $walletData->getData();
        $this->assertEquals(1000, $wallet->getBalance());
        $this->assertEquals(700, $wallet->getAvailable());
        $this->assertEquals(300, $wallet->getReserved());
        /** @var WalletEntity $oldWallet */
        $oldWallet = $walletData->getOldData();
        $this->assertEquals(1000, $oldWallet->getBalance());
        $this->assertEquals(1000, $oldWallet->getAvailable());
        $this->assertEquals(0, $oldWallet->getReserved());
    }

    public function testReserveFundsForDepositNotifiesObservers(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders();

        $this->transactionService->reserveFundsForDeposit(
            TransactionDTO::create($walletId, 350)->setDescription('Test Reserve Deposit')
        );

        $this->assertCount(1, $transactionRecorder->processed);
        $transactionData = $transactionRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Insert, $transactionData->getEvent());
        /** @var TransactionEntity $transaction */
        $transaction = $transactionData->getData();
        $this->assertEquals(TransactionEntity::DEPOSIT_BLOCKED, $transaction->getTypeId());
        $this->assertEquals(350, $transaction->getAmount());
        $this->assertEquals(1000, $transaction->getBalance());
        $this->assertEquals(1350, $transaction->getAvailable());
        $this->assertEquals(-350, $transaction->getReserved());

        $this->assertCount(1, $walletRecorder->processed);
        $walletData = $walletRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Update, $walletData->getEvent());
        /** @var WalletEntity $wallet */
        $wallet = $walletData->getData();
        $this->assertEquals(1000, $wallet->getBalance());
        $this->assertEquals(1350, $wallet->getAvailable());
        $this->assertEquals(-350, $wallet->getReserved());
        /** @var WalletEntity $oldWallet */
        $oldWallet = $walletData->getOldData();
        $this->assertEquals(1000, $oldWallet->getBalance());
        $this->assertEquals(1000, $oldWallet->getAvailable());
        $this->assertEquals(0, $oldWallet->getReserved());
    }

    public function testAcceptFundsByIdNotifiesObservers(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserved = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Test Reserve Withdraw')
        );
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders();

        $acceptedId = $this->transactionService->acceptFundsById($reserved->getTransactionId());

        $this->assertCount(1, $transactionRecorder->processed);
        $transactionData = $transactionRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Insert, $transactionData->getEvent());
        /** @var TransactionEntity $transaction */
        $transaction = $transactionData->getData();
        $this->assertInstanceOf(TransactionEntity::class, $transaction);
        $this->assertEquals($acceptedId, $transaction->getTransactionId());
        $this->assertEquals(TransactionEntity::WITHDRAW, $transaction->getTypeId());
        $this->assertEquals($reserved->getTransactionId(), $transaction->getTransactionParentId());
        $this->assertEquals(300, $transaction->getAmount());

        // The wallet is saved once, with the balance changes and the new last_uuid together
        $this->assertCount(1, $walletRecorder->processed);
        $walletData = $walletRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Update, $walletData->getEvent());
        /** @var WalletEntity $wallet */
        $wallet = $walletData->getData();
        $this->assertEquals(700, $wallet->getBalance());
        $this->assertEquals(700, $wallet->getAvailable());
        $this->assertEquals(0, $wallet->getReserved());
    }

    public function testRejectFundsByIdNotifiesObservers(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserved = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Test Reserve Withdraw')
        );
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders();

        $rejectedId = $this->transactionService->rejectFundsById($reserved->getTransactionId());

        $this->assertCount(1, $transactionRecorder->processed);
        $transactionData = $transactionRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Insert, $transactionData->getEvent());
        /** @var TransactionEntity $transaction */
        $transaction = $transactionData->getData();
        $this->assertInstanceOf(TransactionEntity::class, $transaction);
        $this->assertEquals($rejectedId, $transaction->getTransactionId());
        $this->assertEquals(TransactionEntity::REJECT, $transaction->getTypeId());
        $this->assertEquals($reserved->getTransactionId(), $transaction->getTransactionParentId());

        // The wallet is saved once, with the reserved/available changes and the new last_uuid together
        $this->assertCount(1, $walletRecorder->processed);
        $walletData = $walletRecorder->processed[0];
        $this->assertEquals(ObserverEvent::Update, $walletData->getEvent());
        /** @var WalletEntity $wallet */
        $wallet = $walletData->getData();
        $this->assertEquals(1000, $wallet->getBalance());
        $this->assertEquals(1000, $wallet->getAvailable());
        $this->assertEquals(0, $wallet->getReserved());
    }

    public function testObserverOnErrorReceivesProcessException(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        [$walletRecorder, $transactionRecorder] = $this->attachRecorders(
            fn() => throw new RuntimeException('Wallet observer failed'),
            fn() => throw new RuntimeException('Transaction observer failed')
        );

        $transaction = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)->setDescription('Test Add Funds')
        );

        // A failing observer must not abort the operation
        $this->assertEquals(250, $transaction->getAmount());
        $this->assertEquals(1250, $this->walletService->getById($walletId)->getBalance());

        $this->assertCount(1, $walletRecorder->processed);
        $this->assertCount(1, $walletRecorder->errors);
        $this->assertInstanceOf(RuntimeException::class, $walletRecorder->errors[0]['exception']);
        $this->assertEquals('Wallet observer failed', $walletRecorder->errors[0]['exception']->getMessage());
        $this->assertSame($walletRecorder->processed[0], $walletRecorder->errors[0]['observerData']);

        $this->assertCount(1, $transactionRecorder->processed);
        $this->assertCount(1, $transactionRecorder->errors);
        $this->assertInstanceOf(RuntimeException::class, $transactionRecorder->errors[0]['exception']);
        $this->assertEquals('Transaction observer failed', $transactionRecorder->errors[0]['exception']->getMessage());
        $this->assertSame($transactionRecorder->processed[0], $transactionRecorder->errors[0]['observerData']);

        /** @var TransactionEntity $observedTransaction */
        $observedTransaction = $transactionRecorder->errors[0]['observerData']->getData();
        $this->assertEquals(250, $observedTransaction->getAmount());
        $this->assertEquals($transaction->getTransactionId(), $observedTransaction->getTransactionId());
    }
}
