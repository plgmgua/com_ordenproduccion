-- Migration for Manual Asistencia Notes Field
-- Version 3.7.0
-- Adds mandatory notes field to manual entries to justify the entry

-- Add notes column to manual asistencia table
-- Note: Run this only once. If column already exists, it will fail - that's OK.
ALTER TABLE `#__ordenproduccion_asistencia_manual`
    ADD COLUMN `notes` TEXT NOT NULL DEFAULT '' AFTER `direction`;

