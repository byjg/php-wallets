<?php

namespace ByJG\Wallets\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\Wallets\Entity\WalletEntity;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

class WalletRepository extends BaseRepository
{
    /**
     * WalletRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $walletEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $walletEntity, array $fieldMappingList = [])
    {
        $this->repository = new Repository($dbExecutor, $walletEntity);

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
     * @param string $walletType
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByUserId(string $userId, string $walletType = ""): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('userid = :userid', ['userid' => $userId])
        ;

        if (!empty($walletType)) {
            $query->where("wallettypeid = :acctype", ["acctype" => $walletType]);
        }

        return $this->repository
            ->getByQuery($query);
    }

    /**
     * @param string $walletTypeId
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByWalletTypeId(string $walletTypeId): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("wallettypeid = :acctype", ["acctype" => $walletTypeId])
        ;


        return $this->repository
            ->getByQuery($query);
    }

    /**
     * @param int $transactionid
     * @return WalletEntity|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getByTransactionId(int $transactionid): ?WalletEntity
    {
        $query = Query::getInstance()
            ->fields(['wallet.*'])
            ->table($this->repository->getMapper()->getTable())
            ->join('transaction', 'transaction.walletid = wallet.walletid')
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
