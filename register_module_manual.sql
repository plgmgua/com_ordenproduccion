-- Manual Module Registration for Joomla 5.x
-- Production Actions Module (mod_acciones_produccion)

-- Step 1: Register the module in joomla_extensions table
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

-- Step 2: Get the extension ID (you'll need to run this separately)
-- SELECT extension_id FROM `joomla_extensions` WHERE `element` = 'mod_acciones_produccion' AND `type` = 'module';

-- Step 3: Create module instance in joomla_modules table
-- Replace {EXTENSION_ID} with the actual extension ID from step 2
INSERT INTO `joomla_modules` (
    `title`, `note`, `content`, `ordering`, `position`, `checked_out`, 
    `checked_out_time`, `publish_up`, `publish_down`, `published`, `module`, 
    `access`, `showtitle`, `params`, `client_id`, `language`
) VALUES (
    'Production Actions',
    'Production management and PDF generation',
    '',
    0,
    'sidebar-right',
    0,
    '0000-00-00 00:00:00',
    '0000-00-00 00:00:00',
    '0000-00-00 00:00:00',
    1,
    'mod_acciones_produccion',
    1,
    1,
    '{"show_statistics":"1","show_pdf_button":"1","show_excel_button":"1","order_id":""}',
    0,
    '*'
);

-- Step 4: Update the existing "Acciones Produccion" module (ID 117) to use the new module
-- This will replace the existing mod_custom with our new module
UPDATE `joomla_modules` 
SET `module` = 'mod_acciones_produccion',
    `params` = '{"show_statistics":"1","show_pdf_button":"1","show_excel_button":"1","order_id":""}',
    `note` = 'Production management and PDF generation'
WHERE `id` = 117;

-- Step 5: Verify the registration
-- Run these queries to verify:
-- SELECT * FROM `joomla_extensions` WHERE `element` = 'mod_acciones_produccion' AND `type` = 'module';
-- SELECT * FROM `joomla_modules` WHERE `module` = 'mod_acciones_produccion';
