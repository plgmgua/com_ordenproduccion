-- com_ordenproduccion 3.119.273-STABLE: approval workflow for payment proof deletion (Control de Pagos).

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Eliminación de comprobante de pago', 'Aprueba la eliminación de comprobantes de pago desde Control de Pagos.', 'payment_proof_deletion', 0, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'payment_proof_deletion');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Revisar eliminación de comprobante de pago', 'named_group', 'Aprobaciones Ventas', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'payment_proof_deletion' AND s.`id` IS NULL;
