-- Manual rollback for 3.119.228 MT-940 payment matching.
-- After running: set component param payment_proof_mt940_verification = 0 (or disable approval_workflow_payment_proof).

DROP TABLE IF EXISTS `joomla_ordenproduccion_payment_mt940_match_log`;

ALTER TABLE `joomla_ordenproduccion_payment_proof_lines`
    DROP INDEX IF EXISTS `idx_mt940_transaction_id`,
    DROP INDEX IF EXISTS `idx_mt940_match_status`,
    DROP COLUMN IF EXISTS `mt940_match_checked_at`,
    DROP COLUMN IF EXISTS `mt940_match_status`,
    DROP COLUMN IF EXISTS `mt940_transaction_id`;
