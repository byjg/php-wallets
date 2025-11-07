<?php


namespace ByJG\AccountTransactions\DTO;


use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Literal\Literal;

class TransactionDTO
{
    protected ?int $accountId = null;
    protected ?int $amount = null;

    protected ?string $description = null;
    protected ?string $referenceId = null;
    protected ?string $referenceSource = null;
    protected ?string $code = null;
    protected string|Literal|null $uuid = null;

    protected array $properties = [];

    /**
     * TransactionDTO constructor.
     * @param int|null $accountId
     * @param int|null $amount
     */
    public function __construct(?int $accountId, ?int $amount)
    {
        $this->accountId = $accountId;
        $this->amount = $amount;
    }

    public static function create(int $accountId, int $amount): static
    {
        return new TransactionDTO($accountId, $amount);
    }

    public static function createEmpty(): static
    {
        return new TransactionDTO(null, null);
    }

    public function hasAccount(): bool
    {
        return !empty($this->accountId) && (!is_null($this->amount));
    }

    public function setToTransaction(TransactionEntity $transaction): void
    {
        if (!empty($this->getAccountId())) {
            $transaction->setAccountId($this->getAccountId());
        }
        if (!empty($this->getAmount()) || $this->getAmount() === 0) {
            $transaction->setAmount($this->getAmount());
        }
        if (!empty($this->getDescription())) {
            $transaction->setDescription($this->getDescription());
        }
        if (!empty($this->getCode())) {
            $transaction->setCode($this->getCode());
        }
        if (!empty($this->getReferenceId())) {
            $transaction->setReferenceId($this->getReferenceId());
        }
        if (!empty($this->getReferenceSource())) {
            $transaction->setReferenceSource($this->getReferenceSource());
        }
        if (!empty($this->getUuid())) {
            $transaction->setUuid($this->getUuid());
        }

        foreach ($this->getProperties() as $name => $value) {
            if (method_exists($transaction, "set$name")) {
                $transaction->{"set$name"}($value);
            } else if (property_exists($transaction, $name)) {
                $transaction->{$name} = $value;
            } else {
                throw new \InvalidArgumentException("Property $name not found in TransactionEntity");
            }
        }
    }

    /**
     * @return int|null
     */
    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * @return int|null
     */
    public function getAmount(): ?int
    {
        return $this->amount;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    /**
     * @return string|null
     */
    public function getReferenceSource(): ?string
    {
        return $this->referenceSource;
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setAccountId(int $accountId): static
    {
        $this->accountId = $accountId;
        return $this;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string|null $referenceId
     * @return $this
     */
    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    /**
     * @param string|null $referenceSource
     * @return $this
     */
    public function setReferenceSource(?string $referenceSource): static
    {
        $this->referenceSource = $referenceSource;
        return $this;
    }

    /**
     * @param string|null $code
     * @return $this
     */
    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function setProperty(string $name, ?string $value): static
    {
        $this->properties[$name] = $value;
        return $this;
    }
    
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setUuid(string|Literal|null $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getUuid(): string|Literal|null
    {
        return $this->uuid;
    }

    public function calculateUuid(DatabaseExecutor $dbExecutor): mixed
    {
        return new Literal("X'" . $dbExecutor->getScalar("SELECT hex(uuid_to_bin(uuid()))") . "'");
    }
}