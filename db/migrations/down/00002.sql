-- Remove checksum v2 columns

ALTER TABLE `transaction`
    DROP COLUMN `checksumversion`,
    DROP COLUMN `previouschecksum`;
