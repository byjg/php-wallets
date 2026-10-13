<?php

namespace Tests\Database;

use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Exception\TransactionException;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

/**
 * A caller-supplied UUID acts as an idempotency key: retrying the same
 * operation must not create a duplicate movement.
 */
class IdempotencyTest extends TestCase
{
    use BaseDALTrait;

    const IDEMPOTENCY_KEY = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';

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

    public function testAddFundsReplayWithSameUuidReturnsOriginalTransaction(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $first = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)->setDescription('Idempotent Add')->setUuid(self::IDEMPOTENCY_KEY)
        );

        // Simulates a client retry after a timeout: same UUID, same data
        $replay = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)->setDescription('Idempotent Add')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $this->assertEquals($first->getTransactionId(), $replay->getTransactionId());
        $this->assertEquals(
            HexUuidLiteral::getFormattedUuid($first->getUuid()),
            HexUuidLiteral::getFormattedUuid($replay->getUuid())
        );

        // The amount must have been applied only once
        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(1250, $wallet->getBalance());
        $this->assertEquals(1250, $wallet->getAvailable());
    }

    public function testWithdrawFundsReplayWithSameUuidReturnsOriginalTransaction(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $first = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 300)->setDescription('Idempotent Withdraw')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $replay = $this->transactionService->withdrawFunds(
            TransactionDTO::create($walletId, 300)->setDescription('Idempotent Withdraw')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $this->assertEquals($first->getTransactionId(), $replay->getTransactionId());

        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(700, $wallet->getBalance());
        $this->assertEquals(700, $wallet->getAvailable());
    }

    public function testReusedUuidWithDifferentDataThrows(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)->setDescription('Idempotent Add')->setUuid(self::IDEMPOTENCY_KEY)
        );

        try {
            $this->transactionService->addFunds(
                TransactionDTO::create($walletId, 300)->setDescription('Idempotent Add')->setUuid(self::IDEMPOTENCY_KEY)
            );
            $this->fail('Expected TransactionException was not thrown');
        } catch (TransactionException $ex) {
            $this->assertEquals('The UUID was already used by a different transaction', $ex->getMessage());
        }

        // The conflicting operation must not have been applied
        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(1250, $wallet->getBalance());
    }

    public function testAcceptFundsByUuidDrivesFullLifecycleWithCallerKeys(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        // The caller drives reserve -> accept using only identifiers it generated
        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Reserve')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $acceptedId = $this->transactionService->acceptFundsByUuid(self::IDEMPOTENCY_KEY);

        $accepted = $this->transactionService->getById($acceptedId);
        $this->assertEquals('W', $accepted->getTypeId());
        $this->assertEquals(300, $accepted->getAmount());

        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(700, $wallet->getBalance());
        $this->assertEquals(700, $wallet->getAvailable());
        $this->assertEquals(0, $wallet->getReserved());
    }

    public function testRejectFundsByUuidDrivesFullLifecycleWithCallerKeys(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Reserve')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $rejectedId = $this->transactionService->rejectFundsByUuid(self::IDEMPOTENCY_KEY);

        $rejected = $this->transactionService->getById($rejectedId);
        $this->assertEquals('R', $rejected->getTypeId());

        $wallet = $this->walletService->getById($walletId);
        $this->assertEquals(1000, $wallet->getBalance());
        $this->assertEquals(1000, $wallet->getAvailable());
        $this->assertEquals(0, $wallet->getReserved());
    }

    public function testAcceptFundsByUuidUnknownUuidThrows(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Transaction not found');
        $this->transactionService->acceptFundsByUuid(self::IDEMPOTENCY_KEY);
    }

    public function testGetByUuidReturnsTransactionOrNull(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $this->assertNull($this->transactionService->getByUuid(self::IDEMPOTENCY_KEY));

        $created = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 250)->setDescription('Add')->setUuid(self::IDEMPOTENCY_KEY)
        );

        $found = $this->transactionService->getByUuid(self::IDEMPOTENCY_KEY);
        $this->assertNotNull($found);
        $this->assertEquals($created->getTransactionId(), $found->getTransactionId());
    }

    public function testGeneratedUuidStillWorksWhenNotSupplied(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $first = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 100)->setDescription('No UUID')
        );
        $second = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 100)->setDescription('No UUID')
        );

        // Without an idempotency key, each call is a new movement
        $this->assertNotEquals($first->getTransactionId(), $second->getTransactionId());
        $this->assertEquals(1200, $this->walletService->getById($walletId)->getBalance());
    }
}
