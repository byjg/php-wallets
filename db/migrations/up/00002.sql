-- Ledger hardening:
-- 1) A reserved transaction can be processed (accepted/rejected) only once
-- 2) Ledger transactions are immutable: block UPDATE at the database level

ALTER TABLE `transaction`
    ADD CONSTRAINT `idx_transaction_parentid_unique` UNIQUE (`transactionparentid`);

CREATE TRIGGER `trg_transaction_no_update` BEFORE UPDATE ON `transaction`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger transactions are immutable and cannot be updated';
