-- Checksum v2: chained, versioned transaction checksums
-- 1) previouschecksum stores a copy of the previous transaction's checksum and is part
--    of the new checksum, chaining the hashes: tampering with any row invalidates every
--    subsequent checksum
-- 2) checksumversion records which algorithm produced the row's checksum
--    (1 = legacy amount/balances/uuids hash, 2 = chained full-row hash)

ALTER TABLE `transaction`
    ADD COLUMN `previouschecksum` varchar(64) NULL AFTER `checksum`,
    ADD COLUMN `checksumversion` tinyint NOT NULL DEFAULT 1 AFTER `previouschecksum`;
