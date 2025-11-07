<?php

namespace ByJG\AccountTransactions\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Repository;
use ReflectionException;

class AccountTypeRepository extends BaseRepository
{
    /**
     * AccountTypeRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $accountTypeEntity
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $accountTypeEntity)
    {
        $this->repository = new Repository($dbExecutor, $accountTypeEntity);
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
