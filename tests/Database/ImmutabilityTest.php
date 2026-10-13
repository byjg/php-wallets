<?php

namespace Tests\Database;

use ByJG\Wallets\DTO\TransactionDTO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

/**
 * The transaction table is an immutable ledger: rows cannot be updated
 * (database trigger) and a reserved transaction can be processed only
 * once (unique index on transactionparentid).
 */
class ImmutabilityTest extends TestCase
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

    public function testTransactionRowsCannotBeUpdated(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        try {
            $this->dbExecutor->execute("UPDATE transaction SET description = 'tampered' WHERE walletid = " . $walletId);
            $this->fail('Expected PDOException was not thrown');
        } catch (PDOException $ex) {
            $this->assertStringContainsString('immutable', $ex->getMessage());
        }

        // The row must be untouched
        $transactions = $this->transactionService->getRepository()->getByWalletId($walletId);
        $this->assertCount(1, $transactions);
        $this->assertEquals('Opening Balance', $transactions[0]->getDescription());
    }

    public function testReservedTransactionCannotHaveTwoChildrenAtDatabaseLevel(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserved = $this->transactionService->reserveFundsForWithdraw(
            TransactionDTO::create($walletId, 300)->setDescription('Reserve')
        );
        $parentId = $reserved->getTransactionId();

        $insert = "INSERT INTO transaction (walletid, wallettypeid, typeid, amount, checksum, transactionparentid) " .
            "VALUES ($walletId, 'USDTEST', 'W', 300, 'test-checksum', $parentId)";

        $this->dbExecutor->execute($insert);

        try {
            $this->dbExecutor->execute($insert);
            $this->fail('Expected PDOException was not thrown');
        } catch (PDOException $ex) {
            $this->assertStringContainsString('Duplicate entry', $ex->getMessage());
        }
    }
}
