<?php

namespace ByJG\AccountStatements\Entity;

use ByJG\AccountStatements\Exception\AmountException;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\Serializer\BaseModel;

/**
 * @OA\Definition(
 *   description="Account",
 * )
 *
 * @object:NodeName account
 */
#[TableAttribute('account')]
class AccountEntity extends BaseModel
{
    /**
     * @var int|null
     * @OA\Property()
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?int $accountid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $accounttypeid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $userid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $grossbalance = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $uncleared = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $netbalance = null;

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

    public function getAccountId(): ?int
    {
        return $this->accountid;
    }

    public function getAccountTypeId(): ?string
    {
        return $this->accounttypeid;
    }

    public function getUserId(): ?string
    {
        return $this->userid;
    }

    public function getGrossBalance(): ?int
    {
        return $this->grossbalance;
    }

    public function getUnCleared(): ?int
    {
        return $this->uncleared;
    }

    public function getNetBalance(): ?int
    {
        return $this->netbalance;
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

    public function setAccountId($accountid): void
    {
        $this->accountid = $accountid;
    }

    public function setAccountTypeId(?string $accounttypeid): void
    {
        $this->accounttypeid = $accounttypeid;
    }

    public function setUserId(?string $userid): void
    {
        $this->userid = $userid;
    }

    public function setGrossBalance(?int $grossbalance): void
    {
        $this->grossbalance = $grossbalance;
    }

    public function setUncleared(?int $unCleared): void
    {
        $this->uncleared = $unCleared;
    }

    public function setNetBalance(?int $netbalance): void
    {
        $this->netbalance = $netbalance;
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

        if ($this->getNetBalance() < $minValue
            || $this->getGrossBalance() < $minValue
            || $this->getUnCleared() < $minValue
        ) {
            throw new AmountException('Valor não pode ser menor que ' . $minValue);
        }
    }
}
