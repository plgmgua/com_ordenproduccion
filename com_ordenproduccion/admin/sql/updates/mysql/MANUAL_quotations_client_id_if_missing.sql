-- Run this in phpMyAdmin if the webhook returns "Quotations table does not have client_id."
-- Replace joomla_ with your actual Joomla table prefix if different.
-- Run each statement separately; if you get "Duplicate column name", the column already exists (skip that statement).

-- 1) Add client_id to quotations
ALTER TABLE `joomla_ordenproduccion_quotations`
  ADD COLUMN `client_id` int(11) DEFAULT NULL COMMENT 'External client id from URL' AFTER `client_nit`;

-- 2) Add sales_agent to quotations (optional, for Agente de Ventas)
ALTER TABLE `joomla_ordenproduccion_quotations`
  ADD COLUMN `sales_agent` varchar(255) DEFAULT NULL COMMENT 'Agente de Ventas' AFTER `contact_phone`;
