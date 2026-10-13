<?php

namespace Tests\Database;

use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Query;
use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\Wallets\Outbox\OutboxService;
use ByJG\Wallets\Repository\OutboxRepository;
use ByJG\Wallets\Service\TransactionService;
use ByJG\Wallets\Service\WalletService;
use ByJG\Wallets\Service\WalletTypeService;
use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\BaseDALTrait;
use Tests\Classes\FailingDepositTransactionService;
use Tests\Classes\FailingWalletRepository;
use Tests\Classes\OutboxProcessorRecorder;
use Tests\Classes\TransactionExtended;

/**
 * Transactional outbox: an outbox entry is written in the same database
 * transaction as the ledger row (all-or-nothing), and OutboxService::dispatch()
 * delivers pending entries to a processor with at-least-once semantics.
 */
class OutboxTest extends TestCase
{
    use BaseDALTrait;

    #[\Override]
    protected function setUp(): void
    {
        $this->dbSetUp();
        $this->prepareObjects(outboxRepository: new OutboxRepository($this->dbExecutor));
        $this->createDummyData();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->dbClear();
    }

    private function outboxCount(): int
    {
        return intval($this->dbExecutor->getScalar('SELECT count(*) FROM outbox'));
    }

    /**
     * @return OutboxEntity[]
     */
    private function getEntriesForTransaction(int $transactionId): array
    {
        $query = Query::getInstance()
            ->table('outbox')
            ->where('transactionid = :id', ['id' => $transactionId]);

        return $this->outboxRepository->getRepository()->getByQuery($query);
    }

    private function makeOutboxService(?Closure $processCallback = null): array
    {
        $recorder = new OutboxProcessorRecorder($processCallback);
        $service = new OutboxService(
            $this->outboxRepository,
            $this->transactionService->getRepository(),
            $recorder
        );

        return [$service, $recorder];
    }

    /**
     * Dispatch everything currently pending so a test starts from a clean slate
     * (createDummyData already produced entries for the dummy wallet).
     */
    private function drainOutbox(): void
    {
        [$service] = $this->makeOutboxService();
        $service->dispatch(1000);
    }

    public function testDisabledByDefaultWritesNothing(): void
    {
        $countBefore = $this->outboxCount();

        // Services without the outbox repository: default behavior must not change
        $this->prepareObjects();
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->transactionService->addFunds(TransactionDTO::create($walletId, 500));

        $this->assertEquals($countBefore, $this->outboxCount());
    }

    public function testAddFundsWritesPendingEntry(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 500)->setDescription('Add')
        );

        $entries = $this->getEntriesForTransaction($transaction->getTransactionId());
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertEquals(OutboxEntity::EVENT_TRANSACTION_CREATED, $entry->getEvent());
        $this->assertEquals(OutboxEntity::STATUS_PENDING, $entry->getStatus());
        $this->assertEquals(0, $entry->getAttempts());
        $this->assertEquals(
            HexUuidLiteral::getFormattedUuid($transaction->getUuid()),
            HexUuidLiteral::getFormattedUuid($entry->getUuid())
        );
        $this->assertNotEmpty($entry->getCreatedAt());
        $this->assertNull($entry->getProcessedAt());
        $this->assertNull($entry->getLastError());
    }

    public function testEveryWritePathWritesAnEntry(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $otherWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-2", 1000);

        $this->transactionService->addFunds(TransactionDTO::create($walletId, 500));
        $this->transactionService->withdrawFunds(TransactionDTO::create($walletId, 100));

        $reserveAccept = $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create($walletId, 200));
        $this->transactionService->acceptFundsById($reserveAccept->getTransactionId());

        $reserveReject = $this->transactionService->reserveFundsForDeposit(TransactionDTO::create($walletId, 300));
        $this->transactionService->rejectFundsById($reserveReject->getTransactionId());

        $reservePartial = $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create($walletId, 200));
        $this->transactionService->acceptPartialFundsById(
            $reservePartial->getTransactionId(),
            TransactionDTO::createEmpty()->setAmount(150),
            TransactionDTO::createEmpty()
        );

        $this->walletService->overrideBalance($walletId, 5000);
        $this->walletService->transferFunds($walletId, $otherWalletId, 250);

        // One outbox entry per ledger transaction, no more, no less
        $transactionCount = intval($this->dbExecutor->getScalar(
            'SELECT count(*) FROM transaction WHERE walletid IN (:w1, :w2)',
            ['w1' => $walletId, 'w2' => $otherWalletId]
        ));
        $outboxCount = intval($this->dbExecutor->getScalar(
            'SELECT count(*) FROM outbox INNER JOIN transaction ON outbox.transactionid = transaction.transactionid ' .
            'WHERE transaction.walletid IN (:w1, :w2)',
            ['w1' => $walletId, 'w2' => $otherWalletId]
        ));
        $this->assertGreaterThanOrEqual(11, $transactionCount);
        $this->assertEquals($transactionCount, $outboxCount);
    }

    public function testRollbackDiscardsOutboxEntry(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $reserved = $this->transactionService->reserveFundsForWithdraw(TransactionDTO::create($walletId, 300));
        $countBefore = $this->outboxCount();

        // The accept path writes the transaction row AND the outbox entry before
        // the wallet save fails: both must be rolled back together
        $failingService = new TransactionService(
            $this->transactionService->getRepository(),
            new FailingWalletRepository($this->dbExecutor, WalletEntity::class),
            null,
            $this->outboxRepository
        );

        try {
            $failingService->acceptFundsById($reserved->getTransactionId());
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $ex) {
            $this->assertEquals('Simulated wallet save failure', $ex->getMessage());
        }

        $this->assertEquals($countBefore, $this->outboxCount());
    }

    public function testTransferRollbackDiscardsOutboxEntries(): void
    {
        $sourceWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $targetWalletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-2", 500);
        $countBefore = $this->outboxCount();

        // The withdraw leg records an outbox entry, then the deposit fails:
        // the whole transfer (including the entry) must be rolled back
        $failingTransactionService = new FailingDepositTransactionService(
            $this->transactionService->getRepository(),
            $this->walletService->getRepository(),
            null,
            $this->outboxRepository
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

        $this->assertEquals($countBefore, $this->outboxCount());
    }

    public function testDispatchHappyPath(): void
    {
        $this->drainOutbox();

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 500)->setDescription('Add')
        );

        [$service, $recorder] = $this->makeOutboxService();
        $result = $service->dispatch();

        // Opening balance + addFunds
        $this->assertEquals(2, $result->getDispatched());
        $this->assertEquals(0, $result->getFailed());
        $this->assertEquals(0, $result->getRemainingPending());
        $this->assertCount(2, $recorder->processed);

        // The processor received the entry and the hydrated ledger transaction
        $delivered = array_values(array_filter(
            $recorder->processed,
            fn (array $item) => $item['entry']->getTransactionId() === $transaction->getTransactionId()
        ));
        $this->assertCount(1, $delivered);
        $this->assertEquals(500, $delivered[0]['transaction']->getAmount());
        $this->assertEquals('Add', $delivered[0]['transaction']->getDescription());
        $this->assertEquals(
            HexUuidLiteral::getFormattedUuid($transaction->getUuid()),
            HexUuidLiteral::getFormattedUuid($delivered[0]['transaction']->getUuid())
        );

        $entry = $this->getEntriesForTransaction($transaction->getTransactionId())[0];
        $this->assertEquals(OutboxEntity::STATUS_PROCESSED, $entry->getStatus());
        $this->assertEquals(1, $entry->getAttempts());
        $this->assertNotEmpty($entry->getProcessedAt());
        $this->assertNull($entry->getLastError());
    }

    public function testProcessorThrowKeepsEntryPendingAndRetries(): void
    {
        $this->drainOutbox();

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $failing = $this->transactionService->addFunds(TransactionDTO::create($walletId, 500));

        // The processor fails only for the addFunds event: the rest of the batch continues
        [$service] = $this->makeOutboxService(function (OutboxEntity $entry) use ($failing) {
            if ($entry->getTransactionId() === $failing->getTransactionId()) {
                throw new RuntimeException('queue down');
            }
        });
        $result = $service->dispatch();

        $this->assertEquals(1, $result->getDispatched());  // opening balance
        $this->assertEquals(1, $result->getFailed());
        $this->assertEquals(1, $result->getRemainingPending());

        $entry = $this->getEntriesForTransaction($failing->getTransactionId())[0];
        $this->assertEquals(OutboxEntity::STATUS_PENDING, $entry->getStatus());
        $this->assertEquals(1, $entry->getAttempts());
        $this->assertStringContainsString('queue down', $entry->getLastError());
        $this->assertNull($entry->getProcessedAt());

        // Next run redelivers and succeeds (at-least-once)
        [$service, $recorder] = $this->makeOutboxService();
        $result = $service->dispatch();

        $this->assertEquals(1, $result->getDispatched());
        $this->assertEquals(0, $result->getFailed());
        $this->assertEquals(0, $result->getRemainingPending());
        $this->assertCount(1, $recorder->processed);

        $entry = $this->getEntriesForTransaction($failing->getTransactionId())[0];
        $this->assertEquals(OutboxEntity::STATUS_PROCESSED, $entry->getStatus());
        $this->assertEquals(2, $entry->getAttempts());
        $this->assertNull($entry->getLastError());
    }

    public function testDispatchLimitAndFifoOrder(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->drainOutbox();

        $tx1 = $this->transactionService->addFunds(TransactionDTO::create($walletId, 100));
        $tx2 = $this->transactionService->addFunds(TransactionDTO::create($walletId, 200));
        $tx3 = $this->transactionService->addFunds(TransactionDTO::create($walletId, 300));

        [$service, $recorder] = $this->makeOutboxService();
        $result = $service->dispatch(2);

        $this->assertEquals(2, $result->getDispatched());
        $this->assertEquals(1, $result->getRemainingPending());

        // FIFO: the two oldest entries were delivered, the newest is still pending
        $this->assertCount(2, $recorder->processed);
        $this->assertEquals($tx1->getTransactionId(), $recorder->processed[0]['entry']->getTransactionId());
        $this->assertEquals($tx2->getTransactionId(), $recorder->processed[1]['entry']->getTransactionId());
        $this->assertEquals(
            OutboxEntity::STATUS_PENDING,
            $this->getEntriesForTransaction($tx3->getTransactionId())[0]->getStatus()
        );
    }

    public function testPurgeProcessed(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $this->drainOutbox();
        $processedCount = $this->outboxCount();
        $this->assertGreaterThan(0, $processedCount);

        // One pending entry that the purge must not touch
        $pending = $this->transactionService->addFunds(TransactionDTO::create($walletId, 100));

        [$service] = $this->makeOutboxService();

        // Fresh processed entries are kept when an age is required
        $this->assertEquals(0, $service->purgeProcessed(30));
        $this->assertEquals($processedCount + 1, $this->outboxCount());

        // Unconditional purge removes only the processed entries
        $this->assertEquals($processedCount, $service->purgeProcessed());
        $this->assertEquals(1, $this->outboxCount());
        $this->assertEquals(
            OutboxEntity::STATUS_PENDING,
            $this->getEntriesForTransaction($pending->getTransactionId())[0]->getStatus()
        );
        $this->assertEquals(1, $service->countPending());
    }

    public function testDispatchHydratesExtendedEntity(): void
    {
        $this->prepareObjects(
            transactionEntity: TransactionExtended::class,
            outboxRepository: new OutboxRepository($this->dbExecutor)
        );

        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);
        $transaction = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 500)->setProperty('extraProperty', 'Extra')
        );

        [$service, $recorder] = $this->makeOutboxService();
        $service->dispatch();

        $delivered = array_values(array_filter(
            $recorder->processed,
            fn (array $item) => $item['entry']->getTransactionId() === $transaction->getTransactionId()
        ));
        $this->assertCount(1, $delivered);
        $this->assertInstanceOf(TransactionExtended::class, $delivered[0]['transaction']);
        $this->assertEquals('Extra', $delivered[0]['transaction']->getExtraProperty());
    }

    public function testIdempotentReplayWritesSingleEntry(): void
    {
        $walletId = $this->walletService->createWallet('USDTEST', "___TESTUSER-1", 1000);

        $uuid = 'F47AC10B-58CC-4372-A567-0E02B2C3D479';
        $first = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 500)->setUuid($uuid)->setDescription('Payment')
        );
        $replay = $this->transactionService->addFunds(
            TransactionDTO::create($walletId, 500)->setUuid($uuid)->setDescription('Payment')
        );

        $this->assertEquals($first->getTransactionId(), $replay->getTransactionId());
        $this->assertCount(1, $this->getEntriesForTransaction($first->getTransactionId()));
    }
}
