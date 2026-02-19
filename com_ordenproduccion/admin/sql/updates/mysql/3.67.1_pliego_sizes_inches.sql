-- ============================================
-- Version 3.67.1 - Pliego sizes: change unit from cm to inches
-- ============================================
-- Run this ONLY if you already have pliego_sizes with width_cm/height_cm.
-- (New installs use 3.67.0 which already has width_in/height_in.)
-- Converts existing values to inches (divide by 2.54) and drops cm columns.
-- Replace #__ with your DB prefix (e.g. joomla_).

-- Add new columns (inches)
ALTER TABLE `#__ordenproduccion_pliego_sizes`
    ADD COLUMN `width_in` decimal(8,2) DEFAULT NULL COMMENT 'Width in inches' AFTER `code`,
    ADD COLUMN `height_in` decimal(8,2) DEFAULT NULL COMMENT 'Height in inches' AFTER `width_in`;

-- Convert existing cm to inches (1 in = 2.54 cm) where old columns exist
UPDATE `#__ordenproduccion_pliego_sizes`
SET
    `width_in` = ROUND(`width_cm` / 2.54, 2),
    `height_in` = ROUND(`height_cm` / 2.54, 2)
WHERE `width_cm` IS NOT NULL AND `height_cm` IS NOT NULL;

-- Drop old columns
ALTER TABLE `#__ordenproduccion_pliego_sizes`
    DROP COLUMN `width_cm`,
    DROP COLUMN `height_cm`;
