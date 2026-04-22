-- com_ordenproduccion 3.113.6-STABLE: Registro de eventos — solicitud de cotización a proveedor (pre-cot proveedor externo).

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_precot_vendor_quote_event` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `pre_cotizacion_id` int(11) NOT NULL,
    `proveedor_id` int(11) NOT NULL DEFAULT 0,
    `event_type` varchar(32) NOT NULL COMMENT 'email_sent|pdf_download|cellphone_compose',
    `meta` text COMMENT 'JSON: actor_name, to_email, subject, filename, phone, etc.',
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_precot` (`pre_cotizacion_id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for vendor quote requests on external-vendor pre-cotizaciones';
