<?php


namespace ByJG\Wallets\DTO;


use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use InvalidArgumentException;

class TransactionDTO
{
    protected ?int $walletId = null;
    protected ?int $amount = null;

    protected ?string $description = null;
    protected ?string $referenceId = null;
    protected ?string $referenceSource = null;
    protected ?string $code = null;
    protected ?int $scale = null;
    protected string|Literal|null $uuid = null;

    protected array $properties = [];

    /**
     * TransactionDTO constructor.
     * @param int|null $walletId
     * @param int|null $amount
     */
    public function __construct(?int $walletId, ?int $amount)
    {
        $this->walletId = $walletId;
        $this->amount = $amount;
    }

    public static function create(int $walletId, int $amount): static
    {
        return new TransactionDTO($walletId, $amount);
    }

    public static function createEmpty(): static
    {
        return new TransactionDTO(null, null);
    }

    public function hasWallet(): bool
    {
        return !empty($this->walletId) && (!is_null($this->amount));
    }

    public function setToTransaction(TransactionEntity $transaction): void
    {
        if (!empty($this->getWalletId())) {
            $transaction->setWalletId($this->getWalletId());
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
        if (!empty($this->get))

        foreach ($this->getProperties() as $name => $value) {
            if (method_exists($transaction, "set$name")) {
                $transaction->{"set$name"}($value);
            } else if (property_exists($transaction, $name)) {
                $transaction->{$name} = $value;
            } else {
                throw new InvalidArgumentException("Property $name not found in TransactionEntity");
            }
        }
    }

    /**
     * @return int|null
     */
    public function getWalletId(): ?int
    {
        return $this->walletId;
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

    public function setWalletId(int $walletId): static
    {
        $this->walletId = $walletId;
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

    public function setUuid(string|Literal|null $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getUuid(): string|Literal|null
    {
        return $this->uuid;
    }

    /**
     * @throws DatabaseException
     * @throws XmlUtilException
     * @throws DbDriverNotConnected
     * @throws FileException
     */
    public function calculateUuid(DatabaseExecutor $dbExecutor): mixed
    {
        return new Literal("X'" . $dbExecutor->getScalar("SELECT hex(uuid_to_bin(uuid()))") . "'");
    }


    public function setScale(?int $scale): static
    {
        $this->scale = $scale;
        return $this;
    }

    public function getScale(): ?int
    {
        return $this->scale;
    }

    public function setAmountFloat(float $amount, int $scale = 2): static
    {
        $this->amount = intval(round($amount * (float)pow(10, $scale)));
        $this->scale = $scale;
        return $this;
    }
}