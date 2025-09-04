ALTER TABLE statement
    ADD CONSTRAINT statement_chk_value_nonnegative
        CHECK (netbalance >= 0);

ALTER TABLE account
    ADD CONSTRAINT chk_value_nonnegative
        CHECK (netbalance >= 0);
