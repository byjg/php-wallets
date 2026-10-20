<?php

namespace ByJG\Wallets\Outbox;

use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\TransactionEntity;

/**
 * User-implemented delivery target for outbox events - typically a publisher
 * to a message broker (RabbitMQ, SQS, Kafka, ...), but any side effect that
 * must not be lost works.
 *
 * Delivery is at-least-once: throwing any Throwable from process() keeps the
 * entry pending (attempts incremented, error recorded) and it is retried on
 * the next OutboxService::dispatch() run. A crash between a successful
 * process() and the dispatcher commit also redelivers the entry, so consumers
 * must deduplicate - the transaction UUID ($transaction->getUuid()) is the
 * natural idempotency key.
 */
interface OutboxProcessorInterface
{
    /**
     * Deliver one outbox event. Throw to have the entry retried later.
     *
     * @param OutboxEntity $outboxEntry The outbox entry (event name, attempts, timestamps)
     * @param TransactionEntity $transaction The ledger transaction the event is about,
     *        hydrated with the repository the service was configured with (extended
     *        entities are preserved)
     */
    public function process(OutboxEntity $outboxEntry, TransactionEntity $transaction): void;
}
