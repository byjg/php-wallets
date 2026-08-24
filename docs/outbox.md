# Transactional Outbox

## Why

Observers registered with `Repository::addObserver()` are notified in-process, after
the database commit. That is correct ordering, but the notification lives only in PHP
memory: if the process crashes between the commit and the callback — or the observer
throws — the event is lost, with no record it was ever owed. For consumers that must
not miss an event (message brokers, accounting exports, webhooks, fraud checks), that
is not good enough.

The transactional outbox closes the gap:

1. Inside the **same database transaction** that creates a ledger transaction, an
   event row is written to the `outbox` table. All-or-nothing: if the money moved,
   the event exists; if the operation rolled back, no ghost event.
2. A dispatcher — run from a worker loop or cron — delivers pending entries to a
   processor you implement (typically a publisher to RabbitMQ, SQS, Kafka, ...) and
   marks them processed. If the processor throws or the process dies mid-dispatch,
   the entry stays pending and is retried on the next run.

The outbox **complements** the observers, it does not replace them: observers remain
the fast, synchronous, best-effort channel; the outbox is the guaranteed one.

## Enabling

The outbox is opt-in. Pass an `OutboxRepository` as the 4th constructor argument of
`TransactionService` (the 3rd is the [checksum secret](transaction-operations.md#checksum-secret)):

```php
use ByJG\Wallets\Repository\OutboxRepository;
use ByJG\Wallets\Service\TransactionService;

$outboxRepo = new OutboxRepository($dbExecutor);

$transactionService = new TransactionService(
    $transactionRepo,
    $walletRepo,
    getenv('WALLET_CHECKSUM_SECRET'),
    $outboxRepo
);
```

From then on, every created ledger transaction — `addFunds()`, `withdrawFunds()`,
`reserveFundsForWithdraw()`, `reserveFundsForDeposit()`, `acceptFundsById()`,
`rejectFundsById()`, `acceptPartialFundsById()`, `transferFunds()`, `createWallet()`,
`overrideBalance()` — also writes one `transaction.created` outbox entry in the same
database transaction. Without the argument, nothing changes.

## Implementing the processor

```php
use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Outbox\OutboxProcessorInterface;

class RabbitMqPublisher implements OutboxProcessorInterface
{
    public function __construct(private AMQPChannel $channel)
    {
    }

    public function process(OutboxEntity $outboxEntry, TransactionEntity $transaction): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $outboxEntry->getEvent(),
            'uuid' => (string)$transaction->getUuid(),   // idempotency key for consumers
            'walletId' => $transaction->getWalletId(),
            'typeId' => $transaction->getTypeId(),
            'amount' => $transaction->getAmount(),
            'balance' => $transaction->getBalance(),
        ]), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

        // Throwing here keeps the entry pending; it is retried on the next dispatch()
        $this->channel->basic_publish($message, 'wallet-events');
    }
}
```

The `TransactionEntity` is hydrated with the `TransactionRepository` you configure,
so extended entities (custom fields, custom tables) arrive with their own class.

## Running the dispatcher

`TransactionService` never calls `OutboxService` — nothing connects them at runtime.
The request path only *writes* outbox rows; delivery is driven by a separate process
**you** run (a worker loop, a cron job, a supervisord/systemd service), which polls
the table and pushes pending entries to your processor. That decoupling is the
guarantee: if a request crashes right after commit, the worker still finds the row
on its next pass. The two sides share only the `outbox` table - they can live in
different processes, deployments, and database connections.

```php
// bin/outbox-worker.php - a separate long-running process
use ByJG\Wallets\Outbox\OutboxService;

$outboxService = new OutboxService($outboxRepo, $transactionRepo, new RabbitMqPublisher($channel));

while (true) {
    $result = $outboxService->dispatch(100);
    if ($result->getRemainingPending() === 0) {
        sleep(1);   // idle: nothing to deliver
    }
}
```

The cost of the decoupling is latency: an event reaches the broker up to one polling
interval after the commit, not instantly. If you need near-instant *and* guaranteed,
combine the channels: call `dispatch()` opportunistically from an in-process observer
(fast path) and keep the worker as the safety net for whatever a crashed request
left behind.

```php
$result = $outboxService->dispatch(100);

echo $result->getDispatched();       // delivered and marked processed
echo $result->getFailed();           // processor threw; still pending, retried next run
echo $result->getRemainingPending(); // backlog after this run

// Housekeeping, on your own schedule
$outboxService->purgeProcessed(30);  // delete entries processed more than 30 days ago
$outboxService->countPending();      // monitor the backlog (alert if it grows)
```

## Delivery semantics

- **At-least-once.** A crash after the processor succeeded but before the dispatcher
  committed redelivers the entry on the next run. Consumers must deduplicate — the
  transaction UUID is the natural idempotency key (the same key the ledger itself
  uses for [idempotent operations](transaction-operations.md#idempotency)).
- **Per-entry failure isolation.** A processor exception marks only that entry as
  failed (`attempts` incremented, `lasterror` recorded, still `pending`); the rest of
  the batch continues.
- **FIFO within a run**, ordered by `outboxid`. No ordering guarantee across failed
  retries: a failed entry can be delivered after newer entries that succeeded.
- **Single dispatcher.** Entries are claimed with a plain `FOR UPDATE` (no
  `SKIP LOCKED`), so run one dispatcher process. Concurrent dispatchers do not
  double-deliver committed work — they just serialize on the batch lock.
- **Keep the processor fast.** The batch is delivered inside the claim transaction;
  ledger writers inserting new entries can wait on its lock. Use small limits and
  quick publishes; do slow work in the consumer, not the processor.

## Schema

Migration 00004 creates the table:

| Column          | Type                          | Description                                    |
|-----------------|-------------------------------|------------------------------------------------|
| `outboxid`      | INT AUTO_INCREMENT            | Entry id (FIFO dispatch order)                 |
| `transactionid` | INT                           | The ledger transaction the event is about      |
| `uuid`          | BINARY(16)                    | The transaction UUID (consumer idempotency key)|
| `event`         | VARCHAR(40)                   | Event name (`transaction.created`)             |
| `status`        | ENUM('pending','processed')   | Delivery status                                |
| `attempts`      | INT                           | Delivery attempts so far                       |
| `lasterror`     | VARCHAR(500)                  | Last processor error (null after success)      |
| `createdat`     | TIMESTAMP                     | When the ledger transaction committed          |
| `processedat`   | TIMESTAMP NULL                | When the entry was delivered                   |

There is intentionally **no foreign key** to `transaction(transactionid)`: extended
entities may store their ledger in a different table. The same-transaction write and
the ledger immutability already guarantee the referenced row exists and never changes.
There is also no immutability trigger on `outbox` — the dispatcher updates it.
