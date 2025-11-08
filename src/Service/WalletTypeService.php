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
     * Get a WalletType by ID.
     * If ID is not provided, returns all WalletTypes.
     *
     * @param string $walletTypeId Optional. If not provided, returns all
     * @return WalletTypeEntity|WalletTypeEntity[]|null
     * @throws \ByJG\MicroOrm\Exception\InvalidArgumentException
     */
    public function getById(string $walletTypeId): array|WalletTypeEntity|null
    {
        return $this->walletTypeRepository->getById($walletTypeId);
    }

    /**
     * Save or Update a WalletType
     *
     * @param mixed $data
     * @return string ID of the inserted/updated object
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
            throw new WalletTypeException('Wallet type ID cannot be blank');
        }

        if (empty($object->getName())) {
            throw new WalletTypeException('Name cannot be blank');
        }

        $this->walletTypeRepository->save($object);

        return $walletTypeId ?? "";
    }

    public function getRepository(): WalletTypeRepository
    {
        return $this->walletTypeRepository;
    }
}
