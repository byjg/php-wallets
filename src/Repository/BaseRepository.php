<?php

namespace ByJG\AccountTransactions\Repository;

use ByJG\AnyDataset\Core\Exception\DatabaseException;
use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Exception\DbDriverNotConnected;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\XmlUtil\Exception\FileException;
use ByJG\XmlUtil\Exception\XmlUtilException;

abstract class BaseRepository
{
    /**
     * @var Repository
     */
    protected Repository $repository;

    /**
     * @param string|int $itemId
     * @return mixed
     * @throws OrmInvalidFieldsException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws InvalidArgumentException
     * @throws FileException
     * @throws XmlUtilException
     */
    public function getById(string|int $itemId): mixed
    {
        // Check if there's an active transaction and use forUpdate if so
        if ($this->getExecutor()->hasActiveTransaction()) {
            [$filterList, $filterKeys] = $this->repository->getMapper()->getPkFilter($itemId);
            $result = $this->repository->getByFilter($filterList, $filterKeys, true); // forUpdate = true
            
            if (count($result) === 1) {
                return $result[0];
            }
            
            return null;
        }
        
        return $this->repository->get($itemId);
    }

    /**
     * @param int|null $page
     * @param int|null $size
     * @param string|null $orderBy
     * @param array|IteratorFilter|null $filter
     * @return array
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     */
    public function getAll(?int $page = 0, ?int $size = 20, ?string $orderBy = null, array|IteratorFilter|null $filter = null): array
    {
        if (empty($page)) {
            $page = 0;
        }

        if (empty($size)) {
            $size = 20;
        }

        $query = Query::getInstance()
            ->table($this->repository->getMapper()->getTable())
            ->limit($page*$size, $size);

        if (!empty($orderBy)) {
            $query->orderBy((array)$orderBy);
        }

        if ($filter instanceof IteratorFilter) {
            $query->where($filter);
        } elseif (is_array($filter)) {
            foreach ($filter as $item) {
                $query->where($item[0], $item[1]);
            }
        }

        return $this->repository
            ->getByQuery($query);
    }

    public function model(): object
    {
        $class = $this->repository->getMapper()->getEntity();

        return new $class();
    }

    /**
     * @param $model
     * @return mixed
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws XmlUtilException
     * @throws InvalidArgumentException
     */
    public function save($model): mixed
    {
        return $this->repository->save($model);
    }

    /**
     * @throws XmlUtilException
     * @throws DatabaseException
     * @throws DbDriverNotConnected
     * @throws FileException
     * @throws InvalidArgumentException
     */
    public function bulkExecute(array $queries): ?GenericIterator
    {
        return $this->repository->bulkExecute($queries, IsolationLevelEnum::SERIALIZABLE);
    }

    public function getExecutor(): DatabaseExecutor
    {
        return $this->repository->getExecutor();
    }
}
