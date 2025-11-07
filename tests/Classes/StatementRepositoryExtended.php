<?php

namespace Tests\Classes;

use ByJG\AccountStatements\Repository\StatementRepository;
use ByJG\AnyDataset\Db\DatabaseExecutor;

class StatementRepositoryExtended extends StatementRepository
{
    protected bool $reach = false;

    public function __construct(DatabaseExecutor $dbExecutor, string $statementEntity, array $fieldMappingList = [])
    {
        parent::__construct($dbExecutor, $statementEntity, $fieldMappingList);
        $this->getRepository()->addObserver(new ObserverStatement($this));
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