<?php

namespace Tests\Classes;

use ByJG\AccountTransactions\Repository\TransactionRepository;
use ByJG\AnyDataset\Db\DatabaseExecutor;

class TransactionRepositoryExtended extends TransactionRepository
{
    protected bool $reach = false;

    public function __construct(DatabaseExecutor $dbExecutor, string $transactionEntity, array $fieldMappingList = [])
    {
        parent::__construct($dbExecutor, $transactionEntity, $fieldMappingList);
        $this->getRepository()->addObserver(new ObserverTransaction($this));
    }

    public function getReach(): bool
    {
        return $this->reach;
    }

    public function setReach(bool $reach): void
    {
        $this->reach = $reach;
    }
}