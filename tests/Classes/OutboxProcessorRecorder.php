<?php

namespace Tests\Classes;

use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Outbox\OutboxProcessorInterface;
use Closure;

/**
 * Outbox processor for tests: records every delivered event. An optional
 * callback allows a test to make process() throw and exercise the retry path.
 */
class OutboxProcessorRecorder implements OutboxProcessorInterface
{
    /** @var array<array{entry: OutboxEntity, transaction: TransactionEntity}> */
    public array $processed = [];

    public function __construct(
        private readonly ?Closure $processCallback = null
    ) {
    }

    #[\Override]
    public function process(OutboxEntity $outboxEntry, TransactionEntity $transaction): void
    {
        $this->processed[] = ['entry' => $outboxEntry, 'transaction' => $transaction];
        if ($this->processCallback !== null) {
            ($this->processCallback)($outboxEntry, $transaction);
        }
    }
}
