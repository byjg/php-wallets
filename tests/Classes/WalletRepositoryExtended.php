<?php

namespace Tests\Classes;

use ByJG\AccountTransactions\Repository\WalletRepository;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ReflectionException;

class WalletRepositoryExtended extends WalletRepository
{

    protected bool $reach = false;

    /**
     * AccountRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $walletEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $walletEntity, array $fieldMappingList = [])
    {
        parent::__construct($dbExecutor, $walletEntity, $fieldMappingList);
        $this->getRepository()->addObserver(new ObserverWallet($this));
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