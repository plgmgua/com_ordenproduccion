-- --------------------------------------------------------
-- Database installation script for com_ordenproduccion
-- Version: 1.0.0-STABLE
-- Author: Grimpsa
-- Date: 2025-01-27
-- --------------------------------------------------------

-- Main work orders table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_ordenes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `orden_de_trabajo` varchar(50) NOT NULL,
    `marca_temporal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_de_solicitud` date DEFAULT NULL,
    `fecha_de_entrega` date DEFAULT NULL,
    `nombre_del_cliente` varchar(255) NOT NULL,
    `nit` varchar(50) DEFAULT NULL,
    `direccion_de_entrega` text,
    `agente_de_ventas` varchar(255) DEFAULT NULL,
    `descripcion_de_trabajo` text,
    `material` varchar(255) DEFAULT NULL,
    `medidas_en_pulgadas` varchar(100) DEFAULT NULL,
    `adjuntar_cotizacion` varchar(255) DEFAULT NULL,
    `corte` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_corte` text,
    `bloqueado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_bloqueado` text,
    `doblado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_doblado` text,
    `laminado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_laminado` text,
    `lomo` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_lomo` text,
    `numerado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_numerado` text,
    `pegado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_pegado` text,
    `sizado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_sizado` text,
    `engrapado` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_engrapado` text,
    `troquel` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_troquel` text,
    `troquel_cameo` enum('SI','NO') DEFAULT 'NO',
    `detalles_de_troquel_cameo` text,
    `observaciones_instrucciones_generales` text,
    `barniz` enum('SI','NO') DEFAULT 'NO',
    `descripcion_de_barniz` text,
    `impresion_en_blanco` enum('SI','NO') DEFAULT 'NO',
    `descripcion_de_acabado_en_blanco` text,
    `color_de_impresion` varchar(100) DEFAULT NULL,
    `direccion_de_correo_electronico` varchar(255) DEFAULT NULL,
    `tiro_retiro` varchar(100) DEFAULT NULL,
    `valor_a_facturar` decimal(10,2) DEFAULT NULL,
    `archivo_de_arte` varchar(255) DEFAULT NULL,
    `despuntados` enum('SI','NO') DEFAULT 'NO',
    `descripcion_de_despuntados` text,
    `ojetes` enum('SI','NO') DEFAULT 'NO',
    `descripcion_de_ojetes` text,
    `perforado` enum('SI','NO') DEFAULT 'NO',
    `descripcion_de_perforado` text,
    `contacto_nombre` varchar(255) DEFAULT NULL,
    `contacto_telefono` varchar(50) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    `version` varchar(20) DEFAULT '1.0.0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_orden_de_trabajo` (`orden_de_trabajo`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_fecha_entrega` (`fecha_de_entrega`),
    KEY `idx_cliente` (`nombre_del_cliente`),
    KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EAV (Entity-Attribute-Value) data structure for flexible order information
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_info` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero_de_orden` varchar(50) NOT NULL,
    `tipo_de_campo` varchar(50) NOT NULL,
    `valor` text,
    `usuario` varchar(100) DEFAULT NULL,
    `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_numero_orden` (`numero_de_orden`),
    KEY `idx_tipo_campo` (`tipo_de_campo`),
    KEY `idx_timestamp` (`timestamp`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration settings table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `description` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_setting_key` (`setting_key`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Technician assignments table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_technicians` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero_de_orden` varchar(50) NOT NULL,
    `technician_name` varchar(255) NOT NULL,
    `assigned_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` int(11) NOT NULL DEFAULT 0,
    `status` enum('assigned','working','completed') DEFAULT 'assigned',
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_numero_orden` (`numero_de_orden`),
    KEY `idx_technician_name` (`technician_name`),
    KEY `idx_assigned_date` (`assigned_date`),
    KEY `idx_status` (`status`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily attendance integration table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_attendance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `person_name` varchar(255) NOT NULL,
    `auth_date` date NOT NULL,
    `auth_time` time DEFAULT NULL,
    `auth_datetime` datetime DEFAULT NULL,
    `direction` varchar(20) DEFAULT NULL,
    `device_name` varchar(100) DEFAULT NULL,
    `device_serial_no` varchar(100) DEFAULT NULL,
    `card_no` varchar(50) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_person_name` (`person_name`),
    KEY `idx_auth_date` (`auth_date`),
    KEY `idx_auth_datetime` (`auth_datetime`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook processing logs table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_webhook_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `webhook_type` varchar(50) NOT NULL,
    `request_data` longtext,
    `response_data` longtext,
    `status` enum('success','error','pending') DEFAULT 'pending',
    `error_message` text,
    `processing_time` decimal(10,4) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_webhook_type` (`webhook_type`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping and delivery tracking table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_shipping` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero_de_orden` varchar(50) NOT NULL,
    `shipping_type` enum('completa','parcial') DEFAULT 'completa',
    `shipping_description` text,
    `delivery_address` text,
    `contact_name` varchar(255) DEFAULT NULL,
    `contact_phone` varchar(50) DEFAULT NULL,
    `delivery_instructions` text,
    `delivery_date` date DEFAULT NULL,
    `delivery_time` time DEFAULT NULL,
    `delivery_status` enum('pending','in_transit','delivered','failed') DEFAULT 'pending',
    `delivery_notes` text,
    `delivery_image` longtext,
    `tracking_number` varchar(100) DEFAULT NULL,
    `shipping_cost` decimal(10,2) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_numero_orden` (`numero_de_orden`),
    KEY `idx_delivery_status` (`delivery_status`),
    KEY `idx_delivery_date` (`delivery_date`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Production notes table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_production_notes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `numero_de_orden` varchar(50) NOT NULL,
    `note_type` enum('production','quality','shipping','general') DEFAULT 'general',
    `note_content` text NOT NULL,
    `note_author` varchar(255) NOT NULL,
    `note_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_urgent` tinyint(1) DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_numero_orden` (`numero_de_orden`),
    KEY `idx_note_type` (`note_type`),
    KEY `idx_note_date` (`note_date`),
    KEY `idx_is_urgent` (`is_urgent`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration values
INSERT INTO `#__ordenproduccion_config` (`setting_key`, `setting_value`, `description`, `created_by`) VALUES
('default_order_prefix', 'ORD', 'Default prefix for order numbers', 0),
('enable_debug', '0', 'Enable debug logging', 0),
('debug_log_level', 'DEBUG', 'Debug log level', 0),
('debug_log_retention_days', '7', 'Debug log retention in days', 0),
('webhook_enabled', '0', 'Enable webhook integration', 0),
('webhook_secret', '', 'Webhook secret key for HMAC validation', 0),
('auto_assign_technicians', '1', 'Auto-assign technicians from attendance', 0),
('items_per_page', '20', 'Default items per page', 0),
('enable_calendar_view', '1', 'Enable calendar view in dashboard', 0),
('component_version', '1.0.0-STABLE', 'Current component version', 0),
('order_status_nueva', 'Nueva', 'Order status: New', 0),
('order_status_en_proceso', 'En Proceso', 'Order status: In Process', 0),
('order_status_terminada', 'Terminada', 'Order status: Completed', 0),
('order_status_cerrada', 'Cerrada', 'Order status: Closed', 0),
('order_type_interna', 'Interna', 'Order type: Internal', 0),
('order_type_externa', 'Externa', 'Order type: External', 0);

-- Approval workflow engine (same DDL as admin/sql/updates/mysql/3.102.0.sql; fresh installs)
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_workflows` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `entity_type` varchar(64) NOT NULL COMMENT 'cotizacion_confirmation, orden_status, timesheet, payment_proof',
    `published` tinyint(1) NOT NULL DEFAULT 1,
    `email_subject_assign` varchar(255) DEFAULT NULL,
    `email_body_assign` mediumtext,
    `email_subject_decided` varchar(255) DEFAULT NULL,
    `email_body_decided` mediumtext,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_entity_published` (`entity_type`, `published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_workflow_steps` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `workflow_id` int(11) NOT NULL,
    `step_number` int(11) NOT NULL,
    `step_name` varchar(255) NOT NULL,
    `approver_type` varchar(32) NOT NULL DEFAULT 'named_group' COMMENT 'user, joomla_group, named_group',
    `approver_value` varchar(512) NOT NULL DEFAULT '' COMMENT 'user id, group id(s), or Joomla group title(s) comma-separated',
    `require_all` tinyint(1) NOT NULL DEFAULT 0,
    `timeout_hours` int(11) NOT NULL DEFAULT 0,
    `timeout_action` varchar(32) NOT NULL DEFAULT 'escalate' COMMENT 'escalate, auto_approve, auto_reject',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_workflow_step` (`workflow_id`, `step_number`),
    KEY `idx_workflow` (`workflow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` varchar(64) NOT NULL,
    `entity_id` int(11) NOT NULL,
    `workflow_id` int(11) NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, cancelled',
    `submitter_id` int(11) NOT NULL,
    `current_step_number` int(11) NOT NULL DEFAULT 1,
    `metadata` text COMMENT 'JSON: transition_key for orden_status, etc.',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed` datetime DEFAULT NULL,
    `modified` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_status` (`status`),
    KEY `idx_workflow` (`workflow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_request_steps` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `request_id` int(11) NOT NULL,
    `step_number` int(11) NOT NULL,
    `approver_user_id` int(11) NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, skipped',
    `comments` text,
    `decided_date` datetime DEFAULT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_request` (`request_id`),
    KEY `idx_approver_pending` (`approver_user_id`, `status`),
    KEY `idx_step` (`request_id`, `step_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_audit_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `request_id` int(11) NOT NULL,
    `action` varchar(64) NOT NULL,
    `user_id` int(11) NOT NULL,
    `old_status` varchar(32) DEFAULT NULL,
    `new_status` varchar(32) DEFAULT NULL,
    `comments` text,
    `ip_address` varchar(45) DEFAULT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_request` (`request_id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_email_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `request_id` int(11) NOT NULL,
    `event` varchar(32) NOT NULL DEFAULT 'assign' COMMENT 'assign, decided',
    `to_user_id` int(11) DEFAULT NULL,
    `to_email` varchar(255) DEFAULT NULL,
    `subject` varchar(255) NOT NULL,
    `body` mediumtext NOT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT 'pending, sent, failed',
    `attempt_count` int(11) NOT NULL DEFAULT 0,
    `last_attempt` datetime DEFAULT NULL,
    `next_retry_at` datetime DEFAULT NULL,
    `error_message` text,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status_retry` (`status`, `next_retry_at`),
    KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `description` text,
    `published` tinyint(1) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_approval_group_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_approval_group_user` (`group_id`, `user_id`),
    KEY `idx_group_id` (`group_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Cotización — confirmación', 'Aprueba la confirmación de cotización tras cargar documentos.', 'cotizacion_confirmation', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'cotizacion_confirmation');

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Orden — cambio de estado', 'Aprueba transiciones de estado restringidas (ej. Nueva → En Proceso).', 'orden_status', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'orden_status');

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Hoja de tiempo', 'Aprueba registros de tiempo.', 'timesheet', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'timesheet');

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Comprobante de pago', 'Verifica comprobantes de pago.', 'payment_proof', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'payment_proof');

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Pre-cotización — solicitud de descuento', 'Notifica a ventas para revisar y ajustar subtotales de línea.', 'solicitud_descuento', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'solicitud_descuento');

INSERT INTO `#__ordenproduccion_approval_workflows` (`name`, `description`, `entity_type`, `published`, `created_by`)
SELECT 'Solicitud de Cotizacion', 'Autoriza solicitar cotización al proveedor en pre-cotización modo proveedor externo.', 'solicitud_cotizacion', 1, 0
FROM (SELECT 1 AS `x`) AS `t`
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_approval_workflows` WHERE `entity_type` = 'solicitud_cotizacion');

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Aprobar', 'named_group', 'Administracion', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE s.`id` IS NULL;

UPDATE `#__ordenproduccion_approval_workflow_steps` AS s
INNER JOIN `#__ordenproduccion_approval_workflows` AS w ON w.`id` = s.`workflow_id`
SET s.`step_name` = 'Revisar descuento', s.`approver_value` = 'Aprobaciones Ventas'
WHERE w.`entity_type` = 'solicitud_descuento' AND s.`step_number` = 1;

UPDATE `#__ordenproduccion_approval_workflow_steps` AS s
INNER JOIN `#__ordenproduccion_approval_workflows` AS w ON w.`id` = s.`workflow_id`
SET s.`step_name` = 'Revisar solicitud de cotización', s.`approver_value` = 'Aprobaciones Ventas'
WHERE w.`entity_type` = 'solicitud_cotizacion' AND s.`step_number` = 1;

-- Telegram bot: per-user chat_id (3.105.0)
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_users` (
    `user_id` int(11) NOT NULL,
    `chat_id` varchar(32) NOT NULL,
    `created` datetime NOT NULL,
    `modified` datetime DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram outbound queue (3.108.0): cron processes pending rows
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_queue` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` varchar(32) NOT NULL,
    `body` mediumtext NOT NULL,
    `attempts` tinyint unsigned NOT NULL DEFAULT 0,
    `created` datetime NOT NULL,
    `last_try` datetime DEFAULT NULL,
    `last_error` varchar(1024) DEFAULT NULL,
    `mismatch_anchor_payment_proof_id` int unsigned DEFAULT NULL,
    `mismatch_anchor_joomla_user_id` int unsigned DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram inbound webhook log (3.109.41): each POST (or non-POST attempt) to task=webhook
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_webhook_log` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `created` datetime NOT NULL,
    `ip` varchar(64) NOT NULL DEFAULT '',
    `user_agent` varchar(512) NOT NULL DEFAULT '',
    `http_method` varchar(8) NOT NULL DEFAULT '',
    `body_length` int unsigned NOT NULL DEFAULT 0,
    `secret_header_present` tinyint unsigned NOT NULL DEFAULT 0,
    `secret_valid` tinyint unsigned NOT NULL DEFAULT 0,
    `http_status` smallint NOT NULL DEFAULT 0,
    `outcome` varchar(80) NOT NULL DEFAULT '',
    `update_id` bigint DEFAULT NULL,
    `chat_id` varchar(32) DEFAULT NULL,
    `text_preview` varchar(512) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created`),
    KEY `idx_outcome` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram sent log (3.109.0): rows copied here when a queued message is delivered
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_sent_log` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` varchar(32) NOT NULL,
    `body` mediumtext NOT NULL,
    `queued_created` datetime NOT NULL,
    `sent_at` datetime NOT NULL,
    `source_queue_id` int unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_sent_at` (`sent_at`),
    KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proveedores (vendors) — 3.110.0
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_proveedores` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `nit` varchar(64) NOT NULL DEFAULT '',
    `address` text,
    `phone` varchar(64) NOT NULL DEFAULT '',
    `contact_name` varchar(255) NOT NULL DEFAULT '',
    `contact_cellphone` varchar(64) NOT NULL DEFAULT '',
    `contact_email` varchar(255) NOT NULL DEFAULT '',
    `state` tinyint(3) NOT NULL DEFAULT 1 COMMENT '1=Activo 0=Inactivo',
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_proveedor_productos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `proveedor_id` int(11) NOT NULL,
    `product_value` varchar(500) NOT NULL DEFAULT '' COMMENT 'Product or service line (metadata-style)',
    `ordering` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_proveedor_id` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendor quote request templates (3.113.0)
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_vendor_quote_templates` (
    `channel` varchar(16) NOT NULL COMMENT 'email|cellphone|pdf',
    `subject` varchar(512) NOT NULL DEFAULT '',
    `body` mediumtext NOT NULL,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__ordenproduccion_vendor_quote_templates` (`channel`, `subject`, `body`, `modified`, `modified_by`) VALUES
('email', 'Solicitud de cotización {PRECOT_NUMERO}', 'Estimados {PROVEEDOR_NOMBRE},\n\nSolicitamos cotización para la siguiente solicitud ({PRECOT_NUMERO}):\n\n{LINEAS_TEXTO}\n\nNotas / descripción general:\n{PRECOT_DESCRIPCION}\n\nQuedamos atentos.\n\n{USUARIO_NOMBRE}\n{USUARIO_EMAIL}', NOW(), 0),
('cellphone', '', 'Hola {PROVEEDOR_NOMBRE}, solicito cotización {PRECOT_NUMERO}. Detalle: {LINEAS_TEXTO_CORTO}', NOW(), 0),
('pdf', '', 'SOLICITUD DE COTIZACIÓN {PRECOT_NUMERO}\n\nProveedor: {PROVEEDOR_NOMBRE}\n\n{LINEAS_TEXTO}\n\nDescripción: {PRECOT_DESCRIPCION}\nMedidas / notas: {PRECOT_MEDIDAS}\n\nSolicitante: {USUARIO_NOMBRE} ({USUARIO_EMAIL})', NOW(), 0);

-- Pre-cot proveedor externo: vendor quote request event log (3.113.6)
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_precot_vendor_quote_event` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `pre_cotizacion_id` int(11) NOT NULL,
    `proveedor_id` int(11) NOT NULL DEFAULT 0,
    `event_type` varchar(32) NOT NULL COMMENT 'email_sent|pdf_download|cellphone_compose',
    `meta` text COMMENT 'JSON: actor_name, to_email, subject, filename, phone, etc.',
    `vendor_quote_attachment` varchar(512) DEFAULT NULL COMMENT 'Relative path under site root (precot_vendor_quote)',
    `condiciones_entrega` text COMMENT 'Delivery conditions for this request (user-editable)',
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_precot` (`pre_cotizacion_id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for vendor quote requests on external-vendor pre-cotizaciones';
