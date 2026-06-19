-- ============================================
-- Version 3.119.188 - Widen invoice_source for invoice_fel_duplicate (21 chars)
-- ============================================

ALTER TABLE `#__ordenproduccion_invoices`
    MODIFY COLUMN `invoice_source` varchar(32) NOT NULL DEFAULT 'order';
