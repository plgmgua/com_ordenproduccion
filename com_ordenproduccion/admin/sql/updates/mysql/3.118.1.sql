-- ============================================
-- Version 3.118.1 - Bank accounts: default flag
-- ============================================

ALTER TABLE `#__ordenproduccion_bank_accounts`
    ADD COLUMN `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=default account for display' AFTER `state`;
