<?php

namespace Tests\Classes;

use ByJG\Wallets\Repository\WalletRepository;
use RuntimeException;

/**
 * Simulates a wallet persistence failure to test the all-or-nothing
 * guarantee of the TransactionService operations.
 */
class FailingWalletRepository extends WalletRepository
{
    #[\Override]
    public function save($model): mixed
    {
        throw new RuntimeException('Simulated wallet save failure');
    }
}
