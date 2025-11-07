<?php

namespace ByJG\AccountTransactions\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Repository;
use ReflectionException;

class WalletTypeRepository extends BaseRepository
{
    /**
     * WalletTypeRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $walletTypeEntity
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $walletTypeEntity)
    {
        $this->repository = new Repository($dbExecutor, $walletTypeEntity);
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    public function getMapper(): Mapper
    {
        return $this->repository->getMapper();
    }

}
