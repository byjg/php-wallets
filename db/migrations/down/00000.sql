-- Remove CHECK constraints to allow negative balances
ALTER TABLE transaction
    DROP CONSTRAINT transaction_chk_value_nonnegative;

ALTER TABLE wallet
    DROP CONSTRAINT wallet_chk_value_nonnegative;
