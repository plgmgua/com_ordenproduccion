-- 3.118.26 - Approval workflow: cotización facturación manual (factura fuera de monto exacto)
INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Cotización — facturación manual', 'Aprueba facturación cuando la cotización no es “facturar monto exacto”; instrucciones en la cotización.', 'cotizacion_facturacion_manual', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'cotizacion_facturacion_manual');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Aprobar', 'named_group', 'Administracion', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'cotizacion_facturacion_manual' AND s.`id` IS NULL;
