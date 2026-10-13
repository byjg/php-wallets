-- Remove ledger immutability enforcement

DROP TRIGGER IF EXISTS `trg_transaction_no_update`;

ALTER TABLE `transaction`
    DROP INDEX `idx_transaction_parentid_unique`;
