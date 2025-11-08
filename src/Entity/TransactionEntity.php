<?php

namespace ByJG\Wallets\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\FieldUuidAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\Serializer\BaseModel;
use ByJG\Wallets\Exception\AmountException;

/**
 * @OA\Definition(
 *   description="Transaction",
 * )
 *
 * @object:NodeName transaction
 */
#[TableAttribute('transaction')]
class TransactionEntity extends BaseModel
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
    protected ?int $transactionid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $walletid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $typeid = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $amount = null;

    /**
     * @var int|null
     * @OA\Property()
     */
    protected ?int $scale = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    #[FieldAttribute(syncWithDb: false)]
    protected ?string $date = null;

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
    protected ?int $transactionparentid = null;

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
     * @var string|Literal|null
     * @OA\Property()
     */
    #[FieldUuidAttribute()]
    protected string|Literal|null $previousuuid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $wallettypeid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $checksum = null;

    public function getTransactionId(): ?int
    {
        return $this->transactionid;
    }

    /**
     * @return int|null
     */
    public function getWalletId(): ?int
    {
        return $this->walletid;
    }

    /**
     * @return string|null
     */
    public function getTypeId(): ?string
    {
        return $this->typeid;
    }

    /**
     * @return int|null
     */
    public function getAmount(): ?int
    {
        return $this->amount;
    }

    /**
     * @return int|null
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * @return string|null
     */
    public function getDate(): ?string
    {
        return $this->date;
    }

    /**
     * @return int|null
     */
    public function getBalance(): ?int
    {
        return $this->balance;
    }

    /**
     * @return int|null
     */
    public function getReserved(): ?int
    {
        return $this->reserved;
    }

    /**
     * @return int|null
     */
    public function getAvailable(): ?int
    {
        return $this->available;
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
    public function getTransactionParentId(): ?int
    {
        return $this->transactionparentid;
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
    public function getWalletTypeId(): ?string
    {
        return $this->wallettypeid;
    }

    public function setTransactionId(?int $transactionid): void
    {
        $this->transactionid = $transactionid;
    }

    public function setWalletId(?int $walletid): void
    {
        $this->walletid = $walletid;
    }

    public function setTypeId(?string $typeid): void
    {
        $this->typeid = $typeid;
    }

    public function setAmount(?int $amount): void
    {
        $this->amount = $amount;
    }

    public function setScale(?int $scale): void
    {
        $this->scale = $scale;
    }

    public function setDate(?string $date): void
    {
        $this->date = $date;
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

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setTransactionParentId(?int $transactionparentid): void
    {
        $this->transactionparentid = $transactionparentid;
    }

    public function setWalletTypeId(?string $wallettypeid): void
    {
        $this->wallettypeid = $wallettypeid;
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
     * Get the amount as a float value based on scale.
     * For example: with scale=2, amount=1234 returns 12.34
     *
     * @return float|null
     */
    public function getAmountFloat(): ?float
    {
        if ($this->amount === null || $this->scale === null) {
            return null;
        }
        return $this->amount / pow(10, $this->scale);
    }

    /**
     * Get the balance as a float value based on scale.
     * For example: with scale=2, balance=1234 returns 12.34
     *
     * @return float|null
     */
    public function getBalanceFloat(): ?float
    {
        if ($this->balance === null || $this->scale === null) {
            return null;
        }
        return $this->balance / pow(10, $this->scale);
    }

    /**
     * Get the reserved as a float value based on scale.
     * For example: with scale=2, reserved=1234 returns 12.34
     *
     * @return float|null
     */
    public function getReservedFloat(): ?float
    {
        if ($this->reserved === null || $this->scale === null) {
            return null;
        }
        return $this->reserved / pow(10, $this->scale);
    }

    /**
     * Get the available as a float value based on scale.
     * For example: with scale=2, available=1234 returns 12.34
     *
     * @return float|null
     */
    public function getAvailableFloat(): ?float
    {
        if ($this->available === null || $this->scale === null) {
            return null;
        }
        return $this->available / pow(10, $this->scale);
    }

    /**
     * @var WalletEntity|null
     */
    protected ?WalletEntity $wallet = null;

    public function attachWallet(WalletEntity $wallet): void
    {
        $this->setWalletId($wallet->getWalletId());
        $this->setWalletTypeId($wallet->getWalletTypeId());
        $this->setBalance($wallet->getBalance());
        $this->setAvailable($wallet->getAvailable());
        $this->setReserved($wallet->getReserved());
        $this->setScale($wallet->getScale());

        $this->wallet = $wallet;
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
            throw new AmountException('Amount cannot be less than zero');
        }

        if (empty($this->wallet)) {
            return;
        }

        if ($this->getAvailable() < $this->wallet->getMinValue()
            || $this->getBalance() < $this->wallet->getMinValue()
            || $this->getReserved() < $this->wallet->getMinValue()
        ) {
            throw new AmountException('Value cannot be less than ' . $this->wallet->getMinValue());
        }
    }

    public function getPreviousUuid(): Literal|string|null
    {
        return $this->previousuuid;
    }

    public function setPreviousUuid(Literal|string|null $previousuuid): void
    {
        $this->previousuuid = $previousuuid;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): void
    {
        $this->checksum = $checksum;
    }
    
    public static function calculateChecksum(TransactionEntity|int $transactionEntityOrAmount, ?int $balance = null, ?int $reserved = null, ?int $available = null, ?string $uuid = null, ?string $previousUuid = null): string
    {
        if (!($transactionEntityOrAmount instanceof TransactionEntity)) {
            $data = implode('|', [
                $transactionEntityOrAmount,
                $balance,
                $reserved,
                $available,
                HexUuidLiteral::getFormattedUuid($uuid, throwErrorIfInvalid: false),
                HexUuidLiteral::getFormattedUuid($previousUuid, throwErrorIfInvalid: false),
            ]);
        } else {
            $data = implode('|', [
                $transactionEntityOrAmount->getAmount(),
                $transactionEntityOrAmount->getBalance(),
                $transactionEntityOrAmount->getReserved(),
                $transactionEntityOrAmount->getAvailable(),
                HexUuidLiteral::getFormattedUuid($transactionEntityOrAmount->getUuid(), throwErrorIfInvalid: false),
                HexUuidLiteral::getFormattedUuid($transactionEntityOrAmount->getPreviousUuid(), throwErrorIfInvalid: false),
            ]);
        }
        return hash('sha256', $data);
    }

    public static function validateChecksum(TransactionEntity $transactionEntity, string $checksum): bool
    {
        return $checksum === self::calculateChecksum($transactionEntity);
    }
}
