<?php

namespace ByJG\Wallets\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\Serializer\BaseModel;
use ByJG\Wallets\Exception\AmountException;

/**
 * @OA\Definition(
 *   description="Wallet",
 * )
 *
 * @object:NodeName wallet
 */
#[TableAttribute('wallet')]
class WalletEntity extends BaseModel
{
    /**
     * @var int|null
     * @OA\Property()
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?int $walletid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $wallettypeid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $userid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $balance = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $reserved = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $available = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $price = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $extra = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    #[FieldAttribute(syncWithDb: false)]
    protected ?string $entrydate = null;

    #[FieldUuidAttribute(fieldName: 'last_uuid')]
    protected string|Literal|null $lastUuid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $minvalue = null;

    public function getWalletId(): ?int
    {
        return $this->walletid;
    }

    public function getWalletTypeId(): ?string
    {
        return $this->wallettypeid;
    }

    public function getUserId(): ?string
    {
        return $this->userid;
    }

    public function getBalance(): ?int
    {
        return $this->balance;
    }

    public function getReserved(): ?int
    {
        return $this->reserved;
    }

    public function getAvailable(): ?int
    {
        return $this->available;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function getEntrydate(): ?string
    {
        return $this->entrydate;
    }

    public function getMinValue(): ?int
    {
        return $this->minvalue;
    }

    public function setWalletId($walletid): void
    {
        $this->walletid = $walletid;
    }

    public function setWalletTypeId(?string $wallettypeid): void
    {
        $this->wallettypeid = $wallettypeid;
    }

    public function setUserId(?string $userid): void
    {
        $this->userid = $userid;
    }

    public function setBalance(?int $balance): void
    {
        $this->balance = $balance;
    }

    public function setReserved(?int $reserved): void
    {
        $this->reserved = $reserved;
    }

    public function setAvailable(?int $available): void
    {
        $this->available = $available;
    }

    public function setPrice(?int $price): void
    {
        $this->price = $price;
    }

    public function setExtra(?string $extra): void
    {
        $this->extra = $extra;
    }

    public function setEntryDate(?string $entryDate): void
    {
        $this->entrydate = $entryDate;
    }

    public function setMinValue(?int $minvalue): void
    {
        $this->minvalue = $minvalue;
    }

    public function getLastUuid(): Literal|string|null
    {
        return $this->lastUuid;
    }

    public function setLastUuid(Literal|string|null $lastUuid): void
    {
        $this->lastUuid = $lastUuid;
    }

    /**
     *
     * @throws AmountException
     */
    public function validate(): void
    {
        $minValue = $this->getMinValue();

        if ($this->getAvailable() < $minValue
            || $this->getBalance() < $minValue
            || $this->getReserved() < $minValue
        ) {
            throw new AmountException('Valor não pode ser menor que ' . $minValue);
        }
    }
}
