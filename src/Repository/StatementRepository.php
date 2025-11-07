<?php

namespace ByJG\AccountStatements\Repository;

use ByJG\AccountStatements\Entity\StatementEntity;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\FieldMapping;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Mapper;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ReflectionException;

class StatementRepository extends BaseRepository
{
    /**
     * StatementRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $statementEntity
     * @param FieldMapping[] $fieldMappingList
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $statementEntity, array $fieldMappingList = [])
    {
        $this->repository = new Repository($dbExecutor, $statementEntity);

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
     * Obtém um Statement pelo seu ID.
     *
     * @param int $parentId
     * @param bool $forUpdate
     * @return StatementEntity|null
     */
    public function getByParentId(int $parentId, bool $forUpdate = false): ?StatementEntity
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->where('statementparentid = :id', ['id' => $parentId])
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
     * Get a Statement by its UUID.
     *
     * @param Literal|string $uuid
     * @return StatementEntity|null
     */
    public function getByUuid(Literal|string $uuid): ?StatementEntity
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
     * @return StatementEntity[]
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
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getReservedStatements(?int $accountId = null): array
    {
        $query = Query::getInstance()
            ->fields([
                "st1.*",
                "ac.accounttypeid",
            ])
            ->table($this->repository->getMapper()->getTable() . " st1")
            ->join("account ac", "st1.accountid = ac.accountid")
            ->leftJoin("statement st2", "st1.statementid = st2.statementparentid")
            ->where("st1.typeid in ('WB', 'DB')")
            ->where("st2.statementid is null")
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
