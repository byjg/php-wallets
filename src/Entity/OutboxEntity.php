<?php

namespace ByJG\Wallets\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\Serializer\BaseModel;

/**
 * A transactional-outbox entry: an event about a ledger transaction, written
 * inside the same database transaction that created the transaction row.
 * Delivered by OutboxService::dispatch() with at-least-once semantics.
 *
 * @object:NodeName outbox
 */
#[TableAttribute('outbox')]
class OutboxEntity extends BaseModel
{
    const EVENT_TRANSACTION_CREATED = 'transaction.created';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';

    /**
     * @var int|null
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?int $outboxid = null;

    /**
     * @var int|null
     */
    protected ?int $transactionid = null;

    /**
     * @var string|Literal|null
     */
    #[FieldUuidAttribute]
    protected string|Literal|null $uuid = null;

    /**
     * @var string|null
     */
    protected ?string $event = null;

    /**
     * @var string|null
     */
    protected ?string $status = null;

    /**
     * @var int|null
     */
    protected ?int $attempts = null;

    /**
     * @var string|null
     */
    protected ?string $lasterror = null;

    /**
     * @var string|null
     */
    #[FieldAttribute(syncWithDb: false)]
    protected ?string $createdat = null;

    /**
     * @var string|null
     */
    protected ?string $processedat = null;

    public function getOutboxId(): ?int
    {
        return $this->outboxid;
    }

    public function setOutboxId(?int $outboxid): void
    {
        $this->outboxid = $outboxid;
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionid;
    }

    public function setTransactionId(?int $transactionid): void
    {
        $this->transactionid = $transactionid;
    }

    public function getUuid(): string|Literal|null
    {
        return $this->uuid;
    }

    public function setUuid(string|Literal|null $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): void
    {
        $this->event = $event;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    public function setAttempts(?int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lasterror;
    }

    public function setLastError(?string $lasterror): void
    {
        $this->lasterror = $lasterror;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdat;
    }

    public function setCreatedAt(?string $createdat): void
    {
        $this->createdat = $createdat;
    }

    public function getProcessedAt(): ?string
    {
        return $this->processedat;
    }

    public function setProcessedAt(?string $processedat): void
    {
        $this->processedat = $processedat;
    }
}
