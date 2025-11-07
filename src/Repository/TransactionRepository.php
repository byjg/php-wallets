<?php

namespace ByJG\AccountTransactions\Repository;

use ByJG\AccountTransactions\Entity\TransactionEntity;
use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

class TransactionRepository extends BaseRepository
{
    /**
     * TransactionRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $transactionEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $transactionEntity, array $fieldMappingList = [])
    {
        $this->repository = new Repository($dbExecutor, $transactionEntity);

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
     * get a Transaction by ID.
     *
     * @param int $parentId
     * @param bool $forUpdate
     * @return TransactionEntity|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByParentId(int $parentId, bool $forUpdate = false): ?TransactionEntity
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('transactionparentid = :id', ['id' => $parentId])
        ;

        if ($forUpdate) {
            $query->forUpdate();
        }

        $result = $this->repository->getByQuery($query);

        if (count($result) > 0) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Get a Transaction by its UUID.
     *
     * @param Literal|string $uuid
     * @return TransactionEntity|null
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByUuid(Literal|string $uuid): ?TransactionEntity
    {
        if (is_string($uuid)) {
            $uuid = new HexUuidLiteral($uuid);
        }

        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('uuid = :uuid', ['uuid' => $uuid])
        ;

        if ($this->repository->getExecutor()->hasActiveTransaction()) {
            $query->forUpdate();
        }

        $result = $this->repository->getByQuery($query);

        return $result[0] ?? null;
    }

    /**
     * @param int $accountId
     * @param int $limit
     * @return TransactionEntity[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getByAccountId(int $accountId, int $limit = 20): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("accountid = :id", ["id" => $accountId])
            ->limit(0, $limit)
        ;

        return $this->repository->getByQuery($query);
    }

    /**
     * @param int|null $accountId
     * @return array
     * @throws InvalidArgumentException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getReservedTransactions(?int $accountId = null): array
    {
        $query = Query::getInstance()
            ->fields([
                "st1.*",
                "ac.accounttypeid",
            ])
            ->table($this->repository->getMapper()->getTable() . " st1")
            ->join("account ac", "st1.accountid = ac.accountid")
            ->leftJoin("transaction st2", "st1.transactionid = st2.transactionparentid")
            ->where("st1.typeid in ('WB', 'DB')")
            ->where("st2.transactionid is null")
            ->orderBy(["st1.date desc"])
        ;

        if (!empty($accountId)) {
            $query->where("st1.accountid = :id", ["id" => $accountId]);
        }

        return $this->repository->getByQuery($query);
    }

    /**
     * @param int $accountId
     * @param string $startDate
     * @param string $endDate
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getByDate(int $accountId, string $startDate, string $endDate): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("date between :start and :end", ["start" => $startDate, "end" => $endDate])
            ->where("accountid = :id", ["id" => $accountId])
            ->orderBy(["date"])
        ;

        return $this->repository->getByQuery($query);
    }

    /**
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     */
    public function getByCode(int $accountId, string $code, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("code = :code", ["code" => $code])
            ->where("accountid = :id", ["id" => $accountId])
            ->orderBy(["date"])
        ;

        if (!empty($startDate)) {
            $query->where("date >= :start", ["start" => $startDate]);
        }

        if (!empty($endDate)) {
            $query->where("date <= :end", ["end" => $endDate]);
        }

        return $this->repository->getByQuery($query);
    }

    /**
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     */
    public function getByReferenceId(int $accountId, string $referenceSource, string $referenceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where("referencesource = :source", ["source" => $referenceSource])
            ->where("referenceid = :id", ["id" => $referenceId])
            ->where("accountid = :accountid", ["accountid" => $accountId])
            ->orderBy(["date"])
        ;

        if (!empty($startDate)) {
            $query->where("date >= :start", ["start" => $startDate]);
        }

        if (!empty($endDate)) {
            $query->where("date <= :end", ["end" => $endDate]);
        }

        return $this->repository->getByQuery($query);
    }
}
