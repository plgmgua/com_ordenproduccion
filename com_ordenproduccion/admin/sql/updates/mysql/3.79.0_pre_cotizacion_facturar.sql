-- Add Facturar flag to Pre-Cotización: when 1, totals exclude IVA and ISR.
-- Version: 3.79.0
-- Run manually or via component update. Replace joomla_ with your table prefix.

ALTER TABLE `joomla_ordenproduccion_pre_cotizacion`
ADD COLUMN `facturar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = include IVA/ISR in total, 0 = exclude' AFTER `state`;
