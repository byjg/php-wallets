<?php

namespace ByJG\AccountTransactions\Bll;

use ByJG\AccountTransactions\Entity\AccountTypeEntity;
use ByJG\AccountTransactions\Exception\AccountTypeException;
use ByJG\AccountTransactions\Repository\AccountTypeRepository;
use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\Serializer\Exception\InvalidArgumentException;

class AccountTypeBLL
{

    protected AccountTypeRepository $accountTypeRepository;

    /**
     * AccountTypeBLL constructor.
     * @param AccountTypeRepository $accountTypeRepository
     */
    public function __construct(AccountTypeRepository $accountTypeRepository)
    {
        $this->accountTypeRepository = $accountTypeRepository;
    }


    /**
     * Obtém um AccountType por ID.
     * Se o ID não for passado, então devolve todos os AccountTypes.
     *
     * @param string $accountTypeId Opcional. Se não for passado obtém todos
     * @return AccountTypeEntity|AccountTypeEntity[]|null
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(string $accountTypeId): array|AccountTypeEntity|null
    {
        return $this->accountTypeRepository->getById($accountTypeId);
    }

    /**
     * Salvar ou Atualizar um AccountType
     *
     * @param mixed $data
     * @return string Id do objeto inserido atualizado
     * @throws AccountTypeException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function update(mixed $data): string
    {
        $object = new AccountTypeEntity($data);
        $accountTypeId = $object->getAccountTypeId();

        if (empty($object->getAccountTypeId())) {
            throw new AccountTypeException('Id account type não pode ser em branco');
        }

        if (empty($object->getName())) {
            throw new AccountTypeException('Nome não pode ser em branco');
        }

        $this->accountTypeRepository->save($object);

        return $accountTypeId ?? "";
    }

    public function getRepository(): AccountTypeRepository
    {
        return $this->accountTypeRepository;
    }
}
