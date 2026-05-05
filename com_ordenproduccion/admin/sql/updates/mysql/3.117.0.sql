-- com_ordenproduccion 3.117.0-STABLE: Servicio tercerizado — importe pendiente (Ventas) + workflow «Servicios y elementos externos»
-- Version: 3.117.0

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1
);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'tercerizado_importe_pendiente') = 0,
    CONCAT(
        'ALTER TABLE `', @tbl,
        '` ADD COLUMN `tercerizado_importe_pendiente` TINYINT(1) NOT NULL DEFAULT 0',
        ' COMMENT ''1 = precio por definir (solicitud a Aprobaciones Ventas)'' AFTER `tercerizado_producto`'
    ),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Servicios y elementos externos', 'Notifica a Aprobaciones Ventas para definir el importe de una línea de servicio tercerizado.', 'servicios_elementos_externos', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'servicios_elementos_externos');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Definir importe servicio/externo', 'named_group', 'Aprobaciones Ventas', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'servicios_elementos_externos' AND s.`id` IS NULL;
