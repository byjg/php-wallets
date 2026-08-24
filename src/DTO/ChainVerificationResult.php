<?php

namespace ByJG\Wallets\DTO;

/**
 * Result of a transaction chain verification (see TransactionService::verifyChain()).
 * The chain is valid when no errors were found.
 */
class ChainVerificationResult
{
    /**
     * @param int $walletId
     * @param string[] $errors
     * @param int $transactionsVerified
     * @param int $legacyChecksums Transactions still carrying a pre-v2 (legacy) checksum.
     *        In a healthy wallet this number never grows; an increase means a row was
     *        rewritten with a downgraded checksum.
     */
    public function __construct(
        protected int $walletId,
        protected array $errors = [],
        protected int $transactionsVerified = 0,
        protected int $legacyChecksums = 0
    ) {
    }

    public function getWalletId(): int
    {
        return $this->walletId;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getTransactionsVerified(): int
    {
        return $this->transactionsVerified;
    }

    public function getLegacyChecksums(): int
    {
        return $this->legacyChecksums;
    }
}
