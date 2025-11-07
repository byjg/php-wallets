-- Remove CHECK constraints to allow negative balances
ALTER TABLE transaction
    DROP CONSTRAINT transaction_chk_value_nonnegative;

ALTER TABLE account
    DROP CONSTRAINT account_chk_value_nonnegative;
