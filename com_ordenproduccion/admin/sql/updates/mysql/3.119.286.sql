-- ============================================
-- Version 3.119.286 - Payment type skip_validation flag
-- ============================================

ALTER TABLE `#__ordenproduccion_payment_types`
    ADD COLUMN `skip_validation` tinyint(1) NOT NULL DEFAULT 0
        COMMENT '1=skip payment verification (auto-verificado on save; no MT-940/approval)'
        AFTER `super_user_only`;

UPDATE `#__ordenproduccion_payment_types`
SET `skip_validation` = 1
WHERE `code` = 'nota_credito';
