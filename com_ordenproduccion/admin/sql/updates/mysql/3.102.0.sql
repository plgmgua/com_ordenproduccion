-- Internal approval workflow engine (Option B) — schema only; entity hooks in later versions.
-- Version: 3.102.0

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

-- Seed: one workflow per entity type, single step, named_group Administracion (extend to Admon in admin UI if needed)
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

INSERT INTO `#__ordenproduccion_approval_workflow_steps` (`workflow_id`, `step_number`, `step_name`, `approver_type`, `approver_value`, `require_all`, `timeout_hours`, `timeout_action`, `created_by`)
SELECT w.`id`, 1, 'Aprobar', 'named_group', 'Administracion', 0, 0, 'escalate', 0
FROM `#__ordenproduccion_approval_workflows` AS w
LEFT JOIN `#__ordenproduccion_approval_workflow_steps` AS s ON s.`workflow_id` = w.`id` AND s.`step_number` = 1
WHERE s.`id` IS NULL;
