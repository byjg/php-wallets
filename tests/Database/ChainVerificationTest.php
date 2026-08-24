<?php

namespace Tests\Database;

use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Exception\WalletException;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

/**
 * TransactionService::verifyChain() must detect any corruption of the
 * transaction chain: tampered wallet state, tampered transaction data,
 * deleted (broken chain) and orphan rows.
 */
class ChainVerificationTest extends TestCase
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
     * Create a wallet with a realistic mix of operations.
     * Chain: opening balance + add + reserve + accept + withdraw = 5 transactions.
     */
    private function createWalletWithHistory(): int
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 500)->setDescription('Add'));
        $reserved = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 200)->setDescription('Reserve')
        );
        $this->transactionService->acceptFundsById($reserved->getTransactionId());
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 100)->setDescription('Withdraw'));

        return $walletId;
    }

    public function testValidChainPasses(): void
    {
        $walletId = $this->createWalletWithHistory();

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals(5, $result->getTransactionsVerified());
        $this->assertEquals($walletId, $result->getWalletId());
    }

    public function testWalletNotFoundThrows(): void
    {
        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('Wallet not found');
        $this->transactionService->verifyChain(999999999);
    }

    public function testDetectsTamperedWalletState(): void
    {
        $walletId = $this->createWalletWithHistory();

        // An attacker changes the wallet balance without a transaction
        $this->dbExecutor->execute("UPDATE wallet SET balance = 999999 WHERE walletid = $walletId");

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('does not match the head transaction', $result->getErrors()[0]);
    }

    public function testDetectsTamperedTransactionData(): void
    {
        $walletId = $this->createWalletWithHistory();

        // Simulate an attacker with full DB access: disable the immutability
        // trigger and rewrite an amount without recalculating the checksum
        $this->dbExecutor->execute("DROP TRIGGER trg_transaction_no_update");
        $this->dbExecutor->execute(
            "UPDATE transaction SET amount = 999999 WHERE walletid = $walletId AND typeid = 'D' AND description = 'Add'"
        );

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('Checksum mismatch', $result->getErrors()[0]);
    }

    public function testDetectsDeletedTransaction(): void
    {
        $walletId = $this->createWalletWithHistory();

        // Delete a middle transaction: the chain must report the broken link
        // and the transactions before the gap become unreachable
        $this->dbExecutor->execute(
            "DELETE FROM transaction WHERE walletid = $walletId AND typeid = 'D' AND description = 'Add'"
        );

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertFalse($result->isValid());
        $errors = implode('; ', $result->getErrors());
        $this->assertStringContainsString('Broken chain', $errors);
        $this->assertStringContainsString('not reachable', $errors);
    }

    public function testDetectsOrphanTransaction(): void
    {
        $walletId = $this->createWalletWithHistory();

        // Insert a row that is not part of the chain (bypassing the application)
        $this->dbExecutor->execute(
            "INSERT INTO transaction (walletid, wallettypeid, typeid, amount, checksum, uuid) " .
            "VALUES ($walletId, 'USDTEST', 'D', 777, 'fake-checksum', uuid_to_bin(uuid()))"
        );

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not reachable from the wallet head', implode('; ', $result->getErrors()));
    }

    public function testDetectsForgedLastUuid(): void
    {
        $walletId = $this->createWalletWithHistory();

        // Point the wallet head to a UUID that no transaction has
        $this->dbExecutor->execute(
            "UPDATE wallet SET last_uuid = uuid_to_bin(uuid()) WHERE walletid = $walletId"
        );

        $result = $this->transactionService->verifyChain($walletId);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('does not match any transaction', $result->getErrors()[0]);
        $this->assertEquals(0, $result->getTransactionsVerified());
    }
}
