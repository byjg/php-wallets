<?php

namespace ByJG\AccountStatements\Repository;

use ByJG\AnyDataset\Core\GenericIterator;
use ByJG\AnyDataset\Core\IteratorFilter;
use ByJG\AnyDataset\Db\DbDriverInterface;
use ByJG\AnyDataset\Db\IsolationLevelEnum;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ByJG\Serializer\Exception\InvalidArgumentException;

abstract class BaseRepository
{
    /**
     * @var Repository
     */
    protected Repository $repository;

    /**
     * @param string|int $itemId
     * @return mixed
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(string|int $itemId): mixed
    {
        return $this->repository->get($itemId);
    }

    /**
     * @param int|null $page
     * @param int|null $size
     * @param string|null $orderBy
     * @param array|IteratorFilter|null $filter
     * @return array
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
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

    public function model()
    {
        $class = $this->repository->getMapper()->getEntity();

        return new $class();
    }

    /**
     * @param $model
     * @return mixed
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     */
    public function save($model): mixed
    {
        return $this->repository->save($model);
    }

    public function bulkExecute(array $queries): ?GenericIterator
    {
        return $this->repository->bulkExecute($queries, IsolationLevelEnum::SERIALIZABLE);
    }

    public function getDbDriver(): DbDriverInterface
    {
        return $this->repository->getDbDriver();
    }
}
