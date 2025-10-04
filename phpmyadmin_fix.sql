-- phpMyAdmin Fix Script for com_ordenproduccion
-- Version: 1.0.0
-- Run this script directly in phpMyAdmin to create tables and register component

-- ==========================================
-- STEP 1: DROP EXISTING TABLES (if they exist with wrong prefix)
-- ==========================================

DROP TABLE IF EXISTS `#__ordenproduccion_attendance`;
DROP TABLE IF EXISTS `#__ordenproduccion_config`;
DROP TABLE IF EXISTS `#__ordenproduccion_info`;
DROP TABLE IF EXISTS `#__ordenproduccion_ordenes`;
DROP TABLE IF EXISTS `#__ordenproduccion_production_notes`;
DROP TABLE IF EXISTS `#__ordenproduccion_shipping`;
DROP TABLE IF EXISTS `#__ordenproduccion_technicians`;
DROP TABLE IF EXISTS `#__ordenproduccion_webhook_logs`;

-- ==========================================
-- STEP 2: CREATE TABLES WITH CORRECT PREFIX
-- ==========================================

-- Table structure for table `joomla_ordenproduccion_ordenes`
CREATE TABLE `joomla_ordenproduccion_ordenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `client_id` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `invoice_value` decimal(10,2) DEFAULT NULL,
  `work_description` text,
  `print_color` varchar(100) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `material` varchar(255) DEFAULT NULL,
  `quotation_files` text,
  `art_files` text,
  `cutting` enum('SI','NO') DEFAULT 'NO',
  `cutting_details` text,
  `blocking` enum('SI','NO') DEFAULT 'NO',
  `blocking_details` text,
  `folding` enum('SI','NO') DEFAULT 'NO',
  `folding_details` text,
  `laminating` enum('SI','NO') DEFAULT 'NO',
  `laminating_details` text,
  `spine` enum('SI','NO') DEFAULT 'NO',
  `gluing` enum('SI','NO') DEFAULT 'NO',
  `numbering` enum('SI','NO') DEFAULT 'NO',
  `numbering_details` text,
  `sizing` enum('SI','NO') DEFAULT 'NO',
  `stapling` enum('SI','NO') DEFAULT 'NO',
  `die_cutting` enum('SI','NO') DEFAULT 'NO',
  `die_cutting_details` text,
  `varnish` enum('SI','NO') DEFAULT 'NO',
  `varnish_details` text,
  `white_print` enum('SI','NO') DEFAULT 'NO',
  `trimming` enum('SI','NO') DEFAULT 'NO',
  `trimming_details` text,
  `eyelets` enum('SI','NO') DEFAULT 'NO',
  `perforation` enum('SI','NO') DEFAULT 'NO',
  `perforation_details` text,
  `instructions` text,
  `sales_agent` varchar(255) DEFAULT NULL,
  `request_date` datetime DEFAULT NULL,
  `status` enum('New','In Process','Completed','Closed') DEFAULT 'New',
  `order_type` enum('Internal','External') DEFAULT 'External',
  `assigned_technician` int(11) DEFAULT NULL,
  `production_notes` text,
  `shipping_address` text,
  `shipping_contact` varchar(255) DEFAULT NULL,
  `shipping_phone` varchar(50) DEFAULT NULL,
  `shipping_email` varchar(255) DEFAULT NULL,
  `shipping_date` date DEFAULT NULL,
  `shipping_status` enum('Pending','Shipped','Delivered') DEFAULT 'Pending',
  `tracking_number` varchar(100) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  `version` varchar(20) DEFAULT '1.0.0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_delivery_date` (`delivery_date`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`),
  KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_info`
CREATE TABLE `joomla_ordenproduccion_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_attribute_name` (`attribute_name`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`),
  CONSTRAINT `fk_info_order` FOREIGN KEY (`order_id`) REFERENCES `joomla_ordenproduccion_ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_technicians`
CREATE TABLE `joomla_ordenproduccion_technicians` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `workload` int(11) DEFAULT 0,
  `max_workload` int(11) DEFAULT 10,
  `is_active` tinyint(3) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_name` (`name`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_attendance`
CREATE TABLE `joomla_ordenproduccion_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `technician_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half Day') DEFAULT 'Present',
  `notes` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `technician_date` (`technician_id`, `attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`),
  CONSTRAINT `fk_attendance_technician` FOREIGN KEY (`technician_id`) REFERENCES `joomla_ordenproduccion_technicians` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_production_notes`
CREATE TABLE `joomla_ordenproduccion_production_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `note_type` enum('Progress','Issue','Completion','Quality') DEFAULT 'Progress',
  `note_content` text NOT NULL,
  `attachments` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_technician_id` (`technician_id`),
  KEY `idx_note_type` (`note_type`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`),
  CONSTRAINT `fk_notes_order` FOREIGN KEY (`order_id`) REFERENCES `joomla_ordenproduccion_ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_technician` FOREIGN KEY (`technician_id`) REFERENCES `joomla_ordenproduccion_technicians` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_shipping`
CREATE TABLE `joomla_ordenproduccion_shipping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `shipping_method` enum('Pickup','Delivery','Courier') DEFAULT 'Pickup',
  `shipping_address` text,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `shipping_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Shipped','Delivered','Returned') DEFAULT 'Pending',
  `notes` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_shipping_date` (`shipping_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`),
  CONSTRAINT `fk_shipping_order` FOREIGN KEY (`order_id`) REFERENCES `joomla_ordenproduccion_ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_webhook_logs`
CREATE TABLE `joomla_ordenproduccion_webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` varchar(100) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` text,
  `request_headers` text,
  `request_body` longtext,
  `response_status` int(11) DEFAULT NULL,
  `response_body` text,
  `processing_time` decimal(10,4) DEFAULT NULL,
  `status` enum('Success','Error','Pending') DEFAULT 'Pending',
  `error_message` text,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `joomla_ordenproduccion_config`
CREATE TABLE `joomla_ordenproduccion_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `config_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text,
  `is_system` tinyint(3) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `state` tinyint(3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_config_type` (`config_type`),
  KEY `idx_is_system` (`is_system`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- STEP 3: INSERT DEFAULT CONFIGURATION
-- ==========================================

INSERT INTO `joomla_ordenproduccion_config` (`config_key`, `config_value`, `config_type`, `description`, `is_system`, `created_by`) VALUES
('webhook_enabled', '1', 'boolean', 'Enable webhook endpoint', 1, 1),
('webhook_auto_assign', '1', 'boolean', 'Auto-assign orders to available technicians', 1, 1),
('default_order_status', 'New', 'string', 'Default status for new orders', 1, 1),
('max_workload_per_technician', '10', 'integer', 'Maximum workload per technician', 1, 1),
('debug_enabled', '0', 'boolean', 'Enable debug logging', 1, 1),
('debug_log_level', 'DEBUG', 'string', 'Debug log level', 1, 1),
('debug_log_retention_days', '7', 'integer', 'Days to retain debug logs', 1, 1);

-- ==========================================
-- STEP 4: REGISTER COMPONENT IN JOOMLA
-- ==========================================

-- Check if component is already registered and update/insert accordingly
INSERT INTO `joomla_extensions` (
    `package_id`, `name`, `type`, `element`, `changelogurl`, `folder`, `client_id`, 
    `enabled`, `access`, `protected`, `locked`, `manifest_cache`, `params`, 
    `custom_data`, `checked_out`, `checked_out_time`, `ordering`, `state`, `note`
) VALUES (
    0, 'com_ordenproduccion', 'component', 'com_ordenproduccion', '', '', 1,
    1, 1, 0, 0, 
    '{"legacy":false,"name":"com_ordenproduccion","type":"component","creationDate":"2025-01-27","author":"Grimpsa","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","license":"GNU General Public License version 2 or later","version":"1.0.0","description":"COM_ORDENPRODUCCION_XML_DESCRIPTION","group":""}',
    '{}', '{}', 0, NULL, 0, 0, ''
) ON DUPLICATE KEY UPDATE
    `enabled` = 1,
    `access` = 1,
    `protected` = 0,
    `locked` = 0,
    `manifest_cache` = '{"legacy":false,"name":"com_ordenproduccion","type":"component","creationDate":"2025-01-27","author":"Grimpsa","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","license":"GNU General Public License version 2 or later","version":"1.0.0","description":"COM_ORDENPRODUCCION_XML_DESCRIPTION","group":""}',
    `params` = '{}',
    `custom_data` = '{}',
    `checked_out` = 0,
    `checked_out_time` = NULL,
    `ordering` = 0,
    `state` = 0,
    `note` = '';

-- ==========================================
-- STEP 5: ADD MENU ENTRY
-- ==========================================

-- Get the component ID for the menu entry
SET @component_id = (SELECT extension_id FROM joomla_extensions WHERE element = 'com_ordenproduccion');

-- Insert menu entry
INSERT INTO `joomla_menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`
) VALUES (
    'main', 'Orden Produccion', 'orden-produccion', '', 'orden-produccion', 
    'index.php?option=com_ordenproduccion', 'component', 1, 1, 1, @component_id, 
    0, NULL, 0, 1, 'class:ordenproduccion', 0, '{}', 0, 0, 0, '*', 1, NULL, NULL
) ON DUPLICATE KEY UPDATE
    `published` = 1,
    `access` = 1;

-- ==========================================
-- STEP 6: VERIFICATION QUERIES
-- ==========================================

-- Show created tables
SELECT 'Created Tables:' as info;
SHOW TABLES LIKE 'joomla_ordenproduccion_%';

-- Show component registration
SELECT 'Component Registration:' as info;
SELECT extension_id, name, type, element, enabled FROM joomla_extensions WHERE element = 'com_ordenproduccion';

-- Show menu entry
SELECT 'Menu Entry:' as info;
SELECT id, title, alias, link, published FROM joomla_menu WHERE link LIKE '%com_ordenproduccion%';

-- Show configuration
SELECT 'Configuration:' as info;
SELECT config_key, config_value, config_type FROM joomla_ordenproduccion_config;
