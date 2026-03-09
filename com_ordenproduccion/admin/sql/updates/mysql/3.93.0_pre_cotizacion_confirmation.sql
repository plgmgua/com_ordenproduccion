-- ============================================
-- Version 3.93.0 - Pre-cotización confirmation (Confirmar cotización steps snapshot)
-- ============================================
-- One row per "Generar Orden de Trabajo" click: links quotation + pre_cotizacion and stores
-- step 1 (signed doc path) and step 2 (instrucciones facturación). Step 3 (line detalles)
-- remains in #__ordenproduccion_pre_cotizacion_line_detalles. The unique id is sent as
-- pre_cotizacion_confirmation_id to the Order Request URL for use when creating orden de trabajo.
-- Replace joomla_ with your table prefix if different.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_pre_cotizacion_confirmation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `pre_cotizacion_id` int(11) NOT NULL,
  `signed_document_path` varchar(500) DEFAULT NULL COMMENT 'Step 1: path to signed cotización file',
  `instrucciones_facturacion` text DEFAULT NULL COMMENT 'Step 2: billing instructions',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_quotation_id` (`quotation_id`),
  KEY `idx_pre_cotizacion_id` (`pre_cotizacion_id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Confirmar cotización snapshot per pre_cotizacion; id sent as pre_cotizacion_confirmation_id';
