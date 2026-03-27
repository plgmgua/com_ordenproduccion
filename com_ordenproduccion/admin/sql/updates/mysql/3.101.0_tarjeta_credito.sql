-- Tarjeta de crédito: commission rates by installments + pre-cotización optional surcharge.
-- Version: 3.101.0. Replace #__ with your table prefix.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_tarjeta_credito_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuotas` int(11) NOT NULL COMMENT 'Plazo (meses)',
  `tasa_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ordering` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cuotas` (`cuotas`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ordenproduccion_tarjeta_credito_rates` (`cuotas`, `tasa_percent`, `ordering`, `state`) VALUES
(2, 5.25, 2, 1),
(3, 5.75, 3, 1),
(6, 7.00, 6, 1),
(10, 7.25, 10, 1),
(12, 8.00, 12, 1),
(15, 10.00, 15, 1),
(18, 12.00, 18, 1),
(24, 12.50, 24, 1),
(36, 13.50, 36, 1),
(48, 16.75, 48, 1)
ON DUPLICATE KEY UPDATE
  `tasa_percent` = VALUES(`tasa_percent`),
  `ordering` = VALUES(`ordering`),
  `state` = VALUES(`state`);

ALTER TABLE `#__ordenproduccion_pre_cotizacion`
  ADD COLUMN `tarjeta_credito_cuotas` int(11) DEFAULT NULL COMMENT 'Plazo meses; NULL = sin cargo TC',
  ADD COLUMN `tarjeta_credito_tasa` decimal(6,2) DEFAULT NULL,
  ADD COLUMN `tarjeta_credito_monto` decimal(12,2) DEFAULT NULL,
  ADD COLUMN `total_con_tarjeta` decimal(12,2) DEFAULT NULL;
