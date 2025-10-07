-- Update Existing Module (ID 117) to use mod_acciones_produccion
-- This is the simplest approach since you already have "Acciones Produccion" module

-- Step 1: Register the module extension first
INSERT INTO `joomla_extensions` (
    `name`, `type`, `element`, `folder`, `client_id`, `enabled`, `access`, `protected`, 
    `manifest_cache`, `params`, `custom_data`, `system_data`, `checked_out`, 
    `checked_out_time`, `ordering`, `state`
) VALUES (
    'MOD_ACCIONES_PRODUCCION',
    'module',
    'mod_acciones_produccion',
    '',
    0,
    1,
    1,
    0,
    '{"name":"MOD_ACCIONES_PRODUCCION","type":"module","creationDate":"2025-01-27","author":"Grimpsa Development Team","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","version":"1.0.0","description":"MOD_ACCIONES_PRODUCCION_XML_DESCRIPTION","group":"","filename":"mod_acciones_produccion"}',
    '{}',
    '',
    '',
    0,
    '0000-00-00 00:00:00',
    0,
    0
);

-- Step 2: Update the existing module (ID 117) to use our new module
UPDATE `joomla_modules` 
SET `module` = 'mod_acciones_produccion',
    `params` = '{"show_statistics":"1","show_pdf_button":"1","show_excel_button":"1","order_id":""}',
    `note` = 'Production management and PDF generation'
WHERE `id` = 117;

-- Step 3: Verify the changes
-- Run this to check if it worked:
-- SELECT * FROM `joomla_modules` WHERE `id` = 117;
-- SELECT * FROM `joomla_extensions` WHERE `element` = 'mod_acciones_produccion';
