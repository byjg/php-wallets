<?php

namespace Tests\Classes;

use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;

#[TableAttribute('transaction_extended')]
class TransactionExtended extends TransactionEntity
{
    #[FieldAttribute(fieldName: 'extra_property')]
    protected ?string $extraProperty = null;

    public function getExtraProperty(): ?string
    {
        return $this->extraProperty;
    }

    public function setExtraProperty(?string $extraProperty): void
    {
        $this->extraProperty = $extraProperty;
    }
}