<?php


namespace ByJG\AccountStatements\DTO;


use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\MicroOrm\Literal\Literal;

class StatementDTO
{
    protected ?int $accountId = null;
    protected ?float $amount = null;

    protected ?string $description = null;
    protected ?string $referenceId = null;
    protected ?string $referenceSource = null;
    protected ?string $code = null;
    protected string|Literal|null $uuid = null;
    
    protected array $properties = [];

    /**
     * StatementDTO constructor.
     * @param int|null $accountId
     * @param float|null $amount
     */
    public function __construct(?int $accountId, ?float $amount)
    {
        $this->accountId = $accountId;
        $this->amount = $amount;
    }

    public static function create(int $accountId, float $amount): static
    {
        return new StatementDTO($accountId, $amount);
    }

    public static function createEmpty(): static
    {
        return new StatementDTO(null, null);
    }

    public function hasAccount(): bool
    {
        return !empty($this->accountId) && (!is_null($this->amount));
    }

    public function setToStatement(StatementEntity $statement): void
    {
        if (!empty($this->getAccountId())) {
            $statement->setAccountId($this->getAccountId());
        }
        if (!empty($this->getAmount()) || $this->getAmount() === 0.0) {
            $statement->setAmount($this->getAmount());
        }
        if (!empty($this->getDescription())) {
            $statement->setDescription($this->getDescription());
        }
        if (!empty($this->getCode())) {
            $statement->setCode($this->getCode());
        }
        if (!empty($this->getReferenceId())) {
            $statement->setReferenceId($this->getReferenceId());
        }
        if (!empty($this->getReferenceSource())) {
            $statement->setReferenceSource($this->getReferenceSource());
        }
        if (!empty($this->getUuid())) {
            $statement->setUuid($this->getUuid());
        }

        foreach ($this->getProperties() as $name => $value) {
            if (method_exists($statement, "set$name")) {
                $statement->{"set$name"}($value);
            } else if (property_exists($statement, $name)) {
                $statement->{$name} = $value;
            } else {
                throw new \InvalidArgumentException("Property $name not found in StatementEntity");
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
     * @return float|null
     */
    public function getAmount(): ?float
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

    public function setAmount(float $amount): static
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

    public function calculateUuid(DbDriverInterface $dbDriver): mixed
    {
        return new Literal("X'" . $dbDriver->getScalar("SELECT hex(uuid_to_bin(uuid()))") . "'");
    }
}