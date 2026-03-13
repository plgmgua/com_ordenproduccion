-- --------------------------------------------------------
-- Database update: Invoices table - FEL extra data (JSON)
-- Version: 3.98.0
-- Store full emisor address, certification, taxes, complementos, etc.
-- --------------------------------------------------------

ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_extra` longtext NULL DEFAULT NULL COMMENT 'JSON: emisor_direccion, certificacion, total_impuestos, complemento_abonos, etc.' AFTER `invoice_source`;
