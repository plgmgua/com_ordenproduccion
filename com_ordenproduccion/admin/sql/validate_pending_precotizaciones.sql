-- =============================================================================
-- Validate "Pending pre-cotizaciones" webhook result (same dataset as the API)
-- Replace joomla_ with your Joomla table prefix.
-- Replace 7 with the client_id you are testing (e.g. from ?client_id=7).
-- pre_cotizacion_value in the API = lines_subtotal + margin/IVA/ISR/commission
-- (component parameters); this query only shows lines_subtotal for comparison.
-- =============================================================================

SET @client_id = 7;

SELECT
    q.id                    AS quotation_id,
    COALESCE(NULLIF(TRIM(q.quotation_number), ''), CONCAT('COT-', LPAD(q.id, 6, '0'))) AS quotation_number,
    qi.pre_cotizacion_id    AS pre_cotizacion_id,
    COALESCE(NULLIF(TRIM(pc.number), ''), CONCAT('PRE-', LPAD(pc.id, 5, '0'))) AS pre_cotizacion_number,
    COALESCE(pc.descripcion, '') AS pre_cotizacion_description,
    -- Lines subtotal (API applies margin/IVA/ISR/commission to get pre_cotizacion_value)
    COALESCE((
        SELECT SUM(l.total)
        FROM joomla_ordenproduccion_pre_cotizacion_line l
        WHERE l.pre_cotizacion_id = pc.id
          AND (NOT (l.line_type <=> 'envio'))
    ), 0) AS lines_subtotal
FROM joomla_ordenproduccion_quotations q
INNER JOIN joomla_ordenproduccion_quotation_items qi
    ON qi.quotation_id = q.id
    AND qi.pre_cotizacion_id IS NOT NULL
INNER JOIN joomla_ordenproduccion_pre_cotizacion pc
    ON pc.id = qi.pre_cotizacion_id
    AND pc.state = 1
LEFT JOIN joomla_ordenproduccion_ordenes o
    ON o.pre_cotizacion_id = qi.pre_cotizacion_id
    AND o.state = 1
WHERE q.state = 1
  AND (q.client_id = @client_id OR (q.client_id IS NOT NULL AND CAST(q.client_id AS CHAR) = CAST(@client_id AS CHAR)))
  AND o.id IS NULL
GROUP BY q.id, q.quotation_number, qi.pre_cotizacion_id, pc.id, pc.number, pc.descripcion
ORDER BY q.id, qi.pre_cotizacion_id;

-- -----------------------------------------------------------------------------
-- Alternative if pre_cotizacion_line has NO line_type column (older installs):
-- Use this lines_subtotal instead (no envio exclusion):
--
--   COALESCE((SELECT SUM(l.total) FROM joomla_ordenproduccion_pre_cotizacion_line l WHERE l.pre_cotizacion_id = pc.id), 0) AS lines_subtotal
--
-- Then run the same FROM/WHERE/GROUP BY as above.
-- =============================================================================
