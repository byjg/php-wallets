<?php

namespace ByJG\Wallets\DTO;

/**
 * Result of one OutboxService::dispatch() run.
 */
class OutboxDispatchResult
{
    /**
     * @param int $dispatched Entries delivered and marked processed in this run
     * @param int $failed Entries whose processor threw; they remain pending for retry
     * @param int $remainingPending Entries still pending after this run (including failed ones)
     */
    public function __construct(
        protected int $dispatched = 0,
        protected int $failed = 0,
        protected int $remainingPending = 0
    ) {
    }

    public function getDispatched(): int
    {
        return $this->dispatched;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getRemainingPending(): int
    {
        return $this->remainingPending;
    }
}
