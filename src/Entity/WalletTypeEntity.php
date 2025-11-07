<?php

namespace ByJG\AccountTransactions\Entity;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Serializer\BaseModel;

/**
 * @OA\Definition(
 *   description="AccountType",
 * )
 *
 * @object:nodename accounttype
 */
#[TableAttribute('wallettype')]
class WalletTypeEntity extends BaseModel
{

    /**
     * @var string|null
     * @OA\Property()
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?string $wallettypeid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $name = null;
    
    public function getWalletTypeId(): ?string
    {
        return $this->wallettypeid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setWalletTypeId(?string $wallettypeid): void
    {
        $this->wallettypeid = $wallettypeid;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
