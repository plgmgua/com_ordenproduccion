-- Add Descripcion (long text) to Pre-Cotización
-- Version: 3.75.0
-- Run manually in phpMyAdmin. Table prefix: joomla_

ALTER TABLE `joomla_ordenproduccion_pre_cotizacion`
ADD COLUMN `descripcion` TEXT NULL DEFAULT NULL COMMENT 'Description (long text)' AFTER `state`;
