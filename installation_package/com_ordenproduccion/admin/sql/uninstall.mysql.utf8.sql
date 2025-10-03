-- --------------------------------------------------------
-- Database uninstall script for com_ordenproduccion
-- Version: 1.0.0-STABLE
-- Author: Grimpsa
-- Date: 2025-01-27
-- 
-- WARNING: This will permanently delete all component data!
-- Make sure to backup your data before uninstalling.
-- --------------------------------------------------------

-- Drop all component tables
DROP TABLE IF EXISTS `#__ordenproduccion_production_notes`;
DROP TABLE IF EXISTS `#__ordenproduccion_shipping`;
DROP TABLE IF EXISTS `#__ordenproduccion_webhook_logs`;
DROP TABLE IF EXISTS `#__ordenproduccion_attendance`;
DROP TABLE IF EXISTS `#__ordenproduccion_technicians`;
DROP TABLE IF EXISTS `#__ordenproduccion_config`;
DROP TABLE IF EXISTS `#__ordenproduccion_info`;
DROP TABLE IF EXISTS `#__ordenproduccion_ordenes`;

-- Note: The following tables are kept for compatibility with existing Grimpsa system:
-- - asistencia (attendance system)
-- - ordenes_de_trabajo (existing work orders)
-- - ordenes_info (existing order info)
-- 
-- These tables are managed by the existing system and should not be dropped
-- during component uninstallation to preserve data integrity.
