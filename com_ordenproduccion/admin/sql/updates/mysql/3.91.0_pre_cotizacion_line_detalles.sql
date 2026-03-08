-- ============================================
-- Version 3.91.0 - Pre-Cotización line "Detalles" (instructions per concept)
-- ============================================
-- Stores user instructions for each element/concept of a line (e.g. Impresión, Laminación, Corte, Grapa for pliego; Interiores, Espiral metálico, Portada for Otros Elementos). Used when generating Orden de Trabajo.
-- Replace joomla_ with your table prefix if different.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pre_cotizacion_line_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pre_cotizacion_line_id` int(11) NOT NULL,
  `concepto_key` varchar(100) NOT NULL,
  `concepto_label` varchar(255) NOT NULL DEFAULT '',
  `detalle` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_line_concepto` (`pre_cotizacion_line_id`,`concepto_key`),
  KEY `idx_pre_cotizacion_line_id` (`pre_cotizacion_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Instructions (Detalles) per concept per pre-cotizacion line';
