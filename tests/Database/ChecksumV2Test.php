<?php

namespace Tests\Database;

use ByJG\Wallets\Checksum\ChecksumFactory;
use ByJG\Wallets\Checksum\ChecksumV1;
use ByJG\Wallets\Checksum\ChecksumV2;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Repository\TransactionRepository;
use ByJG\Wallets\Repository\WalletRepository;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use PHPUnit\Framework\TestCase;
use Tests\BaseDALTrait;

/**
 * Checksum v2: every new transaction must carry a version 2 checksum chained to the
 * previous transaction's checksum, tampering must be detectable even when the attacker
 * recomputes checksums, and legacy (v1) rows must keep validating.
 */
class ChecksumV2Test extends TestCase
{
    use BaseDALTrait;

    private const SECRET = 's3cr3t-checksum-key';

    #[\Override]
    protected function setUp(): void
    {
        $this->dbSetUp();
        $this->prepareObjects();
        $this->createDummyData();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->dbClear();
    }

    /**
     * Create a wallet exercising every transaction-creating path:
     * opening balance + add + reserve + accept + withdraw = 5 transactions.
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

    /**
     * @return TransactionEntity[] all transactions of the wallet in chronological order
     */
    private function getRows(int $walletId): array
    {
        return $this->transactionService->getRepository()->getAllByWalletId($walletId);
    }

    public function testNewTransactionsFormAChainedChecksumLedger(): void
    {
        $walletId = $this->createWalletWithHistory();
        $rows = $this->getRows($walletId);

        $this->assertCount(5, $rows);
        $previous = null;
        foreach ($rows as $row) {
            $this->assertEquals(ChecksumV2::VERSION, $row->getChecksumVersion());
            $this->assertEquals(
                ChecksumFactory::current()->calculate($row),
                $row->getChecksum(),
                "Stored checksum of transaction {$row->getTransactionId()} must match the calculated one"
            );
            if ($previous === null) {
                $this->assertNull($row->getPreviousChecksum(), 'Genesis transaction must have no previous checksum');
            } else {
                $this->assertEquals(
                    $previous->getChecksum(),
                    $row->getPreviousChecksum(),
                    "Transaction {$row->getTransactionId()} must chain to the checksum of the previous transaction"
                );
            }
            $previous = $row;
        }

        $result = $this->transactionService->verifyChain($walletId);
        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));
        $this->assertEquals(0, $result->getLegacyChecksums());
    }

    public function testOverrideBalanceProducesAValidChain(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->walletService->overrideBalance($walletId, 5000);

        $rows = $this->getRows($walletId);
        $this->assertCount(2, $rows);
        $this->assertEquals(ChecksumV2::VERSION, $rows[1]->getChecksumVersion());
        $this->assertEquals($rows[0]->getChecksum(), $rows[1]->getPreviousChecksum());

        $result = $this->transactionService->verifyChain($walletId);
        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));
    }

    public function testTamperAndRecomputeWithoutSecretIsDetected(): void
    {
        $walletId = $this->createWalletWithHistory();
        $rows = $this->getRows($walletId);

        // The attacker tampers with a middle transaction AND recomputes its checksum
        // (no secret configured, so the checksum itself can be recomputed)
        $victim = $rows[1];
        $victim->setAmount(999999);
        $forgedChecksum = ChecksumFactory::current()->calculate($victim);
        $this->dbExecutor->execute("DROP TRIGGER trg_transaction_no_update");
        $this->dbExecutor->execute(
            "UPDATE transaction SET amount = 999999, checksum = '$forgedChecksum' " .
            "WHERE transactionid = {$victim->getTransactionId()}"
        );

        // The next transaction stores a copy of the old checksum, so the chain breaks there
        $result = $this->transactionService->verifyChain($walletId);
        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('Previous checksum mismatch', $result->getErrors()[0]);
    }

    public function testSecretPreventsChecksumRecomputation(): void
    {
        // Services configured with an installation secret
        $walletRepository = new WalletRepository($this->dbExecutor, \ByJG\Wallets\Entity\WalletEntity::class);
        $transactionRepository = new TransactionRepository($this->dbExecutor, TransactionEntity::class);
        $secretTransactionService = new TransactionService($transactionRepository, $walletRepository, self::SECRET);
        $secretWalletService = new WalletService($walletRepository, $this->walletTypeService, $secretTransactionService);

        $walletId = $secretWalletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $secretTransactionService->addFunds(TransactionDTO::create($walletId, 500)->setDescription('Add'));

        // The chain validates only with the correct secret
        $result = $secretTransactionService->verifyChain($walletId);
        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));

        $resultWithoutSecret = $this->transactionService->verifyChain($walletId);
        $this->assertFalse($resultWithoutSecret->isValid());
        $this->assertStringContainsString('Checksum mismatch', implode('; ', $resultWithoutSecret->getErrors()));

        // An attacker without the secret tampers with the head transaction and recomputes
        // the checksum: verification with the secret must still fail
        $rows = $this->transactionService->getRepository()->getAllByWalletId($walletId);
        $head = $rows[count($rows) - 1];
        $head->setAmount(999999);
        $forgedChecksum = ChecksumFactory::current()->calculate($head);
        $this->dbExecutor->execute("DROP TRIGGER trg_transaction_no_update");
        $this->dbExecutor->execute(
            "UPDATE transaction SET amount = 999999, checksum = '$forgedChecksum' " .
            "WHERE transactionid = {$head->getTransactionId()}"
        );

        $result = $secretTransactionService->verifyChain($walletId);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Checksum mismatch', implode('; ', $result->getErrors()));
    }

    public function testLegacyV1RowsStillValidate(): void
    {
        $walletId = $this->createWalletWithHistory();
        $rows = $this->getRows($walletId);

        // Simulate a database created before the checksum v2 upgrade:
        // every row carries a v1 checksum, no previous checksum and version 1
        $this->dbExecutor->execute("DROP TRIGGER trg_transaction_no_update");
        foreach ($rows as $row) {
            $legacyChecksum = (new ChecksumV1())->calculate($row);
            $this->dbExecutor->execute(
                "UPDATE transaction SET checksum = '$legacyChecksum', previouschecksum = NULL, checksumversion = 1 " .
                "WHERE transactionid = {$row->getTransactionId()}"
            );
        }

        $result = $this->transactionService->verifyChain($walletId);
        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));
        $this->assertEquals(5, $result->getTransactionsVerified());
        $this->assertEquals(5, $result->getLegacyChecksums());

        // A new transaction on top of the legacy chain produces a valid mixed chain
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 50)->setDescription('After upgrade'));

        $result = $this->transactionService->verifyChain($walletId);
        $this->assertTrue($result->isValid(), implode('; ', $result->getErrors()));
        $this->assertEquals(6, $result->getTransactionsVerified());
        $this->assertEquals(5, $result->getLegacyChecksums());
    }

    public function testChecksumVersionDowngradeIsDetected(): void
    {
        $walletId = $this->createWalletWithHistory();
        $rows = $this->getRows($walletId);

        // The attacker rewrites the head transaction with a valid LEGACY checksum,
        // trying to escape the v2 chained validation
        $head = $rows[count($rows) - 1];
        $legacyChecksum = (new ChecksumV1())->calculate($head);
        $this->dbExecutor->execute("DROP TRIGGER trg_transaction_no_update");
        $this->dbExecutor->execute(
            "UPDATE transaction SET checksum = '$legacyChecksum', previouschecksum = NULL, checksumversion = 1 " .
            "WHERE transactionid = {$head->getTransactionId()}"
        );

        $result = $this->transactionService->verifyChain($walletId);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Checksum version downgrade', implode('; ', $result->getErrors()));
    }
}
