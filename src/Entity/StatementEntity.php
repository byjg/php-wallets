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
 *   description="Statement",
 * )
 *
 * @object:NodeName statement
 */
#[TableAttribute('statement')]
class StatementEntity extends BaseModel
{

    const BALANCE = "B"; // Inicia um novo valor desprezando os antigos
    const DEPOSIT = "D"; // Adiciona um valor imediatamente ao banco
    const WITHDRAW = "W";
    const REJECT = "R";
    const DEPOSIT_BLOCKED = "DB";
    const WITHDRAW_BLOCKED = "WB";

    /**
     * @var int|null
     * @OA\Property()
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?int $statementid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $accountid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $typeid = null;

    /**
     * @var float|string|int|null
     * @OA\Property()
     */
    protected float|string|int|null $amount = null;

    /**
     * @var float|string|int|null
     * @OA\Property()
     */
    protected float|string|int|null $price = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    #[FieldAttribute(syncWithDb: false)]
    protected ?string $date = null;

    /**
     * @var float|string|int|null
     * @OA\Property()
     */
    protected float|string|int|null $grossbalance = null;

    /**
     * @var float|string|int|null
     * @OA\Property()
     */
    protected float|string|int|null $uncleared = null;

    /**
     * @var float|string|int|null
     * @OA\Property()
     */
    protected float|string|int|null $netbalance = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $code = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $description = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $statementparentid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $referenceid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $referencesource = null;

    /**
     * @var string|Literal|null
     * @OA\Property()
     */
    #[FieldUuidAttribute()]
    protected string|Literal|null $uuid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $accounttypeid = null;

    public function getStatementId(): ?int
    {
        return $this->statementid;
    }

    /**
     * @return int|null
     */
    public function getAccountId(): ?int
    {
        return $this->accountid;
    }

    /**
     * @return string|null
     */
    public function getTypeId(): ?string
    {
        return $this->typeid;
    }

    /**
     * @return float|string|int|null
     */
    public function getAmount(): float|string|int|null
    {
        return $this->amount;
    }

    /**
     * @return float|string|int|null
     */
    public function getPrice(): float|string|int|null
    {
        return $this->price;
    }

    /**
     * @return string|null
     */
    public function getDate(): ?string
    {
        return $this->date;
    }

    /**
     * @return float|string|int|null
     */
    public function getGrossBalance(): float|string|int|null
    {
        return $this->grossbalance;
    }

    /**
     * @return float|string|int|null
     */
    public function getUnCleared(): float|string|int|null
    {
        return $this->uncleared;
    }

    /**
     * @return float|string|int|null
     */
    public function getNetBalance(): float|string|int|null
    {
        return $this->netbalance;
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return int|null
     */
    public function getStatementParentId(): ?int
    {
        return $this->statementparentid;
    }

    /**
     * @return string|null
     */
    public function getReferenceId(): ?string
    {
        return $this->referenceid;
    }

    /**
     * @return string|null
     */
    public function getReferenceSource(): ?string
    {
        return $this->referencesource;
    }

    /**
     * @return string|null
     */
    public function getAccountTypeId(): ?string
    {
        return $this->accounttypeid;
    }

    public function setStatementId(?int $statementid): void
    {
        $this->statementid = $statementid;
    }

    public function setAccountId(?int $accountid): void
    {
        $this->accountid = $accountid;
    }

    public function setTypeId(?string $typeid): void
    {
        $this->typeid = $typeid;
    }

    public function setAmount(float|string|int|null $amount): void
    {
        $this->amount = $amount;
    }

    public function setPrice(float|string|int|null $price): void
    {
        $this->price = $price;
    }

    public function setDate(?string $date): void
    {
        $this->date = $date;
    }

    public function setGrossBalance(float|string|int|null $grossbalance): void
    {
        $this->grossbalance = $grossbalance;
    }

    public function setUnCleared(float|string|int|null $uncleared): void
    {
        $this->uncleared = $uncleared;
    }

    public function setNetBalance(float|string|int|null $netbalance): void
    {
        $this->netbalance = $netbalance;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setStatementParentId(?int $statementparentid): void
    {
        $this->statementparentid = $statementparentid;
    }

    public function setAccountTypeId(?string $accounttypeid): void
    {
        $this->accounttypeid = $accounttypeid;
    }

    public function setReferenceId(?string $referenceid): void
    {
        $this->referenceid = $referenceid;
    }

    public function setReferenceSource(?string $referencesource): void
    {
        $this->referencesource = $referencesource;
    }

    public function getUuid(): string|Literal|null
    {
        return $this->uuid;
    }

    public function setUuid(string|Literal|null $uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @var AccountEntity|null
     */
    protected ?AccountEntity $account = null;

    public function attachAccount(AccountEntity $account): void
    {
        $this->setAccountId($account->getAccountId());
        $this->setAccountTypeId($account->getAccountTypeId());
        $this->setGrossBalance($account->getGrossBalance());
        $this->setNetBalance($account->getNetBalance());
        $this->setUnCleared($account->getUnCleared());
        $this->setPrice($account->getPrice());

        $this->account = $account;
    }

    /**
     * @throws AmountException
     * @throws AmountException
     *
     * @return void
     */
    public function validate()
    {
        if ($this->getAmount() < 0) {
            throw new AmountException('Amount não pode ser menor que zero');
        }

        if (empty($this->account)) {
            return;
        }

        if ($this->getNetBalance() < $this->account->getMinValue()
            || $this->getGrossBalance() < $this->account->getMinValue()
            || $this->getUnCleared() < $this->account->getMinValue()
        ) {
            throw new AmountException('Valor não pode ser menor que ' . $this->account->getMinValue());
        }
    }
}
