-- com_ordenproduccion 3.113.0-STABLE: Vendor quote request message templates (email / cellphone / PDF).

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_vendor_quote_templates` (
    `channel` varchar(16) NOT NULL COMMENT 'email|cellphone|pdf',
    `subject` varchar(512) NOT NULL DEFAULT '',
    `body` mediumtext NOT NULL,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__ordenproduccion_vendor_quote_templates` (`channel`, `subject`, `body`, `modified`, `modified_by`) VALUES
('email', 'Solicitud de cotización {PRECOT_NUMERO}', 'Estimados {PROVEEDOR_NOMBRE},\n\nSolicitamos cotización para la siguiente solicitud ({PRECOT_NUMERO}):\n\n{LINEAS_TEXTO}\n\nNotas / descripción general:\n{PRECOT_DESCRIPCION}\n\nQuedamos atentos.\n\n{USUARIO_NOMBRE}\n{USUARIO_EMAIL}', NOW(), 0),
('cellphone', '', 'Hola {PROVEEDOR_NOMBRE}, solicito cotización {PRECOT_NUMERO}. Detalle: {LINEAS_TEXTO_CORTO}', NOW(), 0),
('pdf', '', 'SOLICITUD DE COTIZACIÓN {PRECOT_NUMERO}\n\nProveedor: {PROVEEDOR_NOMBRE}\n\n{LINEAS_TEXTO}\n\nDescripción: {PRECOT_DESCRIPCION}\nMedidas / notas: {PRECOT_MEDIDAS}\n\nSolicitante: {USUARIO_NOMBRE} ({USUARIO_EMAIL})', NOW(), 0);
