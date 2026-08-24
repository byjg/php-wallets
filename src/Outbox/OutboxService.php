<?php

namespace ByJG\Wallets\Outbox;

use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\Wallets\DTO\OutboxDispatchResult;
use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Exception\TransactionException;
use ByJG\Wallets\Repository\OutboxRepository;
use ByJG\Wallets\Repository\TransactionRepository;
use Throwable;

/**
 * Dispatches transactional-outbox entries to a user-implemented processor
 * (typically a message-broker publisher). Run dispatch() from a worker loop
 * or a cron job.
 *
 * Delivery semantics:
 * - At-least-once: a crash after the processor succeeded but before the
 *   dispatcher committed redelivers the entry on the next run. Consumers
 *   deduplicate by transaction UUID.
 * - Per-entry failure isolation: a processor Throwable marks only that entry
 *   as failed (attempts incremented, error recorded, still pending); the rest
 *   of the batch continues.
 * - FIFO within a run (ordered by outboxid).
 *
 * Deployment note: entries are claimed with a plain FOR UPDATE (no SKIP
 * LOCKED), so run a single dispatcher process. Concurrent dispatchers do not
 * double-deliver committed work, they just serialize on the batch lock. Keep
 * the processor fast: ledger writers inserting new pending entries can wait
 * on the dispatcher's lock at the tail of the pending index range.
 */
class OutboxService
{
    public function __construct(
        protected OutboxRepository $outboxRepository,
        protected TransactionRepository $transactionRepository,
        protected OutboxProcessorInterface $processor
    ) {
    }

    /**
     * Deliver up to $limit pending entries to the processor.
     *
     * @param int $limit
     * @return OutboxDispatchResult
     * @throws Throwable
     */
    public function dispatch(int $limit = 100): OutboxDispatchResult
    {
        $executor = $this->outboxRepository->getExecutor();

        $dispatched = 0;
        $failed = 0;

        // Short claim transaction: READ COMMITTED is enough (rows are claimed
        // with FOR UPDATE) and avoids the wider gap locks of SERIALIZABLE
        $executor->beginTransaction(IsolationLevelEnum::READ_COMMITTED, allowJoin: true);
        try {
            $entries = $this->outboxRepository->getPendingForUpdate($limit);

            foreach ($entries as $entry) {
                try {
                    /** @var TransactionEntity|null $transaction */
                    $transaction = $this->transactionRepository->getById((int)$entry->getTransactionId());
                    if (empty($transaction)) {
                        throw new TransactionException(
                            "Outbox entry {$entry->getOutboxId()}: transaction {$entry->getTransactionId()} not found"
                        );
                    }

                    $this->processor->process($entry, $transaction);

                    $entry->setStatus(OutboxEntity::STATUS_PROCESSED);
                    $entry->setProcessedAt(date('Y-m-d H:i:s'));
                    $entry->setAttempts(intval($entry->getAttempts()) + 1);
                    $entry->setLastError(null);
                    $dispatched++;
                } catch (Throwable $ex) {
                    $entry->setAttempts(intval($entry->getAttempts()) + 1);
                    $entry->setLastError(substr(get_class($ex) . ': ' . $ex->getMessage(), 0, 500));
                    $failed++;
                }
                $this->outboxRepository->save($entry);
            }

            $executor->commitTransaction();
        } catch (Throwable $ex) {
            if ($executor->hasActiveTransaction()) {
                $executor->rollbackTransaction();
            }
            throw $ex;
        }

        return new OutboxDispatchResult($dispatched, $failed, $this->outboxRepository->countPending());
    }

    /**
     * Delete processed entries, optionally only those processed more than
     * $olderThanDays days ago. Returns the number of entries purged.
     *
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function purgeProcessed(?int $olderThanDays = null): int
    {
        return $this->outboxRepository->purgeProcessed($olderThanDays);
    }

    /**
     * Number of entries still waiting to be dispatched.
     *
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function countPending(): int
    {
        return $this->outboxRepository->countPending();
    }
}
