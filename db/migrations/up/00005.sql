-- Overdraft: a wallet's available funds may go down to its minvalue (0 by default, negative to
-- allow an overdraft). The wallet row is updated in the same database transaction as the ledger
-- row, so its CHECK guards every operation; the ledger row has no minvalue to compare with.

ALTER TABLE `transaction`
    DROP CONSTRAINT `transaction_chk_value_nonnegative`;

ALTER TABLE `wallet`
    DROP CONSTRAINT `wallet_chk_value_nonnegative`;

ALTER TABLE `wallet`
    ADD CONSTRAINT `wallet_chk_available_minvalue`
        CHECK (`available` >= `minvalue`);
