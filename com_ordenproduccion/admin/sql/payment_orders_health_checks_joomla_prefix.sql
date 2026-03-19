-- ---------------------------------------------------------------------------
-- Payment / comprobante data integrity checks (READ-ONLY)
-- Table prefix: joomla_  (change if your site uses a different prefix)
-- Run in phpMyAdmin or mysql CLI. Review results before any manual DELETE.
-- ---------------------------------------------------------------------------

-- 1) Duplicate (payment_proof_id, order_id) — should return 0 rows if UNIQUE idx_payment_order exists
SELECT payment_proof_id, order_id, COUNT(*) AS cnt
FROM joomla_ordenproduccion_payment_orders
GROUP BY payment_proof_id, order_id
HAVING cnt > 1;

-- 2) payment_orders rows with no matching published comprobante
SELECT po.*
FROM joomla_ordenproduccion_payment_orders po
LEFT JOIN joomla_ordenproduccion_payment_proofs pp
  ON pp.id = po.payment_proof_id AND pp.state = 1
WHERE pp.id IS NULL;

-- 3) payment_orders rows with no matching published orden de trabajo
SELECT po.*
FROM joomla_ordenproduccion_payment_orders po
LEFT JOIN joomla_ordenproduccion_ordenes o
  ON o.id = po.order_id AND o.state = 1
WHERE o.id IS NULL;

-- 4) Published comprobantes with no payment_orders row (possible failed save)
SELECT pp.*
FROM joomla_ordenproduccion_payment_proofs pp
LEFT JOIN joomla_ordenproduccion_payment_orders po
  ON po.payment_proof_id = pp.id
WHERE pp.state = 1
  AND po.id IS NULL;

-- 5) payment_proof_lines pointing to a missing proof
SELECT ppl.*
FROM joomla_ordenproduccion_payment_proof_lines ppl
LEFT JOIN joomla_ordenproduccion_payment_proofs pp
  ON pp.id = ppl.payment_proof_id
WHERE pp.id IS NULL;

-- 6) Inspect one comprobante by id (example: 120)
-- SELECT * FROM joomla_ordenproduccion_payment_proofs WHERE id = 120;
-- SELECT * FROM joomla_ordenproduccion_payment_orders WHERE payment_proof_id = 120;
-- SELECT * FROM joomla_ordenproduccion_payment_proof_lines WHERE payment_proof_id = 120;
