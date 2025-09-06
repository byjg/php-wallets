<?php

namespace Tests\Classes;

use ByJG\AccountStatements\Repository\StatementRepository;
use ByJG\AnyDataset\Db\DbDriverInterface;

class StatementRepositoryExtended extends StatementRepository
{
    protected bool $reach = false;

    public function __construct(DbDriverInterface $dbDriver, string $statementEntity, array $fieldMappingList = [])
    {
        parent::__construct($dbDriver, $statementEntity, $fieldMappingList);
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