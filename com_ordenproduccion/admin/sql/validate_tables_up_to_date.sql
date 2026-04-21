-- =============================================================================
-- Validate component tables are up to date (expected columns exist)
-- Prefix: joomla_ — change if your site uses a different prefix.
-- Run in phpMyAdmin; run each section separately or run the combined check at
-- the end. Any "MISSING" means run the corresponding migration.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1) Payment proofs: mismatch note (3.82.0) — reason when Diferencia exists
-- Expect 2 rows. If 0, run 3.82.0_payment_proof_mismatch_note migration.
-- -----------------------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_payment_proofs'
  AND COLUMN_NAME IN ('mismatch_note', 'mismatch_difference')
ORDER BY ORDINAL_POSITION;


-- -----------------------------------------------------------------------------
-- 2) Ordenes: pre_cotizacion_id (3.81.0) — link work order to pre-cotización
-- Expect 1 row. If 0, run 3.81.0_ordenes_pre_cotizacion_id migration.
-- -----------------------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_ordenes'
  AND COLUMN_NAME = 'pre_cotizacion_id';


-- -----------------------------------------------------------------------------
-- 3) Quotations: client_id, sales_agent (3.74.0) — API and client association
-- Expect 2 rows. If missing, run 3.74.0 or MANUAL_quotations_client_id migration.
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_quotations'
  AND COLUMN_NAME IN ('client_id', 'sales_agent')
ORDER BY COLUMN_NAME;


-- -----------------------------------------------------------------------------
-- 4) Quotation items: pre_cotizacion_id (3.74.0)
-- Expect 1 row.
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_quotation_items'
  AND COLUMN_NAME = 'pre_cotizacion_id';


-- -----------------------------------------------------------------------------
-- 5) Quotations: confirmation fields (3.80.0) — signed doc, billing instructions
-- Expect 2 rows.
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_quotations'
  AND COLUMN_NAME IN ('signed_document_path', 'instrucciones_facturacion')
ORDER BY COLUMN_NAME;


-- -----------------------------------------------------------------------------
-- 6) Pre-cotización: descripcion (3.75.0)
-- Expect 1 row.
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_pre_cotizacion'
  AND COLUMN_NAME = 'descripcion';


-- -----------------------------------------------------------------------------
-- 7) Payment proofs: verification_status (3.84.0) — Ingresado/Verificado
-- Expect 1 row. If 0, run 3.84.0_payment_proof_verification_status migration.
-- -----------------------------------------------------------------------------
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'joomla_ordenproduccion_payment_proofs'
  AND COLUMN_NAME = 'verification_status';


-- =============================================================================
-- 8) COMBINED CHECK — one result set: all key columns, YES or MISSING
-- Every row should show column_exists = YES. Any MISSING = run that migration.
-- =============================================================================
SELECT t.TABLE_NAME,
       t.COLUMN_NAME,
       IF(c.COLUMN_NAME IS NOT NULL, 'YES', 'MISSING') AS column_exists
FROM (
    SELECT 'joomla_ordenproduccion_payment_proofs' AS TABLE_NAME, 'mismatch_note' AS COLUMN_NAME
    UNION SELECT 'joomla_ordenproduccion_payment_proofs', 'mismatch_difference'
    UNION SELECT 'joomla_ordenproduccion_payment_proofs', 'verification_status'
    UNION SELECT 'joomla_ordenproduccion_ordenes', 'pre_cotizacion_id'
    UNION SELECT 'joomla_ordenproduccion_quotations', 'client_id'
    UNION SELECT 'joomla_ordenproduccion_quotations', 'sales_agent'
    UNION SELECT 'joomla_ordenproduccion_quotations', 'signed_document_path'
    UNION SELECT 'joomla_ordenproduccion_quotations', 'instrucciones_facturacion'
    UNION SELECT 'joomla_ordenproduccion_quotation_items', 'pre_cotizacion_id'
    UNION SELECT 'joomla_ordenproduccion_quotation_items', 'valor_final'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion', 'descripcion'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion', 'lines_subtotal'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion', 'total_final'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion', 'margen_adicional'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion_line', 'tipo_elemento'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion_line_detalles', 'concepto_key'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion_confirmation', 'quotation_id'
    UNION SELECT 'joomla_ordenproduccion_pre_cotizacion_confirmation', 'line_detalles_json'
    UNION SELECT 'joomla_ordenproduccion_payment_proof_lines', 'document_date'
) t
LEFT JOIN INFORMATION_SCHEMA.COLUMNS c
  ON c.TABLE_SCHEMA = DATABASE()
  AND c.TABLE_NAME = t.TABLE_NAME
  AND c.COLUMN_NAME = t.COLUMN_NAME
ORDER BY t.TABLE_NAME, t.COLUMN_NAME;

-- -----------------------------------------------------------------------------
-- Proveedores (3.110.0) — expect tables exist after component update
-- -----------------------------------------------------------------------------
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('joomla_ordenproduccion_proveedores', 'joomla_ordenproduccion_proveedor_productos')
ORDER BY TABLE_NAME;
