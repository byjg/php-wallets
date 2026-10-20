<?php

namespace ByJG\Wallets\Checksum;

use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\Entity\TransactionEntity;

/**
 * Legacy checksum (version 1): hash of amount|balance|reserved|available|uuid|previousuuid.
 *
 * Kept only to validate rows created before the checksum v2 upgrade. It does not cover
 * the descriptive fields, is not chained to the previous transaction and ignores the
 * secret, so anyone with database access can recompute it. Do not use it for new rows.
 */
class ChecksumV1 implements ChecksumInterface
{
    const VERSION = 1;

    #[\Override]
    public function getVersion(): int
    {
        return self::VERSION;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function calculate(TransactionEntity $transaction, ?string $secret = null): string
    {
        $data = implode('|', [
            $transaction->getAmount(),
            $transaction->getBalance(),
            $transaction->getReserved(),
            $transaction->getAvailable(),
            HexUuidLiteral::getFormattedUuid($transaction->getUuid(), throwErrorIfInvalid: false),
            HexUuidLiteral::getFormattedUuid($transaction->getPreviousUuid(), throwErrorIfInvalid: false),
        ]);
        return hash('sha256', $data);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function validate(TransactionEntity $transaction, string $checksum, ?string $secret = null): bool
    {
        return $checksum === $this->calculate($transaction, $secret);
    }
}
