-- com_ordenproduccion 3.113.26-STABLE: solicitud_cotizacion approval workflow (pre-cot proveedor externo).

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Solicitud de Cotizacion', 'Autoriza solicitar cotización al proveedor en pre-cotización modo proveedor externo.', 'solicitud_cotizacion', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'solicitud_cotizacion');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Revisar solicitud de cotización', 'named_group', 'Aprobaciones Ventas', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'solicitud_cotizacion' AND s.`id` IS NULL;
