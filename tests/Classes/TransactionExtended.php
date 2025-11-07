<?php

namespace Tests\Classes;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Wallets\Entity\TransactionEntity;

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