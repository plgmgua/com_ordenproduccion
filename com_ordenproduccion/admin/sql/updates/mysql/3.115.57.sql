-- com_ordenproduccion 3.115.57-STABLE: approval workflow for «Creación de Orden de Trabajo» (wizard → Aprobaciones Ventas → create OT).

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Creación de Orden de Trabajo', 'Aprueba la creación de la orden de trabajo desde la cotización confirmada (asistente de 3 pasos).', 'creacion_orden_trabajo', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'creacion_orden_trabajo');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Revisar creación de orden de trabajo', 'named_group', 'Aprobaciones Ventas', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'creacion_orden_trabajo' AND s.`id` IS NULL;
