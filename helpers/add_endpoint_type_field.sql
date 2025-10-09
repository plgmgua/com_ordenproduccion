-- Add endpoint_type field to webhook_logs table
-- This field will distinguish between 'production' and 'test' webhook requests

ALTER TABLE `joomla_ordenproduccion_webhook_logs` 
ADD COLUMN `endpoint_type` VARCHAR(20) DEFAULT 'production' AFTER `webhook_id`,
ADD INDEX `idx_endpoint_type` (`endpoint_type`);

-- Update the status enum to be more descriptive
ALTER TABLE `joomla_ordenproduccion_webhook_logs` 
MODIFY COLUMN `status` ENUM('success','error','pending') DEFAULT 'pending';

-- Verify the changes
DESCRIBE `joomla_ordenproduccion_webhook_logs`;

-- Show sample of the updated structure
SELECT 
    'UPDATED STRUCTURE' AS info,
    '' AS field,
    '' AS type,
    '' AS endpoint_type
UNION ALL
SELECT 
    '',
    COLUMN_NAME,
    COLUMN_TYPE,
    CASE 
        WHEN COLUMN_NAME = 'endpoint_type' THEN '✓ NEW FIELD'
        ELSE ''
    END
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'grimpsa_prod'
AND TABLE_NAME = 'joomla_ordenproduccion_webhook_logs'
ORDER BY ORDINAL_POSITION;
