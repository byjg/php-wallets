-- Remove CHECK constraints to allow negative balances
ALTER TABLE statement
    DROP CONSTRAINT statement_chk_value_nonnegative;

ALTER TABLE account
    DROP CONSTRAINT account_chk_value_nonnegative;
