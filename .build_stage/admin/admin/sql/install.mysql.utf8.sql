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
