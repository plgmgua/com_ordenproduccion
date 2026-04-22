-- com_ordenproduccion 3.113.47-STABLE: Orden de compra (ORC) + approval workflow orden_compra.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_orden_compra` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `number` varchar(32) NOT NULL COMMENT 'ORC-00001',
    `precotizacion_id` int unsigned NOT NULL,
    `proveedor_id` int unsigned NOT NULL,
    `vendor_quote_event_id` int unsigned DEFAULT NULL,
    `condiciones_entrega` text COMMENT 'Copied from event / editable snapshot',
    `proveedor_snapshot` mediumtext COMMENT 'JSON: vendor fields at creation',
    `currency` varchar(8) NOT NULL DEFAULT 'Q',
    `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
    `workflow_status` varchar(32) NOT NULL DEFAULT 'pending_approval' COMMENT 'pending_approval|approved|rejected',
    `approval_request_id` int unsigned DEFAULT NULL,
    `state` tinyint NOT NULL DEFAULT 1,
    `created` datetime NOT NULL,
    `created_by` int NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_oc_number` (`number`),
    KEY `idx_precot_prov` (`precotizacion_id`, `proveedor_id`),
    KEY `idx_wf_status` (`workflow_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_orden_compra_line` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `orden_compra_id` int unsigned NOT NULL,
    `precotizacion_line_id` int unsigned NOT NULL,
    `quantity` int NOT NULL DEFAULT 1,
    `descripcion` text,
    `vendor_unit_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'P.Unit Proveedor',
    `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_orden_compra` (`orden_compra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Orden de Compra', 'Aprueba la creación de órdenes de compra a proveedor desde pre-cotización (P.Unit Proveedor).', 'orden_compra', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'orden_compra');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Revisar orden de compra', 'named_group', 'Administracion,Administración', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE w.`entity_type` = 'orden_compra' AND s.`id` IS NULL;
