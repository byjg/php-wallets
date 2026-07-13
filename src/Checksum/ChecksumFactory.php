<?php

namespace ByJG\Wallets\Checksum;

use ByJG\Wallets\Exception\TransactionException;

/**
 * Resolves the checksum algorithm for a transaction row.
 */
class ChecksumFactory
{
    /**
     * The algorithm used for NEW transactions.
     */
    public static function current(): ChecksumV2
    {
        return new ChecksumV2();
    }

    /**
     * The algorithm that produced a row, from its transaction.checksumversion.
     * Rows without a version (null) predate the versioning and validate as v1.
     *
     * @throws TransactionException when the version is unknown
     */
    public static function get(?int $version): ChecksumInterface
    {
        return match ($version ?? ChecksumV1::VERSION) {
            ChecksumV1::VERSION => new ChecksumV1(),
            ChecksumV2::VERSION => new ChecksumV2(),
            default => throw new TransactionException("Unknown checksum version $version"),
        };
    }
}
