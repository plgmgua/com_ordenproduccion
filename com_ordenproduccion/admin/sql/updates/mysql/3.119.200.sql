-- ============================================
-- Version 3.119.200 - Payment type super-user-only flag
-- ============================================

ALTER TABLE `#__ordenproduccion_payment_types`
    ADD COLUMN `super_user_only` tinyint(1) NOT NULL DEFAULT 0
        COMMENT '1=show in payment forms only to Super Users (core.admin)'
        AFTER `requires_bank`;
