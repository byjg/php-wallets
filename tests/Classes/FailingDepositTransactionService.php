<?php

namespace Tests\Classes;

use ByJG\Wallets\DTO\TransactionDTO;
use ByJG\Wallets\Entity\TransactionEntity;
use ByJG\Wallets\Service\TransactionService;
use RuntimeException;

/**
 * Simulates a deposit failure after a successful withdraw, to test the
 * all-or-nothing guarantee of WalletService::transferFunds().
 */
class FailingDepositTransactionService extends TransactionService
{
    #[\Override]
    public function addFunds(TransactionDTO $dto): TransactionEntity
    {
        throw new RuntimeException('Simulated deposit failure');
    }
}
