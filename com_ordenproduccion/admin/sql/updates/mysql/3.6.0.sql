-- Migration for Manual Asistencia Entries Table
-- Version 3.6.0
-- Creates separate table for manual entries to preserve original asistencia table integrity

-- Create manual asistencia entries table (matches structure of original asistencia table)
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_asistencia_manual` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) DEFAULT NULL,
    `personname` varchar(255) NOT NULL,
    `authdate` varchar(20) DEFAULT NULL,
    `authtime` varchar(20) DEFAULT NULL,
    `authdatetime` varchar(30) DEFAULT NULL,
    `direction` varchar(50) DEFAULT NULL,
    `devicename` varchar(255) DEFAULT 'Manual Entry',
    `deviceserialno` varchar(255) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_personname` (`personname`),
    KEY `idx_authdate` (`authdate`),
    KEY `idx_cardno` (`cardno`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

