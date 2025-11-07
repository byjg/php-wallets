-- Add CHECK constraints to prevent negative balances
ALTER TABLE statement
    ADD CONSTRAINT statement_chk_value_nonnegative
        CHECK (available >= 0);

ALTER TABLE account
    ADD CONSTRAINT account_chk_value_nonnegative
        CHECK (available >= 0);
