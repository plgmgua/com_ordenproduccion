-- Retenciones (Constancia de Exención de IVA) — Administración → Retenciones
-- Version: 3.119.257

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_retenciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(128) NOT NULL DEFAULT '' COMMENT 'Document title from PDF (e.g. Constancia de Exención de IVA)',
  `autorizacion` varchar(64) NOT NULL DEFAULT '' COMMENT 'UUID autorización DTE retención',
  `serie` varchar(32) NOT NULL DEFAULT '',
  `numero` varchar(32) NOT NULL DEFAULT '' COMMENT 'Número de DTE',
  `fact_autorizacion` varchar(64) NOT NULL DEFAULT '' COMMENT 'UUID factura referenciada',
  `fact_serie` varchar(32) NOT NULL DEFAULT '',
  `fact_numero` varchar(32) NOT NULL DEFAULT '',
  `fact_iva_exento` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto IVA exento (Referencias)',
  `monto_retencion` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto RETENCIÓN (Q) from SAT-2229/SAT-1911',
  `nit_emisor` varchar(32) DEFAULT NULL,
  `nit_receptor` varchar(32) DEFAULT NULL,
  `nombre_receptor` varchar(255) DEFAULT NULL,
  `fecha_emision` datetime DEFAULT NULL,
  `source_filename` varchar(255) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retencion_autorizacion` (`autorizacion`),
  KEY `idx_tipo_documento` (`tipo_documento`),
  KEY `idx_serie_numero` (`serie`, `numero`),
  KEY `idx_fact_autorizacion` (`fact_autorizacion`),
  KEY `idx_state` (`state`),
  KEY `idx_fecha_emision` (`fecha_emision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Constancias de retención / exención IVA (PDF)';
