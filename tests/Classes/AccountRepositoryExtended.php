<?php

namespace Tests\Classes;

use ByJG\AccountTransactions\Repository\AccountRepository;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ReflectionException;

class AccountRepositoryExtended extends AccountRepository
{

    protected bool $reach = false;

    /**
     * AccountRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $accountEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $accountEntity, array $fieldMappingList = [])
    {
        parent::__construct($dbExecutor, $accountEntity, $fieldMappingList);
        $this->getRepository()->addObserver(new ObserverAccount($this));
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