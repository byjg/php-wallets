<?php

namespace ByJG\Wallets\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\MicroOrm\DeleteQuery;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Wallets\Entity\OutboxEntity;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;
use ReflectionException;

class OutboxRepository extends BaseRepository
{
    /**
     * OutboxRepository constructor.
     *
     * @param DatabaseExecutor $dbExecutor
     * @param string $outboxEntity
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $dbExecutor, string $outboxEntity = OutboxEntity::class)
    {
        $this->repository = new Repository($dbExecutor, $outboxEntity);
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * Get the oldest pending entries in FIFO order, locked FOR UPDATE.
     * Must be called inside an active transaction (the dispatcher's claim transaction).
     *
     * @param int $limit
     * @return OutboxEntity[]
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getPendingForUpdate(int $limit): array
    {
        // micro-orm renders FOR UPDATE before LIMIT (invalid in MySQL), so the batch
        // is claimed in two steps: scan the ids, then lock the rows by id re-checking
        // the status under the lock (rows claimed by a concurrent dispatcher between
        // the two steps are excluded)
        $table = $this->repository->getMapper()->getTable();
        $iterator = $this->getExecutor()->getIterator(
            "SELECT outboxid FROM $table WHERE status = :status ORDER BY outboxid LIMIT " . intval($limit),
            ['status' => OutboxEntity::STATUS_PENDING]
        );

        $ids = [];
        foreach ($iterator as $row) {
            $ids[] = intval($row->get('outboxid'));
        }
        if (empty($ids)) {
            return [];
        }

        $query = Query::getInstance()
            ->table($table)
            ->where('outboxid in (' . implode(',', $ids) . ')')
            ->where('status = :status', ['status' => OutboxEntity::STATUS_PENDING])
            ->orderBy(['outboxid'])
            ->forUpdate()
        ;

        return $this->repository->getByQuery($query);
    }

    /**
     * Number of entries still waiting to be dispatched.
     *
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function countPending(): int
    {
        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->fields(['count(*)'])
            ->where('status = :status', ['status' => OutboxEntity::STATUS_PENDING])
        ;

        return intval($this->repository->getScalar($query));
    }

    /**
     * Delete processed entries, optionally only those processed more than
     * $olderThanDays days ago. Returns the number of entries purged.
     *
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function purgeProcessed(?int $olderThanDays = null): int
    {
        $table = $this->repository->getMapper()->getTable();

        $countQuery = Query::getInstance()
            ->table($table)
            ->fields(['count(*)'])
            ->where('status = :status', ['status' => OutboxEntity::STATUS_PROCESSED])
        ;
        $deleteQuery = DeleteQuery::getInstance()
            ->table($table)
            ->where('status = :status', ['status' => OutboxEntity::STATUS_PROCESSED])
        ;

        if ($olderThanDays !== null) {
            $cutoff = date('Y-m-d H:i:s', time() - ($olderThanDays * 86400));
            $countQuery->where('processedat < :cutoff', ['cutoff' => $cutoff]);
            $deleteQuery->where('processedat < :cutoff', ['cutoff' => $cutoff]);
        }

        $count = intval($this->repository->getScalar($countQuery));
        if ($count > 0) {
            $this->repository->deleteByQuery($deleteQuery);
        }

        return $count;
    }
}
