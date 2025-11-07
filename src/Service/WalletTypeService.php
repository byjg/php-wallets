<?php

namespace ByJG\Wallets\Service;

use ByJG\MicroOrm\Exception\OrmBeforeInvalidException;
use ByJG\MicroOrm\Exception\OrmInvalidFieldsException;
use ByJG\MicroOrm\Exception\RepositoryReadOnlyException;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\Serializer\Exception\InvalidArgumentException;
use ByJG\Wallets\Entity\WalletTypeEntity;
use ByJG\Wallets\Exception\WalletTypeException;
use ByJG\Wallets\Repository\WalletTypeRepository;

class WalletTypeService
{

    protected WalletTypeRepository $walletTypeRepository;

    /**
     * WalletTypeService constructor.
     * @param WalletTypeRepository $walletTypeRepository
     */
    public function __construct(WalletTypeRepository $walletTypeRepository)
    {
        $this->walletTypeRepository = $walletTypeRepository;
    }


    /**
     * Obtém um AccountType por ID.
     * Se o ID não for passado, então devolve todos os AccountTypes.
     *
     * @param string $walletTypeId Opcional. Se não for passado obtém todos
     * @return WalletTypeEntity|WalletTypeEntity[]|null
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(string $walletTypeId): array|WalletTypeEntity|null
    {
        return $this->walletTypeRepository->getById($walletTypeId);
    }

    /**
     * Salvar ou Atualizar um AccountType
     *
     * @param mixed $data
     * @return string Id do objeto inserido atualizado
     * @throws WalletTypeException
     * @throws InvalidArgumentException
     * @throws OrmBeforeInvalidException
     * @throws OrmInvalidFieldsException
     * @throws RepositoryReadOnlyException
     * @throws UpdateConstraintException
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function update(mixed $data): string
    {
        $object = new WalletTypeEntity($data);
        $walletTypeId = $object->getWalletTypeId();

        if (empty($object->getWalletTypeId())) {
            throw new WalletTypeException('Id account type não pode ser em branco');
        }

        if (empty($object->getName())) {
            throw new WalletTypeException('Nome não pode ser em branco');
        }

        $this->walletTypeRepository->save($object);

        return $walletTypeId ?? "";
    }

    public function getRepository(): WalletTypeRepository
    {
        return $this->walletTypeRepository;
    }
}
