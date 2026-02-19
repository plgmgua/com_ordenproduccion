-- ============================================
-- Pliego sizes – for manual run in phpMyAdmin
-- Prefix: joomla_
-- ============================================
-- Run EITHER Option A OR Option B, not both.
-- Option A = table does not exist yet (fresh install).
-- Option B = table already exists with width_cm/height_cm (migrate to inches).

-- ========== OPTION A: Create table (inches) – run only if table does NOT exist ==========
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pliego_sizes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT 'Display name e.g. 23.6x35.4',
    `code` varchar(50) DEFAULT NULL,
    `width_in` decimal(8,2) DEFAULT NULL COMMENT 'Width in inches',
    `height_in` decimal(8,2) DEFAULT NULL COMMENT 'Height in inches',
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pliego sizes (shared by paper and lamination)';


-- ========== OPTION B: Migrate cm → inches – run only if table already has width_cm/height_cm ==========
-- Add new columns (inches)
ALTER TABLE `joomla_ordenproduccion_pliego_sizes`
    ADD COLUMN `width_in` decimal(8,2) DEFAULT NULL COMMENT 'Width in inches' AFTER `code`,
    ADD COLUMN `height_in` decimal(8,2) DEFAULT NULL COMMENT 'Height in inches' AFTER `width_in`;

-- Convert existing cm to inches (1 in = 2.54 cm)
UPDATE `joomla_ordenproduccion_pliego_sizes`
SET
    `width_in` = ROUND(`width_cm` / 2.54, 2),
    `height_in` = ROUND(`height_cm` / 2.54, 2)
WHERE `width_cm` IS NOT NULL AND `height_cm` IS NOT NULL;

-- Drop old columns
ALTER TABLE `joomla_ordenproduccion_pliego_sizes`
    DROP COLUMN `width_cm`,
    DROP COLUMN `height_cm`;
