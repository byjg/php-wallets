<?php

namespace Tests\Classes;

use ByJG\Wallets\Repository\TransactionRepository;
use RuntimeException;

/**
 * Simulates a transaction persistence failure to test the all-or-nothing
 * guarantee of the TransactionService operations.
 */
class FailingTransactionRepository extends TransactionRepository
{
    #[\Override]
    public function save($model): mixed
    {
        throw new RuntimeException('Simulated transaction save failure');
    }
}
