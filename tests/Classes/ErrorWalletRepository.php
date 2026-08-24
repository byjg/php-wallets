<?php

namespace Tests\Classes;

use ByJG\Wallets\Repository\WalletRepository;
use Error;

/**
 * Simulates a PHP Error (not an Exception) during the wallet persistence,
 * to test that the rollback also covers Throwable errors.
 */
class ErrorWalletRepository extends WalletRepository
{
    #[\Override]
    public function save($model): mixed
    {
        throw new Error('Simulated wallet save error');
    }
}
