-- Add CHECK constraints to prevent negative balances
ALTER TABLE transaction
    ADD CONSTRAINT transaction_chk_value_nonnegative
        CHECK (available >= 0);

ALTER TABLE wallet
    ADD CONSTRAINT wallet_chk_value_nonnegative
        CHECK (available >= 0);
