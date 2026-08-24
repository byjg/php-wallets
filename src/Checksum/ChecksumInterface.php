<?php

namespace ByJG\Wallets\Checksum;

use ByJG\Wallets\Entity\TransactionEntity;

/**
 * A checksum algorithm for ledger transactions.
 *
 * Each transaction row records the algorithm that produced its checksum
 * (transaction.checksumversion), so old rows keep validating after the
 * algorithm evolves. Use ChecksumFactory to resolve the algorithm for a row.
 */
interface ChecksumInterface
{
    /**
     * The version recorded in transaction.checksumversion for rows produced
     * by this algorithm.
     */
    public function getVersion(): int;

    /**
     * Calculate the checksum of a transaction.
     *
     * @param TransactionEntity $transaction
     * @param string|null $secret Optional installation secret mixed into the hash.
     *        Algorithms that predate the secret ignore it.
     */
    public function calculate(TransactionEntity $transaction, ?string $secret = null): string;

    /**
     * Validate a checksum against the transaction.
     */
    public function validate(TransactionEntity $transaction, string $checksum, ?string $secret = null): bool;
}
