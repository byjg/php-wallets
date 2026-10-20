-- Back to available >= 0 (fails while a wallet is in overdraft)

ALTER TABLE `wallet`
    DROP CONSTRAINT `wallet_chk_available_minvalue`;

ALTER TABLE `wallet`
    ADD CONSTRAINT `wallet_chk_value_nonnegative`
        CHECK (`available` >= 0);

ALTER TABLE `transaction`
    ADD CONSTRAINT `transaction_chk_value_nonnegative`
        CHECK (`available` >= 0);
