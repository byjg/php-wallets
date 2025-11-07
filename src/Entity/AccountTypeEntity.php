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
#[TableAttribute('accounttype')]
class AccountTypeEntity extends BaseModel
{

    /**
     * @var string|null
     * @OA\Property()
     */
    #[FieldAttribute(primaryKey: true)]
    protected ?string $accounttypeid = null;

    /**
     * @var string|null
     * @OA\Property()
     */
    protected ?string $name = null;
    
    public function getAccountTypeId(): ?string
    {
        return $this->accounttypeid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setAccountTypeId(?string $accounttypeid): void
    {
        $this->accounttypeid = $accounttypeid;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
