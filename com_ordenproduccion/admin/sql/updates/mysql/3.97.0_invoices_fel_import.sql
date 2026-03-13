-- --------------------------------------------------------
-- Database update: Invoices table - support SAT FEL XML import
-- Version: 3.97.0
-- Allow orden_id NULL for FEL-imported invoices; add FEL-specific columns
-- --------------------------------------------------------

-- Make orden_id nullable so invoices can be imported from XML without a work order
ALTER TABLE `#__ordenproduccion_invoices`
    MODIFY COLUMN `orden_id` int(11) NULL DEFAULT NULL;

-- Allow orden_de_trabajo empty for FEL imports (keep NOT NULL but default '')
ALTER TABLE `#__ordenproduccion_invoices`
    MODIFY COLUMN `orden_de_trabajo` varchar(50) NOT NULL DEFAULT '';

-- FEL authorization UUID (SAT Guatemala) - unique for imported DTE
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_autorizacion_uuid` varchar(64) NULL DEFAULT NULL AFTER `version`,
    ADD UNIQUE KEY `idx_fel_autorizacion_uuid` (`fel_autorizacion_uuid`);

-- FEL document type (e.g. FCAM = Factura Cambiaria)
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_tipo_dte` varchar(20) NULL DEFAULT NULL AFTER `fel_autorizacion_uuid`;

-- FEL emission datetime (from DatosGenerales FechaHoraEmision)
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_fecha_emision` datetime NULL DEFAULT NULL AFTER `fel_tipo_dte`;

-- Emisor (issuer) from XML
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_emisor_nit` varchar(20) NULL DEFAULT NULL AFTER `fel_fecha_emision`,
    ADD COLUMN `fel_emisor_nombre` varchar(255) NULL DEFAULT NULL AFTER `fel_emisor_nit`;

-- Receptor (receiver) from XML
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_receptor_id` varchar(50) NULL DEFAULT NULL AFTER `fel_emisor_nombre`,
    ADD COLUMN `fel_receptor_nombre` varchar(255) NULL DEFAULT NULL AFTER `fel_receptor_id`,
    ADD COLUMN `fel_receptor_direccion` text NULL DEFAULT NULL AFTER `fel_receptor_nombre`;

-- Currency from XML (CodigoMoneda)
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `fel_moneda` varchar(5) NULL DEFAULT NULL AFTER `fel_receptor_direccion`;

-- Source: 'order' = from work order, 'fel_import' = from XML
ALTER TABLE `#__ordenproduccion_invoices`
    ADD COLUMN `invoice_source` varchar(20) NOT NULL DEFAULT 'order' AFTER `fel_moneda`;
