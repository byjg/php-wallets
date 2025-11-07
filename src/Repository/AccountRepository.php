<?php

namespace ByJG\AccountTransactions\Repository;

use ByJG\AccountTransactions\Entity\AccountEntity;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

class AccountRepository extends BaseRepository
{
    /**
     * AccountRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $accountEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $accountEntity, array $fieldMappingList = [])
    {
        $this->repository = new Repository($dbExecutor, $accountEntity);

        $mapper = $this->repository->getMapper();
        foreach ($fieldMappingList as $fieldMapping) {
            $mapper->addFieldMapping($fieldMapping);
        }
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    public function getMapper(): Mapper
    {
        return $this->repository->getMapper();
    }

    /**
     * @param string $userId
     * @param string $accountType
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByUserId(string $userId, string $accountType = ""): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('userid = :userid', ['userid' => $userId])
        ;

        if (!empty($accountType)) {
            $query->where("accounttypeid = :acctype", ["acctype" => $accountType]);
        }

        return $this->repository
            ->getByQuery($query);
    }

    /**
     * @param string $accountTypeId
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByAccountTypeId(string $accountTypeId): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("accounttypeid = :acctype", ["acctype" => $accountTypeId])
        ;


        return $this->repository
            ->getByQuery($query);
    }

    /**
     * @param int $transactionid
     * @return AccountEntity|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getByTransactionId(int $transactionid): ?AccountEntity
    {
        $query = Query::getInstance()
            ->fields(['account.*'])
            ->table($this->repository->getMapper()->getTable())
            ->join('transaction', 'transaction.accountid = account.accountid')
            ->where('transactionid = :transactionid', ['transactionid' => $transactionid])
        ;

        $result = $this->repository
            ->getByQuery($query);

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }
}
