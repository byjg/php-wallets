<?php

namespace Tests\Database;

use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\Wallets\Exception\WalletException;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Service\WalletTypeService;
use Error;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\BaseDALTrait;
use Tests\Classes\ErrorWalletRepository;
use Tests\Classes\FailingDepositTransactionService;
use Tests\Classes\FailingTransactionRepository;
use Tests\Classes\FailingWalletRepository;

/**
 * Guarantees the all-or-nothing behavior of accept/reject: when any write
 * inside the operation fails, the error must reach the caller and the
 * database must keep no partial state.
 */
class TransactionRollbackTest extends TestCase
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
     * Create a wallet with 1000 and reserve 300 for withdraw.
     * Returns [walletId, reservedTransaction].
     */
    private function createWalletWithReservation(): array
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserved = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Test Reserve Withdraw')
        );

        return [$walletId, $reserved];
    }

    /**
     * Assert the reservation is still open and the wallet still holds the reserved state.
     */
    private function assertNothingChanged(int $walletId, TransactionEntity $reserved): void
    {
        $this->assertNull(
            $this->transactionService->getRepository()->getByParentId($reserved->getTransactionId()),
            'The reserved transaction must not have a child transaction after the rollback'
        );

        /** @var WalletEntity $wallet */
        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(1000, $wallet->getBalance());
        $this->assertEquals(300, $wallet->getReserved());
        $this->assertEquals(700, $wallet->getAvailable());
        $this->assertEquals(
            HexUuidLiteral::getFormattedUuid($reserved->getUuid()),
            HexUuidLiteral::getFormattedUuid($wallet->getLastUuid()),
            'The wallet last_uuid must still point to the reserved transaction after the rollback'
        );
    }

    public function testAcceptFundsByIdRollsBackWhenWalletSaveFails(): void
    {
        [$walletId, $reserved] = $this->createWalletWithReservation();

        $failingService = new TransactionService(
            $this->transactionService->getRepository(),
            new FailingWalletRepository($this->dbExecutor, WalletEntity::class)
        );

        try {
            $failingService->acceptFundsById($reserved->getTransactionId());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated wallet save failure', $ex->getMessage());
        }

        $this->assertNothingChanged($walletId, $reserved);
    }

    public function testAcceptFundsByIdRollsBackWhenTransactionSaveFails(): void
    {
        [$walletId, $reserved] = $this->createWalletWithReservation();

        $failingService = new TransactionService(
            new FailingTransactionRepository($this->dbExecutor, TransactionEntity::class),
            $this->walletService->getRepository()
        );

        try {
            $failingService->acceptFundsById($reserved->getTransactionId());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated transaction save failure', $ex->getMessage());
        }

        $this->assertNothingChanged($walletId, $reserved);
    }

    public function testAcceptFundsByIdRollsBackWhenWalletSaveThrowsError(): void
    {
        [$walletId, $reserved] = $this->createWalletWithReservation();

        $failingService = new TransactionService(
            $this->transactionService->getRepository(),
            new ErrorWalletRepository($this->dbExecutor, WalletEntity::class)
        );

        try {
            $failingService->acceptFundsById($reserved->getTransactionId());
            $this->fail('Expected Error was not thrown');
        } catch (Error $ex) {
            $this->assertEquals('Simulated wallet save error', $ex->getMessage());
        }

        $this->assertNothingChanged($walletId, $reserved);
    }

    public function testRejectFundsByIdRollsBackWhenWalletSaveFails(): void
    {
        [$walletId, $reserved] = $this->createWalletWithReservation();

        $failingService = new TransactionService(
            $this->transactionService->getRepository(),
            new FailingWalletRepository($this->dbExecutor, WalletEntity::class)
        );

        try {
            $failingService->rejectFundsById($reserved->getTransactionId());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated wallet save failure', $ex->getMessage());
        }

        $this->assertNothingChanged($walletId, $reserved);
    }

    public function testRejectFundsByIdRollsBackWhenTransactionSaveFails(): void
    {
        [$walletId, $reserved] = $this->createWalletWithReservation();

        $failingService = new TransactionService(
            new FailingTransactionRepository($this->dbExecutor, TransactionEntity::class),
            $this->walletService->getRepository()
        );

        try {
            $failingService->rejectFundsById($reserved->getTransactionId());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated transaction save failure', $ex->getMessage());
        }

        $this->assertNothingChanged($walletId, $reserved);
    }

    public function testTransferFundsRollsBackWhenDepositFails(): void
    {
        $sourceWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $targetWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-2", 500);

        // WalletService wired with a TransactionService that fails on deposit:
        // the withdraw succeeds and then the deposit blows up mid-transfer
        $failingTransactionService = new FailingDepositTransactionService(
            $this->transactionService->getRepository(),
            $this->walletService->getRepository()
        );
        $failingWalletService = new WalletService(
            $this->walletService->getRepository(),
            new WalletTypeService($this->walletTypeService->getRepository()),
            $failingTransactionService
        );

        try {
            $failingWalletService->transferFunds($sourceWalletId, $targetWalletId, 300);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated deposit failure', $ex->getMessage());
        }

        // The successful withdraw must have been rolled back together with the failed deposit
        $sourceWallet = $this->walletService->getById($sourceWalletId);
        $this->assertEquals(1000, $sourceWallet->getBalance());
        $this->assertEquals(1000, $sourceWallet->getAvailable());
        $targetWallet = $this->walletService->getById($targetWalletId);
        $this->assertEquals(500, $targetWallet->getBalance());
        $this->assertEquals(500, $targetWallet->getAvailable());

        // Only the opening balance transaction must exist on each wallet
        $this->assertCount(1, $this->transactionService->getRepository()->getByWalletId($sourceWalletId));
        $this->assertCount(1, $this->transactionService->getRepository()->getByWalletId($targetWalletId));
    }

    public function testTransferFundsRollsBackWhenTargetWalletDoesNotExist(): void
    {
        $sourceWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        try {
            $this->walletService->transferFunds($sourceWalletId, 999999999, 300);
            $this->fail('Expected WalletException was not thrown');
        } catch (WalletException $ex) {
            $this->assertStringContainsString('not found', $ex->getMessage());
        }

        $sourceWallet = $this->walletService->getById($sourceWalletId);
        $this->assertEquals(1000, $sourceWallet->getBalance());
        $this->assertCount(1, $this->transactionService->getRepository()->getByWalletId($sourceWalletId));
    }

    public function testTransferFundsRejectsSameWallet(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Source and target wallets must be different');
        $this->walletService->transferFunds($walletId, $walletId, 300);
    }
}
