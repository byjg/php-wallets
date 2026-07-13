<?php

namespace ByJG\Wallets\Checksum;

use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\Wallets\Entity\TransactionEntity;

/**
 * Checksum version 2: chained hash of every business field.
 *
 * Covers every business field of the row plus the previous transaction's checksum,
 * chaining the hashes: tampering with any row invalidates every subsequent checksum.
 * When a secret is provided, the checksum becomes a keyed hash that cannot be
 * recomputed by an attacker with database access only.
 *
 * Null and empty string are canonicalized to the same value, and custom fields of
 * extended entities are not covered.
 */
class ChecksumV2 implements ChecksumInterface
{
    const VERSION = 2;

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
            $transaction->getWalletId(),
            $transaction->getWalletTypeId(),
            $transaction->getTypeId(),
            $transaction->getAmount(),
            $transaction->getScale(),
            $transaction->getBalance(),
            $transaction->getReserved(),
            $transaction->getAvailable(),
            $transaction->getCode(),
            $transaction->getDescription(),
            $transaction->getReferenceId(),
            $transaction->getReferenceSource(),
            $transaction->getTransactionParentId(),
            HexUuidLiteral::getFormattedUuid($transaction->getUuid(), throwErrorIfInvalid: false),
            HexUuidLiteral::getFormattedUuid($transaction->getPreviousUuid(), throwErrorIfInvalid: false),
            $transaction->getPreviousChecksum(),
        ]);
        return hash('sha256', $data . '|' . ($secret ?? ''));
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function validate(TransactionEntity $transaction, string $checksum, ?string $secret = null): bool
    {
        return $checksum === $this->calculate($transaction, $secret);
    }

    /**
     * The SQL expression that resolves the previous transaction's checksum through
     * the wallet head (wallet.last_uuid), for use inside the atomic INSERT-SELECT.
     */
    public function buildPreviousChecksumSqlExpression(string $transactionTable): string
    {
        return "(SELECT prevtx.checksum FROM $transactionTable prevtx WHERE prevtx.uuid = wallet.last_uuid)";
    }

    /**
     * The SQL expression that calculates this checksum inside the atomic INSERT-SELECT
     * that reads from the wallet table.
     *
     * The field list and order MUST match calculate(), with NULLs canonicalized to ''
     * and binary UUIDs rendered as UPPER(BIN_TO_UUID()). It relies on the named
     * parameters bound by the insert query (:operation, :code, :description,
     * :referenceid, :referencesource, :uuid and :checksumsecret) and hardcodes
     * transactionparentid to '' because that path never sets a parent.
     *
     * @param string $expressionAmount SQL expression for the transaction amount
     * @param string $expressionBalance SQL expression for the new balance
     * @param string $expressionReserved SQL expression for the new reserved value
     * @param string $expressionAvailable SQL expression for the new available value
     * @param string $transactionTable Table where the transactions are stored
     */
    public function buildSqlExpression(
        string $expressionAmount,
        string $expressionBalance,
        string $expressionReserved,
        string $expressionAvailable,
        string $transactionTable
    ): string {
        $previousChecksumExpression = $this->buildPreviousChecksumSqlExpression($transactionTable);

        return "LOWER(SHA2(CONCAT(" .
            "walletid, '|', " .
            "wallettypeid, '|', " .
            ":operation, '|', " .
            "$expressionAmount, '|', " .
            "scale, '|', " .
            "$expressionBalance, '|', " .
            "$expressionReserved, '|', " .
            "$expressionAvailable, '|', " .
            "COALESCE(:code, ''), '|', " .
            "COALESCE(:description, ''), '|', " .
            "COALESCE(:referenceid, ''), '|', " .
            "COALESCE(:referencesource, ''), '|', " .
            "'', '|', " .
            "UPPER(BIN_TO_UUID(:uuid)), '|', " .
            "COALESCE(UPPER(BIN_TO_UUID(wallet.last_uuid)), ''), '|', " .
            "COALESCE($previousChecksumExpression, ''), '|', " .
            ":checksumsecret" .
            "), 256))";
    }
}
